<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../API/Legacy/Bridge.php';
requireAuth();

$pdo = getPDO();
require_once __DIR__ . '/BesoinService.php';
$besoinService = new BesoinService($pdo);

$activePage = $activePage ?? 'besoins';
$extraCss = ['besoins/assets/css/besoins.css'];

$isGestionnaire = isAdmin() || isPersonnelVS() || isProfesseur();
$sidebarLinks = '<li class="sidebar-item"><a href="/besoins/besoins.php" class="sidebar-link ' . ($activePage === 'besoins' ? 'active' : '') . '"><i class="fas fa-hands-helping"></i><span>Plans</span></a></li>';
if (isAdmin() || isPersonnelVS()) {
    $sidebarLinks .= '<li class="sidebar-item"><a href="/besoins/creer.php" class="sidebar-link ' . ($activePage === 'creer' ? 'active' : '') . '"><i class="fas fa-plus-circle"></i><span>Nouveau plan</span></a></li>';
}

$sidebarExtraContent = $sidebarLinks;
$pageTitle = $pageTitle ?? 'Besoins particuliers';
require_once __DIR__ . '/../../templates/shared_header.php';
?>
