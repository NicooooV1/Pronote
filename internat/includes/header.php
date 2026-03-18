<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../API/Legacy/Bridge.php';
requireAuth();

$pdo = getPDO();
require_once __DIR__ . '/InternatService.php';
$internatService = new InternatService($pdo);

$activePage = $activePage ?? 'internat';
$extraCss = ['internat/assets/css/internat.css'];
$isGestionnaire = isAdmin() || isPersonnelVS();

$sidebarLinks = '<li class="sidebar-item">
    <a href="/internat/chambres.php" class="sidebar-link ' . ($activePage === 'chambres' ? 'active' : '') . '"><i class="fas fa-bed"></i><span>Chambres</span></a>
</li>
<li class="sidebar-item">
    <a href="/internat/affectations.php" class="sidebar-link ' . ($activePage === 'affectations' ? 'active' : '') . '"><i class="fas fa-user-check"></i><span>Affectations</span></a>
</li>';
if ($isGestionnaire) {
    $sidebarLinks .= '<li class="sidebar-item">
        <a href="/internat/mouvements.php" class="sidebar-link ' . ($activePage === 'mouvements' ? 'active' : '') . '"><i class="fas fa-exchange-alt"></i><span>Mouvements</span></a>
    </li>
    <li class="sidebar-item">
        <a href="/internat/incidents.php" class="sidebar-link ' . ($activePage === 'incidents' ? 'active' : '') . '"><i class="fas fa-exclamation-triangle"></i><span>Incidents</span></a>
    </li>';
}

$sidebarExtraContent = $sidebarLinks;
$pageTitle = $pageTitle ?? 'Internat';
require_once __DIR__ . '/../../templates/shared_header.php';
?>
