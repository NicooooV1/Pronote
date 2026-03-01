<?php
require_once __DIR__ . '/../../API/core.php';

$pageTitle = $pageTitle ?? 'Vie scolaire';
$activePage = 'vie_scolaire';
$extraCss = ['assets/css/vie_scolaire.css'];

$user = getCurrentUser();
$user_role = getUserRole();
$user_fullname = getUserFullName();
$user_initials = getUserInitials();

ob_start();
?>
<div class="sidebar-nav">
    <a href="dashboard.php" class="sidebar-nav-item <?= ($currentPage ?? '') === 'dashboard' ? 'active' : '' ?>">
        <span class="sidebar-nav-icon"><i class="fas fa-tachometer-alt"></i></span>
        <span>Tableau de bord</span>
    </a>
    <a href="suivi_eleve.php" class="sidebar-nav-item <?= ($currentPage ?? '') === 'suivi' ? 'active' : '' ?>">
        <span class="sidebar-nav-icon"><i class="fas fa-user-graduate"></i></span>
        <span>Suivi élève</span>
    </a>
    <a href="stats_classes.php" class="sidebar-nav-item <?= ($currentPage ?? '') === 'stats' ? 'active' : '' ?>">
        <span class="sidebar-nav-icon"><i class="fas fa-chart-bar"></i></span>
        <span>Statistiques classes</span>
    </a>
</div>
<?php
$sidebarExtraContent = ob_get_clean();

require_once __DIR__ . '/../../templates/shared_header.php';
require_once __DIR__ . '/../../templates/shared_sidebar.php';
require_once __DIR__ . '/../../templates/shared_topbar.php';
?>
<div class="main-content">
