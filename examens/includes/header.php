<?php
/**
 * En-tête standardisé pour le module Examens (topbar layout)
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../API/core.php';
requireAuth();

$pdo = getPDO();
require_once __DIR__ . '/ExamenService.php';
$examenService = new ExamenService($pdo);

$activePage = $activePage ?? 'examens';
$extraCss = ['assets/css/examens.css'];
$isGestionnaire = isAdmin() || isVieScolaire();

// Feature flags
$_exFeatures = null;
try { $_exFeatures = app('features'); } catch (\Throwable $e) {}
$ffAutoRoom       = $_exFeatures ? $_exFeatures->isEnabled('examens.auto_room_assignment') : true;
$ffPdfConvoc      = $_exFeatures ? $_exFeatures->isEnabled('examens.pdf_convocations') : true;
$ffSurveillance   = $_exFeatures ? $_exFeatures->isEnabled('examens.surveillance_planning') : true;

$pageTitle = $pageTitle ?? 'Examens';
$user_initials = $user_initials ?? getUserInitials();
$user_fullname = $user_fullname ?? getUserFullName();

require_once __DIR__ . '/../../templates/shared_header.php';
require_once __DIR__ . '/../../templates/shared_topbar.php';
