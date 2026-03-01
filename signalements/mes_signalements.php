<?php
/**
 * M45 – Signalements — Mes signalements
 */
$pageTitle = 'Mes signalements';
$activePage = 'mes_signalements';
require_once __DIR__ . '/includes/header.php';

$signalements = $signalementService->getMesSignalements(getUserId(), getUserRole());
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-folder"></i> Mes signalements</h1>
        <a href="signaler.php" class="btn btn-primary"><i class="fas fa-plus"></i> Nouveau signalement</a>
    </div>

    <?php if (!empty($_SESSION['success_message'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success_message'] ?></div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (empty($signalements)): ?>
        <div class="empty-state"><i class="fas fa-inbox"></i><p>Aucun signalement.</p></div>
    <?php else: ?>
    <div class="signalement-list">
        <?php foreach ($signalements as $s): ?>
        <div class="signalement-item">
            <div class="sig-info">
                <div class="sig-header">
                    <h3><?= SignalementService::typesSignalement()[$s['type']] ?? $s['type'] ?></h3>
                    <?= SignalementService::statutBadge($s['statut']) ?>
                    <?= SignalementService::urgenceBadge($s['urgence']) ?>
                </div>
                <p class="sig-desc"><?= htmlspecialchars(mb_substr($s['description'], 0, 150)) ?>...</p>
                <span class="sig-date"><i class="fas fa-clock"></i> <?= formatDateTime($s['date_signalement']) ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
