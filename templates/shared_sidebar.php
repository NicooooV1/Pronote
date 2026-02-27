<?php
/**
 * Template partagé : Sidebar de navigation
 * 
 * Variables attendues :
 *   $activePage  — string : 'accueil'|'notes'|'agenda'|'cahierdetextes'|'messagerie'|'absences'
 *   $isAdmin     — bool   : afficher la section admin (optionnel, défaut false)
 *   $user_role   — string : profil de l'utilisateur (optionnel)
 * 
 * Variable optionnelle :
 *   $sidebarExtraContent — string/HTML : contenu supplémentaire à insérer après la nav (filtres, calendrier, etc.)
 */

$activePage = $activePage ?? '';
$isAdmin = $isAdmin ?? false;
$user_role = $user_role ?? '';
$sidebarExtraContent = $sidebarExtraContent ?? '';

// Déterminer le préfixe relatif vers la racine du projet selon le module appelant
// (chaque page inclut ce fichier depuis son dossier)
$templateDir = dirname(__DIR__);
$scriptDir = dirname($_SERVER['SCRIPT_FILENAME']);
$relativeToRoot = rtrim(str_repeat('../', substr_count(str_replace($templateDir, '', $scriptDir), DIRECTORY_SEPARATOR)), '/');
if (empty($relativeToRoot)) $relativeToRoot = '.';
// Utiliser un chemin relatif simple basé sur le dossier courant
$rootPrefix = $rootPrefix ?? '../';
// Si on est à la racine (accueil appelle depuis accueil/)
// Tous les modules sont dans un sous-dossier, donc ../ ramène toujours à la racine.
?>

<!-- Sidebar -->
<div class="sidebar">
    <a href="<?= $rootPrefix ?>accueil/accueil.php" class="logo-container">
        <div class="app-logo">P</div>
        <div class="app-title">PRONOTE</div>
    </a>

    <div class="sidebar-section">
        <div class="sidebar-section-header">Navigation</div>
        <div class="sidebar-nav">
            <a href="<?= $rootPrefix ?>accueil/accueil.php" class="sidebar-nav-item <?= $activePage === 'accueil' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon"><i class="fas fa-home"></i></span>
                <span>Accueil</span>
            </a>
            <a href="<?= $rootPrefix ?>notes/notes.php" class="sidebar-nav-item <?= $activePage === 'notes' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon"><i class="fas fa-chart-bar"></i></span>
                <span>Notes</span>
            </a>
            <a href="<?= $rootPrefix ?>agenda/agenda.php" class="sidebar-nav-item <?= $activePage === 'agenda' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon"><i class="fas fa-calendar"></i></span>
                <span>Agenda</span>
            </a>
            <a href="<?= $rootPrefix ?>cahierdetextes/cahierdetextes.php" class="sidebar-nav-item <?= $activePage === 'cahierdetextes' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon"><i class="fas fa-book"></i></span>
                <span>Cahier de textes</span>
            </a>
            <a href="<?= $rootPrefix ?>messagerie/index.php" class="sidebar-nav-item <?= $activePage === 'messagerie' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon"><i class="fas fa-envelope"></i></span>
                <span>Messagerie</span>
            </a>
            <a href="<?= $rootPrefix ?>absences/absences.php" class="sidebar-nav-item <?= $activePage === 'absences' ? 'active' : '' ?>">
                <span class="sidebar-nav-icon"><i class="fas fa-calendar-times"></i></span>
                <span>Absences</span>
            </a>
        </div>
    </div>

    <?php if ($isAdmin): ?>
    <div class="sidebar-section">
        <div class="sidebar-section-header">Administration</div>
        <div class="sidebar-nav">
            <a href="<?= $rootPrefix ?>login/public/register.php" class="sidebar-nav-item">
                <span class="sidebar-nav-icon"><i class="fas fa-user-plus"></i></span>
                <span>Ajouter un utilisateur</span>
            </a>
            <a href="<?= $rootPrefix ?>admin/reset_user_password.php" class="sidebar-nav-item">
                <span class="sidebar-nav-icon"><i class="fas fa-key"></i></span>
                <span>Réinitialiser mot de passe</span>
            </a>
            <a href="<?= $rootPrefix ?>admin/reset_requests.php" class="sidebar-nav-item">
                <span class="sidebar-nav-icon"><i class="fas fa-clipboard-list"></i></span>
                <span>Demandes de réinitialisation</span>
            </a>
            <a href="<?= $rootPrefix ?>admin/admin_accounts.php" class="sidebar-nav-item">
                <span class="sidebar-nav-icon"><i class="fas fa-user-shield"></i></span>
                <span>Gestion des administrateurs</span>
            </a>
            <a href="<?= $rootPrefix ?>admin/user_accounts.php" class="sidebar-nav-item">
                <span class="sidebar-nav-icon"><i class="fas fa-users-cog"></i></span>
                <span>Gestion des utilisateurs</span>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <?php
    // Contenu supplémentaire spécifique au module (filtres, mini-calendrier, etc.)
    if (!empty($sidebarExtraContent)) {
        echo $sidebarExtraContent;
    }
    ?>

    <div class="sidebar-footer">
        &copy; <?= date('Y') ?> PRONOTE
    </div>
</div>
