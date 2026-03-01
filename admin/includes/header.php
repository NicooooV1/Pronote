<?php
/**
 * En-tête pour le module Administration (root level: dashboard.php)
 * N'injecte PLUS de sous-navigation admin dans la sidebar.
 * La sidebar globale gère la section Administration avec un seul lien.
 * Ajoute un fil d'Ariane dans le contenu.
 */

// S'assurer que l'API est chargée
require_once __DIR__ . '/../../API/core.php';
require_once __DIR__ . '/admin_functions.php';

requireAuth();
requireRole('administrateur');

// Récupérer les informations utilisateur via l'API
if (!isset($user_initials)) {
    $user_initials = getUserInitials();
    $user_fullname = getUserFullName();
}

// Variables pour les templates partagés
$pageTitle = $pageTitle ?? 'Administration';
$activePage = 'admin';
$isAdmin = true;
$user_fullname = $user_fullname ?? '';
$currentPage = $currentPage ?? '';
$extraCss = array_merge([], $extraCss ?? []);
$extraHeadHtml = ($extraHeadHtml ?? '') . '';

// Pas de contenu sidebar supplémentaire pour l'admin
$sidebarExtraContent = '';

// Inclure les templates partagés (sidebar globale intacte)
include __DIR__ . '/../../templates/shared_header.php';
include __DIR__ . '/../../templates/shared_sidebar.php';
include __DIR__ . '/../../templates/shared_topbar.php';
?>

<div class="content-container">
    <?= renderAdminBreadcrumb($currentPage, $pageTitle, '../') ?>
