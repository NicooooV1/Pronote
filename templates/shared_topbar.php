<?php
/**
 * Template: Topbar + main content opening.
 * Replaces the old sidebar + topbar combo.
 * Include after shared_header.php.
 *
 * Variables (from shared_header.php):
 *   $pageTitle, $pageSubtitle, $user_initials, $user_fullname,
 *   $headerExtraActions, $rootPrefix, $activePage, $isAdmin
 */

$pageTitle = $pageTitle ?? 'FRONOTE';
$pageSubtitle = $pageSubtitle ?? '';
$user_initials = $user_initials ?? '';
$user_fullname = $user_fullname ?? '';
$headerExtraActions = $headerExtraActions ?? '';
$rootPrefix = $rootPrefix ?? '../';
$activePage = $activePage ?? '';
$isAdmin = $isAdmin ?? false;

// Include the topbar navigation
require __DIR__ . '/shared_topbar_nav.php';
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
            </div>
        </div>
