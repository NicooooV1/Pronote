<?php
/**
 * M45 – Anti-harcèlement — Header
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../API/Legacy/Bridge.php';
requireAuth();

$pdo = getPDO();
require_once __DIR__ . '/SignalementService.php';
$signalementService = new SignalementService($pdo);

$activePage = $activePage ?? 'signalements';
$extraCss = ['signalements/assets/css/signalements.css'];

$sidebarLinks = '';
if (isAdmin() || isPersonnelVS()) {
    $sidebarLinks .= '<li class="sidebar-item">
        <a href="/signalements/signalements.php" class="sidebar-link ' . ($activePage === 'signalements' ? 'active' : '') . '">
            <i class="fas fa-list"></i><span>Signalements</span>
        </a>
    </li>';
}
$sidebarLinks .= '<li class="sidebar-item">
    <a href="/signalements/signaler.php" class="sidebar-link ' . ($activePage === 'signaler' ? 'active' : '') . '">
        <i class="fas fa-exclamation-circle"></i><span>Signaler</span>
    </a>
</li>';
if (!isAdmin() && !isPersonnelVS()) {
    $sidebarLinks .= '<li class="sidebar-item">
        <a href="/signalements/mes_signalements.php" class="sidebar-link ' . ($activePage === 'mes_signalements' ? 'active' : '') . '">
            <i class="fas fa-folder"></i><span>Mes signalements</span>
        </a>
    </li>';
}

$sidebarExtraContent = $sidebarLinks;
$pageTitle = $pageTitle ?? 'Signalements';
require_once __DIR__ . '/../../templates/shared_header.php';
?>
