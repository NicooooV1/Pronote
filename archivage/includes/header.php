<?php
/**
 * M35 – Archivage annuel — Header
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../API/Legacy/Bridge.php';
requireAuth();

// Admin uniquement
if (!isAdmin()) {
    redirect('/accueil/accueil.php');
}

$pdo = getPDO();
require_once __DIR__ . '/ArchiveService.php';
$archiveService = new ArchiveService($pdo);

$activePage = $activePage ?? 'archivage';
$extraCss = ['archivage/assets/css/archivage.css'];

$sidebarExtraContent = '
<li class="sidebar-item">
    <a href="/archivage/archivage.php" class="sidebar-link ' . ($activePage === 'archivage' ? 'active' : '') . '">
        <i class="fas fa-archive"></i><span>Archives</span>
    </a>
</li>
<li class="sidebar-item">
    <a href="/archivage/creer.php" class="sidebar-link ' . ($activePage === 'creer' ? 'active' : '') . '">
        <i class="fas fa-plus-circle"></i><span>Nouvelle archive</span>
    </a>
</li>';

$pageTitle = $pageTitle ?? 'Archivage';
require_once __DIR__ . '/../../templates/shared_header.php';
?>
