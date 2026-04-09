<?php
/**
 * En-tête commun pour le module Cahier de Textes (topbar layout)
 */
require_once __DIR__ . '/../../API/core.php';

if (!isset($user_initials)) {
    $user_initials = getUserInitials();
}
$user_fullname = $user_fullname ?? getUserFullName();

$pageTitle  = $pageTitle ?? 'Cahier de Textes';
$activePage = 'cahierdetextes';
$isAdmin    = isAdmin();
$extraCss = $extraCss ?? ['assets/css/cahierdetextes.css'];
$extraJs  = $extraJs  ?? ['assets/js/cahierdetextes.js'];

// Feature flags
$_cdtFeatures = null;
try { $_cdtFeatures = app('features'); } catch (\Throwable $e) {}
$ffRichEditor     = $_cdtFeatures ? $_cdtFeatures->isEnabled('cahierdetextes.rich_editor') : true;
$ffFileAttach     = $_cdtFeatures ? $_cdtFeatures->isEnabled('cahierdetextes.file_attachments') : true;
$ffCopyToClass    = $_cdtFeatures ? $_cdtFeatures->isEnabled('cahierdetextes.copy_to_class') : true;

include __DIR__ . '/../../templates/shared_header.php';
include __DIR__ . '/../../templates/shared_topbar.php';
?>

            <div class="content-container">
