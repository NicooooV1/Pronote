<?php
/**
 * En-tête standardisé pour le module Emploi du Temps (M03)
 * Utilise les templates partagés Fronote
 */

require_once __DIR__ . '/../../API/core.php';

$pageTitle = $pageTitle ?? 'Emploi du temps';
$currentPage = $currentPage ?? '';

if (!isset($user_initials)) {
    $user_initials = getUserInitials();
    $user_fullname = getUserFullName();
}

function isActiveEdtLink($page) {
    global $currentPage;
    return $currentPage === $page ? 'active' : '';
}

// Variables pour les templates partagés
$activePage = 'emploi_du_temps';
$isAdmin = isAdmin();
$user_fullname = $user_fullname ?? '';
$extraCss = array_merge(['assets/css/emploi_du_temps.css'], $extraCss ?? []);

// Sidebar : actions du module
if (!isset($sidebarExtraContent)) {
ob_start();
?>
            <div class="sidebar-nav">
                <a href="emploi_du_temps.php" class="sidebar-nav-item <?= isActiveEdtLink('grille') ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-calendar-week"></i></span>
                    <span>Grille hebdomadaire</span>
                </a>

                <?php if (isAdmin() || isVieScolaire()): ?>
                <a href="gerer_cours.php" class="sidebar-nav-item <?= isActiveEdtLink('gerer') ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-edit"></i></span>
                    <span>Gérer les cours</span>
                </a>
                <a href="conflits.php" class="sidebar-nav-item <?= isActiveEdtLink('conflits') ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-exclamation-triangle"></i></span>
                    <span>Conflits</span>
                </a>
                <a href="salles.php" class="sidebar-nav-item <?= isActiveEdtLink('salles') ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-door-open"></i></span>
                    <span>Salles</span>
                </a>
                <a href="creneaux.php" class="sidebar-nav-item <?= isActiveEdtLink('creneaux') ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-clock"></i></span>
                    <span>Créneaux horaires</span>
                </a>
                <?php endif; ?>
            </div>
<?php
$sidebarExtraContent = ob_get_clean();
}

if (!isset($headerExtraActions)) {
ob_start();
?>
                <?php if (isAdmin() && $currentPage !== 'gerer'): ?>
                <a href="gerer_cours.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Ajouter un cours
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
