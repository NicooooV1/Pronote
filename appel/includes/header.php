<?php
/**
 * En-tête standardisé pour le module Appel / Présence (M04)
 * Utilise les templates partagés Fronote
 */

require_once __DIR__ . '/../../API/core.php';

$pageTitle = $pageTitle ?? 'Appel';
$currentPage = $currentPage ?? '';

if (!isset($user_initials)) {
    $user_initials = getUserInitials();
    $user_fullname = getUserFullName();
}

function isActiveAppelLink($page) {
    global $currentPage;
    return $currentPage === $page ? 'active' : '';
}

$activePage = 'appel';
$isAdmin = isAdmin();
$user_fullname = $user_fullname ?? '';
$extraCss = array_merge(['assets/css/appel.css'], $extraCss ?? []);

if (!isset($sidebarExtraContent)) {
ob_start();
?>
            <div class="sidebar-nav">
                <a href="appel.php" class="sidebar-nav-item <?= isActiveAppelLink('appel') ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-clipboard-check"></i></span>
                    <span>Faire l'appel</span>
                </a>

                <?php if (isAdmin() || isVieScolaire()): ?>
                <a href="historique.php" class="sidebar-nav-item <?= isActiveAppelLink('historique') ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-history"></i></span>
                    <span>Historique</span>
                </a>
                <a href="statistiques.php" class="sidebar-nav-item <?= isActiveAppelLink('stats') ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-chart-pie"></i></span>
                    <span>Statistiques</span>
                </a>
                <?php endif; ?>
            </div>
<?php
$sidebarExtraContent = ob_get_clean();
}

if (!isset($headerExtraActions)) {
ob_start();
?>
                <?php if (isTeacher()): ?>
                <a href="appel.php" class="btn btn-primary">
                    <i class="fas fa-clipboard-check"></i> Nouvel appel
                </a>
                <?php endif; ?>
<?php
$headerExtraActions = ob_get_clean();
}

include __DIR__ . '/../../templates/shared_header.php';
include __DIR__ . '/../../templates/shared_sidebar.php';
include __DIR__ . '/../../templates/shared_topbar.php';
?>

            <div class="content-container">
