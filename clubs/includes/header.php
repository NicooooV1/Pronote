<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../API/Legacy/Bridge.php';
requireAuth();

$pdo = getPDO();
require_once __DIR__ . '/ClubService.php';
$clubService = new ClubService($pdo);

$activePage = $activePage ?? 'clubs';
$extraCss = ['clubs/assets/css/clubs.css'];

$isGestionnaire = isAdmin() || isPersonnelVS() || isProfesseur();
$sidebarLinks = '<li class="sidebar-item">
    <a href="/clubs/clubs.php" class="sidebar-link ' . ($activePage === 'clubs' ? 'active' : '') . '">
        <i class="fas fa-users"></i><span>Clubs</span>
    </a>
</li>';
if (isEleve()) {
    $sidebarLinks .= '<li class="sidebar-item">
        <a href="/clubs/mes_clubs.php" class="sidebar-link ' . ($activePage === 'mes_clubs' ? 'active' : '') . '">
            <i class="fas fa-id-card"></i><span>Mes clubs</span>
        </a>
    </li>';
}
if ($isGestionnaire) {
    $sidebarLinks .= '<li class="sidebar-item">
        <a href="/clubs/creer.php" class="sidebar-link ' . ($activePage === 'creer' ? 'active' : '') . '">
            <i class="fas fa-plus-circle"></i><span>Créer un club</span>
        </a>
    </li>';
}

$sidebarExtraContent = $sidebarLinks;
$pageTitle = $pageTitle ?? 'Clubs';
require_once __DIR__ . '/../../templates/shared_header.php';
?>
