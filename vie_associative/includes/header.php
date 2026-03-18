<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../API/bootstrap.php';
$bridge = new \Pronote\Legacy\Bridge();
$bridge->requireAuth();
$pdo = $bridge->getPDO();
require_once __DIR__ . '/VieAssociativeService.php';
$vieAssoService = new VieAssociativeService($pdo);

$activePage = $activePage ?? 'associations';
$extraCss = ['vie_associative/assets/css/vie_associative.css'];
$sidebarLinks = [
    ['url' => '/vie_associative/associations.php', 'icon' => 'fas fa-hands-helping', 'label' => 'Associations', 'id' => 'associations'],
];
require_once __DIR__ . '/../../templates/shared_header.php';
