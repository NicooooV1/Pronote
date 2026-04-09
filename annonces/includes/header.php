<?php
/**
 * En-tête standardisé pour le module Annonces / Sondages (M11)
 */

require_once __DIR__ . '/../../API/core.php';

$pageTitle = $pageTitle ?? 'Annonces';
$currentPage = $currentPage ?? '';

if (!isset($user_initials)) {
    $user_initials = getUserInitials();
    $user_fullname = getUserFullName();
}

function isActiveAnnonceLink($page) {
    global $currentPage;
    return $currentPage === $page ? 'active' : '';
}

$activePage = 'annonces';
$isAdmin = isAdmin();
$user_fullname = $user_fullname ?? '';
$extraCss = array_merge(['assets/css/annonces.css'], $extraCss ?? []);

if (!isset($sidebarExtraContent)) {
ob_start();
?>
            <div class="sidebar-nav">
                <a href="annonces.php" class="sidebar-nav-item <?= isActiveAnnonceLink('annonces') ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-bullhorn"></i></span>
                    <span>Annonces</span>
                </a>
                <?php if (isAdmin() || isVieScolaire() || isTeacher()): ?>
                <a href="creer_annonce.php" class="sidebar-nav-item <?= isActiveAnnonceLink('creer') ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-plus-circle"></i></span>
                    <span>Nouvelle annonce</span>
                </a>
                <?php endif; ?>
                <?php if (isAdmin()): ?>
                <a href="gestion.php" class="sidebar-nav-item <?= isActiveAnnonceLink('gestion') ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-cog"></i></span>
                    <span>Gestion</span>
                </a>
                <?php endif; ?>
            </div>
<?php
$sidebarExtraContent = ob_get_clean();
}

if (!isset($headerExtraActions)) {
ob_start();
?>
                <?php if (isAdmin() || isVieScolaire() || isTeacher()): ?>
                <a href="creer_annonce.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nouvelle annonce
                </a>
                <?php endif; ?>
<?php
$headerExtraActions = ob_get_clean();
}

include __DIR__ . '/../../templates/shared_header.php';
include __DIR__ . '/../../templates/shared_topbar.php';
?>

            <div class="content-container">
