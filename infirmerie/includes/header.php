<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../API/Legacy/Bridge.php';
requireAuth();

$pdo = getPDO();
require_once __DIR__ . '/InfirmerieService.php';
$infirmerieService = new InfirmerieService($pdo);

$activePage = $activePage ?? 'infirmerie';
$extraCss = ['infirmerie/assets/css/infirmerie.css'];

$isGestionnaire = isAdmin() || isPersonnelVS();

$sidebarLinks = '<li class="sidebar-item">
    <a href="/infirmerie/infirmerie.php" class="sidebar-link ' . ($activePage === 'infirmerie' ? 'active' : '') . '">
        <i class="fas fa-heartbeat"></i><span>Infirmerie</span>
    </a>
</li>';
if ($isGestionnaire) {
    $sidebarLinks .= '<li class="sidebar-item">
        <a href="/infirmerie/passage.php" class="sidebar-link ' . ($activePage === 'passage' ? 'active' : '') . '">
            <i class="fas fa-plus-circle"></i><span>Nouveau passage</span>
        </a>
    </li>
    <li class="sidebar-item">
        <a href="/infirmerie/fiches.php" class="sidebar-link ' . ($activePage === 'fiches' ? 'active' : '') . '">
            <i class="fas fa-folder-open"></i><span>Fiches santé</span>
        </a>
    </li>';
}

$sidebarExtraContent = $sidebarLinks;
$pageTitle = $pageTitle ?? 'Infirmerie';
require_once __DIR__ . '/../../templates/shared_header.php';
?>
