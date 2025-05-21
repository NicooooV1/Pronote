<?php
/**
 * En-tête standardisé pour le module Absences 
 * Conforme au design système Pronote
 */

// S'assurer que les variables nécessaires sont définies
$pageTitle = $pageTitle ?? 'Absences';
$currentPage = $currentPage ?? '';

// Vérifier si les informations utilisateur sont disponibles
if (!isset($user_initials) && isset($_SESSION['user'])) {
    $user = $_SESSION['user'];
    $user_initials = strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1));
    $user_fullname = $user['prenom'] . ' ' . $user['nom'];
}

// Pour l'onglet actif dans le menu
function isActiveLink($page) {
    global $currentPage;
    return $currentPage === $page ? 'active' : '';
}

// Vérifier si la fonction canManageAbsences existe déjà avant de la déclarer
if (!function_exists('canManageAbsences')) {
    // Vérifie les permissions pour l'interface
    function canManageAbsences() {
        if (!isset($_SESSION['user'])) return false;
        $role = $_SESSION['user']['profil'];
        return in_array($role, ['administrateur', 'professeur', 'vie_scolaire']);
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - PRONOTE</title>
    <!-- CSS commun à tous les modules -->
    <link rel="stylesheet" href="../assets/css/pronote-core.css">
    <!-- CSS spécifique aux absences -->
    <link rel="stylesheet" href="assets/css/absences.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="module-absences">
    <div class="app-container">
        <!-- Barre latérale -->
        <div class="sidebar">
            <a href="../accueil/accueil.php" class="logo-container">
                <div class="app-logo">P</div>
                <div class="app-title">PRONOTE</div>
            </a>
            
            <!-- Module de navigation principal - Même casse que l'accueil -->
            <div class="sidebar-section">
                <div class="sidebar-section-header">NAVIGATION</div>
                <div class="sidebar-nav">
                    <a href="../accueil/accueil.php" class="sidebar-nav-item">
                        <span class="sidebar-nav-icon"><i class="fas fa-home"></i></span>
                        <span>Accueil</span>
                    </a>
                    <a href="../notes/notes.php" class="sidebar-nav-item">
                        <span class="sidebar-nav-icon"><i class="fas fa-chart-bar"></i></span>
                        <span>Notes</span>
                    </a>
                    <a href="../agenda/agenda.php" class="sidebar-nav-item">
                        <span class="sidebar-nav-icon"><i class="fas fa-calendar"></i></span>
                        <span>Agenda</span>
                    </a>
                    <a href="../cahierdetextes/cahierdetextes.php" class="sidebar-nav-item">
                        <span class="sidebar-nav-icon"><i class="fas fa-book"></i></span>
                        <span>Cahier de textes</span>
                    </a>
                    <a href="../messagerie/index.php" class="sidebar-nav-item">
                        <span class="sidebar-nav-icon"><i class="fas fa-envelope"></i></span>
                        <span>Messagerie</span>
                    </a>
                    <a href="absences.php" class="sidebar-nav-item active">
                        <span class="sidebar-nav-icon"><i class="fas fa-calendar-times"></i></span>
                        <span>Absences</span>
                    </a>
                </div>
            </div>
            
            <!-- Actions spécifiques au module absences - Même casse que l'accueil -->
            <div class="sidebar-section">
                <div class="sidebar-section-header">ACTIONS</div>
                <div class="sidebar-nav">
                    <a href="absences.php" class="sidebar-nav-item <?= isActiveLink('liste') ?>">
                        <span class="sidebar-nav-icon"><i class="fas fa-list"></i></span>
                        <span>Liste des absences</span>
                    </a>
                    
                    <?php if (canManageAbsences()): ?>
                    <a href="ajouter_absence.php" class="sidebar-nav-item <?= isActiveLink('ajouter') ?>">
                        <span class="sidebar-nav-icon"><i class="fas fa-plus"></i></span>
                        <span>Signaler une absence</span>
                    </a>
                    <a href="appel.php" class="sidebar-nav-item <?= isActiveLink('appel') ?>">
                        <span class="sidebar-nav-icon"><i class="fas fa-clipboard-list"></i></span>
                        <span>Faire l'appel</span>
                    </a>
                    <?php endif; ?>
                    
                    <?php if (isAdmin() || isVieScolaire()): ?>
                    <a href="statistiques.php" class="sidebar-nav-item <?= isActiveLink('statistiques') ?>">
                        <span class="sidebar-nav-icon"><i class="fas fa-chart-pie"></i></span>
                        <span>Statistiques</span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="sidebar-footer">
                <div class="text-small">
                    © <?= date('Y') ?> PRONOTE
                </div>
            </div>
        </div>

        <!-- Contenu principal -->
        <div class="main-content">
            <!-- En-tête de page -->
            <div class="top-header">
                <div class="page-title">
                    <?php if (isset($showBackButton) && $showBackButton): ?>
                    <a href="<?= $backLink ?? 'absences.php' ?>" class="back-button">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <?php endif; ?>
                    <h1><?= htmlspecialchars($pageTitle) ?></h1>
                </div>
                
                <div class="header-actions">
                    <?php if (canManageAbsences() && $currentPage !== 'ajouter'): ?>
                    <a href="ajouter_absence.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Signaler une absence
                    </a>
                    <?php endif; ?>
                    <a href="../login/public/logout.php" class="logout-button" title="Déconnexion">⏻</a>
                    <div class="user-avatar" title="<?= htmlspecialchars($user_fullname ?? '') ?>"><?= $user_initials ?? '' ?></div>
                </div>
            </div>

            <div class="content-wrapper">
