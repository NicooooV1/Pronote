<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../API/Legacy/Bridge.php';
requireAuth();

$pdo = getPDO();
require_once __DIR__ . '/ExamenService.php';
$examenService = new ExamenService($pdo);

$activePage = $activePage ?? 'examens';
$extraCss = ['examens/assets/css/examens.css'];

$isGestionnaire = isAdmin() || isPersonnelVS();
$sidebarLinks = '<li class="sidebar-item"><a href="/examens/examens.php" class="sidebar-link ' . ($activePage === 'examens' ? 'active' : '') . '"><i class="fas fa-graduation-cap"></i><span>Examens</span></a></li>';
if ($isGestionnaire) {
    $sidebarLinks .= '<li class="sidebar-item"><a href="/examens/creer.php" class="sidebar-link ' . ($activePage === 'creer' ? 'active' : '') . '"><i class="fas fa-plus-circle"></i><span>Créer examen</span></a></li>';
}
if (isEleve()) {
    $sidebarLinks .= '<li class="sidebar-item"><a href="/examens/mes_convocations.php" class="sidebar-link ' . ($activePage === 'mes_convocations' ? 'active' : '') . '"><i class="fas fa-file-alt"></i><span>Mes convocations</span></a></li>';
}

$sidebarExtraContent = $sidebarLinks;
$pageTitle = $pageTitle ?? 'Examens';
require_once __DIR__ . '/../../templates/shared_header.php';
?>
