<?php
require_once __DIR__ . '/../../API/core.php';

$pageTitle = $pageTitle ?? 'Devoirs en ligne';
$activePage = 'devoirs';
$extraCss = ['assets/css/devoirs.css'];

$user = getCurrentUser();
$user_role = getUserRole();
$user_fullname = getUserFullName();
$user_initials = getUserInitials();

// Feature flags
$_devFeatures = null;
try { $_devFeatures = app('features'); } catch (\Throwable $e) {}
$ffOnlineSubmission = $_devFeatures ? $_devFeatures->isEnabled('devoirs.online_submission') : true;
$ffAutoReminders    = $_devFeatures ? $_devFeatures->isEnabled('devoirs.auto_reminders') : true;
$ffAnnotation       = $_devFeatures ? $_devFeatures->isEnabled('devoirs.annotation') : true;

require_once __DIR__ . '/../../templates/shared_header.php';
require_once __DIR__ . '/../../templates/shared_topbar.php';
?>
<div class="main-content">
