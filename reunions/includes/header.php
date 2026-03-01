<?php
/**
 * M14 – Réunions — Header
 */
$rootPrefix = '../';
require_once __DIR__ . '/../../API/bootstrap.php';
requireAuth();

require_once __DIR__ . '/ReunionService.php';
$reunionService = new ReunionService(getPDO());

$activePage = 'reunions';
$pageTitle = $pageTitle ?? 'Réunions';
$extraCss = ['assets/css/reunions.css'];

if (isAdmin() || isTeacher() || isVieScolaire()) {
    $sidebarExtraContent = '
    <div class="sidebar-nav">
        <a href="reunions.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-calendar-alt"></i></span><span>Réunions</span></a>
        <a href="creer.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-plus"></i></span><span>Planifier</span></a>
        <a href="convocations.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-file-invoice"></i></span><span>Convocations</span></a>
    </div>';
} else {
    $sidebarExtraContent = '
    <div class="sidebar-nav">
        <a href="reunions.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-calendar-alt"></i></span><span>Réunions</span></a>
        <a href="mes_rdv.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-handshake"></i></span><span>Mes RDV</span></a>
        <a href="convocations.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-file-invoice"></i></span><span>Convocations</span></a>
    </div>';
}

require_once __DIR__ . '/../../templates/shared_header.php';
require_once __DIR__ . '/../../templates/shared_sidebar.php';
require_once __DIR__ . '/../../templates/shared_topbar.php';
