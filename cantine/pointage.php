<?php
/**
 * M18 – Cantine — Pointage
 */
$activePage = 'pointage';
$pageTitle = 'Cantine — Pointage';
require_once __DIR__ . '/includes/header.php';

if (!$isGestionnaire) { header('Location: menus.php'); exit; }

$dateVue = $_GET['date'] ?? date('Y-m-d');
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pointer'])) {
    $ids = $_POST['reservation_ids'] ?? [];
    $count = 0;
    foreach ($ids as $rid) {
        if ($cantineService->pointer((int)$rid, $user['id'])) $count++;
    }
    $message = "$count élève(s) pointé(s).";
}

$pointage = $cantineService->getPointageJour($dateVue);
$nonPointes = array_filter($pointage, fn($r) => $r['statut'] === 'reserve');
$pointes = array_filter($pointage, fn($r) => $r['statut'] === 'consomme');
?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-check-double"></i> Pointage cantine</h1>
        <form method="get" class="inline-form">
            <input type="date" name="date" value="<?= $dateVue ?>" class="form-control" onchange="this.form.submit()">
        </form>
    </div>

    <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>

    <div class="stats-row">
        <div class="stat-card"><div class="stat-value"><?= count($pointage) ?></div><div class="stat-label">Réservations</div></div>
        <div class="stat-card stat-success"><div class="stat-value"><?= count($pointes) ?></div><div class="stat-label">Pointés</div></div>
        <div class="stat-card stat-warning"><div class="stat-value"><?= count($nonPointes) ?></div><div class="stat-label">En attente</div></div>
    </div>

    <?php if (!empty($nonPointes)): ?>
    <div class="card">
        <div class="card-header"><h3>Élèves à pointer</h3></div>
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="pointer" value="1">
                <div class="pointage-actions">
                    <button type="button" onclick="toggleAll(true)" class="btn btn-sm btn-outline">Tout cocher</button>
                    <button type="button" onclick="toggleAll(false)" class="btn btn-sm btn-outline">Tout décocher</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Pointer la sélection</button>
                </div>
                <table class="table">
                    <thead><tr><th><input type="checkbox" id="checkAll" onchange="toggleAll(this.checked)"></th><th>Élève</th><th>Classe</th><th>Régime</th></tr></thead>
                    <tbody>
                    <?php foreach ($nonPointes as $r): ?>
                        <tr>
                            <td><input type="checkbox" name="reservation_ids[]" value="<?= $r['id'] ?>" class="pointage-cb"></td>
                            <td><?= htmlspecialchars($r['prenom'] . ' ' . $r['nom']) ?></td>
                            <td><?= htmlspecialchars($r['classe'] ?? '') ?></td>
                            <td><?= htmlspecialchars($r['regime'] ?? 'Normal') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($pointes)): ?>
    <div class="card">
        <div class="card-header"><h3>Déjà pointés</h3></div>
        <div class="card-body">
            <table class="table">
                <thead><tr><th>Élève</th><th>Classe</th><th>Heure</th></tr></thead>
                <tbody>
                <?php foreach ($pointes as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['prenom'] . ' ' . $r['nom']) ?></td>
                        <td><?= htmlspecialchars($r['classe'] ?? '') ?></td>
                        <td><?= $r['heure_passage'] ? date('H:i', strtotime($r['heure_passage'])) : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function toggleAll(checked) {
    document.querySelectorAll('.pointage-cb').forEach(cb => cb.checked = checked);
    document.getElementById('checkAll').checked = checked;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
