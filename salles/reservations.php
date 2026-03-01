<?php
/**
 * M40 – Réservations de salles
 */
$pageTitle = 'Réservations de salles';
require_once __DIR__ . '/includes/header.php';

$salles = $smService->getSalles();
$filtreDate = $_GET['date'] ?? date('Y-m-d');
$filtreSalle = $_GET['salle_id'] ?? '';
$filters = ['date' => $filtreDate];
if ($filtreSalle) $filters['salle_id'] = $filtreSalle;
$reservations = $smService->getReservations($filters);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'reserver') {
        $dispo = $smService->verifierDisponibilite((int)$_POST['salle_id'], $_POST['date_reservation'], $_POST['heure_debut'], $_POST['heure_fin']);
        if (!$dispo) {
            $error = 'Créneau non disponible pour cette salle.';
        } else {
            $smService->creerReservation([
                'salle_id' => (int)$_POST['salle_id'], 'reserveur_id' => getUserId(),
                'objet' => trim($_POST['objet']), 'date_reservation' => $_POST['date_reservation'],
                'heure_debut' => $_POST['heure_debut'], 'heure_fin' => $_POST['heure_fin'],
            ]);
            header('Location: reservations.php?date=' . $_POST['date_reservation']); exit;
        }
    } elseif ($action === 'annuler') {
        $smService->annulerReservation((int)$_POST['reservation_id']);
        header('Location: reservations.php?date=' . $filtreDate); exit;
    }
}
?>

<div class="content-wrapper">
    <div class="content-header"><h1><i class="fas fa-door-open"></i> Réservations de salles</h1></div>

    <?php if (!empty($error)): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

    <div class="filter-bar">
        <input type="date" value="<?= $filtreDate ?>" onchange="location.href='reservations.php?date='+this.value" class="form-control" style="width:auto;">
        <select onchange="location.href='reservations.php?date=<?= $filtreDate ?>&salle_id='+this.value" class="form-control" style="width:auto;">
            <option value="">Toutes salles</option>
            <?php foreach ($salles as $s): ?><option value="<?= $s['id'] ?>" <?= $filtreSalle == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['nom']) ?></option><?php endforeach; ?>
        </select>
    </div>

    <div class="card" style="margin-bottom:1.5rem;">
        <div class="card-header"><h2>Nouvelle réservation</h2></div>
        <div class="card-body">
            <form method="post">
                <?= csrfField() ?><input type="hidden" name="action" value="reserver">
                <div class="form-grid-3">
                    <div class="form-group"><label>Salle *</label><select name="salle_id" class="form-control" required><option value="">—</option><?php foreach ($salles as $s): ?><option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['nom']) ?> (<?= $s['capacite'] ?>p)</option><?php endforeach; ?></select></div>
                    <div class="form-group"><label>Date *</label><input type="date" name="date_reservation" class="form-control" value="<?= $filtreDate ?>" required></div>
                    <div class="form-group"><label>Objet *</label><input type="text" name="objet" class="form-control" required></div>
                    <div class="form-group"><label>Début *</label><input type="time" name="heure_debut" class="form-control" required></div>
                    <div class="form-group"><label>Fin *</label><input type="time" name="heure_fin" class="form-control" required></div>
                    <div class="form-group" style="align-self:end;"><button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Réserver</button></div>
                </div>
            </form>
        </div>
    </div>

    <?php if (empty($reservations)): ?>
        <div class="empty-state"><i class="fas fa-calendar-check"></i><p>Aucune réservation pour cette date.</p></div>
    <?php else: ?>
    <div class="table-container">
        <table class="table">
            <thead><tr><th>Salle</th><th>Horaire</th><th>Objet</th><th>Réservé par</th><th>Statut</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($reservations as $r): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($r['salle_nom']) ?></strong></td>
                    <td><?= substr($r['heure_debut'], 0, 5) ?> - <?= substr($r['heure_fin'], 0, 5) ?></td>
                    <td><?= htmlspecialchars($r['objet']) ?></td>
                    <td><?= htmlspecialchars($r['reserveur_nom']) ?></td>
                    <td><span class="badge badge-<?= $r['statut'] === 'confirmee' ? 'success' : ($r['statut'] === 'annulee' ? 'danger' : 'warning') ?>"><?= ucfirst($r['statut']) ?></span></td>
                    <td>
                        <?php if ($r['statut'] === 'confirmee' && ($r['reserveur_id'] == getUserId() || isAdmin())): ?>
                        <form method="post" style="display:inline;"><?= csrfField() ?><input type="hidden" name="reservation_id" value="<?= $r['id'] ?>"><button name="action" value="annuler" class="btn btn-sm btn-danger" onclick="return confirm('Annuler ?')"><i class="fas fa-times"></i></button></form>
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
