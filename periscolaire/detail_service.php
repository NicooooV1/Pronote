<?php
/**
 * M16 – Détail service + inscriptions
 */
$pageTitle = 'Détail service';
require_once __DIR__ . '/includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$service = $periService->getService($id);
if (!$service) { header('Location: services.php'); exit; }

$inscriptions = $periService->getInscriptions($id);
$isGestionnaire = isAdmin() || isPersonnelVS();
$types = PeriscolaireService::typesService();
$jours = PeriscolaireService::jours();
$eleves = $isGestionnaire ? $periService->getEleves() : [];
$enfants = isParent() ? $periService->getEnfantsParent(getUserId()) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken()) {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'inscrire') {
            $eleveId = (int)$_POST['eleve_id'];
            $jour = $_POST['jour'];
            $periService->inscrire($id, $eleveId, $jour);
            $_SESSION['success_message'] = 'Inscription réussie.';
        } elseif ($action === 'desinscrire') {
            $periService->desinscrire((int)$_POST['inscription_id']);
            $_SESSION['success_message'] = 'Désinscription effectuée.';
        }
    } catch (RuntimeException $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
    header('Location: detail_service.php?id=' . $id); exit;
}
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-<?= PeriscolaireService::iconeType($service['type']) ?>"></i> <?= htmlspecialchars($service['nom']) ?></h1>
        <a href="services.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>

    <?php if (!empty($_SESSION['success_message'])): ?><div class="alert alert-success"><?= $_SESSION['success_message'] ?></div><?php unset($_SESSION['success_message']); endif; ?>
    <?php if (!empty($_SESSION['error_message'])): ?><div class="alert alert-danger"><?= $_SESSION['error_message'] ?></div><?php unset($_SESSION['error_message']); endif; ?>

    <div class="info-grid">
        <div class="info-item"><span class="badge badge-secondary"><?= $types[$service['type']] ?? $service['type'] ?></span></div>
        <div class="info-item"><i class="fas fa-users"></i><span><?= count($inscriptions) ?><?= $service['places_max'] ? '/' . $service['places_max'] : '' ?> inscrits</span></div>
        <?php if ($service['tarif'] > 0): ?><div class="info-item"><i class="fas fa-euro-sign"></i><span><?= number_format($service['tarif'], 2, ',', ' ') ?> €</span></div><?php endif; ?>
        <?php if ($service['horaires']): ?><div class="info-item"><i class="fas fa-clock"></i><span><?= htmlspecialchars($service['horaires']) ?></span></div><?php endif; ?>
    </div>

    <!-- Inscription -->
    <?php if ($isGestionnaire || isParent()): ?>
    <div class="card" style="margin-bottom:1rem;">
        <div class="card-header"><h2>Inscrire un élève</h2></div>
        <div class="card-body">
            <form method="post" class="form-inline">
                <?= csrfField() ?><input type="hidden" name="action" value="inscrire">
                <select name="eleve_id" class="form-control" required>
                    <option value="">— Élève —</option>
                    <?php if ($isGestionnaire): foreach ($eleves as $e): ?><option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['nom'] . ' ' . $e['prenom']) ?></option><?php endforeach; endif; ?>
                    <?php if (isParent()): foreach ($enfants as $e): ?><option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['prenom'] . ' ' . $e['nom']) ?></option><?php endforeach; endif; ?>
                </select>
                <select name="jour" class="form-control"><?php foreach ($jours as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select>
                <button class="btn btn-primary"><i class="fas fa-plus"></i> Inscrire</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Liste inscrits -->
    <div class="card">
        <div class="card-header"><h2>Inscrits (<?= count($inscriptions) ?>)</h2></div>
        <div class="card-body">
            <?php if (empty($inscriptions)): ?><p class="text-muted">Aucun inscrit.</p>
            <?php else: ?>
            <table class="table">
                <thead><tr><th>Élève</th><th>Classe</th><th>Jour</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($inscriptions as $i): ?>
                    <tr>
                        <td><?= htmlspecialchars($i['eleve_nom']) ?></td>
                        <td><?= htmlspecialchars($i['classe_nom'] ?? '-') ?></td>
                        <td><?= $jours[$i['jour']] ?? $i['jour'] ?></td>
                        <td>
                            <?php if ($isGestionnaire): ?>
                            <form method="post" style="display:inline;"><?= csrfField() ?><input type="hidden" name="inscription_id" value="<?= $i['id'] ?>"><button name="action" value="desinscrire" class="btn btn-sm btn-danger" onclick="return confirm('Désinscrire ?')"><i class="fas fa-times"></i></button></form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
