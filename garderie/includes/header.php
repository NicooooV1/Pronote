<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../API/Legacy/Bridge.php';
requireAuth();

$pdo = getPDO();
require_once __DIR__ . '/GarderieService.php';
$garderieService = new GarderieService($pdo);

$activePage = $activePage ?? 'garderie';
$extraCss = ['garderie/assets/css/garderie.css'];
$isGestionnaire = isAdmin() || isPersonnelVS();

$sidebarLinks = '<li class="sidebar-item">
    <a href="/garderie/creneaux.php" class="sidebar-link ' . ($activePage === 'creneaux' ? 'active' : '') . '"><i class="fas fa-clock"></i><span>Créneaux</span></a>
</li>
<li class="sidebar-item">
    <a href="/garderie/inscriptions.php" class="sidebar-link ' . ($activePage === 'inscriptions' ? 'active' : '') . '"><i class="fas fa-user-plus"></i><span>Inscriptions</span></a>
</li>';
if ($isGestionnaire) {
    $sidebarLinks .= '<li class="sidebar-item">
        <a href="/garderie/presences.php" class="sidebar-link ' . ($activePage === 'presences' ? 'active' : '') . '"><i class="fas fa-check-circle"></i><span>Présences</span></a>
    </li>';
}

$sidebarExtraContent = $sidebarLinks;
$pageTitle = $pageTitle ?? 'Garderie';
require_once __DIR__ . '/../../templates/shared_header.php';
?>
