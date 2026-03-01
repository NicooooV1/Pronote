<?php
/**
 * M45 – Signalements — Liste admin/VS
 */
$pageTitle = 'Gestion des signalements';
require_once __DIR__ . '/includes/header.php';

if (!isAdmin() && !isPersonnelVS()) { redirect('/signalements/signaler.php'); }

$filtreStatut = $_GET['statut'] ?? '';
$filtreType = $_GET['type'] ?? '';
$filters = [];
if ($filtreStatut) $filters['statut'] = $filtreStatut;
if ($filtreType) $filters['type'] = $filtreType;
$signalements = $signalementService->getTousSignalements($filters);
$stats = $signalementService->getStats();
$types = SignalementService::typesSignalement();
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-shield-alt"></i> Signalements</h1>
    </div>

    <?php if (!empty($_SESSION['success_message'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success_message'] ?></div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card"><div class="stat-value"><?= $stats['total'] ?></div><div class="stat-label">Total</div></div>
        <div class="stat-card stat-danger"><div class="stat-value"><?= $stats['nouveaux'] ?></div><div class="stat-label">Nouveaux</div></div>
        <div class="stat-card stat-warning"><div class="stat-value"><?= $stats['en_cours'] ?></div><div class="stat-label">En cours</div></div>
        <div class="stat-card stat-danger"><div class="stat-value"><?= $stats['urgents'] ?></div><div class="stat-label">Urgents</div></div>
        <div class="stat-card"><div class="stat-value"><?= $stats['anonymes'] ?></div><div class="stat-label">Anonymes</div></div>
    </div>

    <div class="filter-row">
        <form method="get" class="filter-form">
            <select name="statut" class="form-control" onchange="this.form.submit()">
                <option value="">Tous les statuts</option>
                <option value="nouveau" <?= $filtreStatut === 'nouveau' ? 'selected' : '' ?>>Nouveau</option>
                <option value="en_cours" <?= $filtreStatut === 'en_cours' ? 'selected' : '' ?>>En cours</option>
                <option value="traite" <?= $filtreStatut === 'traite' ? 'selected' : '' ?>>Traité</option>
            </select>
            <select name="type" class="form-control" onchange="this.form.submit()">
                <option value="">Tous les types</option>
                <?php foreach ($types as $k => $v): ?>
                <option value="<?= $k ?>" <?= $filtreType === $k ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <?php if (empty($signalements)): ?>
        <div class="empty-state"><i class="fas fa-check-circle"></i><p>Aucun signalement.</p></div>
    <?php else: ?>
    <div class="signalement-list">
        <?php foreach ($signalements as $s): ?>
        <div class="signalement-item urgence-<?= $s['urgence'] ?>">
            <div class="sig-urgence"><?= SignalementService::urgenceBadge($s['urgence']) ?></div>
            <div class="sig-info">
                <div class="sig-header">
                    <h3><?= $types[$s['type']] ?? $s['type'] ?></h3>
                    <?= SignalementService::statutBadge($s['statut']) ?>
                </div>
                <p class="sig-desc"><?= htmlspecialchars(mb_substr($s['description'], 0, 150)) ?>...</p>
                <div class="sig-meta">
                    <?php if ($s['anonyme']): ?><span class="sig-anon"><i class="fas fa-user-secret"></i> Anonyme</span><?php endif; ?>
                    <span><i class="fas fa-clock"></i> <?= formatDateTime($s['date_signalement']) ?></span>
                    <?php if ($s['lieu']): ?><span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($s['lieu']) ?></span><?php endif; ?>
                </div>
            </div>
            <a href="detail.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-outline"><i class="fas fa-eye"></i></a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
