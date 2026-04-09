<?php
/**
 * M17 – Paramètres — Header
 */
$rootPrefix = '../';
require_once __DIR__ . '/../../API/bootstrap.php';
requireAuth();

require_once __DIR__ . '/SettingsService.php';
$settingsService = new SettingsService(getPDO());

// Make ModuleService available via use statement import
use API\Services\ModuleService;

$activePage = 'parametres';
$pageTitle = $pageTitle ?? 'Paramètres';
$extraCss = ['assets/css/parametres.css'];

$section = $_GET['section'] ?? 'profil';
$sidebarExtraContent = '
<div class="sidebar-nav">
    <a href="parametres.php?section=profil" class="sidebar-nav-item' . ($section === 'profil' ? ' active' : '') . '"><span class="sidebar-nav-icon"><i class="fas fa-user"></i></span><span>Profil</span></a>
    <a href="parametres.php?section=securite" class="sidebar-nav-item' . ($section === 'securite' ? ' active' : '') . '"><span class="sidebar-nav-icon"><i class="fas fa-lock"></i></span><span>Sécurité & 2FA</span></a>
    <a href="parametres.php?section=accueil" class="sidebar-nav-item' . ($section === 'accueil' ? ' active' : '') . '"><span class="sidebar-nav-icon"><i class="fas fa-th-large"></i></span><span>Tableau de bord</span></a>
</div>';

require_once __DIR__ . '/../../templates/shared_header.php';
require_once __DIR__ . '/../../templates/shared_topbar.php';
