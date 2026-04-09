<?php
/**
 * M34 – Support & Aide — Header
 */
$rootPrefix = '../';
require_once __DIR__ . '/../../API/bootstrap.php';
requireAuth();

require_once __DIR__ . '/SupportService.php';
$supportService = new SupportService(getPDO());

$activePage = 'support';
$pageTitle = $pageTitle ?? 'Support & Aide';
$extraCss = ['assets/css/support.css'];

$sidebarExtraContent = '
<div class="sidebar-nav">
    <a href="aide.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-question-circle"></i></span><span>FAQ</span></a>
    <a href="tickets.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-ticket-alt"></i></span><span>Mes tickets</span></a>
    <a href="nouveau_ticket.php" class="sidebar-nav-item"><span class="sidebar-nav-icon"><i class="fas fa-plus"></i></span><span>Nouveau ticket</span></a>
</div>';

require_once __DIR__ . '/../../templates/shared_header.php';
require_once __DIR__ . '/../../templates/shared_topbar.php';
