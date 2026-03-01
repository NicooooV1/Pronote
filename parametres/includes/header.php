<?php
/**
 * M17 – Paramètres — Header
 */
$rootPrefix = '../';
require_once __DIR__ . '/../../API/bootstrap.php';
requireAuth();

require_once __DIR__ . '/SettingsService.php';
$settingsService = new SettingsService(getPDO());

$activePage = 'parametres';
$pageTitle = $pageTitle ?? 'Paramètres';
$extraCss = ['assets/css/parametres.css'];

$sidebarExtraContent = '
<div class="sidebar-nav">
    <a href="parametres.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-user-cog"></i></span><span>Profil & préférences</span></a>
    <a href="parametres.php#securite" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-lock"></i></span><span>Sécurité</span></a>
</div>';

require_once __DIR__ . '/../../templates/shared_header.php';
require_once __DIR__ . '/../../templates/shared_sidebar.php';
require_once __DIR__ . '/../../templates/shared_topbar.php';
