<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../API/Legacy/Bridge.php';
requireAuth();

$pdo = getPDO();
require_once __DIR__ . '/TransportInternatService.php';
$tiService = new TransportInternatService($pdo);

$activePage = $activePage ?? 'lignes';
$extraCss = ['transports/assets/css/transports.css'];

$sidebarLinks = '<li class="sidebar-item"><a href="/transports/lignes.php" class="sidebar-link ' . ($activePage === 'lignes' ? 'active' : '') . '"><i class="fas fa-bus"></i><span>Transports</span></a></li>';
$sidebarLinks .= '<li class="sidebar-item"><a href="/transports/internat.php" class="sidebar-link ' . ($activePage === 'internat' ? 'active' : '') . '"><i class="fas fa-bed"></i><span>Internat</span></a></li>';

$sidebarExtraContent = $sidebarLinks;
$pageTitle = $pageTitle ?? 'Transports & Internat';
require_once __DIR__ . '/../../templates/shared_header.php';
?>
