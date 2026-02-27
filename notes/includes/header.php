<?php
/**
 * En-tête standardisé pour le module Notes
 * Utilise les templates partagés Pronote
 */

// S'assurer que l'API est chargée
require_once __DIR__ . '/../../API/core.php';

// S'assurer que les variables nécessaires sont définies
$pageTitle = $pageTitle ?? 'Notes';

if (!isset($user_initials)) {
    $user_initials = getUserInitials();
    $user_fullname = getUserFullName();
}
if (!isset($user_role)) {
    $user_role = getUserRole();
}

$user_fullname = $user_fullname ?? '';
$user_initials = $user_initials ?? '';

// Variables pour les templates partagés
$activePage = 'notes';
$isAdmin = ($user_role ?? '') === 'administrateur';
$extraCss = $extraCss ?? ['assets/css/notes.css'];

// Contenu supplémentaire sidebar
ob_start();
?>
        <div class="sidebar-section">
            <div class="sidebar-section-header">Actions</div>
            <div class="sidebar-nav">
                <a href="notes.php" class="sidebar-nav-item">
                    <span class="sidebar-nav-icon"><i class="fas fa-list"></i></span>
                    <span>Liste des notes</span>
                </a>
                <?php if (in_array($user_role ?? '', ['professeur', 'administrateur'])): ?>
                <a href="ajouter_note.php" class="sidebar-nav-item">
                    <span class="sidebar-nav-icon"><i class="fas fa-plus"></i></span>
                    <span>Ajouter une note</span>
                </a>
                <?php endif; ?>
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
