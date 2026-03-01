<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../API/Legacy/Bridge.php';
requireAuth();

$pdo = getPDO();
require_once __DIR__ . '/RessourceService.php';
$resService = new RessourceService($pdo);

$activePage = $activePage ?? 'ressources';
$extraCss = ['ressources/assets/css/ressources.css'];

$sidebarLinks  = '<li class="sidebar-item"><a href="/ressources/ressources.php" class="sidebar-link ' . ($activePage === 'ressources' ? 'active' : '') . '"><i class="fas fa-book-open"></i><span>Ressources</span></a></li>';
if (isAdmin() || isProfesseur()) {
    $sidebarLinks .= '<li class="sidebar-item"><a href="/ressources/creer.php" class="sidebar-link ' . ($activePage === 'creer' ? 'active' : '') . '"><i class="fas fa-plus-circle"></i><span>Créer</span></a></li>';
    $sidebarLinks .= '<li class="sidebar-item"><a href="/ressources/mes_ressources.php" class="sidebar-link ' . ($activePage === 'mes_ressources' ? 'active' : '') . '"><i class="fas fa-folder"></i><span>Mes ressources</span></a></li>';
}

$sidebarExtraContent = $sidebarLinks;
$pageTitle = $pageTitle ?? 'Ressources pédagogiques';
require_once __DIR__ . '/../../templates/shared_header.php';
?>
