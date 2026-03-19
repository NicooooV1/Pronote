<?php
/**
 * En-tête commun pour le module Agenda
 * Utilise les templates partagés Fronote
 */

// S'assurer que l'API est chargée
require_once __DIR__ . '/../../API/core.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/EventRepository.php';

// Récupérer les informations utilisateur via l'API
if (!isset($user_initials)) {
    $user_initials = getUserInitials();
}

$user_fullname = $user_fullname ?? getUserFullName();

// EventRepository pour les types
if (!isset($repo)) {
    $repo = new EventRepository(getPDO());
}

// Définition des paramètres du module
$pageTitle = $pageTitle ?? 'Agenda';
$activePage = 'agenda';
$isAdmin = isAdmin();
$extraCss = array_merge(['assets/css/agenda.css'], $extraCss ?? []);

// Sidebar simplifié — plus de mini-calendrier redondant
$sidebarExtraContent = '';

// Actions dans le header (bouton ajouter événement)
ob_start();
?>
                <?php if (canManageAgendaEvents()): ?>
                <a href="ajouter_evenement.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus"></i> Événement
                </a>
                <?php endif; ?>
<?php
$headerExtraActions = ob_get_clean();

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