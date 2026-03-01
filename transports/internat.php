<?php
/**
 * M32 – Internat — Chambres
 */
$pageTitle = 'Internat';
$activePage = 'internat';
require_once __DIR__ . '/includes/header.php';

$isGestionnaire = isAdmin() || isPersonnelVS();
$filtreBatiment = $_GET['batiment'] ?? '';
$chambres = $tiService->getChambres($filtreBatiment ?: null);
$batiments = $tiService->getBatiments();
$typesChambre = TransportInternatService::typesChambre();
$eleves = $isGestionnaire ? $tiService->getEleves() : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken() && $isGestionnaire) {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'creer_chambre') {
            $tiService->creerChambre([
                'numero' => trim($_POST['numero']), 'batiment' => trim($_POST['batiment'] ?? ''),
                'etage' => (int)$_POST['etage'], 'capacite' => (int)$_POST['capacite'],
                'type' => $_POST['type'],
            ]);
        } elseif ($action === 'affecter') {
            $tiService->affecterChambre((int)$_POST['chambre_id'], (int)$_POST['eleve_id']);
        }
    } catch (RuntimeException $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
    header('Location: internat.php'); exit;
}
?>

<div class="content-wrapper">
    <div class="content-header"><h1><i class="fas fa-bed"></i> Internat</h1></div>

    <?php if (!empty($_SESSION['error_message'])): ?><div class="alert alert-danger"><?= $_SESSION['error_message'] ?></div><?php unset($_SESSION['error_message']); endif; ?>

    <?php if (!empty($batiments)): ?>
    <div class="filter-bar">
        <a href="internat.php" class="btn <?= !$filtreBatiment ? 'btn-primary' : 'btn-outline' ?>">Tous</a>
        <?php foreach ($batiments as $b): ?>
        <a href="internat.php?batiment=<?= urlencode($b) ?>" class="btn <?= $filtreBatiment === $b ? 'btn-primary' : 'btn-outline' ?>"><?= htmlspecialchars($b) ?></a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($isGestionnaire): ?>
    <div class="card" style="margin-bottom:1.5rem;">
        <div class="card-header"><h2>Nouvelle chambre</h2></div>
        <div class="card-body">
            <form method="post" class="form-inline">
                <?= csrfField() ?><input type="hidden" name="action" value="creer_chambre">
                <input type="text" name="numero" class="form-control" placeholder="N° chambre" required>
                <input type="text" name="batiment" class="form-control" placeholder="Bâtiment">
                <input type="number" name="etage" class="form-control" placeholder="Étage" value="0" style="width:80px;">
                <input type="number" name="capacite" class="form-control" placeholder="Capacité" value="2" min="1" style="width:90px;">
                <select name="type" class="form-control"><?php foreach ($typesChambre as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select>
                <button class="btn btn-primary"><i class="fas fa-plus"></i></button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <?php if (empty($chambres)): ?>
        <div class="empty-state"><i class="fas fa-bed"></i><p>Aucune chambre.</p></div>
    <?php else: ?>
    <div class="chambres-grid">
        <?php foreach ($chambres as $c): ?>
        <div class="chambre-card <?= $c['nb_occupants'] >= $c['capacite'] ? 'full' : '' ?>">
            <div class="chambre-number"><?= htmlspecialchars($c['numero']) ?></div>
            <div class="chambre-info">
                <span class="badge badge-secondary"><?= $typesChambre[$c['type']] ?? $c['type'] ?></span>
                <?php if ($c['batiment']): ?><span>Bât. <?= htmlspecialchars($c['batiment']) ?></span><?php endif; ?>
                <span>Ét. <?= $c['etage'] ?></span>
                <span class="occupancy"><?= $c['nb_occupants'] ?>/<?= $c['capacite'] ?></span>
            </div>
            <?php if ($isGestionnaire && $c['nb_occupants'] < $c['capacite']): ?>
            <form method="post" class="form-inline" style="margin-top:.5rem;">
                <?= csrfField() ?><input type="hidden" name="action" value="affecter"><input type="hidden" name="chambre_id" value="<?= $c['id'] ?>">
                <select name="eleve_id" class="form-control form-control-sm" required><option value="">— Élève —</option><?php foreach ($eleves as $e): ?><option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['nom'] . ' ' . $e['prenom']) ?></option><?php endforeach; ?></select>
                <button class="btn btn-sm btn-primary"><i class="fas fa-plus"></i></button>
            </form>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
