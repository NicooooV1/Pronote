<?php
/**
 * En-tête commun pour le module Cahier de Textes
 * Utilise les templates partagés Fronote
 * Inclut shared_header + shared_sidebar + shared_topbar + ouvre content-container
 */

// S'assurer que l'API est chargée
require_once __DIR__ . '/../../API/core.php';

// Récupérer les informations utilisateur via l'API
if (!isset($user_initials)) {
    $user_initials = getUserInitials();
}
$user_fullname = $user_fullname ?? getUserFullName();

// Définition des paramètres du module
$pageTitle  = $pageTitle ?? 'Cahier de Textes';
$activePage = 'cahierdetextes';
$isAdmin    = isAdmin();

$extraCss = $extraCss ?? ['assets/css/cahierdetextes.css'];
$extraJs  = $extraJs  ?? ['assets/js/cahierdetextes.js'];

// Construction du contenu sidebar spécifique
ob_start();
?>
        <div class="sidebar-nav">
          <a href="cahierdetextes.php" class="sidebar-nav-item">
            <span class="sidebar-nav-icon"><i class="fas fa-list"></i></span>
            <span>Liste des devoirs</span>
          </a>
          <?php if (canManageDevoirs()): ?>
          <a href="form_devoir.php" class="sidebar-nav-item">
            <span class="sidebar-nav-icon"><i class="fas fa-plus"></i></span>
            <span>Ajouter un devoir</span>
          </a>
          <?php endif; ?>
        </div>
<?php
$sidebarExtraContent = ($sidebarExtraContent ?? '') . ob_get_clean();

// Inclusion des templates partagés
include __DIR__ . '/../../templates/shared_header.php';
include __DIR__ . '/../../templates/shared_sidebar.php';
include __DIR__ . '/../../templates/shared_topbar.php';
?>

            <div class="content-container">