<?php
/**
 * Template partagé : Top bar (zone entre sidebar et contenu)
 * Ouvre le main-content et affiche le top-header.
 * Doit être inclus APRÈS shared_sidebar.php.
 * 
 * Variables attendues (déjà définies par shared_header.php) :
 *   $pageTitle, $user_initials
 * 
 * Variables optionnelles :
 *   $pageSubtitle, $headerExtraActions, $user_fullname
 */

$pageTitle = $pageTitle ?? 'FRONOTE';
$user_initials = $user_initials ?? '';
$pageSubtitle = $pageSubtitle ?? '';
$headerExtraActions = $headerExtraActions ?? '';
$user_fullname = $user_fullname ?? '';
$rootPrefix = $rootPrefix ?? '../';
?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-header">
            <div class="page-title">
                <h1><?= htmlspecialchars($pageTitle) ?></h1>
                <?php if (!empty($pageSubtitle)): ?>
                <p class="subtitle"><?= htmlspecialchars($pageSubtitle) ?></p>
                <?php endif; ?>
            </div>

            <div class="header-actions">
                <?= $headerExtraActions ?>
                <a href="<?= $rootPrefix ?>login/public/logout.php" class="logout-button" title="Déconnexion">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
                <div class="user-avatar" title="<?= htmlspecialchars($user_fullname) ?>"><?= htmlspecialchars($user_initials) ?></div>
            </div>
        </div>
