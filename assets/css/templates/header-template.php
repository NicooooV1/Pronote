<?php
/**
 * Header Template for the new Fronote design system
 * 
 * @param string $pageTitle - Title of the page
 * @param string $moduleColor - Color class for the module (notes, agenda, cahier, messagerie, absences)
 * @param array $user - User information array with at least 'nom' and 'prenom'
 */

// Default values
$pageTitle = $pageTitle ?? 'Fronote';
$moduleColor = $moduleColor ?? 'primary';
$moduleClass = isset($moduleClass) ? $moduleClass : '';

// Get current user information
if (!isset($user) && isset($_SESSION['user'])) {
    $user = $_SESSION['user'];
}

// Calculate user initials for avatar
if (isset($user['prenom'], $user['nom'])) {
    $userInitials = mb_strtoupper(mb_substr($user['prenom'], 0, 1) . mb_substr($user['nom'], 0, 1));
} else {
    $userInitials = 'U';
}

// Define module classes for consistent styling
$moduleClasses = [
    'notes' => ['color' => 'var(--accent-notes)', 'icon' => 'fa-chart-bar'],
    'agenda' => ['color' => 'var(--accent-agenda)', 'icon' => 'fa-calendar-alt'],
    'cahier' => ['color' => 'var(--accent-cahier)', 'icon' => 'fa-book'],
    'messagerie' => ['color' => 'var(--accent-messagerie)', 'icon' => 'fa-envelope'],
    'absences' => ['color' => 'var(--accent-absences)', 'icon' => 'fa-calendar-times']
];

// Get current module info
$currentModule = $moduleClasses[$moduleClass] ?? ['color' => 'var(--primary-color)', 'icon' => 'fa-home'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <!-- Common stylesheets -->
    <link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '' ?>/assets/css/pronote-theme.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Module specific stylesheet if needed -->
    <?php if (!empty($moduleClass) && file_exists(__DIR__ . "/../modules/{$moduleClass}.css")): ?>
    <link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '' ?>/assets/css/modules/<?= $moduleClass ?>.css">
    <?php endif; ?>
    <!-- Additional head content -->
    <?php if (isset($additionalHead)) echo $additionalHead; ?>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-logo">
                <div class="logo-icon">P</div>
                <div class="logo-text">Fronote</div>
            </div>
            
            <?php if (isset($sidebarContent)): ?>
                <?= $sidebarContent ?>
            <?php else: ?>
                <!-- Default sidebar content -->
                <div class="sidebar-section">
                    <div class="sidebar-title">Navigation</div>
                    <div class="sidebar-menu">
                        <a href="<?= defined('HOME_URL') ? HOME_URL : '../accueil/accueil.php' ?>" class="sidebar-link <?= $moduleClass === 'accueil' ? 'active' : '' ?>">
                            <i class="fas fa-home"></i> Accueil
                        </a>
                        <a href="<?= defined('BASE_URL') ? BASE_URL : '' ?>/notes/notes.php" class="sidebar-link <?= $moduleClass === 'notes' ? 'active' : '' ?>">
                            <i class="fas fa-chart-bar"></i> Notes
                        </a>
                        <a href="<?= defined('BASE_URL') ? BASE_URL : '' ?>/absences/absences.php" class="sidebar-link <?= $moduleClass === 'absences' ? 'active' : '' ?>">
                            <i class="fas fa-calendar-times"></i> Absences
                        </a>
                        <a href="<?= defined('BASE_URL') ? BASE_URL : '' ?>/agenda/agenda.php" class="sidebar-link <?= $moduleClass === 'agenda' ? 'active' : '' ?>">
                            <i class="fas fa-calendar-alt"></i> Agenda
                        </a>
                        <a href="<?= defined('BASE_URL') ? BASE_URL : '' ?>/cahierdetextes/cahierdetextes.php" class="sidebar-link <?= $moduleClass === 'cahier' ? 'active' : '' ?>">
                            <i class="fas fa-book"></i> Cahier de textes
                        </a>
                        <a href="<?= defined('BASE_URL') ? BASE_URL : '' ?>/messagerie/index.php" class="sidebar-link <?= $moduleClass === 'messagerie' ? 'active' : '' ?>">
                            <i class="fas fa-envelope"></i> Messagerie
                        </a>
                    </div>
                </div>
                
                <?php if (isset($user['profil']) && in_array($user['profil'], ['administrateur', 'professeur', 'vie_scolaire'])): ?>
                <div class="sidebar-section">
                    <div class="sidebar-title">Administration</div>
                    <div class="sidebar-menu">
                        <?php if ($user['profil'] === 'administrateur'): ?>
                        <a href="<?= defined('BASE_URL') ? BASE_URL : '' ?>/admin/dashboard.php" class="sidebar-link">
                            <i class="fas fa-tachometer-alt"></i> Tableau de bord
                        </a>
                        <?php endif; ?>
                        
                        <?php if (in_array($user['profil'], ['professeur', 'vie_scolaire'])): ?>
                        <a href="<?= defined('BASE_URL') ? BASE_URL : '' ?>/admin/students.php" class="sidebar-link">
                            <i class="fas fa-user-graduate"></i> Élèves
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($user['profil'] === 'administrateur'): ?>
                        <a href="<?= defined('BASE_URL') ? BASE_URL : '' ?>/admin/settings.php" class="sidebar-link">
                            <i class="fas fa-cog"></i> Paramètres
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <div class="sidebar-footer">
                <div class="text-small">
                    © <?= date('Y') ?> Fronote v<?= defined('APP_VERSION') ? APP_VERSION : '1.0.0' ?>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="header">
                <div class="header-title">
                    <h1><?= htmlspecialchars($pageTitle) ?></h1>
                </div>
                
                <div class="header-actions">
                    <?php if (isset($headerActions)): ?>
                        <?= $headerActions ?>
                    <?php endif; ?>
                    
                    <a href="<?= defined('LOGOUT_URL') ? LOGOUT_URL : '../login/public/logout.php' ?>" class="header-icon-button logout-button" title="Déconnexion">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                    
                    <div class="user-avatar" title="<?= isset($user['prenom'], $user['nom']) ? htmlspecialchars($user['prenom'] . ' ' . $user['nom']) : 'Utilisateur' ?>">
                        <?= htmlspecialchars($userInitials) ?>
                    </div>
                </div>
            </div>
            
            <!-- Content Container -->
            <div class="content-container">
                <!-- Success/Error Messages -->
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
                
                <?php if (isset($_SESSION['info_message'])): ?>
                    <div class="alert-banner alert-info">
                        <i class="fas fa-info-circle"></i>
                        <?= htmlspecialchars($_SESSION['info_message']) ?>
                        <button class="alert-close">&times;</button>
                    </div>
                    <?php unset($_SESSION['info_message']); ?>
                <?php endif; ?>
                
                <!-- Begin page content -->
