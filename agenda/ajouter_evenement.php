<?php
/**
 * Ajouter un événement — Module Agenda
 * Nettoyé : CSRF centralisé, EventRepository, pas d'inline JS/CSS, pas d'établissement.json.
 */
ob_start();

require_once __DIR__ . '/../API/core.php';
$pdo = getPDO();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/EventRepository.php';

requireAuth();

$user          = getCurrentUser();
$user_fullname = getUserFullName();
$user_role     = getUserRole();
$user_initials = getUserInitials();
$repo          = new EventRepository($pdo);

// Date par défaut (aujourd'hui ou paramètre GET)
$date_par_defaut    = filter_input(INPUT_GET, 'date', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: date('Y-m-d');
$heure_debut_defaut = date('H:i');
$heure_fin_defaut   = date('H:i', strtotime('+1 hour'));

// CSRF via système centralisé (remplace bin2hex manual)
$csrf_token = csrf_token();

// Types et visibilité depuis EventRepository (remplace ~80 lignes dupliquées)
$types_evenements   = EventRepository::getTypesForRole($user_role);
$options_visibilite = EventRepository::getVisibilityForRole($user_role);

// Classes depuis la BDD (remplace parsing établissement.json ~55 lignes)
$classes = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT classe FROM eleves WHERE classe IS NOT NULL AND classe != '' ORDER BY classe");
    $classes = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Erreur chargement classes: " . $e->getMessage());
}

// Matière du professeur connecté
$prof_matiere = '';
if (isTeacher() && isset($user['nom'], $user['prenom'])) {
    try {
        $stmt = $pdo->prepare('SELECT matiere FROM professeurs WHERE nom = ? AND prenom = ?');
        $stmt->execute([$user['nom'], $user['prenom']]);
        $prof_matiere = $stmt->fetchColumn() ?: '';
    } catch (PDOException $e) {
        error_log("Erreur matière prof: " . $e->getMessage());
    }
}

/* ── Traitement POST ── */
$message = '';
$erreur  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $erreur = "Erreur de sécurité : formulaire invalide ou expiré. Veuillez actualiser la page.";
    } else {
        // Lecture des champs
        $titre          = trim(filter_input(INPUT_POST, 'titre', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '');
        $description    = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
        $date_debut     = filter_input(INPUT_POST, 'date_debut', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $heure_debut    = filter_input(INPUT_POST, 'heure_debut', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $date_fin       = filter_input(INPUT_POST, 'date_fin', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $heure_fin      = filter_input(INPUT_POST, 'heure_fin', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $type_evenement = filter_input(INPUT_POST, 'type_evenement', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $visibilite     = filter_input(INPUT_POST, 'visibilite', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $lieu           = filter_input(INPUT_POST, 'lieu', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
        $type_perso     = filter_input(INPUT_POST, 'type_personnalise', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';

        // Vérifier type autorisé pour ce rôle
        if ($type_evenement && !array_key_exists($type_evenement, $types_evenements)) {
            $erreur = "Le type d'événement sélectionné n'est pas autorisé pour votre profil.";
        } else {
            // Combiner date + heure en datetime
            $date_debut_str = $date_debut . ' ' . $heure_debut . ':00';
            $date_fin_str   = $date_fin   . ' ' . $heure_fin   . ':00';

            // Traiter visibilité / classes
            $classes_str = '';
            if ($visibilite === 'classes_specifiques' && !empty($_POST['classes']) && is_array($_POST['classes'])) {
                $selected = array_filter($_POST['classes'], fn($c) => in_array($c, $classes));
                if (empty($selected)) {
                    $erreur = "Aucune classe valide sélectionnée.";
                } else {
                    $classes_str = implode(',', $selected);
                    $visibilite  = 'classes:' . $classes_str;
                }
            }

            // Personnes concernées (format: "type:id")
            $personnes = '';
            if (!empty($_POST['personnes_concernees']) && is_array($_POST['personnes_concernees'])) {
                $valid = array_filter(
                    $_POST['personnes_concernees'],
                    fn($p) => preg_match('/^(eleve|professeur|personnel|parent):[a-zA-Z0-9]+$/', $p)
                );
                $personnes = implode(',', $valid);
            }

            // Matière : professeur → auto, sinon formulaire
            $matieres = (isTeacher() && $prof_matiere)
                ? $prof_matiere
                : (filter_input(INPUT_POST, 'matieres', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '');

            if (!$erreur) {
                // Construire RRULE si récurrence demandée
                $rrule = EventRepository::buildRRule($_POST);

                $data = [
                    'titre'                => $titre,
                    'description'          => $description,
                    'date_debut'           => $date_debut_str,
                    'date_fin'             => $date_fin_str,
                    'type_evenement'       => $type_evenement,
                    'type_personnalise'    => ($type_evenement === 'autre') ? $type_perso : '',
                    'visibilite'           => $visibilite,
                    'createur'             => $user_fullname,
                    'lieu'                 => $lieu,
                    'classes'              => $classes_str,
                    'matieres'             => $matieres,
                    'personnes_concernees' => $personnes,
                    'rrule'                => $rrule,
                ];

                $errors = $repo->validate($data);
                if (!empty($errors)) {
                    $erreur = implode(' ', $errors);
                } else {
                    // Détection de conflits
                    $conflicts = $repo->detectConflicts($date_debut_str, $date_fin_str, $lieu, $classes_str);
                    $forceCreate = isset($_POST['force_create']);

                    if (!empty($conflicts) && !$forceCreate) {
                        $conflictMsg = count($conflicts) . " conflit(s) détecté(s) : ";
                        foreach (array_slice($conflicts, 0, 3) as $c) {
                            $conflictMsg .= '"' . htmlspecialchars($c['titre']) . '" (' . date('d/m H:i', strtotime($c['date_debut'])) . '), ';
                        }
                        $erreur = rtrim($conflictMsg, ', ') . '. Cochez "Ignorer les conflits" pour forcer la création.';
                        $showForceCheckbox = true;
                    } else {
                        try {
                            $id = $repo->create($data);
                            setFlashMessage('success', "L'événement a été ajouté avec succès." . ($rrule ? " (récurrent: $rrule)" : ''));
                            header("Location: details_evenement.php?id=$id&created=1");
                            exit;
                        } catch (Exception $e) {
                            $erreur = "Erreur lors de l'ajout : " . $e->getMessage();
                            error_log("Erreur ajout événement par $user_fullname: " . $e->getMessage());
                        }
                    }
                }
            }
        }
    }
}

/* ── Page ── */
$pageTitle = 'Ajouter un événement';
$extraCss  = [];

include 'includes/header.php';
?>

<div class="event-creation-container">
    <div class="event-creation-header">
        <a href="agenda.php" class="back-button">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M10 19L3 12L10 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M3 12H21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Retour
        </a>
        <h2>Ajouter un événement</h2>
        <?php if ($user_role === 'eleve' || $user_role === 'parent'): ?>
            <span class="role-indicator">Événement personnel</span>
        <?php endif; ?>
    </div>

    <div class="event-creation-form">
        <?php if ($message): ?>
            <div class="message success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if ($erreur): ?>
            <div class="message error"><?= htmlspecialchars($erreur) ?></div>
        <?php endif; ?>

        <form method="post" id="event-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

            <div class="form-grid">
                <!-- Titre -->
                <div class="form-group form-full">
                    <label for="titre">Titre <span aria-hidden="true">*</span></label>
                    <input type="text" name="titre" id="titre" required placeholder="Titre de l'événement" maxlength="100">
                </div>

                <!-- Type d'événement -->
                <div class="form-group">
                    <label for="type_evenement">Type d'événement <span aria-hidden="true">*</span></label>
                    <select name="type_evenement" id="type_evenement" required>
                        <?php if (count($types_evenements) > 1): ?>
                            <option value="">Sélectionnez un type</option>
                        <?php endif; ?>
                        <?php foreach ($types_evenements as $code => $type): ?>
                            <option value="<?= htmlspecialchars($code) ?>"><?= htmlspecialchars($type['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div id="type_personnalise_container" class="type-personnalise" hidden>
                        <label for="type_personnalise">Précisez le type</label>
                        <input type="text" name="type_personnalise" id="type_personnalise" placeholder="Type personnalisé" maxlength="50">
                    </div>
                </div>

                <!-- Visibilité -->
                <div class="form-group">
                    <label for="visibilite">Visibilité <span aria-hidden="true">*</span></label>
                    <select id="visibilite" name="visibilite" required>
                        <?php foreach ($options_visibilite as $code => $option): ?>
                            <option value="<?= htmlspecialchars($code) ?>"><?= htmlspecialchars($option['nom']) ?></option>
                        <?php endforeach; ?>
                        <?php if ((isAdmin() || isTeacher() || isVieScolaire()) && !empty($classes)): ?>
                            <?php foreach ($classes as $classe): ?>
                                <option value="classes:<?= htmlspecialchars($classe) ?>">Classe : <?= htmlspecialchars($classe) ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <small>Détermine qui peut voir cet événement.</small>
                </div>

                <!-- Dates et heures -->
                <div class="form-group">
                    <label for="date_debut">Date de début <span aria-hidden="true">*</span></label>
                    <input type="date" name="date_debut" id="date_debut" value="<?= htmlspecialchars($date_par_defaut) ?>" required>
                </div>
                <div class="form-group">
                    <label for="heure_debut">Heure de début <span aria-hidden="true">*</span></label>
                    <input type="time" name="heure_debut" id="heure_debut" value="<?= htmlspecialchars($heure_debut_defaut) ?>" required>
                </div>
                <div class="form-group">
                    <label for="date_fin">Date de fin <span aria-hidden="true">*</span></label>
                    <input type="date" name="date_fin" id="date_fin" value="<?= htmlspecialchars($date_par_defaut) ?>" required>
                </div>
                <div class="form-group">
                    <label for="heure_fin">Heure de fin <span aria-hidden="true">*</span></label>
                    <input type="time" name="heure_fin" id="heure_fin" value="<?= htmlspecialchars($heure_fin_defaut) ?>" required>
                </div>

                <!-- Classes spécifiques (affiché si visibilité = classes_specifiques) -->
                <div id="section_classes" class="form-group form-full" hidden>
                    <label>Classes concernées</label>
                    <div class="multiselect-container">
                        <div class="multiselect-search">
                            <input type="text" id="classes_search" placeholder="Rechercher une classe">
                        </div>
                        <div class="multiselect-actions">
                            <button type="button" class="multiselect-action" data-action="select-all" data-target="class-checkbox">Tout sélectionner</button>
                            <button type="button" class="multiselect-action" data-action="deselect-all" data-target="class-checkbox">Tout désélectionner</button>
                        </div>
                        <div class="multiselect-options">
                            <?php foreach ($classes as $classe): ?>
                                <div class="multiselect-option class-option">
                                    <label>
                                        <input type="checkbox" name="classes[]" class="class-checkbox" value="<?= htmlspecialchars($classe) ?>">
                                        <?= htmlspecialchars($classe) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Personnes concernées -->
                <div class="form-group form-full" id="personnesContainer">
                    <label>Personnes concernées</label>
                    <div class="persons-selector">
                        <div class="persons-actions">
                            <button type="button" class="persons-action" data-action="select-all" data-target="person-checkbox">Tout sélectionner</button>
                            <button type="button" class="persons-action" data-action="deselect-all" data-target="person-checkbox">Tout désélectionner</button>
                        </div>
                        <input type="text" id="searchPersons" class="persons-search" placeholder="Rechercher une personne…">
                        <div class="persons-list" id="personsList">
                            <div class="loading-indicator">Chargement…</div>
                        </div>
                        <div class="persons-count" id="personsCount">0 personne(s) sélectionnée(s)</div>
                    </div>
                    <small>Sélectionnez les personnes spécifiquement concernées par cet événement.</small>
                </div>

                <!-- Lieu -->
                <div class="form-group">
                    <label for="lieu">Lieu</label>
                    <input type="text" name="lieu" id="lieu" placeholder="Salle, bâtiment…" maxlength="100">
                </div>

                <!-- Matière -->
                <?php if (isTeacher() && $prof_matiere): ?>
                    <div class="form-group">
                        <label for="matieres">Matière associée</label>
                        <input type="text" name="matieres" id="matieres" value="<?= htmlspecialchars($prof_matiere) ?>" readonly>
                    </div>
                <?php else: ?>
                    <div class="form-group">
                        <label for="matieres">Matière associée</label>
                        <input type="text" name="matieres" id="matieres" placeholder="Matière concernée" maxlength="50">
                    </div>
                <?php endif; ?>

                <!-- Description -->
                <div class="form-group form-full">
                    <label for="description">Description</label>
                    <textarea name="description" id="description" rows="4" placeholder="Détails de l'événement…" maxlength="2000"></textarea>
                </div>

                <!-- Récurrence -->
                <div class="form-group form-full">
                    <label>Récurrence</label>
                    <div style="display:flex;gap:1rem;flex-wrap:wrap;align-items:center;">
                        <select name="recurrence_freq" id="recurrence_freq" class="ds-form-control" style="max-width:200px;">
                            <option value="none">Aucune</option>
                            <option value="daily">Quotidienne</option>
                            <option value="weekly">Hebdomadaire</option>
                            <option value="monthly">Mensuelle</option>
                        </select>
                        <div id="recurrence-options" style="display:none;gap:0.5rem;flex-wrap:wrap;align-items:center;">
                            <label style="font-size:0.85rem;">Tous les</label>
                            <input type="number" name="recurrence_interval" value="1" min="1" max="52" style="width:60px;" class="ds-form-control">
                            <span id="recurrence-freq-label" style="font-size:0.85rem;">semaine(s)</span>
                            <div id="recurrence-byday" style="display:none;gap:0.25rem;">
                                <?php foreach (['MO'=>'L','TU'=>'M','WE'=>'Me','TH'=>'J','FR'=>'V','SA'=>'S','SU'=>'D'] as $code => $label): ?>
                                <label style="display:inline-flex;gap:2px;font-size:0.8rem;">
                                    <input type="checkbox" name="recurrence_byday[]" value="<?= $code ?>"> <?= $label ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                            <label style="font-size:0.85rem;">Jusqu'au :</label>
                            <input type="date" name="recurrence_until" class="ds-form-control" style="max-width:180px;">
                        </div>
                    </div>
                </div>

                <?php if (!empty($showForceCheckbox)): ?>
                <!-- Force : ignorer conflits -->
                <div class="form-group form-full">
                    <label style="display:flex;gap:0.5rem;align-items:center;color:var(--ds-warning);">
                        <input type="checkbox" name="force_create" value="1">
                        <i class="fas fa-exclamation-triangle"></i> Ignorer les conflits et créer l'événement quand même
                    </label>
                </div>
                <?php endif; ?>

                <!-- Actions -->
                <div class="form-full">
                    <div class="form-actions">
                        <a href="agenda.php" class="btn-cancel">Annuler</a>
                        <button type="submit" class="btn-submit">Créer l'événement</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="assets/js/event_form.js"></script>
<script>
// Récurrence toggle
document.getElementById('recurrence_freq').addEventListener('change', function() {
    const opts = document.getElementById('recurrence-options');
    const byday = document.getElementById('recurrence-byday');
    const label = document.getElementById('recurrence-freq-label');
    if (this.value === 'none') {
        opts.style.display = 'none';
    } else {
        opts.style.display = 'flex';
        byday.style.display = this.value === 'weekly' ? 'flex' : 'none';
        const labels = {daily:'jour(s)',weekly:'semaine(s)',monthly:'mois'};
        label.textContent = labels[this.value] || '';
    }
});
</script>

<?php
include 'includes/footer.php';
ob_end_flush();
?>
