<?php
/**
 * M40 – Prêts de matériels
 */
$pageTitle = 'Prêts de matériels';
$activePage = 'prets';
require_once __DIR__ . '/includes/header.php';

$filtreStatut = $_GET['statut'] ?? '';
$filters = [];
if ($filtreStatut) $filters['statut'] = $filtreStatut;
$prets = $smService->getPrets($filters);
$materiels = $smService->getMateriels();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'preter' && (isAdmin() || isPersonnelVS())) {
        $smService->creerPret([
            'materiel_id' => (int)$_POST['materiel_id'],
            'emprunteur_id' => (int)$_POST['emprunteur_id'] ?: getUserId(),
            'date_emprunt' => date('Y-m-d'),
            'date_retour_prevue' => $_POST['date_retour_prevue'],
        ]);
    } elseif ($action === 'retourner') {
        $smService->retournerPret((int)$_POST['pret_id']);
    }
    header('Location: prets.php'); exit;
}
?>

<div class="content-wrapper">
    <div class="content-header"><h1><i class="fas fa-hand-holding"></i> Prêts de matériels</h1></div>

    <div class="filter-bar">
        <a href="prets.php" class="btn <?= !$filtreStatut ? 'btn-primary' : 'btn-outline' ?>">Tous</a>
        <a href="prets.php?statut=en_cours" class="btn <?= $filtreStatut === 'en_cours' ? 'btn-primary' : 'btn-outline' ?>">En cours</a>
        <a href="prets.php?statut=retourne" class="btn <?= $filtreStatut === 'retourne' ? 'btn-primary' : 'btn-outline' ?>">Retournés</a>
        <a href="prets.php?statut=en_retard" class="btn <?= $filtreStatut === 'en_retard' ? 'btn-primary' : 'btn-outline' ?>">En retard</a>
    </div>

    <?php if (isAdmin() || isPersonnelVS()): ?>
    <div class="card" style="margin-bottom:1.5rem;">
        <div class="card-header"><h2>Nouveau prêt</h2></div>
        <div class="card-body">
            <form method="post">
                <?= csrfField() ?><input type="hidden" name="action" value="preter"><input type="hidden" name="emprunteur_id" value="">
                <div class="form-grid-3">
                    <div class="form-group"><label>Matériel *</label><select name="materiel_id" class="form-control" required><option value="">—</option><?php foreach ($materiels as $m): ?><option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nom']) ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label>Retour prévu *</label><input type="date" name="date_retour_prevue" class="form-control" required></div>
                    <div class="form-group" style="align-self:end;"><button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Prêter</button></div>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <?php if (empty($prets)): ?>
        <div class="empty-state"><i class="fas fa-hand-holding"></i><p>Aucun prêt.</p></div>
    <?php else: ?>
    <div class="table-container">
        <table class="table">
            <thead><tr><th>Matériel</th><th>Emprunteur</th><th>Emprunt</th><th>Retour prévu</th><th>Statut</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($prets as $p): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($p['materiel_nom']) ?></strong></td>
                    <td><?= htmlspecialchars($p['emprunteur_nom']) ?></td>
                    <td><?= formatDate($p['date_emprunt']) ?></td>
                    <td><?= formatDate($p['date_retour_prevue']) ?></td>
                    <td><span class="badge badge-<?= $p['statut'] === 'retourne' ? 'success' : ($p['statut'] === 'en_retard' ? 'danger' : 'warning') ?>"><?= ucfirst(str_replace('_', ' ', $p['statut'])) ?></span></td>
                    <td>
                        <?php if ($p['statut'] === 'en_cours'): ?>
                        <form method="post" style="display:inline;"><?= csrfField() ?><input type="hidden" name="pret_id" value="<?= $p['id'] ?>"><button name="action" value="retourner" class="btn btn-sm btn-success">Retourné</button></form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
