<?php
/**
 * M32 – Détail ligne transport
 */
$pageTitle = 'Détail ligne';
require_once __DIR__ . '/includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$ligne = $tiService->getLigne($id);
if (!$ligne) { header('Location: lignes.php'); exit; }

$inscrits = $tiService->getInscritsLigne($id);
$isGestionnaire = isAdmin() || isPersonnelVS();
$eleves = $isGestionnaire ? $tiService->getEleves() : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken() && $isGestionnaire) {
    $tiService->inscrireTransport($id, (int)$_POST['eleve_id'], trim($_POST['arret'] ?? ''));
    header('Location: detail_ligne.php?id=' . $id); exit;
}
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-bus"></i> <?= htmlspecialchars($ligne['nom']) ?></h1>
        <a href="lignes.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>

    <?php if ($ligne['itineraire']): ?><p><?= nl2br(htmlspecialchars($ligne['itineraire'])) ?></p><?php endif; ?>

    <?php if ($isGestionnaire): ?>
    <form method="post" class="form-inline" style="margin-bottom:1rem;">
        <?= csrfField() ?>
        <select name="eleve_id" class="form-control" required><option value="">— Élève —</option><?php foreach ($eleves as $e): ?><option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['nom'] . ' ' . $e['prenom']) ?></option><?php endforeach; ?></select>
        <input type="text" name="arret" class="form-control" placeholder="Arrêt">
        <button class="btn btn-primary"><i class="fas fa-plus"></i> Inscrire</button>
    </form>
    <?php endif; ?>

    <div class="card">
        <div class="card-header"><h2>Inscrits (<?= count($inscrits) ?>)</h2></div>
        <div class="card-body">
            <?php if (empty($inscrits)): ?><p class="text-muted">Aucun inscrit.</p>
            <?php else: ?>
            <table class="table">
                <thead><tr><th>Élève</th><th>Classe</th><th>Arrêt</th></tr></thead>
                <tbody>
                    <?php foreach ($inscrits as $i): ?>
                    <tr><td><?= htmlspecialchars($i['eleve_nom']) ?></td><td><?= htmlspecialchars($i['classe_nom'] ?? '-') ?></td><td><?= htmlspecialchars($i['arret'] ?? '-') ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
