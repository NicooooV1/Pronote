<?php
/**
 * M18 – Cantine — Réservations
 */
$activePage = 'reservations';
$pageTitle = 'Cantine — Réservations';
require_once __DIR__ . '/includes/header.php';

$user = getCurrentUser();
$message = '';

// Réservation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'reserver') {
        $eleveId = (int)($_POST['eleve_id'] ?? $user['id']);
        $dates = $_POST['dates'] ?? [];
        $regime = $_POST['regime'] ?? null;
        $par = getUserRole();
        foreach ($dates as $d) {
            $cantineService->reserver($eleveId, $d, 'dejeuner', $regime, $par);
        }
        $message = count($dates) . ' réservation(s) enregistrée(s).';
    } elseif ($_POST['action'] === 'annuler') {
        $cantineService->annulerReservation((int)$_POST['reservation_id']);
        $message = 'Réservation annulée.';
    }
}

// Semaine courante
$aujourdhui = new DateTime();
$lundi = (clone $aujourdhui)->modify('monday this week');
$vendredi = (clone $lundi)->modify('+4 days');

// Pour élève/parent : propres réservations
$mesReservations = [];
if (isEleve()) {
    $mesReservations = $cantineService->getReservationsEleve($user['id'], $lundi->format('Y-m-d'), $vendredi->format('Y-m-d'));
} elseif (isParent()) {
    // Enfants du parent
    $stmtEnfants = $pdo->prepare("SELECT e.id, e.prenom, e.nom FROM parent_eleve pe JOIN eleves e ON pe.id_eleve = e.id WHERE pe.id_parent = ?");
    $stmtEnfants->execute([$user['id']]);
    $enfants = $stmtEnfants->fetchAll(PDO::FETCH_ASSOC);
}

// Pour admin : toutes les réservations du jour
$reservationsJour = [];
if ($isGestionnaire) {
    $dateVue = $_GET['date'] ?? date('Y-m-d');
    $reservationsJour = $cantineService->getReservationsJour($dateVue);
}
?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-calendar-check"></i> Réservations cantine</h1>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if (isEleve()): ?>
        <div class="card">
            <div class="card-header"><h3>Réserver mes repas</h3></div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="action" value="reserver">
                    <input type="hidden" name="eleve_id" value="<?= $user['id'] ?>">
                    <div class="form-group">
                        <label>Régime alimentaire</label>
                        <select name="regime" class="form-select">
                            <option value="">Normal</option>
                            <option value="végétarien">Végétarien</option>
                            <option value="sans porc">Sans porc</option>
                            <option value="sans gluten">Sans gluten</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Jours :</label>
                        <div class="checkbox-group">
                            <?php for ($i = 0; $i < 5; $i++):
                                $j = (clone $lundi)->modify("+{$i} days");
                                $ds = $j->format('Y-m-d');
                                $dejaReserve = false;
                                foreach ($mesReservations as $r) {
                                    if ($r['date_repas'] === $ds && $r['statut'] === 'reserve') $dejaReserve = true;
                                }
                            ?>
                            <label class="checkbox-label">
                                <input type="checkbox" name="dates[]" value="<?= $ds ?>" <?= $dejaReserve ? 'checked disabled' : '' ?>>
                                <?= ['Lun','Mar','Mer','Jeu','Ven'][$i] ?> <?= $j->format('d/m') ?>
                                <?= $dejaReserve ? '<span class="badge badge-success">Réservé</span>' : '' ?>
                            </label>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Réserver</button>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <?php if (isParent() && !empty($enfants)): ?>
        <?php foreach ($enfants as $enfant): ?>
        <div class="card">
            <div class="card-header"><h3>Réserver pour <?= htmlspecialchars($enfant['prenom'] . ' ' . $enfant['nom']) ?></h3></div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="action" value="reserver">
                    <input type="hidden" name="eleve_id" value="<?= $enfant['id'] ?>">
                    <div class="form-group">
                        <label>Régime</label>
                        <select name="regime" class="form-select">
                            <option value="">Normal</option>
                            <option value="végétarien">Végétarien</option>
                            <option value="sans porc">Sans porc</option>
                        </select>
                    </div>
                    <div class="checkbox-group">
                        <?php for ($i = 0; $i < 5; $i++):
                            $j = (clone $lundi)->modify("+{$i} days");
                        ?>
                        <label class="checkbox-label">
                            <input type="checkbox" name="dates[]" value="<?= $j->format('Y-m-d') ?>">
                            <?= ['Lun','Mar','Mer','Jeu','Ven'][$i] ?> <?= $j->format('d/m') ?>
                        </label>
                        <?php endfor; ?>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Réserver</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($isGestionnaire): ?>
        <div class="card">
            <div class="card-header">
                <h3>Réservations du <?= date('d/m/Y', strtotime($dateVue)) ?></h3>
                <form method="get" class="inline-form">
                    <input type="date" name="date" value="<?= $dateVue ?>" class="form-control" onchange="this.form.submit()">
                </form>
            </div>
            <div class="card-body">
                <p><strong><?= count($reservationsJour) ?></strong> réservation(s)</p>
                <table class="table">
                    <thead><tr><th>Élève</th><th>Classe</th><th>Régime</th><th>Statut</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($reservationsJour as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['prenom'] . ' ' . $r['nom']) ?></td>
                            <td><?= htmlspecialchars($r['classe'] ?? '') ?></td>
                            <td><?= htmlspecialchars($r['regime'] ?? 'Normal') ?></td>
                            <td><span class="badge badge-<?= $r['statut'] === 'consomme' ? 'success' : 'info' ?>"><?= $r['statut'] ?></span></td>
                            <td>
                                <?php if ($r['statut'] === 'reserve'): ?>
                                <form method="post" style="display:inline">
                                    <input type="hidden" name="action" value="annuler">
                                    <input type="hidden" name="reservation_id" value="<?= $r['id'] ?>">
                                    <button class="btn btn-sm btn-danger"><i class="fas fa-times"></i></button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
