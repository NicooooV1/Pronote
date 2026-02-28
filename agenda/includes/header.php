<?php
/**
 * En-tête commun pour le module Agenda
 * Utilise les templates partagés Fronote
 */

// S'assurer que l'API est chargée
require_once __DIR__ . '/../../API/core.php';
require_once __DIR__ . '/auth.php';

// Récupérer les informations utilisateur via l'API
if (!isset($user_initials)) {
    $user_initials = getUserInitials();
}

$user_fullname = $user_fullname ?? getUserFullName();

// Définition des paramètres du module
$pageTitle = $pageTitle ?? 'Agenda';
$activePage = 'agenda';
$isAdmin = isAdmin();
$extraCss = array_merge(['assets/css/agenda.css'], $extraCss ?? []);

// Construction du contenu sidebar spécifique à l'agenda
ob_start();
?>
      <!-- Mini-calendrier pour la navigation -->
      <div class="mini-calendar">
        <?php if (function_exists('generateMiniCalendar')): ?>
          <?= generateMiniCalendar($month ?? date('n'), $year ?? date('Y'), $date ?? date('Y-m-d')) ?>
        <?php endif; ?>
      </div>
      
      <?php if (canManageAgendaEvents()): ?>
      <a href="ajouter_evenement.php" class="sidebar-action-btn">
        <i class="fas fa-plus"></i> Ajouter un événement
      </a>
      <?php endif; ?>
      
      <?php if (isset($available_event_types) && !empty($available_event_types)): ?>
      <div style="margin-top:10px;">
        <div style="font-size:11px;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,0.5);font-weight:600;margin-bottom:6px;">Types d'événements</div>
        <div class="sidebar-nav">
          <?php foreach ($types_evenements ?? [] as $code => $nom): ?>
            <?php if (in_array($code, $available_event_types)): ?>
              <div class="filter-option">
                <label>
                  <span class="color-dot color-<?= $code ?>"></span>
                  <input type="checkbox" class="filter-checkbox" 
                         data-filter-type="type"
                         name="types[]" 
                         value="<?= $code ?>" 
                         <?= in_array($code, $filter_types ?? []) ? 'checked' : '' ?>>
                  <?= htmlspecialchars($nom) ?>
                </label>
              </div>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if (!empty($classes) && canManageAgendaEvents()): ?>
      <div style="margin-top:10px;">
        <div style="font-size:11px;text-transform:uppercase;letter-spacing:1px;color:rgba(255,255,255,0.5);font-weight:600;margin-bottom:6px;">Classes</div>
        <div class="classes-dropdown">
          <button class="classes-dropdown-toggle" id="classesDropdownToggle">
            Filtrer par classe <i class="fas fa-chevron-down"></i>
          </button>
          <div class="dropdown-menu" id="classesDropdown">
            <div class="dropdown-actions">
              <button class="dropdown-action" id="selectAllClasses">Tout sélectionner</button>
              <button class="dropdown-action" id="deselectAllClasses">Tout désélectionner</button>
            </div>
            <div class="dropdown-search">
              <input type="text" id="classSearch" placeholder="Rechercher une classe" onkeyup="filterClasses()">
            </div>
            <div class="dropdown-options">
              <?php foreach ($classes as $classe): ?>
                <div class="dropdown-option">
                  <label>
                    <input type="checkbox" class="filter-checkbox" 
                           data-filter-type="class" 
                           name="classes[]" 
                           value="<?= $classe ?>"
                           <?= in_array($classe, $filter_classes ?? []) ? 'checked' : '' ?>>
                    <?= htmlspecialchars($classe) ?>
                  </label>
                </div>
              <?php endforeach; ?>
            </div>
            <div class="dropdown-footer">
              <button class="apply-button" id="applyClassesFilter">Appliquer</button>
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>
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