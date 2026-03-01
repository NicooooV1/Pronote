<?php
/**
 * M26 – Inscriptions — Header
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../API/Legacy/Bridge.php';
requireAuth();

$pdo = getPDO();
require_once __DIR__ . '/InscriptionService.php';
$inscriptionService = new InscriptionService($pdo);

$activePage = $activePage ?? 'inscriptions';
$extraCss = ['inscriptions/assets/css/inscriptions.css'];

$sidebarLinks = '<li class="sidebar-item">
    <a href="/inscriptions/inscriptions.php" class="sidebar-link ' . ($activePage === 'inscriptions' ? 'active' : '') . '">
        <i class="fas fa-list"></i><span>' . (isAdmin() || isPersonnelVS() ? 'Demandes' : 'Mes inscriptions') . '</span>
    </a>
</li>';

if (isParent()) {
    $sidebarLinks .= '<li class="sidebar-item">
        <a href="/inscriptions/formulaire.php" class="sidebar-link ' . ($activePage === 'formulaire' ? 'active' : '') . '">
            <i class="fas fa-plus-circle"></i><span>Nouvelle inscription</span>
        </a>
    </li>';
}

$sidebarExtraContent = $sidebarLinks;
$pageTitle = $pageTitle ?? 'Inscriptions';
require_once __DIR__ . '/../../templates/shared_header.php';
?>
