<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../API/Legacy/Bridge.php';
requireAuth();

if (!isAdmin() && !isPersonnelVS()) { redirect('/accueil/accueil.php'); }

$pdo = getPDO();
require_once __DIR__ . '/PersonnelService.php';
$personnelService = new PersonnelService($pdo);

$activePage = $activePage ?? 'absences';
$extraCss = ['personnel/assets/css/personnel.css'];

$sidebarLinks = '<li class="sidebar-item"><a href="/personnel/absences.php" class="sidebar-link ' . ($activePage === 'absences' ? 'active' : '') . '"><i class="fas fa-user-clock"></i><span>Absences</span></a></li>';
$sidebarLinks .= '<li class="sidebar-item"><a href="/personnel/remplacements.php" class="sidebar-link ' . ($activePage === 'remplacements' ? 'active' : '') . '"><i class="fas fa-exchange-alt"></i><span>Remplacements</span></a></li>';

$sidebarExtraContent = $sidebarLinks;
$pageTitle = $pageTitle ?? 'Gestion personnel';
require_once __DIR__ . '/../../templates/shared_header.php';
?>
