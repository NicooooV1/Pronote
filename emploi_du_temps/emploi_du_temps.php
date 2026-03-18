<?php
/**
 * emploi_du_temps.php — Page principale du module Emploi du Temps (M03).
 *
 * Affiche la grille hebdomadaire de l'EDT.
 * - Admin/Vie scolaire : sélecteur de classe
 * - Professeur : son EDT
 * - Élève : EDT de sa classe
 * - Parent : EDT de son enfant
 */
ob_start();

require_once __DIR__ . '/../API/core.php';
require_once __DIR__ . '/includes/EdtService.php';

$pdo = getPDO();
requireAuth();

$user      = getCurrentUser();
$user_role = getUserRole();
$user_fullname = getUserFullName();
$user_initials = getUserInitials();

$service = new EdtService($pdo);

// Filtres
$classeId = isset($_GET['classe']) ? (int)$_GET['classe'] : 0;
$eleveId  = isset($_GET['eleve']) ? (int)$_GET['eleve'] : 0;
$cours = [];

// --- Charger les données selon le rôle ---
if (isAdmin() || isVieScolaire()) {
    $classes = $service->getClasses();
    if ($classeId > 0) {
        $cours = $service->getEdtClasse($classeId);
    }
} elseif (isTeacher()) {
    $cours = $service->getEdtProfesseur($user['id']);
} elseif (isStudent()) {
    $cours = $service->getEdtEleve($user['id']);
} elseif (isParent()) {
    // Récupérer les enfants du parent
    $stmtEnfants = $pdo->prepare(
        "SELECT e.id, e.prenom, e.nom, e.classe FROM parent_eleve pe
         JOIN eleves e ON pe.id_eleve = e.id WHERE pe.id_parent = ?"
    );
    $stmtEnfants->execute([$user['id']]);
    $enfants = $stmtEnfants->fetchAll(PDO::FETCH_ASSOC);

    if ($eleveId > 0) {
        $cours = $service->getEdtParent($user['id'], $eleveId);
    } elseif (!empty($enfants)) {
        $eleveId = $enfants[0]['id'];
        $cours = $service->getEdtParent($user['id'], $eleveId);
    }
}

// Construire la grille
$creneaux = $service->getCreneauxCours();
$grille = $service->buildGrille($cours);
$jours = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi'];
$joursLabels = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi'];

$pageTitle = 'Emploi du temps';
$currentPage = 'grille';

include 'includes/header.php';
?>

<!-- Bannière -->
<div class="welcome-banner">
    <div class="welcome-content">
        <h2><i class="fas fa-calendar-week"></i> Emploi du temps</h2>
        <p>
            <?php if (isTeacher()): ?>
                Votre emploi du temps hebdomadaire.
            <?php elseif (isStudent()): ?>
                Votre emploi du temps de la semaine.
            <?php elseif (isParent() && !empty($enfants)): ?>
                Emploi du temps de votre enfant.
            <?php else: ?>
                Consultez et gérez les emplois du temps par classe.
            <?php endif; ?>
        </p>
    </div>
</div>

<!-- Filtres -->
<div class="filters-bar">
    <form method="GET" class="filters-form">
        <?php if (isAdmin() || isVieScolaire()): ?>
        <div class="filter-group">
            <label for="classe">Classe :</label>
            <select name="classe" id="classe" onchange="this.form.submit()">
                <option value="">-- Sélectionner une classe --</option>
                <?php foreach ($classes as $cl): ?>
                    <option value="<?= $cl['id'] ?>" <?= $classeId == $cl['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cl['nom']) ?> (<?= htmlspecialchars($cl['niveau']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <?php if (isParent() && !empty($enfants) && count($enfants) > 1): ?>
        <div class="filter-group">
            <label for="eleve">Enfant :</label>
            <select name="eleve" id="eleve" onchange="this.form.submit()">
                <?php foreach ($enfants as $enf): ?>
                    <option value="<?= $enf['id'] ?>" <?= $eleveId == $enf['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($enf['prenom'] . ' ' . $enf['nom']) ?> — <?= htmlspecialchars($enf['classe']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
    </form>
</div>

<!-- Grille EDT -->
<?php if (!empty($cours)): ?>
<div class="edt-container">
    <div class="edt-grid">
        <table class="edt-table">
            <thead>
                <tr>
                    <th class="edt-time-col">Horaire</th>
                    <?php foreach ($joursLabels as $label): ?>
                        <th><?= $label ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($creneaux as $creneau): ?>
                <tr>
                    <td class="edt-time-cell">
                        <span class="edt-time-label"><?= htmlspecialchars($creneau['label']) ?></span>
                        <span class="edt-time-range">
                            <?= substr($creneau['heure_debut'], 0, 5) ?> - <?= substr($creneau['heure_fin'], 0, 5) ?>
                        </span>
                    </td>
                    <?php foreach ($jours as $jour): ?>
                        <td class="edt-cell" data-jour="<?= $jour ?>" data-creneau-id="<?= $creneau['id'] ?>">
                            <?php if (isset($grille[$creneau['id']][$jour])): ?>
                                <?php $c = $grille[$creneau['id']][$jour]; ?>
                                <div class="edt-cours" <?php if (isAdmin() || isVieScolaire()): ?>draggable="true" data-cours-id="<?= $c['id'] ?>"<?php endif; ?> style="background-color: <?= htmlspecialchars($c['matiere_couleur'] ?? '#3498db') ?>20; border-left: 3px solid <?= htmlspecialchars($c['matiere_couleur'] ?? '#3498db') ?>">
                                    <div class="edt-cours-matiere"><?= htmlspecialchars($c['matiere_nom']) ?></div>
                                    <?php if (isAdmin() || isVieScolaire() || isStudent() || isParent()): ?>
                                        <div class="edt-cours-prof"><i class="fas fa-user"></i> <?= htmlspecialchars($c['professeur_nom']) ?></div>
                                    <?php endif; ?>
                                    <?php if (isTeacher()): ?>
                                        <div class="edt-cours-classe"><i class="fas fa-users"></i> <?= htmlspecialchars($c['classe_nom'] ?? '') ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($c['salle_nom'])): ?>
                                        <div class="edt-cours-salle"><i class="fas fa-door-open"></i> <?= htmlspecialchars($c['salle_nom']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($c['type_cours']) && $c['type_cours'] !== 'cours'): ?>
                                        <span class="edt-cours-type badge badge-sm"><?= htmlspecialchars(ucfirst($c['type_cours'])) ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php elseif ((isAdmin() || isVieScolaire()) && $classeId === 0): ?>
<div class="empty-state">
    <i class="fas fa-calendar-week fa-3x"></i>
    <h3>Sélectionnez une classe</h3>
    <p>Choisissez une classe dans le filtre ci-dessus pour afficher son emploi du temps.</p>
</div>
<?php else: ?>
<div class="empty-state">
    <i class="fas fa-calendar-times fa-3x"></i>
    <h3>Aucun cours</h3>
    <p>Aucun cours n'est planifié pour le moment.</p>
    <?php if (isAdmin()): ?>
    <a href="gerer_cours.php" class="btn btn-primary"><i class="fas fa-plus"></i> Ajouter un cours</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if (isAdmin() || isVieScolaire()): ?>
<script src="assets/js/dragdrop.js"></script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
