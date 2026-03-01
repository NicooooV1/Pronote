<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../API/Legacy/Bridge.php';
requireAuth();

$pdo = getPDO();
require_once __DIR__ . '/StageService.php';
$stageService = new StageService($pdo);

$activePage = $activePage ?? 'stages';
$extraCss = ['stages/assets/css/stages.css'];

$sidebarLinks = '<li class="sidebar-item"><a href="/stages/stages.php" class="sidebar-link ' . ($activePage === 'stages' ? 'active' : '') . '"><i class="fas fa-briefcase"></i><span>Stages</span></a></li>';
if (isAdmin() || isPersonnelVS()) {
    $sidebarLinks .= '<li class="sidebar-item"><a href="/stages/creer.php" class="sidebar-link ' . ($activePage === 'creer' ? 'active' : '') . '"><i class="fas fa-plus-circle"></i><span>Nouveau</span></a></li>';
}

$sidebarExtraContent = $sidebarLinks;
$pageTitle = $pageTitle ?? 'Stages & Alternance';
require_once __DIR__ . '/../../templates/shared_header.php';
?>
