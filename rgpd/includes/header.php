<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../API/Legacy/Bridge.php';
requireAuth();

$pdo = getPDO();
require_once __DIR__ . '/AuditRgpdService.php';
$rgpdService = new AuditRgpdService($pdo);

$activePage = $activePage ?? 'audit';
$extraCss = ['rgpd/assets/css/rgpd.css'];

$sidebarLinks = '';
if (isAdmin()) {
    $sidebarLinks .= '<li class="sidebar-item"><a href="/rgpd/audit.php" class="sidebar-link ' . ($activePage === 'audit' ? 'active' : '') . '"><i class="fas fa-history"></i><span>Journal d\'audit</span></a></li>';
    $sidebarLinks .= '<li class="sidebar-item"><a href="/rgpd/demandes.php" class="sidebar-link ' . ($activePage === 'demandes' ? 'active' : '') . '"><i class="fas fa-file-contract"></i><span>Demandes RGPD</span></a></li>';
}
$sidebarLinks .= '<li class="sidebar-item"><a href="/rgpd/consentements.php" class="sidebar-link ' . ($activePage === 'consentements' ? 'active' : '') . '"><i class="fas fa-check-circle"></i><span>Mes consentements</span></a></li>';
$sidebarLinks .= '<li class="sidebar-item"><a href="/rgpd/mes_donnees.php" class="sidebar-link ' . ($activePage === 'mes_donnees' ? 'active' : '') . '"><i class="fas fa-user-shield"></i><span>Mes données</span></a></li>';

$sidebarExtraContent = $sidebarLinks;
$pageTitle = $pageTitle ?? 'RGPD & Audit';
require_once __DIR__ . '/../../templates/shared_header.php';
?>
