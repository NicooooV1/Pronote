<?php
/**
 * M20 – Garderie — Pointage des présences
 */
$activePage = 'presences';
$pageTitle = 'Garderie — Présences';
require_once __DIR__ . '/includes/header.php';

if (!$isGestionnaire) { header('Location: creneaux.php'); exit; }

$dateVue = $_GET['date'] ?? date('Y-m-d');
$creneauId = isset($_GET['creneau']) ? (int)$_GET['creneau'] : 0;
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pointer'])) {
    $presents = $_POST['present'] ?? [];
    $remarques = $_POST['remarques'] ?? [];
    foreach ($_POST['inscription_ids'] as $insId) {
        $garderieService->pointerPresence(
            (int)$insId, $dateVue,
            in_array($insId, $presents),
            $remarques[$insId] ?? null
        );
    }
    $message = 'Présences enregistrées.';
}

$creneaux = $garderieService->getCreneaux();
$presences = $garderieService->getPresencesJour($dateVue, $creneauId ?: null);
?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-check-circle"></i> Présences garderie</h1>
    </div>

    <form method="get" class="filter-form">
        <div class="form-row">
            <div class="form-group"><label>Date</label><input type="date" name="date" value="<?= $dateVue ?>" class="form-control"></div>
            <div class="form-group"><label>Créneau</label>
                <select name="creneau" class="form-select">
                    <option value="">Tous</option>
                    <?php foreach ($creneaux as $cr): ?><option value="<?= $cr['id'] ?>" <?= $cr['id'] == $creneauId ? 'selected' : '' ?>><?= htmlspecialchars($cr['nom']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filtrer</button>
        </div>
    </form>

    <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>

    <div class="card">
        <div class="card-header"><h3><?= count($presences) ?> élève(s) attendu(s)</h3></div>
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="pointer" value="1">
                <table class="table">
                    <thead><tr><th>Présent</th><th>Élève</th><th>Classe</th><th>Créneau</th><th>Remarques</th></tr></thead>
                    <tbody>
                    <?php foreach ($presences as $p):
                        $insId = $p['inscription_id'] ?? $p['id'];
                    ?>
                        <tr>
                            <td>
                                <input type="hidden" name="inscription_ids[]" value="<?= $insId ?>">
                                <input type="checkbox" name="present[]" value="<?= $insId ?>" <?= ($p['present'] ?? 0) ? 'checked' : '' ?>>
                            </td>
                            <td><?= htmlspecialchars($p['prenom'] . ' ' . $p['nom']) ?></td>
                            <td><?= htmlspecialchars($p['classe'] ?? '') ?></td>
                            <td><?= htmlspecialchars($p['creneau_nom'] ?? '') ?></td>
                            <td><input type="text" name="remarques[<?= $insId ?>]" value="<?= htmlspecialchars($p['remarques'] ?? '') ?>" class="form-control form-control-sm"></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
