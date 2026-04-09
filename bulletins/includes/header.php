<?php
require_once __DIR__ . '/../../API/core.php';

$pageTitle = $pageTitle ?? 'Bulletins';
$activePage = 'bulletins';
$extraCss = ['assets/css/bulletins.css'];

$user = getCurrentUser();
$user_role = getUserRole();
$user_fullname = getUserFullName();
$user_initials = getUserInitials();

// Feature flags
$_bulFeatures = null;
try { $_bulFeatures = app('features'); } catch (\Throwable $e) {}
$ffBatchGen     = $_bulFeatures ? $_bulFeatures->isEnabled('bulletins.batch_generation') : true;
$ffLivePreview  = $_bulFeatures ? $_bulFeatures->isEnabled('bulletins.live_preview') : true;
$ffCustomTpl    = $_bulFeatures ? $_bulFeatures->isEnabled('bulletins.custom_templates') : true;

require_once __DIR__ . '/../../templates/shared_header.php';
require_once __DIR__ . '/../../templates/shared_topbar.php';
?>
<div class="main-content">
