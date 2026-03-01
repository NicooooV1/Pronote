<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../API/Legacy/Bridge.php';
requireAuth();

$pdo = getPDO();
require_once __DIR__ . '/BibliothequeService.php';
$biblioService = new BibliothequeService($pdo);

$activePage = $activePage ?? 'catalogue';
$extraCss = ['bibliotheque/assets/css/bibliotheque.css'];

$isGestionnaire = isAdmin() || isPersonnelVS();
$sidebarLinks = '<li class="sidebar-item">
    <a href="/bibliotheque/catalogue.php" class="sidebar-link ' . ($activePage === 'catalogue' ? 'active' : '') . '">
        <i class="fas fa-book"></i><span>Catalogue</span>
    </a>
</li>
<li class="sidebar-item">
    <a href="/bibliotheque/emprunts.php" class="sidebar-link ' . ($activePage === 'emprunts' ? 'active' : '') . '">
        <i class="fas fa-exchange-alt"></i><span>' . ($isGestionnaire ? 'Gestion emprunts' : 'Mes emprunts') . '</span>
    </a>
</li>';
if ($isGestionnaire) {
    $sidebarLinks .= '<li class="sidebar-item">
        <a href="/bibliotheque/ajouter.php" class="sidebar-link ' . ($activePage === 'ajouter' ? 'active' : '') . '">
            <i class="fas fa-plus-circle"></i><span>Ajouter livre</span>
        </a>
    </li>';
}

$sidebarExtraContent = $sidebarLinks;
$pageTitle = $pageTitle ?? 'Bibliothèque';
require_once __DIR__ . '/../../templates/shared_header.php';
?>
