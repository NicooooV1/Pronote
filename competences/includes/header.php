<?php
/**
 * M38 – Compétences — Header
 */
$rootPrefix = '../';
require_once __DIR__ . '/../../API/bootstrap.php';
requireAuth();

require_once __DIR__ . '/CompetenceService.php';
$compService = new CompetenceService(getPDO());

$activePage = 'competences';
$pageTitle = $pageTitle ?? 'Compétences';
$extraCss = ['assets/css/competences.css'];

$sidebarExtraContent = '<div class="sidebar-nav">';
$sidebarExtraContent .= '<a href="competences.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-clipboard-list"></i></span><span>Référentiel</span></a>';
if (isAdmin() || isTeacher()) {
    $sidebarExtraContent .= '<a href="evaluer.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-check-double"></i></span><span>Évaluer</span></a>';
}
$sidebarExtraContent .= '<a href="bilan.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-chart-pie"></i></span><span>Bilan élève</span></a>';
if (isAdmin() || isTeacher() || isVieScolaire()) {
    $sidebarExtraContent .= '<a href="stats_classe.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-chart-bar"></i></span><span>Stats classe</span></a>';
}
$sidebarExtraContent .= '</div>';

require_once __DIR__ . '/../../templates/shared_header.php';
require_once __DIR__ . '/../../templates/shared_sidebar.php';
require_once __DIR__ . '/../../templates/shared_topbar.php';
