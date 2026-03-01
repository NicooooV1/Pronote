<?php
/**
 * M16 – Services périscolaires — Liste
 */
$pageTitle = 'Services périscolaires';
require_once __DIR__ . '/includes/header.php';

$filtreType = $_GET['type'] ?? '';
$services = $periService->getServices($filtreType ?: null);
$types = PeriscolaireService::typesService();
$isGestionnaire = isAdmin() || isPersonnelVS();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken() && $isGestionnaire && ($_POST['action'] ?? '') === 'creer') {
    $periService->creerService([
        'nom' => trim($_POST['nom']), 'type' => $_POST['type'],
        'description' => trim($_POST['description'] ?? ''),
        'tarif' => $_POST['tarif'] ?: 0, 'places_max' => $_POST['places_max'] ?: null,
        'horaires' => trim($_POST['horaires'] ?? ''),
    ]);
    header('Location: services.php'); exit;
}
?>

<div class="content-wrapper">
    <div class="content-header"><h1><i class="fas fa-concierge-bell"></i> Services périscolaires</h1></div>

    <div class="filter-bar">
        <a href="services.php" class="btn <?= !$filtreType ? 'btn-primary' : 'btn-outline' ?>">Tous</a>
        <?php foreach ($types as $k => $v): ?>
        <a href="services.php?type=<?= $k ?>" class="btn <?= $filtreType === $k ? 'btn-primary' : 'btn-outline' ?>"><?= $v ?></a>
        <?php endforeach; ?>
    </div>

    <?php if ($isGestionnaire): ?>
    <div class="card" style="margin-bottom:1.5rem;">
        <div class="card-header"><h2>Créer un service</h2></div>
        <div class="card-body">
            <form method="post">
                <?= csrfField() ?><input type="hidden" name="action" value="creer">
                <div class="form-grid-3">
                    <div class="form-group"><label>Nom *</label><input type="text" name="nom" class="form-control" required></div>
                    <div class="form-group"><label>Type</label><select name="type" class="form-control"><?php foreach ($types as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label>Tarif (€)</label><input type="number" name="tarif" class="form-control" step="0.01" min="0"></div>
                    <div class="form-group"><label>Places max</label><input type="number" name="places_max" class="form-control" min="1"></div>
                    <div class="form-group"><label>Horaires</label><input type="text" name="horaires" class="form-control" placeholder="ex: 7h30-8h30"></div>
                    <div class="form-group full-width"><label>Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
                </div>
                <button type="submit" class="btn btn-primary" style="margin-top:.5rem;"><i class="fas fa-plus"></i> Créer</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <?php if (empty($services)): ?>
        <div class="empty-state"><i class="fas fa-concierge-bell"></i><p>Aucun service.</p></div>
    <?php else: ?>
    <div class="services-grid">
        <?php foreach ($services as $s): ?>
        <div class="service-card type-<?= $s['type'] ?>">
            <div class="service-icon"><i class="fas fa-<?= PeriscolaireService::iconeType($s['type']) ?>"></i></div>
            <div class="service-info">
                <h3><a href="detail_service.php?id=<?= $s['id'] ?>"><?= htmlspecialchars($s['nom']) ?></a></h3>
                <span class="badge badge-secondary"><?= $types[$s['type']] ?? $s['type'] ?></span>
                <div class="service-meta">
                    <span><i class="fas fa-users"></i> <?= $s['nb_inscrits'] ?><?= $s['places_max'] ? '/' . $s['places_max'] : '' ?></span>
                    <?php if ($s['tarif'] > 0): ?><span><i class="fas fa-euro-sign"></i> <?= number_format($s['tarif'], 2, ',', ' ') ?></span><?php endif; ?>
                    <?php if ($s['horaires']): ?><span><i class="fas fa-clock"></i> <?= htmlspecialchars($s['horaires']) ?></span><?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
