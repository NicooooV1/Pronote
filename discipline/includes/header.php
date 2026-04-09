<?php
/**
 * En-tête standardisé pour le module Discipline (M06)
 */

require_once __DIR__ . '/../../API/core.php';

$pageTitle = $pageTitle ?? 'Discipline';
$currentPage = $currentPage ?? '';

if (!isset($user_initials)) {
    $user_initials = getUserInitials();
    $user_fullname = getUserFullName();
}

function isActiveDisciplineLink($page) {
    global $currentPage;
    return $currentPage === $page ? 'active' : '';
}

$activePage = 'discipline';
$isAdmin = isAdmin();
$user_fullname = $user_fullname ?? '';
$extraCss = array_merge(['assets/css/discipline.css'], $extraCss ?? []);

if (!isset($sidebarExtraContent)) {
ob_start();
?>
            <div class="sidebar-nav">
                <a href="incidents.php" class="sidebar-nav-item <?= isActiveDisciplineLink('incidents') ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-exclamation-triangle"></i></span>
                    <span>Incidents</span>
                </a>
                <a href="sanctions.php" class="sidebar-nav-item <?= isActiveDisciplineLink('sanctions') ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-gavel"></i></span>
                    <span>Sanctions</span>
                </a>
                <a href="retenues.php" class="sidebar-nav-item <?= isActiveDisciplineLink('retenues') ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-user-clock"></i></span>
                    <span>Retenues</span>
                </a>
                <a href="signaler.php" class="sidebar-nav-item <?= isActiveDisciplineLink('signaler') ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-plus-circle"></i></span>
                    <span>Signaler un incident</span>
                </a>
            </div>
<?php
$sidebarExtraContent = ob_get_clean();
}

if (!isset($headerExtraActions)) {
ob_start();
?>
                <a href="signaler.php" class="btn btn-primary">
                    <i class="fas fa-exclamation-triangle"></i> Signaler
                </a>
<?php
$headerExtraActions = ob_get_clean();
}

include __DIR__ . '/../../templates/shared_header.php';
include __DIR__ . '/../../templates/shared_topbar.php';
?>

            <div class="content-container">
