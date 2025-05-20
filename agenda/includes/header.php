<?php
/**
 * En-tête commun pour le module Agenda
 * Utilise le système de design unifié de Pronote
 */

// Vérification si les informations utilisateur sont disponibles
if (!isset($user_initials) && isset($_SESSION['user'])) {
    $user = $_SESSION['user'];
    $user_initials = strtoupper(mb_substr($user['prenom'], 0, 1) . mb_substr($user['nom'], 0, 1));
}

// Définition des paramètres du module
$pageTitle = $pageTitle ?? 'Agenda';
$moduleClass = 'agenda';
$moduleColor = 'var(--accent-agenda)';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> - Pronote</title>
  <link rel="stylesheet" href="../assets/css/pronote-core.css">
  <link rel="stylesheet" href="assets/css/agenda.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
  <div class="app-container">
    <!-- Sidebar -->
    <div class="sidebar">
      <a href="../accueil/accueil.php" class="logo-container">
        <div class="app-logo">P</div>
        <div class="app-title">PRONOTE</div>
      </a>
      
      <!-- Navigation principale -->
      <div class="sidebar-section">
        <div class="sidebar-section-header">Navigation</div>
        <div class="folder-menu">
          <a href="../accueil/accueil.php" class="module-link">
            <i class="fas fa-home"></i> Accueil
          </a>
          <a href="../notes/notes.php" class="module-link">
            <i class="fas fa-chart-bar"></i> Notes
          </a>
          <a href="../agenda/agenda.php" class="module-link active">
            <i class="fas fa-calendar-alt"></i> Agenda
          </a>
          <a href="../cahierdetextes/cahierdetextes.php" class="module-link">
            <i class="fas fa-book"></i> Cahier de textes
          </a>
          <a href="../messagerie/index.php" class="module-link">
            <i class="fas fa-envelope"></i> Messagerie
          </a>
          <a href="../absences/absences.php" class="module-link">
            <i class="fas fa-calendar-times"></i> Absences
          </a>
        </div>
      </div>
      
      <!-- Mini-calendrier pour la navigation -->
      <div class="sidebar-section">
        <div class="sidebar-section-header">Calendrier</div>
        <div class="mini-calendar">
          <!-- Le mini-calendrier sera généré dynamiquement -->
          <?php if (function_exists('generateMiniCalendar')): ?>
            <?= generateMiniCalendar($month ?? date('n'), $year ?? date('Y'), $date ?? date('Y-m-d')) ?>
          <?php endif; ?>
        </div>
      </div>
      
      <?php if (isset($_SESSION['user']) && (in_array($_SESSION['user']['profil'], ['professeur', 'administrateur', 'vie_scolaire']))): ?>
      <!-- Actions -->
      <div class="sidebar-section">
        <div class="sidebar-section-header">Actions</div>
        <a href="ajouter_evenement.php" class="create-button">
          <i class="fas fa-plus"></i> Ajouter un événement
        </a>
      </div>
      <?php endif; ?>
      
      <!-- Filtres par type d'événement -->
      <?php if (isset($available_event_types) && !empty($available_event_types)): ?>
      <div class="sidebar-section">
        <div class="sidebar-section-header">Types d'événements</div>
        <div class="folder-menu">
          <?php foreach ($types_evenements ?? [] as $code => $nom): ?>
            <?php if (in_array($code, $available_event_types)): ?>
              <div class="filter-option">
                <label>
                  <span class="color-dot color-<?= $code ?>"></span>
                  <input type="checkbox" class="filter-checkbox" 
                         id="filter-<?= $code ?>" 
                         name="types[]" 
                         value="<?= $code ?>" 
                         <?= isset($filter_types) && in_array($code, $filter_types) ? 'checked' : '' ?> 
                         data-filter-type="type">
                  <span class="filter-label"><?= $nom ?></span>
                </label>
              </div>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    
    <!-- Main Content -->
    <div class="main-content">
      <!-- Header -->
      <div class="top-header">
        <div class="page-title">
          <h1>Agenda</h1>
        </div>
        <div class="header-actions">
          <a href="../login/public/logout.php" class="logout-button" title="Déconnexion">⏻</a>
          <div class="user-avatar"><?= htmlspecialchars($user_initials ?? '') ?></div>
        </div>
      </div>
      
      <div class="content-container">