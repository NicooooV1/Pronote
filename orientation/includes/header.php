<?php
/**
 * M28 – Orientation — Header
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../API/Legacy/Bridge.php';
requireAuth();

$pdo = getPDO();
require_once __DIR__ . '/OrientationService.php';
$orientationService = new OrientationService($pdo);

$activePage = $activePage ?? 'orientation';
$extraCss = ['orientation/assets/css/orientation.css'];

$sidebarLinks = '<li class="sidebar-item">
    <a href="/orientation/orientation.php" class="sidebar-link ' . ($activePage === 'orientation' ? 'active' : '') . '">
        <i class="fas fa-list"></i><span>Fiches</span>
    </a>
</li>';

if (isEleve()) {
    $sidebarLinks .= '<li class="sidebar-item">
        <a href="/orientation/fiche.php" class="sidebar-link ' . ($activePage === 'fiche' ? 'active' : '') . '">
            <i class="fas fa-edit"></i><span>Ma fiche</span>
        </a>
    </li>';
}

$sidebarExtraContent = $sidebarLinks;
$pageTitle = $pageTitle ?? 'Orientation';
require_once __DIR__ . '/../../templates/shared_header.php';
?>
