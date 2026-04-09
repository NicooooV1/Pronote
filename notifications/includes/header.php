<?php
/**
 * M12 – Notifications — Header
 */
$rootPrefix = '../';
require_once __DIR__ . '/../../API/bootstrap.php';
requireAuth();

require_once __DIR__ . '/NotificationService.php';
$notifService = new NotificationService(getPDO());

$activePage = 'notifications';
$pageTitle = $pageTitle ?? 'Notifications';
$extraCss = ['assets/css/notifications.css'];

$sidebarExtraContent = '
<div class="sidebar-nav">
    <a href="notifications.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-bell"></i></span><span>Toutes</span></a>
    <a href="preferences.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-sliders-h"></i></span><span>Préférences</span></a>
</div>';

require_once __DIR__ . '/../../templates/shared_header.php';
require_once __DIR__ . '/../../templates/shared_topbar.php';
