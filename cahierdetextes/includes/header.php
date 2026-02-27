<?php
/**
 * En-tête commun pour le module Cahier de Textes
 * Utilise les templates partagés Pronote
 */

// S'assurer que l'API est chargée
require_once __DIR__ . '/../../API/core.php';

// Récupérer les informations utilisateur via l'API
if (!isset($user_initials)) {
    $user_initials = getUserInitials();
}

$user_fullname = $user_fullname ?? getUserFullName();

// Définition des paramètres du module
$pageTitle = $pageTitle ?? 'Cahier de Textes';
$activePage = 'cahierdetextes';
$isAdmin = isAdmin();

$extraCss = array_merge(['assets/css/cahierdetextes.css'], $extraCss ?? []);
$extraHeadHtml = ($extraHeadHtml ?? '') . '<style>
    .devoir-description {
      margin-top: 15px;
      padding: 10px;
      background-color: #f9f9f9;
      border-radius: 4px;
    }
    .devoir-description h4 {
      margin-top: 0;
      margin-bottom: 10px;
      color: var(--accent-cahier);
    }
    .devoir-description p {
      margin: 0;
      line-height: 1.5;
    }
</style>';

// Construction du contenu sidebar spécifique
ob_start();
?>
      <div class="sidebar-section">
        <div class="sidebar-section-header">Actions</div>
        <div class="sidebar-nav">
          <a href="cahierdetextes.php" class="sidebar-nav-item">
            <span class="sidebar-nav-icon"><i class="fas fa-list"></i></span>
            <span>Liste des devoirs</span>
          </a>
          <?php if (canManageDevoirs()): ?>
          <a href="ajouter_devoir.php" class="sidebar-nav-item">
            <span class="sidebar-nav-icon"><i class="fas fa-plus"></i></span>
            <span>Ajouter un devoir</span>
          </a>
          <?php endif; ?>
        </div>
      </div>
<?php
$sidebarExtraContent = ob_get_clean();

// Inclusion des templates partagés
include __DIR__ . '/../../templates/shared_header.php';
include __DIR__ . '/../../templates/shared_sidebar.php';
include __DIR__ . '/../../templates/shared_topbar.php';
?>
      <div class="content-container">
        <?php if (isset($_SESSION['success_message'])): ?>
          <div class="alert-banner alert-success">
            <i class="fas fa-check-circle"></i>
            <?= htmlspecialchars($_SESSION['success_message']) ?>
            <button class="alert-close">&times;</button>
          </div>
          <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
          <div class="alert-banner alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?= htmlspecialchars($_SESSION['error_message']) ?>
            <button class="alert-close">&times;</button>
          </div>
          <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>