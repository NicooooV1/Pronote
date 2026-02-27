<?php
/**
 * En-tête standardisé pour le module Administration
 * Utilise les templates partagés Pronote
 */

// S'assurer que l'API est chargée
require_once __DIR__ . '/../../API/core.php';

// Récupérer les informations utilisateur via l'API
if (!isset($user_initials)) {
    $user_initials = getUserInitials();
    $user_fullname = getUserFullName();
}

// Variables pour les templates partagés
$pageTitle = $pageTitle ?? 'Administration';
$activePage = 'admin';
$isAdmin = true;
$user_fullname = $user_fullname ?? '';
$currentPage = $currentPage ?? '';
$extraCss = array_merge([], $extraCss ?? []);
$extraHeadHtml = ($extraHeadHtml ?? '') . '';

// Contenu supplémentaire sidebar : Administration
ob_start();
?>
        <div class="sidebar-section">
            <div class="sidebar-section-header">ADMINISTRATION</div>
            <div class="sidebar-nav">
                <a href="../login/public/register.php" class="sidebar-nav-item <?= $currentPage === 'register' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-user-plus"></i></span>
                    <span>Ajouter un utilisateur</span>
                </a>
                <a href="reset_user_password.php" class="sidebar-nav-item <?= $currentPage === 'reset_password' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-key"></i></span>
                    <span>Réinitialiser mot de passe</span>
                </a>
                <a href="reset_requests.php" class="sidebar-nav-item <?= $currentPage === 'reset_requests' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-clipboard-list"></i></span>
                    <span>Demandes de réinitialisation</span>
                </a>
                <a href="admin_accounts.php" class="sidebar-nav-item <?= $currentPage === 'admin_accounts' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-user-shield"></i></span>
                    <span>Gestion des administrateurs</span>
                </a>
                <a href="user_accounts.php" class="sidebar-nav-item <?= $currentPage === 'user_accounts' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-users-cog"></i></span>
                    <span>Gestion des utilisateurs</span>
                </a>
                <a href="etablissement_config.php" class="sidebar-nav-item <?= $currentPage === 'etablissement' ? 'active' : '' ?>">
                    <span class="sidebar-nav-icon"><i class="fas fa-school"></i></span>
                    <span>Configuration établissement</span>
                </a>
            </div>
        </div>
<?php
$sidebarExtraContent = ob_get_clean();

// Inclure les templates partagés
include __DIR__ . '/../../templates/shared_header.php';
include __DIR__ . '/../../templates/shared_sidebar.php';
include __DIR__ . '/../../templates/shared_topbar.php';
?>

            <div class="content-container">
