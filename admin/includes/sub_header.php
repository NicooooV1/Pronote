<?php
/**
 * En-tête pour les sous-pages admin (users/, scolaire/, classes/, messagerie/, etablissement/, systeme/)
 * N'injecte PLUS de sous-navigation admin dans la sidebar.
 * La sidebar globale gère la section Administration avec un seul lien.
 * Ajuste le rootPrefix pour les templates partagés puisqu'on est un niveau plus profond.
 * Ajoute un fil d'Ariane dans le contenu.
 */
require_once __DIR__ . '/../../API/core.php';
require_once __DIR__ . '/admin_functions.php';

requireAuth();
requireRole('administrateur');

if (!isset($user_initials)) {
    $user_initials = getUserInitials();
    $user_fullname = getUserFullName();
}

$pageTitle = $pageTitle ?? 'Administration';
$activePage = 'admin';
$isAdmin = true;
$rootPrefix = '../../';
$user_fullname = $user_fullname ?? '';
$currentPage = $currentPage ?? '';
$extraCss = array_merge([], $extraCss ?? []);
$extraHeadHtml = ($extraHeadHtml ?? '') . '';
$sidebarExtraContent = '';

include __DIR__ . '/../../templates/shared_header.php';
include __DIR__ . '/../../templates/shared_sidebar.php';
include __DIR__ . '/../../templates/shared_topbar.php';
?>

            <div class="content-container">
                <?= renderAdminBreadcrumb($currentPage, $pageTitle, '../../') ?>