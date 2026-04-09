<?php
/**
 * En-tête standardisé pour le module Notes
 * Utilise les templates partagés Fronote (topbar layout)
 */

// S'assurer que l'API est chargée
require_once __DIR__ . '/../../API/core.php';

// S'assurer que les variables nécessaires sont définies
$pageTitle = $pageTitle ?? 'Notes';

if (!isset($user_initials)) {
    $user_initials = getUserInitials();
    $user_fullname = getUserFullName();
}
if (!isset($user_role)) {
    $user_role = getUserRole();
}

$user_fullname = $user_fullname ?? '';
$user_initials = $user_initials ?? '';

// Variables pour les templates partagés
$activePage = 'notes';
$isAdmin = ($user_role ?? '') === 'administrateur';
$extraCss = $extraCss ?? ['assets/css/notes.css'];

// Inclure les templates partagés
include __DIR__ . '/../../templates/shared_header.php';
include __DIR__ . '/../../templates/shared_topbar.php';
?>

            <div class="content-container">
