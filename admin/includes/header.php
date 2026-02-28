<?php
/**
 * En-tête standardisé pour le module Administration
 * Utilise les templates partagés Fronote
 */

// S'assurer que l'API est chargée
require_once __DIR__ . '/../../API/core.php';

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

// Pas de contenu sidebar supplémentaire pour l'admin (la section est intégrée au template partagé)
$sidebarExtraContent = '';

// Inclure les templates partagés
include __DIR__ . '/../../templates/shared_header.php';
include __DIR__ . '/../../templates/shared_sidebar.php';
include __DIR__ . '/../../templates/shared_topbar.php';
?>

            <div class="content-container">
