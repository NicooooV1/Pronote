<?php
require_once __DIR__ . '/../../API/core.php';

$pageTitle = $pageTitle ?? 'Bulletins';
$activePage = 'bulletins';
$extraCss = ['assets/css/bulletins.css'];

$user = getCurrentUser();
$user_role = getUserRole();
$user_fullname = getUserFullName();
$user_initials = getUserInitials();

$sidebarExtraContent = '';
if (in_array($user_role, ['administrateur', 'professeur', 'vie_scolaire'])) {
    ob_start();
    ?>
    <div class="sidebar-nav">
        <a href="bulletins.php" class="sidebar-nav-item <?= ($currentPage ?? '') === 'liste' ? 'active' : '' ?>">
            <span class="sidebar-nav-icon"><i class="fas fa-file-alt"></i></span>
            <span>Bulletins</span>
        </a>
        <a href="generer.php" class="sidebar-nav-item <?= ($currentPage ?? '') === 'generer' ? 'active' : '' ?>">
            <span class="sidebar-nav-icon"><i class="fas fa-cogs"></i></span>
            <span>Générer bulletins</span>
        </a>
        <a href="conseil.php" class="sidebar-nav-item <?= ($currentPage ?? '') === 'conseil' ? 'active' : '' ?>">
            <span class="sidebar-nav-icon"><i class="fas fa-user-tie"></i></span>
            <span>Conseil de classe</span>
        </a>
    </div>
    <?php
    $sidebarExtraContent = ob_get_clean();
}

require_once __DIR__ . '/../../templates/shared_header.php';
require_once __DIR__ . '/../../templates/shared_sidebar.php';
require_once __DIR__ . '/../../templates/shared_topbar.php';
?>
<div class="main-content">
