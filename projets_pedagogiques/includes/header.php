<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../API/bootstrap.php';
$bridge = new \Pronote\Legacy\Bridge();
$bridge->requireAuth();
$pdo = $bridge->getPDO();
require_once __DIR__ . '/ProjetPedagogiqueService.php';
$projetService = new ProjetPedagogiqueService($pdo);

$activePage = $activePage ?? 'projets';
$extraCss = ['projets_pedagogiques/assets/css/projets.css'];
$sidebarLinks = [
    ['url' => '/projets_pedagogiques/projets.php', 'icon' => 'fas fa-project-diagram', 'label' => 'Projets', 'id' => 'projets'],
    ['url' => '/projets_pedagogiques/creer.php', 'icon' => 'fas fa-plus', 'label' => 'Nouveau projet', 'id' => 'creer'],
];
require_once __DIR__ . '/../../templates/shared_header.php';
