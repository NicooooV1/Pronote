<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../API/Legacy/Bridge.php';
requireAuth();

if (!isAdmin() && !isPersonnelVS() && !isProfesseur()) { redirect('/accueil/accueil.php'); }

$pdo = getPDO();
require_once __DIR__ . '/SallesMaterielService.php';
$smService = new SallesMaterielService($pdo);

$activePage = $activePage ?? 'reservations';
$extraCss = ['salles/assets/css/salles.css'];

$sidebarLinks = '<li class="sidebar-item"><a href="/salles/reservations.php" class="sidebar-link ' . ($activePage === 'reservations' ? 'active' : '') . '"><i class="fas fa-door-open"></i><span>Réservations</span></a></li>';
$sidebarLinks .= '<li class="sidebar-item"><a href="/salles/materiels.php" class="sidebar-link ' . ($activePage === 'materiels' ? 'active' : '') . '"><i class="fas fa-laptop"></i><span>Matériels</span></a></li>';
$sidebarLinks .= '<li class="sidebar-item"><a href="/salles/prets.php" class="sidebar-link ' . ($activePage === 'prets' ? 'active' : '') . '"><i class="fas fa-hand-holding"></i><span>Prêts</span></a></li>';

$sidebarExtraContent = $sidebarLinks;
$pageTitle = $pageTitle ?? 'Salles & Matériels';
require_once __DIR__ . '/../../templates/shared_header.php';
?>
