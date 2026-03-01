<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../API/Legacy/Bridge.php';
requireAuth();

$pdo = getPDO();
require_once __DIR__ . '/DiplomeService.php';
$diplService = new DiplomeService($pdo);

$activePage = $activePage ?? 'diplomes';
$extraCss = ['diplomes/assets/css/diplomes.css'];

$sidebarLinks  = '<li class="sidebar-item"><a href="/diplomes/diplomes.php" class="sidebar-link ' . ($activePage === 'diplomes' ? 'active' : '') . '"><i class="fas fa-graduation-cap"></i><span>Diplômes</span></a></li>';
if (isAdmin() || isPersonnelVS()) {
    $sidebarLinks .= '<li class="sidebar-item"><a href="/diplomes/creer.php" class="sidebar-link ' . ($activePage === 'creer' ? 'active' : '') . '"><i class="fas fa-plus-circle"></i><span>Nouveau</span></a></li>';
}

$sidebarExtraContent = $sidebarLinks;
$pageTitle = $pageTitle ?? 'Diplômes & Relevés';
require_once __DIR__ . '/../../templates/shared_header.php';
?>
