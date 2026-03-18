<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../API/Legacy/Bridge.php';
requireAuth();

$pdo = getPDO();
require_once __DIR__ . '/CantineService.php';
$cantineService = new CantineService($pdo);

$activePage = $activePage ?? 'cantine';
$extraCss = ['cantine/assets/css/cantine.css'];

$isGestionnaire = isAdmin() || isPersonnelVS();

$sidebarLinks = '<li class="sidebar-item">
    <a href="/cantine/menus.php" class="sidebar-link ' . ($activePage === 'menus' ? 'active' : '') . '">
        <i class="fas fa-book-open"></i><span>Menus</span>
    </a>
</li>';
if ($isGestionnaire || isParent() || isEleve()) {
    $sidebarLinks .= '<li class="sidebar-item">
        <a href="/cantine/reservations.php" class="sidebar-link ' . ($activePage === 'reservations' ? 'active' : '') . '">
            <i class="fas fa-calendar-check"></i><span>Réservations</span>
        </a>
    </li>';
}
if ($isGestionnaire) {
    $sidebarLinks .= '<li class="sidebar-item">
        <a href="/cantine/pointage.php" class="sidebar-link ' . ($activePage === 'pointage' ? 'active' : '') . '">
            <i class="fas fa-check-double"></i><span>Pointage</span>
        </a>
    </li>
    <li class="sidebar-item">
        <a href="/cantine/statistiques.php" class="sidebar-link ' . ($activePage === 'statistiques' ? 'active' : '') . '">
            <i class="fas fa-chart-pie"></i><span>Statistiques</span>
        </a>
    </li>';
}

$sidebarExtraContent = $sidebarLinks;
$pageTitle = $pageTitle ?? 'Cantine';
require_once __DIR__ . '/../../templates/shared_header.php';
?>
