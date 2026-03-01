<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../API/Legacy/Bridge.php';
requireAuth();

$pdo = getPDO();
require_once __DIR__ . '/FacturationService.php';
$factService = new FacturationService($pdo);

$activePage = $activePage ?? 'factures';
$extraCss = ['facturation/assets/css/facturation.css'];

$sidebarLinks = '<li class="sidebar-item"><a href="/facturation/factures.php" class="sidebar-link ' . ($activePage === 'factures' ? 'active' : '') . '"><i class="fas fa-file-invoice-dollar"></i><span>Factures</span></a></li>';
if (isAdmin() || isPersonnelVS()) {
    $sidebarLinks .= '<li class="sidebar-item"><a href="/facturation/creer.php" class="sidebar-link ' . ($activePage === 'creer' ? 'active' : '') . '"><i class="fas fa-plus-circle"></i><span>Nouvelle</span></a></li>';
}

$sidebarExtraContent = $sidebarLinks;
$pageTitle = $pageTitle ?? 'Facturation';
require_once __DIR__ . '/../../templates/shared_header.php';
?>
