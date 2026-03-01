<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../API/Legacy/Bridge.php';
requireAuth();

$pdo = getPDO();
require_once __DIR__ . '/PeriscolaireService.php';
$periService = new PeriscolaireService($pdo);

$activePage = $activePage ?? 'services';
$extraCss = ['periscolaire/assets/css/periscolaire.css'];

$sidebarLinks = '<li class="sidebar-item"><a href="/periscolaire/services.php" class="sidebar-link ' . ($activePage === 'services' ? 'active' : '') . '"><i class="fas fa-concierge-bell"></i><span>Services</span></a></li>';
$sidebarLinks .= '<li class="sidebar-item"><a href="/periscolaire/menus.php" class="sidebar-link ' . ($activePage === 'menus' ? 'active' : '') . '"><i class="fas fa-utensils"></i><span>Menus cantine</span></a></li>';
if (isParent()) {
    $sidebarLinks .= '<li class="sidebar-item"><a href="/periscolaire/mes_inscriptions.php" class="sidebar-link ' . ($activePage === 'mes_inscriptions' ? 'active' : '') . '"><i class="fas fa-clipboard-list"></i><span>Mes inscriptions</span></a></li>';
}
if (isAdmin() || isPersonnelVS()) {
    $sidebarLinks .= '<li class="sidebar-item"><a href="/periscolaire/presences.php" class="sidebar-link ' . ($activePage === 'presences' ? 'active' : '') . '"><i class="fas fa-clipboard-check"></i><span>Présences</span></a></li>';
}

$sidebarExtraContent = $sidebarLinks;
$pageTitle = $pageTitle ?? 'Périscolaire';
require_once __DIR__ . '/../../templates/shared_header.php';
?>
