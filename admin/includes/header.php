<?php
/**
 * En-tête unifié pour toutes les pages admin.
 * Détecte automatiquement la profondeur (root ou sous-dossier) pour calculer $rootPrefix.
 * Remplace l'ancien header.php (root) et sub_header.php (sous-pages).
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
$user_fullname = $user_fullname ?? '';
$currentPage = $currentPage ?? '';
$extraCss = array_merge([], $extraCss ?? []);
$extraHeadHtml = ($extraHeadHtml ?? '') . '';
$sidebarExtraContent = '';

// Auto-détection de la profondeur pour $rootPrefix
if (!isset($rootPrefix)) {
    $callerFile = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]['file'] ?? '';
    $adminDir = str_replace('\\', '/', dirname(__DIR__));
    $callerDir = str_replace('\\', '/', dirname($callerFile));
    $relative = substr($callerDir, strlen($adminDir));
    $depth = $relative ? substr_count(ltrim($relative, '/'), '/') + 1 : 0;
    $rootPrefix = str_repeat('../', $depth + 1);
}

include __DIR__ . '/../../templates/shared_header.php';
include __DIR__ . '/../../templates/shared_sidebar.php';
include __DIR__ . '/../../templates/shared_topbar.php';
?>

<div class="content-container">
    <?= renderAdminBreadcrumb($currentPage, $pageTitle, $rootPrefix) ?>
