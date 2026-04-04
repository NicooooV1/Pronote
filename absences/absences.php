<?php
/**
 * absences.php — Page principale unifiée du module Absences.
 * 
 * Fusionne l'ancien absences.php (402 lignes) et retards.php (304 lignes)
 * via le paramètre ?type=absences|retards.
 * Vues: list (défaut), calendar, stats.
 * 
 * Corrections appliquées:
 * - Suppression des 12 error_log() de debug
 * - Suppression du bloc runtime file creation (views/)
 * - Remplacement de etablissement.json par getEtablissementData()
 * - Utilisation d'AbsenceRepository et AbsenceHelper
 */
// Boot standardisé — fournit $user, $user_role, $user_fullname, $user_initials, $pdo, $isAdmin, $rootPrefix
$pageTitle  = 'Absences';
$activePage = 'absences';
require_once __DIR__ . '/../API/module_boot.php';

require_once __DIR__ . '/includes/AbsenceRepository.php';
require_once __DIR__ . '/includes/AbsenceHelper.php';

// Vérifier les droits d'accès
if (!canManageAbsences() && !isStudent() && !isParent()) {
    redirect('/accueil/accueil.php');
}

$repo    = new AbsenceRepository($pdo);
$filters = AbsenceHelper::getFilters();

// Type: absences ou retards
$type = in_array($filters['type'], ['absences', 'retards']) ? $filters['type'] : 'absences';
$view = in_array($filters['view'], ['list', 'calendar', 'stats']) ? $filters['view'] : 'list';

// Stats view réservée aux admin/vie_scolaire
if ($view === 'stats' && !isAdmin() && !isVieScolaire()) {
    $view = 'list';
}

// Récupérer les données selon le type
if ($type === 'retards') {
    $data = $repo->getRetardsByRole($user_role, $user['id'], $filters);
} else {
    $data = $repo->getByRole($user_role, $user['id'], $filters);
}

// Pour les vues, on nomme la variable principale
$absences = $data;
$retards  = $data;

// Récupérer la liste des classes pour les filtres
$classes = AbsenceHelper::getClassesList();

// Formatage des dates
$date_debut = $filters['date_debut'];
$date_fin   = $filters['date_fin'];
$classe     = $filters['classe'];
$justifie   = $filters['justifie'];
$date_debut_formattee = date('d/m/Y', strtotime($date_debut));
$date_fin_formattee   = date('d/m/Y', strtotime($date_fin));

// Configuration de la page
$pageTitle   = $type === 'retards' ? 'Gestion des retards' : 'Absences';
$currentPage = $type === 'retards' ? 'retards' : 'liste';

include 'includes/header.php';
?>

<!-- Bannière de bienvenue -->
<?php if ($type === 'retards'): ?>
<div class="welcome-banner">
    <div class="welcome-content">
        <h2>Gestion des Retards</h2>
        <p>Consultez et gérez les retards des élèves.</p>
    </div>
    <div class="welcome-icon"><i class="fas fa-clock"></i></div>
</div>
<?php elseif (isAdmin() || isVieScolaire()): ?>
<div class="welcome-banner">
    <div class="welcome-content">
        <h2>Gestion des Absences</h2>
        <p>Consultez, gérez et suivez les absences des élèves de l'établissement.</p>
    </div>
    <div class="welcome-icon"><i class="fas fa-user-clock"></i></div>
</div>
<?php elseif (isTeacher()): ?>
<div class="welcome-banner">
    <div class="welcome-content">
        <h2>Suivi des Absences</h2>
        <p>Consultez les absences des élèves de vos classes.</p>
    </div>
    <div class="welcome-icon"><i class="fas fa-chalkboard-teacher"></i></div>
</div>
<?php else: ?>
<div class="welcome-banner">
    <div class="welcome-content">
        <h2>Mes Absences</h2>
        <p>Consultez l'historique de vos absences.</p>
    </div>
    <div class="welcome-icon"><i class="fas fa-calendar-check"></i></div>
</div>
<?php endif; ?>

<?php if (isAdmin()): ?>
<div class="admin-toolbar">
    <span class="admin-toolbar-badge"><i class="fas fa-shield-alt"></i> Administration</span>
    <span style="font-size:13px;color:#4a5568"><?= count($absences) ?> enregistrement(s) — Période du <?= $date_debut_formattee ?> au <?= $date_fin_formattee ?></span>
    <a href="ajouter_absence.php" class="btn-sm" style="background:#059669;color:white;text-decoration:none;margin-left:auto"><i class="fas fa-plus"></i> Saisir une absence</a>
    <a href="justificatifs.php" class="btn-sm" style="background:#d97706;color:white;text-decoration:none"><i class="fas fa-file-medical"></i> Justificatifs</a>
    <a href="../admin/scolaire/absences.php" class="btn-sm" style="background:#0f4c81;color:white;text-decoration:none"><i class="fas fa-cog"></i> Panneau admin</a>
</div>
<?php endif; ?>

<!-- Barre de filtres -->
<div class="filters-bar">
    <form id="filter-form" class="filter-form" method="get" action="absences.php">
        <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
        
        <div class="filter-item">
            <label for="date_debut" class="form-label">Du</label>
            <input type="date" id="date_debut" name="date_debut" value="<?= $date_debut ?>" max="<?= date('Y-m-d') ?>" class="form-control">
        </div>
        
        <div class="filter-item">
            <label for="date_fin" class="form-label">Au</label>
            <input type="date" id="date_fin" name="date_fin" value="<?= $date_fin ?>" max="<?= date('Y-m-d') ?>" class="form-control">
        </div>
        
        <?php if (isAdmin() || isVieScolaire() || isTeacher()): ?>
        <div class="filter-item">
            <label for="classe" class="form-label">Classe</label>
            <select id="classe" name="classe" class="form-control">
                <option value="">Toutes les classes</option>
                <?php foreach ($classes as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>" <?= $classe === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        
        <div class="filter-item">
            <label for="justifie" class="form-label">Justification</label>
            <select id="justifie" name="justifie" class="form-control">
                <option value="">Toutes</option>
                <option value="oui" <?= $justifie === 'oui' ? 'selected' : '' ?>>Justifié<?= $type === 'absences' ? 'es' : 's' ?></option>
                <option value="non" <?= $justifie === 'non' ? 'selected' : '' ?>>Non justifié<?= $type === 'absences' ? 'es' : 's' ?></option>
            </select>
        </div>
        
        <?php if ($type === 'absences'): ?>
        <div class="filter-item">
            <label for="view" class="form-label">Vue</label>
            <select id="view" name="view" class="form-control">
                <option value="list" <?= $view === 'list' ? 'selected' : '' ?>>Liste</option>
                <option value="calendar" <?= $view === 'calendar' ? 'selected' : '' ?>>Calendrier</option>
                <?php if (isAdmin() || isVieScolaire()): ?>
                <option value="stats" <?= $view === 'stats' ? 'selected' : '' ?>>Statistiques</option>
                <?php endif; ?>
            </select>
        </div>
        <?php endif; ?>
        
        <div class="filter-buttons">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-filter"></i> Filtrer
            </button>
            <a href="absences.php?type=<?= $type ?>" class="btn btn-secondary">
                <i class="fas fa-redo"></i> Réinitialiser
            </a>
        </div>
    </form>
</div>

<!-- Contenu principal -->
<div class="content-section">
    <div class="content-header">
        <h2>
            <?php if ($type === 'retards'): ?>
                Retards du <?= $date_debut_formattee ?> au <?= $date_fin_formattee ?>
                <?= !empty($classe) ? ' — Classe ' . htmlspecialchars($classe) : '' ?>
            <?php elseif (!empty($classe)): ?>
                Absences de la classe <?= htmlspecialchars($classe) ?>
            <?php else: ?>
                <?= isStudent() ? 'Mes absences' : 'Absences' ?> du <?= $date_debut_formattee ?> au <?= $date_fin_formattee ?>
            <?php endif; ?>
        </h2>
        <div class="content-actions">
            <?php if (canManageAbsences() && $view === 'list'): ?>
            <a href="export.php?format=excel&<?= http_build_query($_GET) ?>" class="btn btn-outline">
                <i class="fas fa-file-excel"></i> Exporter
            </a>
            <?php endif; ?>
            <?php if ((isAdmin() || isVieScolaire()) && $view === 'list'): ?>
            <a href="imprimer_absences.php?<?= http_build_query($_GET) ?>" class="btn btn-outline" target="_blank">
                <i class="fas fa-print"></i> Imprimer
            </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <span><?= htmlspecialchars($_SESSION['success_message']) ?></span>
            <button class="alert-close"><i class="fas fa-times"></i></button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?= htmlspecialchars($_SESSION['error_message']) ?></span>
            <button class="alert-close"><i class="fas fa-times"></i></button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>
    
    <div class="content-body">
        <?php if ($type === 'retards'): ?>
            <?php if (empty($retards)): ?>
                <div class="no-data-message">
                    <i class="fas fa-clock"></i>
                    <p>Aucun retard ne correspond aux critères sélectionnés.</p>
                </div>
            <?php else: ?>
                <?php include 'views/retards_list_view.php'; ?>
            <?php endif; ?>
        <?php elseif (empty($absences)): ?>
            <div class="no-data-message">
                <i class="fas fa-calendar-times"></i>
                <p>Aucune absence ne correspond aux critères sélectionnés.</p>
                <?php if (canManageAbsences()): ?>
                <a href="ajouter_absence.php" class="btn btn-primary mt-3">
                    <i class="fas fa-plus"></i> Signaler une absence
                </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php if ($view === 'list'): ?>
                <?php include 'views/list_view.php'; ?>
            <?php elseif ($view === 'calendar'): ?>
                <?php include 'views/calendar_view.php'; ?>
            <?php elseif ($view === 'stats'): ?>
                <?php include 'views/stats_view.php'; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php
include 'includes/footer.php';
ob_end_flush();
