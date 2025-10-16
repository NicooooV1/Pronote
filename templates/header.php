<?php
/**
 * Template d'en-tête pour PRONOTE
 * 
 * @param string $pageTitle - Titre de la page
 * @param string $moduleClass - Classe CSS du module (notes, agenda, cahier, messagerie, absences)
 * @param string $moduleName - Nom du module à afficher
 * @param string $moduleIcon - Icône Font Awesome du module
 */

use Pronote\Core\Facades\Auth;

// Valeurs par défaut
$pageTitle = $pageTitle ?? 'PRONOTE';
$moduleClass = $moduleClass ?? '';
$moduleName = $moduleName ?? 'PRONOTE';
$moduleIcon = $moduleIcon ?? 'fa-home';
$welcomeMessage = $welcomeMessage ?? 'Bienvenue dans votre espace PRONOTE';

// Obtenir l'utilisateur via Auth facade
$user = Auth::user();
if ($user) {
    $userFullName = $user['prenom'] . ' ' . $user['nom'];
    $userInitials = strtoupper(substr($user['prenom'] ?? 'U', 0, 1) . substr($user['nom'] ?? 'T', 0, 1));
    $userRole = $user['profil'] ?? '';
} else {
    // Rediriger si non authentifié
    header('Location: /login/public/index.php');
    exit;
}

// Déterminer le trimestre actuel
$currentMonth = (int)date('n');
if ($currentMonth >= 9 && $currentMonth <= 12) {
    $currentTrimester = 1;
} else if ($currentMonth >= 1 && $currentMonth <= 3) {
    $currentTrimester = 2;
} else {
    $currentTrimester = 3;
}

$selectedTrimester = $_GET['trimestre'] ?? $currentTrimester;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - PRONOTE</title>
    <?= \Pronote\Core\Facades\CSRF::meta() ?>
    <link rel="stylesheet" href="/assets/css/pronote-main.css">
    <?php if (!empty($moduleClass)): ?>
    <link rel="stylesheet" href="/assets/css/modules/<?= $moduleClass ?>.css">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <?php if (isset($additionalStyles)): echo $additionalStyles; endif; ?>
</head>
<body>
    <div class="app-container">
        <!-- Menu mobile -->
        <div class="mobile-menu-toggle" id="mobile-menu-toggle">
            <i class="fas fa-bars"></i>
        </div>
        <div class="page-overlay" id="page-overlay"></div>
        
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="logo-container">
                <div class="app-logo">P</div>
                <div class="app-title">PRONOTE</div>
            </div>
            
            <!-- Navigation -->
            <div class="sidebar-section">
                <div class="sidebar-section-header">Navigation</div>
                <div class="sidebar-nav">
                    <a href="/accueil/accueil.php" class="sidebar-nav-item <?= $moduleClass === 'accueil' ? 'active' : '' ?>">
                        <span class="sidebar-nav-icon"><i class="fas fa-home"></i></span>
                        <span>Accueil</span>
                    </a>
                    <a href="/notes/notes.php" class="sidebar-nav-item <?= $moduleClass === 'notes' ? 'active' : '' ?>">
                        <span class="sidebar-nav-icon"><i class="fas fa-chart-bar"></i></span>
                        <span>Notes</span>
                    </a>
                    <a href="/agenda/agenda.php" class="sidebar-nav-item <?= $moduleClass === 'agenda' ? 'active' : '' ?>">
                        <span class="sidebar-nav-icon"><i class="fas fa-calendar"></i></span>
                        <span>Agenda</span>
                    </a>
                    <a href="/cahierdetextes/cahierdetextes.php" class="sidebar-nav-item <?= $moduleClass === 'cahier' ? 'active' : '' ?>">
                        <span class="sidebar-nav-icon"><i class="fas fa-book"></i></span>
                        <span>Cahier de textes</span>
                    </a>
                    <a href="/messagerie/index.php" class="sidebar-nav-item <?= $moduleClass === 'messagerie' ? 'active' : '' ?>">
                        <span class="sidebar-nav-icon"><i class="fas fa-envelope"></i></span>
                        <span>Messagerie</span>
                    </a>
                    <?php if (Auth::hasRole('vie_scolaire') || Auth::hasRole('administrateur')): ?>
                    <a href="/absences/absences.php" class="sidebar-nav-item <?= $moduleClass === 'absences' ? 'active' : '' ?>">
                        <span class="sidebar-nav-icon"><i class="fas fa-calendar-times"></i></span>
                        <span>Absences</span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Sections spécifiques au module -->
            <?php if ($moduleClass === 'notes'): ?>
            <div class="sidebar-section">
                <div class="sidebar-section-header">Périodes</div>
                <div class="sidebar-nav">
                    <a href="?trimestre=1<?= isset($_GET['classe']) ? '&classe=' . urlencode($_GET['classe']) : '' ?>" 
                       class="sidebar-nav-item <?= $selectedTrimester == 1 ? 'active' : '' ?>">
                        <span class="sidebar-nav-icon"><i class="fas fa-calendar-alt"></i></span>
                        <span>Trimestre 1</span>
                    </a>
                    <a href="?trimestre=2<?= isset($_GET['classe']) ? '&classe=' . urlencode($_GET['classe']) : '' ?>" 
                       class="sidebar-nav-item <?= $selectedTrimester == 2 ? 'active' : '' ?>">
                        <span class="sidebar-nav-icon"><i class="fas fa-calendar-alt"></i></span>
                        <span>Trimestre 2</span>
                    </a>
                    <a href="?trimestre=3<?= isset($_GET['classe']) ? '&classe=' . urlencode($_GET['classe']) : '' ?>" 
                       class="sidebar-nav-item <?= $selectedTrimester == 3 ? 'active' : '' ?>">
                        <span class="sidebar-nav-icon"><i class="fas fa-calendar-alt"></i></span>
                        <span>Trimestre 3</span>
                    </a>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (isset($sidebarCustomContent)): echo $sidebarCustomContent; endif; ?>
            
            <!-- Actions pour les enseignants / admin -->
            <?php if ($moduleClass === 'notes' && (Auth::hasRole('professeur') || Auth::hasRole('administrateur') || Auth::hasRole('vie_scolaire'))): ?>
            <div class="sidebar-section">
                <div class="sidebar-section-header">Actions</div>
                <div class="sidebar-nav">
                    <a href="/notes/ajouter_note.php" class="create-button">
                        <i class="fas fa-plus"></i> Ajouter une note
                    </a>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Informations -->
            <div class="sidebar-section">
                <div class="sidebar-section-header">Informations</div>
                <div class="info-item">
                    <div class="info-label">Date</div>
                    <div class="info-value"><?= date('d/m/Y') ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Période</div>
                    <div class="info-value"><?= isset($selectedTrimester) ? $selectedTrimester : $currentTrimester ?>ème trimestre</div>
                </div>
                <?php if (isset($additionalInfoContent)): echo $additionalInfoContent; endif; ?>
            </div>
        </div>

        <!-- Contenu principal -->
        <div class="main-content">
            <!-- En-tête -->
            <div class="top-header">
                <div class="page-title">
                    <h1><?= htmlspecialchars($pageTitle) ?></h1>
                    <?php if (isset($pageSubtitle)): ?>
                    <div class="subtitle"><?= $pageSubtitle ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="header-actions">
                    <a href="/login/public/logout.php" class="logout-button" title="Déconnexion">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                    <div class="user-avatar" title="<?= htmlspecialchars($userFullName) ?>"><?= $userInitials ?></div>
                </div>
            </div>

            <!-- Bannière de bienvenue -->
            <?php if (!isset($hideBanner)): ?>
            <div class="welcome-banner">
                <div class="welcome-content">
                    <h2><?= htmlspecialchars($moduleName) ?></h2>
                    <p><?= $welcomeMessage ?></p>
                    <?php if (isset($additionalBannerContent)): echo $additionalBannerContent; endif; ?>
                </div>
                <div class="welcome-logo">
                    <i class="fas <?= $moduleIcon ?>"></i>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Messages d'alerte -->
            <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert-banner alert-success" id="success-message">
                <i class="fas fa-check-circle"></i>
                <div><?= htmlspecialchars($_SESSION['success_message']) ?></div>
            </div>
            <?php unset($_SESSION['success_message']); endif; ?>
            
            <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert-banner alert-error" id="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <div><?= htmlspecialchars($_SESSION['error_message']) ?></div>
            </div>
            <?php unset($_SESSION['error_message']); endif; ?>
            
            <?php if (isset($_SESSION['info_message'])): ?>
            <div class="alert-banner alert-info" id="info-message">
                <i class="fas fa-info-circle"></i>
                <div><?= htmlspecialchars($_SESSION['info_message']) ?></div>
            </div>
            <?php unset($_SESSION['info_message']); endif; ?>
