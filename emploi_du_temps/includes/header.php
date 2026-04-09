<?php
/**
 * En-tête standardisé pour le module Emploi du Temps (topbar layout)
 */
require_once __DIR__ . '/../../API/core.php';

$pageTitle = $pageTitle ?? 'Emploi du temps';
$currentPage = $currentPage ?? '';

if (!isset($user_initials)) {
    $user_initials = getUserInitials();
    $user_fullname = getUserFullName();
}

$activePage = 'emploi_du_temps';
$isAdmin = isAdmin();
$user_fullname = $user_fullname ?? '';
$extraCss = array_merge(['assets/css/emploi_du_temps.css'], $extraCss ?? []);

// Feature flags
$_edtFeatures = null;
try { $_edtFeatures = app('features'); } catch (\Throwable $e) {}
$ffDragDrop         = $_edtFeatures ? $_edtFeatures->isEnabled('emploi_du_temps.drag_drop_editor') : true;
$ffConflictDetect   = $_edtFeatures ? $_edtFeatures->isEnabled('emploi_du_temps.conflict_detection') : true;
$ffIcalExport       = $_edtFeatures ? $_edtFeatures->isEnabled('emploi_du_temps.ical_export') : true;
$ffReplacements     = $_edtFeatures ? $_edtFeatures->isEnabled('emploi_du_temps.replacements') : true;

if (!isset($headerExtraActions)) {
    ob_start();
    if (isAdmin() && $currentPage !== 'gerer') {
        echo '<a href="gerer_cours.php" class="btn btn-primary"><i class="fas fa-plus"></i> Ajouter un cours</a>';
    }
    if ($ffIcalExport) {
        echo ' <a href="export_ical.php" class="btn btn-secondary btn-sm"><i class="fas fa-calendar-alt"></i> iCal</a>';
    }
    $headerExtraActions = ob_get_clean();
}

include __DIR__ . '/../../templates/shared_header.php';
include __DIR__ . '/../../templates/shared_topbar.php';
?>

            <div class="content-container">
