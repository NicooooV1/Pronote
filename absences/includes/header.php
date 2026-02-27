<?php
/**
 * En-tête standardisé pour le module Absences 
 * Utilise les templates partagés Pronote
 */

// S'assurer que l'API est chargée
require_once __DIR__ . '/../../API/core.php';

// S'assurer que les variables nécessaires sont définies
$pageTitle = $pageTitle ?? 'Absences';
$currentPage = $currentPage ?? '';

// Récupérer les informations utilisateur via l'API
if (!isset($user_initials)) {
    $user_initials = getUserInitials();
    $user_fullname = getUserFullName();
}

// Pour l'onglet actif dans le menu
function isActiveLink($page) {
    global $currentPage;
    return $currentPage === $page ? 'active' : '';
}

// canManageAbsences() est fourni par l'API (Bridge)

// Variables pour les templates partagés
$activePage = 'absences';
$isAdmin = isAdmin();
$user_fullname = $user_fullname ?? '';
$extraCss = array_merge(['assets/css/absences.css'], $extraCss ?? []);
$extraHeadHtml = ($extraHeadHtml ?? '') . '';

// Contenu supplémentaire sidebar : Actions du module absences (sauf si déjà défini par la page)
if (!isset($sidebarExtraContent)) {
ob_start();
?>
        <div class="sidebar-section">
            <div class="sidebar-section-header">ACTIONS</div>
            <div class="sidebar-nav">
                <a href="absences.php" class="sidebar-nav-item <?= isActiveLink('liste') ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-list"></i></span>
                    <span>Liste des absences</span>
                </a>
                
                <?php if (canManageAbsences()): ?>
                <a href="ajouter_absence.php" class="sidebar-nav-item <?= isActiveLink('ajouter') ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-plus"></i></span>
                    <span>Signaler une absence</span>
                </a>
                <a href="appel.php" class="sidebar-nav-item <?= isActiveLink('appel') ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-clipboard-list"></i></span>
                    <span>Faire l'appel</span>
                </a>
                <?php endif; ?>
                
                <?php if (isAdmin() || isVieScolaire()): ?>
                <a href="statistiques.php" class="sidebar-nav-item <?= isActiveLink('statistiques') ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-chart-pie"></i></span>
                    <span>Statistiques</span>
                </a>
                <?php endif; ?>
            </div>
        </div>
<?php
$sidebarExtraContent = ob_get_clean();
} // fin if (!isset($sidebarExtraContent))

// Actions supplémentaires dans le header (sauf si déjà défini)
if (!isset($headerExtraActions)) {
ob_start();
?>
                <?php if (canManageAbsences() && $currentPage !== 'ajouter'): ?>
                <a href="ajouter_absence.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Signaler une absence
                </a>
                <?php endif; ?>
<?php
$headerExtraActions = ob_get_clean();
} // fin if (!isset($headerExtraActions))
include __DIR__ . '/../../templates/shared_header.php';
include __DIR__ . '/../../templates/shared_sidebar.php';
include __DIR__ . '/../../templates/shared_topbar.php';
?>

            <div class="content-container">
