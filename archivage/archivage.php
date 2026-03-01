<?php
/**
 * M35 – Archivage annuel — Liste des archives
 */
$pageTitle = 'Archivage annuel';
require_once __DIR__ . '/includes/header.php';

$filtreAnnee = $_GET['annee'] ?? '';
$filtreType = $_GET['type'] ?? '';
$archives = $archiveService->getArchives($filtreAnnee ?: null, $filtreType ?: null);
$annees = $archiveService->getAnneesDisponibles();
$stats = $archiveService->getStats();
$types = ArchiveService::typesArchive();

// Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken()) {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'verrouiller') { $archiveService->verrouillerArchive($id, true); }
    elseif ($action === 'deverrouiller') { $archiveService->verrouillerArchive($id, false); }
    elseif ($action === 'supprimer') { $archiveService->supprimerArchive($id); }
    header('Location: archivage.php' . ($filtreAnnee ? "?annee=$filtreAnnee" : ''));
    exit;
}
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-archive"></i> Archivage annuel</h1>
        <a href="creer.php" class="btn btn-primary"><i class="fas fa-plus"></i> Nouvelle archive</a>
    </div>

    <div class="stats-grid">
        <div class="stat-card"><div class="stat-value"><?= $stats['total'] ?></div><div class="stat-label">Archives</div></div>
        <div class="stat-card"><div class="stat-value"><?= $stats['annees'] ?></div><div class="stat-label">Années</div></div>
        <div class="stat-card"><div class="stat-value"><?= $stats['verrouillee'] ?></div><div class="stat-label">Verrouillées</div></div>
    </div>

    <!-- Filtres -->
    <div class="filter-row">
        <form method="get" class="filter-form">
            <select name="annee" class="form-control" onchange="this.form.submit()">
                <option value="">Toutes les années</option>
                <?php foreach ($annees as $a): ?>
                <option value="<?= $a ?>" <?= $filtreAnnee === $a ? 'selected' : '' ?>><?= $a ?></option>
                <?php endforeach; ?>
            </select>
            <select name="type" class="form-control" onchange="this.form.submit()">
                <option value="">Tous les types</option>
                <?php foreach ($types as $k => $v): ?>
                <option value="<?= $k ?>" <?= $filtreType === $k ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <!-- Liste -->
    <?php if (empty($archives)): ?>
        <div class="empty-state"><i class="fas fa-archive"></i><p>Aucune archive trouvée.</p></div>
    <?php else: ?>
    <div class="archive-list">
        <?php foreach ($archives as $ar): ?>
        <div class="archive-item <?= $ar['verrouille'] ? 'locked' : '' ?>">
            <div class="archive-icon">
                <i class="fas fa-<?= $ar['verrouille'] ? 'lock' : 'box-open' ?>"></i>
            </div>
            <div class="archive-info">
                <h3><?= $types[$ar['type']] ?? $ar['type'] ?> — <?= htmlspecialchars($ar['annee_scolaire']) ?></h3>
                <div class="archive-meta">
                    <span><i class="fas fa-calendar"></i> <?= formatDateTime($ar['date_archive']) ?></span>
                    <?php if ($ar['verrouille']): ?><span class="badge badge-warning"><i class="fas fa-lock"></i> Verrouillée</span><?php endif; ?>
                </div>
            </div>
            <div class="archive-actions">
                <a href="telecharger.php?id=<?= $ar['id'] ?>" class="btn btn-sm btn-outline" title="Télécharger"><i class="fas fa-download"></i></a>
                <form method="post" style="display:inline;">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= $ar['id'] ?>">
                    <?php if ($ar['verrouille']): ?>
                    <button type="submit" name="action" value="deverrouiller" class="btn btn-sm btn-outline" title="Déverrouiller"><i class="fas fa-unlock"></i></button>
                    <?php else: ?>
                    <button type="submit" name="action" value="verrouiller" class="btn btn-sm btn-outline" title="Verrouiller"><i class="fas fa-lock"></i></button>
                    <button type="submit" name="action" value="supprimer" class="btn btn-sm btn-danger" title="Supprimer" onclick="return confirm('Supprimer cette archive ?')"><i class="fas fa-trash"></i></button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
