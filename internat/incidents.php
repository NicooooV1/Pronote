<?php
/**
 * M19 – Internat — Incidents
 */
$activePage = 'incidents';
$pageTitle = 'Internat — Incidents';
require_once __DIR__ . '/includes/header.php';

if (!$isGestionnaire) { header('Location: affectations.php'); exit; }

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['signaler'])) {
        $internatService->signalerIncident([
            'chambre_id' => $_POST['chambre_id'] ?: null,
            'eleve_id' => $_POST['eleve_id'] ?: null,
            'type' => $_POST['type'], 'description' => $_POST['description'],
            'gravite' => $_POST['gravite'],
        ]);
        $message = 'Incident signalé.';
    } elseif (isset($_POST['traiter'])) {
        $internatService->traiterIncident((int)$_POST['incident_id'], $user['id'], $_POST['suite_donnee']);
        $message = 'Incident traité.';
    }
}

$incidents = $internatService->getIncidents();
$chambres = $internatService->getChambres();
?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-exclamation-triangle"></i> Incidents internat</h1>
        <button class="btn btn-primary" onclick="document.getElementById('formIncident').style.display='block'"><i class="fas fa-plus"></i> Signaler</button>
    </div>

    <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>

    <div id="formIncident" class="card" style="display:none; margin-bottom:20px">
        <div class="card-header"><h3>Signaler un incident</h3></div>
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="signaler" value="1">
                <div class="form-row">
                    <div class="form-group"><label>Chambre</label>
                        <select name="chambre_id" class="form-select">
                            <option value="">— Optionnel —</option>
                            <?php foreach ($chambres as $ch): ?><option value="<?= $ch['id'] ?>"><?= htmlspecialchars($ch['numero']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Type</label>
                        <select name="type" class="form-select">
                            <option value="bruit">Bruit</option><option value="degradation">Dégradation</option>
                            <option value="absence">Absence</option><option value="conflit">Conflit</option><option value="autre">Autre</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Gravité</label>
                        <select name="gravite" class="form-select"><option value="1">Mineur</option><option value="2">Moyen</option><option value="3">Grave</option></select>
                    </div>
                </div>
                <div class="form-group"><label>Élève ID (optionnel)</label><input type="number" name="eleve_id" class="form-control"></div>
                <div class="form-group"><label>Description</label><textarea name="description" required class="form-control" rows="3"></textarea></div>
                <button type="submit" class="btn btn-primary">Signaler</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <table class="table">
                <thead><tr><th>Date</th><th>Chambre</th><th>Élève</th><th>Type</th><th>Gravité</th><th>Statut</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($incidents as $inc):
                    $gravBadge = ['1' => 'success', '2' => 'warning', '3' => 'danger'];
                    $gravLabel = ['1' => 'Mineur', '2' => 'Moyen', '3' => 'Grave'];
                ?>
                    <tr>
                        <td><?= date('d/m/Y H:i', strtotime($inc['date_incident'])) ?></td>
                        <td><?= htmlspecialchars($inc['chambre_numero'] ?? '—') ?></td>
                        <td><?= $inc['nom'] ? htmlspecialchars($inc['prenom'] . ' ' . $inc['nom']) : '—' ?></td>
                        <td><?= ucfirst($inc['type']) ?></td>
                        <td><span class="badge badge-<?= $gravBadge[$inc['gravite']] ?? 'secondary' ?>"><?= $gravLabel[$inc['gravite']] ?? $inc['gravite'] ?></span></td>
                        <td><span class="badge badge-<?= $inc['traite'] ? 'success' : 'warning' ?>"><?= $inc['traite'] ? 'Traité' : 'En attente' ?></span></td>
                        <td>
                            <?php if (!$inc['traite']): ?>
                            <form method="post" style="display:inline">
                                <input type="hidden" name="traiter" value="1">
                                <input type="hidden" name="incident_id" value="<?= $inc['id'] ?>">
                                <input type="text" name="suite_donnee" placeholder="Suite donnée…" class="form-control form-control-sm" style="display:inline-block;width:auto">
                                <button class="btn btn-sm btn-success"><i class="fas fa-check"></i></button>
                            </form>
                            <?php else: ?>
                                <em class="text-muted"><?= htmlspecialchars($inc['suite_donnee'] ?? '') ?></em>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
