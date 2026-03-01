<?php
/**
 * M16 – Documents — Header
 */
$rootPrefix = '../';
require_once __DIR__ . '/../../API/bootstrap.php';
requireAuth();

require_once __DIR__ . '/DocumentService.php';
$docService = new DocumentService(getPDO());

$activePage = 'documents';
$pageTitle = $pageTitle ?? 'Documents';
$extraCss = ['assets/css/documents.css'];

// Sidebar extra pour admin/prof
if (isAdmin() || isTeacher() || isVieScolaire()) {
    $sidebarExtraContent = '
    <div class="sidebar-nav">
        <a href="documents.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-folder-open"></i></span><span>Tous les documents</span></a>
        <a href="ajouter.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-upload"></i></span><span>Ajouter un document</span></a>
    </div>';
}

require_once __DIR__ . '/../../templates/shared_header.php';
require_once __DIR__ . '/../../templates/shared_sidebar.php';
require_once __DIR__ . '/../../templates/shared_topbar.php';
