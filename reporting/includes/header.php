<?php
/**
 * M22 – Reporting — Header
 */
$rootPrefix = '../';
require_once __DIR__ . '/../../API/bootstrap.php';
requireAuth();

if (!isAdmin() && !isTeacher() && !isVieScolaire()) {
    redirect('../accueil/accueil.php');
}

require_once __DIR__ . '/ReportingService.php';
$reportService = new ReportingService(getPDO());

$activePage = 'reporting';
$pageTitle = $pageTitle ?? 'Reporting';
$extraCss = ['assets/css/reporting.css'];

$sidebarExtraContent = '
<div class="sidebar-nav">
    <a href="reporting.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-chart-line"></i></span><span>Tableau de bord</span></a>
    <a href="exporter.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-file-csv"></i></span><span>Exporter CSV</span></a>
</div>';

require_once __DIR__ . '/../../templates/shared_header.php';
require_once __DIR__ . '/../../templates/shared_sidebar.php';
require_once __DIR__ . '/../../templates/shared_topbar.php';
