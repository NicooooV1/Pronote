<?php
/**
 * Modifier un événement — Module Agenda
 * Nettoyé : EventRepository, pas d'établissement.json, matières centralisées,
 *           types/statuts/visibilité depuis constantes, pas d'inline JS.
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

// Vérifier l'ID
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    setFlashMessage('error', "Identifiant d'événement invalide.");
    header('Location: agenda.php');
    exit;
}

// Récupérer l'événement
$evenement = $repo->findById($id);
if (!$evenement) {
    setFlashMessage('error', "L'événement demandé n'existe pas.");
    header('Location: agenda.php');
    exit;
}

// Permission (canEditEvent de auth.php)
if (!canEditEvent($evenement)) {
    setFlashMessage('error', "Vous n'avez pas le droit de modifier cet événement.");
    header('Location: details_evenement.php?id=' . $id);
    exit;
}

// Classes depuis la BDD (remplace établissement.json)
$classes = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT classe FROM eleves WHERE classe IS NOT NULL AND classe != '' ORDER BY classe");
    $classes = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Erreur chargement classes: " . $e->getMessage());
}

// Matières depuis EventRepository (constante fallback + BDD)
$matieres = $repo->getMatieres();

// CSRF
$csrf_token = csrf_token();

// Types et visibilité depuis EventRepository
$types_evenements   = EventRepository::getTypesForRole($user_role);
$options_visibilite = EventRepository::getVisibilityForRole($user_role);

// Formater les dates existantes
try {
    $date_debut = new DateTime($evenement['date_debut']);
    $date_fin   = new DateTime($evenement['date_fin']);
} catch (Exception $e) {
    $date_debut = new DateTime();
    $date_fin   = new DateTime('+1 hour');
}

// Visibilité actuelle pour le select (classes:xxx → classes_specifiques)
$vis_value = $evenement['visibilite'];
if (strpos($vis_value, 'classes:') === 0) {
    $vis_value = 'classes_specifiques';
}

/* ── Traitement POST ── */
$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $erreur = "Erreur de sécurité. Veuillez réessayer.";
    } else {
        $titre       = trim(filter_input(INPUT_POST, 'titre', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '');
        $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
        $date_d      = filter_input(INPUT_POST, 'date_debut', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $heure_d     = filter_input(INPUT_POST, 'heure_debut', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $date_f      = filter_input(INPUT_POST, 'date_fin', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $heure_f     = filter_input(INPUT_POST, 'heure_fin', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $type_ev     = filter_input(INPUT_POST, 'type_evenement', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $statut      = filter_input(INPUT_POST, 'statut', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $lieu        = filter_input(INPUT_POST, 'lieu', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
        $visibilite  = filter_input(INPUT_POST, 'visibilite', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $matiere     = filter_input(INPUT_POST, 'matieres', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
        $personnes   = isset($_POST['personnes_concernees']) ? trim($_POST['personnes_concernees']) : '';

        // Traiter classes
        $classes_str = '';
        if ($visibilite === 'classes_specifiques' && !empty($_POST['classes']) && is_array($_POST['classes'])) {
            $selected = array_filter($_POST['classes'], fn($c) => in_array($c, $classes));
            $classes_str = implode(',', $selected);
            $visibilite  = 'classes:' . $classes_str;
        }

        $data = [
            'titre'                => $titre,
            'description'          => $description,
            'date_debut'           => $date_d . ' ' . $heure_d . ':00',
            'date_fin'             => $date_f . ' ' . $heure_f . ':00',
            'type_evenement'       => $type_ev,
            'statut'               => $statut,
            'lieu'                 => $lieu,
            'visibilite'           => $visibilite,
            'classes'              => $classes_str,
            'matieres'             => $matiere,
            'personnes_concernees' => $personnes,
        ];

        $errors = $repo->validate($data);
        if (!empty($errors)) {
            $erreur = implode('<br>', $errors);
        } else {
            try {
                $repo->update($id, $data);
                setFlashMessage('success', "L'événement a été modifié avec succès.");
                header('Location: details_evenement.php?id=' . $id . '&updated=1');
                exit;
            } catch (Exception $e) {
                error_log("Erreur modification événement $id: " . $e->getMessage());
                $erreur = "Erreur lors de la modification.";
            }
        }
    }
}

// Classes actuelles de l'événement
$classes_evenement = !empty($evenement['classes']) ? explode(',', $evenement['classes']) : [];

/* ── Page ── */
$pageTitle = "Modifier l'événement";
include 'includes/header.php';
?>

<div class="calendar-navigation">
    <a href="details_evenement.php?id=<?= (int) $id ?>" class="back-button">
        <i class="fas fa-arrow-left"></i> Retour aux détails
    </a>
</div>

<div class="event-edit-container">
    <div class="event-edit-header">
        <h1>Modifier l'événement</h1>
    </div>

    <div class="event-edit-form">
        <?php if ($erreur): ?>
            <div class="message error"><i class="fas fa-exclamation-circle"></i> <?= $erreur ?></div>
        <?php endif; ?>

        <form method="post" id="event-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

            <div class="form-grid">
                <!-- Titre -->
                <div class="form-group form-full">
                    <label for="titre">Titre <span aria-hidden="true">*</span></label>
                    <input type="text" name="titre" id="titre" class="form-control"
                           value="<?= htmlspecialchars($evenement['titre']) ?>" required maxlength="100">
                </div>

                <!-- Description -->
                <div class="form-group form-full">
                    <label for="description">Description</label>
                    <textarea name="description" id="description" class="form-control"
                              maxlength="2000"><?= htmlspecialchars($evenement['description'] ?? '') ?></textarea>
                </div>

                <!-- Dates / Heures -->
                <div class="form-group">
                    <label for="date_debut">Date de début <span aria-hidden="true">*</span></label>
                    <input type="date" name="date_debut" id="date_debut" class="form-control"
                           value="<?= $date_debut->format('Y-m-d') ?>" required>
                </div>
                <div class="form-group">
                    <label for="heure_debut">Heure de début <span aria-hidden="true">*</span></label>
                    <input type="time" name="heure_debut" id="heure_debut" class="form-control"
                           value="<?= $date_debut->format('H:i') ?>" required>
                </div>
                <div class="form-group">
                    <label for="date_fin">Date de fin <span aria-hidden="true">*</span></label>
                    <input type="date" name="date_fin" id="date_fin" class="form-control"
                           value="<?= $date_fin->format('Y-m-d') ?>" required>
                </div>
                <div class="form-group">
                    <label for="heure_fin">Heure de fin <span aria-hidden="true">*</span></label>
                    <input type="time" name="heure_fin" id="heure_fin" class="form-control"
                           value="<?= $date_fin->format('H:i') ?>" required>
                </div>

                <!-- Type d'événement -->
                <div class="form-group">
                    <label for="type_evenement">Type <span aria-hidden="true">*</span></label>
                    <select name="type_evenement" id="type_evenement" class="form-control" required>
                        <?php foreach ($types_evenements as $code => $type): ?>
                            <option value="<?= htmlspecialchars($code) ?>"
                                <?= $evenement['type_evenement'] === $code ? 'selected' : '' ?>>
                                <?= htmlspecialchars($type['nom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="type_personnalise_container" class="type-personnalise"
                         <?= $evenement['type_evenement'] !== 'autre' ? 'hidden' : '' ?>>
                        <label for="type_personnalise">Précisez le type</label>
                        <input type="text" name="type_personnalise" id="type_personnalise"
                               value="<?= htmlspecialchars($evenement['type_personnalise'] ?? '') ?>"
                               placeholder="Type personnalisé" maxlength="50">
                    </div>
                </div>

                <!-- Statut -->
                <div class="form-group">
                    <label for="statut">Statut</label>
                    <select name="statut" id="statut" class="form-control">
                        <?php foreach (EventRepository::VALID_STATUTS as $s): ?>
                            <option value="<?= htmlspecialchars($s) ?>"
                                <?= ($evenement['statut'] ?? 'actif') === $s ? 'selected' : '' ?>>
                                <?= htmlspecialchars(ucfirst($s)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Lieu -->
                <div class="form-group">
                    <label for="lieu">Lieu</label>
                    <input type="text" name="lieu" id="lieu" class="form-control"
                           value="<?= htmlspecialchars($evenement['lieu'] ?? '') ?>" maxlength="100">
                </div>

                <!-- Matière -->
                <div class="form-group">
                    <label for="matieres">Matière</label>
                    <select name="matieres" id="matieres" class="form-control">
                        <option value="">-- Sélectionner --</option>
                        <?php foreach ($matieres as $mat): ?>
                            <option value="<?= htmlspecialchars($mat) ?>"
                                <?= ($evenement['matieres'] ?? '') === $mat ? 'selected' : '' ?>>
                                <?= htmlspecialchars($mat) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Visibilité -->
                <div class="form-group">
                    <label for="visibilite">Visibilité <span aria-hidden="true">*</span></label>
                    <select name="visibilite" id="visibilite" class="form-control" required>
                        <?php foreach ($options_visibilite as $code => $opt): ?>
                            <option value="<?= htmlspecialchars($code) ?>"
                                <?= $vis_value === $code ? 'selected' : '' ?>>
                                <?= htmlspecialchars($opt['nom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Classes spécifiques -->
                <div id="section_classes" class="form-group form-full"
                     <?= strpos($evenement['visibilite'], 'classes:') !== 0 ? 'hidden' : '' ?>>
                    <label>Classes concernées</label>
                    <div class="multiselect-container">
                        <div class="multiselect-search">
                            <input type="text" id="classes_search" placeholder="Rechercher une classe">
                        </div>
                        <div class="multiselect-actions">
                            <button type="button" class="multiselect-action" data-action="select-all" data-target="class-checkbox">Tout</button>
                            <button type="button" class="multiselect-action" data-action="deselect-all" data-target="class-checkbox">Aucun</button>
                        </div>
                        <div class="multiselect-options">
                            <?php foreach ($classes as $classe): ?>
                                <div class="multiselect-option class-option">
                                    <label>
                                        <input type="checkbox" name="classes[]" class="class-checkbox"
                                               value="<?= htmlspecialchars($classe) ?>"
                                               <?= in_array($classe, $classes_evenement) ? 'checked' : '' ?>>
                                        <?= htmlspecialchars($classe) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Personnes concernées -->
                <div class="form-group form-full">
                    <label for="personnes_concernees">Personnes concernées <small>(séparées par des virgules)</small></label>
                    <textarea name="personnes_concernees" id="personnes_concernees" class="form-control"
                              placeholder="Jean Dupont, Marie Martin…"><?= htmlspecialchars($evenement['personnes_concernees'] ?? '') ?></textarea>
                </div>

                <!-- Actions -->
                <div class="form-actions form-full">
                    <a href="details_evenement.php?id=<?= (int) $id ?>" class="btn btn-secondary">Annuler</a>
                    <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="assets/js/event_form.js"></script>

<?php
include 'includes/footer.php';
ob_end_flush();
?>
