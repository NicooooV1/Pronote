<?php
/**
 * gerer_cours.php — Ajouter / Modifier un cours dans l'emploi du temps (M03).
 *
 * Accès : administrateur, vie_scolaire uniquement.
 */
ob_start();

require_once __DIR__ . '/../API/core.php';
require_once __DIR__ . '/includes/EdtService.php';

$pdo = getPDO();
requireAuth();

if (!isAdmin() && !isVieScolaire()) {
    redirect('/accueil/accueil.php');
}

$user      = getCurrentUser();
$user_role = getUserRole();
$user_fullname = getUserFullName();
$user_initials = getUserInitials();

$service = new EdtService($pdo);

$editMode = false;
$coursData = null;
$errors = [];
$success = '';

// Mode édition ?
if (isset($_GET['id'])) {
    $coursData = $service->getCours((int)$_GET['id']);
    if ($coursData) $editMode = true;
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken()) {
        $errors[] = 'Token CSRF invalide.';
    } else {
        $data = [
            'classe_id'     => (int)($_POST['classe_id'] ?? 0),
            'matiere_id'    => (int)($_POST['matiere_id'] ?? 0),
            'professeur_id' => (int)($_POST['professeur_id'] ?? 0),
            'salle_id'      => !empty($_POST['salle_id']) ? (int)$_POST['salle_id'] : null,
            'jour'          => $_POST['jour'] ?? '',
            'creneau_id'    => (int)($_POST['creneau_id'] ?? 0),
            'heure_debut'   => $_POST['heure_debut'] ?? '',
            'heure_fin'     => $_POST['heure_fin'] ?? '',
            'groupe'        => !empty($_POST['groupe']) ? trim($_POST['groupe']) : null,
            'type_cours'    => $_POST['type_cours'] ?? 'cours',
            'recurrence'    => $_POST['recurrence'] ?? 'hebdomadaire',
            'couleur'       => !empty($_POST['couleur']) ? $_POST['couleur'] : null,
        ];

        // Validation
        if ($data['classe_id'] <= 0) $errors[] = 'Veuillez sélectionner une classe.';
        if ($data['matiere_id'] <= 0) $errors[] = 'Veuillez sélectionner une matière.';
        if ($data['professeur_id'] <= 0) $errors[] = 'Veuillez sélectionner un professeur.';
        if (empty($data['jour'])) $errors[] = 'Veuillez sélectionner un jour.';
        if ($data['creneau_id'] <= 0) $errors[] = 'Veuillez sélectionner un créneau.';

        if (empty($errors)) {
            try {
                if ($editMode) {
                    $service->updateCours((int)$_GET['id'], $data);
                    $success = 'Cours modifié avec succès.';
                    $coursData = $service->getCours((int)$_GET['id']);
                } else {
                    $newId = $service->createCours($data);
                    $success = 'Cours ajouté avec succès.';
                    header('Location: emploi_du_temps.php?classe=' . $data['classe_id'] . '&msg=created');
                    exit;
                }
            } catch (\RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }
    }
}

// Données pour le formulaire
$classes     = $service->getClasses();
$matieres    = $service->getMatieres();
$professeurs = $service->getProfesseurs();
$salles      = $service->getSalles();
$creneaux    = $service->getCreneauxCours();
$joursOptions = [
    'lundi' => 'Lundi', 'mardi' => 'Mardi', 'mercredi' => 'Mercredi',
    'jeudi' => 'Jeudi', 'vendredi' => 'Vendredi', 'samedi' => 'Samedi'
];

$pageTitle = $editMode ? 'Modifier un cours' : 'Ajouter un cours';
$currentPage = 'gerer';
$pageSubtitle = $editMode ? 'Modification du cours' : 'Planifier un nouveau cours';

include 'includes/header.php';
?>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <ul class="mb-0">
        <?php foreach ($errors as $err): ?>
            <li><?= htmlspecialchars($err) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-<?= $editMode ? 'edit' : 'plus' ?>"></i> <?= htmlspecialchars($pageTitle) ?></h3>
    </div>
    <div class="card-body">
        <form method="POST" class="form-grid">
            <?= csrfField() ?>

            <div class="form-row">
                <div class="form-group">
                    <label for="classe_id">Classe <span class="required">*</span></label>
                    <select name="classe_id" id="classe_id" class="form-control" required>
                        <option value="">-- Classe --</option>
                        <?php foreach ($classes as $cl): ?>
                            <option value="<?= $cl['id'] ?>" <?= ($coursData['classe_id'] ?? '') == $cl['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cl['nom']) ?> (<?= htmlspecialchars($cl['niveau']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="matiere_id">Matière <span class="required">*</span></label>
                    <select name="matiere_id" id="matiere_id" class="form-control" required>
                        <option value="">-- Matière --</option>
                        <?php foreach ($matieres as $mat): ?>
                            <option value="<?= $mat['id'] ?>" <?= ($coursData['matiere_id'] ?? '') == $mat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($mat['nom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="professeur_id">Professeur <span class="required">*</span></label>
                    <select name="professeur_id" id="professeur_id" class="form-control" required>
                        <option value="">-- Professeur --</option>
                        <?php foreach ($professeurs as $prof): ?>
                            <option value="<?= $prof['id'] ?>" <?= ($coursData['professeur_id'] ?? '') == $prof['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($prof['prenom'] . ' ' . $prof['nom']) ?> (<?= htmlspecialchars($prof['matiere']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="salle_id">Salle</label>
                    <select name="salle_id" id="salle_id" class="form-control">
                        <option value="">-- Pas de salle --</option>
                        <?php foreach ($salles as $salle): ?>
                            <option value="<?= $salle['id'] ?>" <?= ($coursData['salle_id'] ?? '') == $salle['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($salle['nom']) ?>
                                <?php if ($salle['capacite']): ?>(<?= $salle['capacite'] ?> places)<?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="jour">Jour <span class="required">*</span></label>
                    <select name="jour" id="jour" class="form-control" required>
                        <option value="">-- Jour --</option>
                        <?php foreach ($joursOptions as $val => $label): ?>
                            <option value="<?= $val ?>" <?= ($coursData['jour'] ?? '') === $val ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="creneau_id">Créneau <span class="required">*</span></label>
                    <select name="creneau_id" id="creneau_id" class="form-control" required>
                        <option value="">-- Créneau --</option>
                        <?php foreach ($creneaux as $cr): ?>
                            <option value="<?= $cr['id'] ?>"
                                data-debut="<?= $cr['heure_debut'] ?>"
                                data-fin="<?= $cr['heure_fin'] ?>"
                                <?= ($coursData['creneau_id'] ?? '') == $cr['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cr['label']) ?> (<?= substr($cr['heure_debut'], 0, 5) ?> - <?= substr($cr['heure_fin'], 0, 5) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="heure_debut">Heure début</label>
                    <input type="time" name="heure_debut" id="heure_debut" class="form-control"
                           value="<?= htmlspecialchars($coursData['heure_debut'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="heure_fin">Heure fin</label>
                    <input type="time" name="heure_fin" id="heure_fin" class="form-control"
                           value="<?= htmlspecialchars($coursData['heure_fin'] ?? '') ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="type_cours">Type de cours</label>
                    <select name="type_cours" id="type_cours" class="form-control">
                        <?php foreach (['cours' => 'Cours', 'td' => 'TD', 'tp' => 'TP', 'examen' => 'Examen', 'autre' => 'Autre'] as $val => $label): ?>
                            <option value="<?= $val ?>" <?= ($coursData['type_cours'] ?? 'cours') === $val ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="recurrence">Récurrence</label>
                    <select name="recurrence" id="recurrence" class="form-control">
                        <option value="hebdomadaire" <?= ($coursData['recurrence'] ?? '') === 'hebdomadaire' ? 'selected' : '' ?>>Hebdomadaire</option>
                        <option value="quinzaine_A" <?= ($coursData['recurrence'] ?? '') === 'quinzaine_A' ? 'selected' : '' ?>>Quinzaine A</option>
                        <option value="quinzaine_B" <?= ($coursData['recurrence'] ?? '') === 'quinzaine_B' ? 'selected' : '' ?>>Quinzaine B</option>
                        <option value="ponctuel" <?= ($coursData['recurrence'] ?? '') === 'ponctuel' ? 'selected' : '' ?>>Ponctuel</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="groupe">Groupe (optionnel)</label>
                    <input type="text" name="groupe" id="groupe" class="form-control" placeholder="Laisser vide = classe entière"
                           value="<?= htmlspecialchars($coursData['groupe'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="couleur">Couleur</label>
                    <input type="color" name="couleur" id="couleur" class="form-control"
                           value="<?= htmlspecialchars($coursData['couleur'] ?? '#3498db') ?>">
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> <?= $editMode ? 'Modifier' : 'Ajouter' ?>
                </button>
                <a href="emploi_du_temps.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Annuler
                </a>
            </div>
        </form>
    </div>
</div>

<?php
ob_start();
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-fill heure_debut/heure_fin quand on sélectionne un créneau
    const creneauSelect = document.getElementById('creneau_id');
    const heureDebut = document.getElementById('heure_debut');
    const heureFin = document.getElementById('heure_fin');

    creneauSelect.addEventListener('change', function() {
        const opt = this.options[this.selectedIndex];
        if (opt.dataset.debut) heureDebut.value = opt.dataset.debut;
        if (opt.dataset.fin) heureFin.value = opt.dataset.fin;
    });
});
</script>
<?php
$extraScriptHtml = ob_get_clean();

include 'includes/footer.php';
?>
