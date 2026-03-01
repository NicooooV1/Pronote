<?php
require_once __DIR__ . '/../../API/core.php';

$pageTitle = $pageTitle ?? 'Devoirs en ligne';
$activePage = 'devoirs';
$extraCss = ['assets/css/devoirs.css'];

$user = getCurrentUser();
$user_role = getUserRole();
$user_fullname = getUserFullName();
$user_initials = getUserInitials();

$sidebarExtraContent = '';
ob_start();
?>
<div class="sidebar-nav">
    <a href="mes_devoirs.php" class="sidebar-nav-item <?= ($currentPage ?? '') === 'mes_devoirs' ? 'active' : '' ?>">
        <span class="sidebar-nav-icon"><i class="fas fa-tasks"></i></span>
        <span>Mes devoirs</span>
    </a>
    <?php if (in_array($user_role, ['professeur', 'administrateur', 'vie_scolaire'])): ?>
    <a href="corriger.php" class="sidebar-nav-item <?= ($currentPage ?? '') === 'corriger' ? 'active' : '' ?>">
        <span class="sidebar-nav-icon"><i class="fas fa-check-double"></i></span>
        <span>Corriger</span>
    </a>
    <?php endif; ?>
</div>
<?php
$sidebarExtraContent = ob_get_clean();

require_once __DIR__ . '/../../templates/shared_header.php';
require_once __DIR__ . '/../../templates/shared_sidebar.php';
require_once __DIR__ . '/../../templates/shared_topbar.php';
?>
<div class="main-content">
