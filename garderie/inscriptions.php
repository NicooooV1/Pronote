<?php
/**
 * M20 – Garderie — Inscriptions
 */
$activePage = 'inscriptions';
$pageTitle = 'Garderie — Inscriptions';
require_once __DIR__ . '/includes/header.php';

$message = '';
$creneauId = isset($_GET['creneau']) ? (int)$_GET['creneau'] : 0;
$user = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['inscrire'])) {
        $jours = $_POST['jours'] ?? [];
        $count = 0;
        foreach ($jours as $j) {
            $garderieService->inscrire((int)$_POST['creneau_id'], (int)$_POST['eleve_id'], $j, date('Y-m-d'), getUserRole());
            $count++;
        }
        $message = "$count inscription(s) enregistrée(s).";
    } elseif (isset($_POST['desinscrire'])) {
        $garderieService->desinscrire((int)$_POST['inscription_id']);
        $message = 'Inscription annulée.';
    }
}

$creneaux = $garderieService->getCreneaux();
$inscriptions = $garderieService->getInscriptions($creneauId ?: null);

// Élèves pour formulaire
$eleves = [];
if ($isGestionnaire) {
    $eleves = $pdo->query("SELECT id, nom, prenom, classe FROM eleves WHERE actif = 1 ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
} elseif (isParent()) {
    $stmtE = $pdo->prepare("SELECT e.id, e.nom, e.prenom, e.classe FROM parent_eleve pe JOIN eleves e ON pe.id_eleve = e.id WHERE pe.id_parent = ?");
    $stmtE->execute([$user['id']]);
    $eleves = $stmtE->fetchAll(PDO::FETCH_ASSOC);
}
?>

<div class="main-content">
    <div class="page-header"><h1><i class="fas fa-user-plus"></i> Inscriptions garderie</h1></div>

    <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>

    <?php if ($isGestionnaire || isParent()): ?>
    <div class="card" style="margin-bottom:20px">
        <div class="card-header"><h3>Inscrire un élève</h3></div>
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="inscrire" value="1">
                <div class="form-row">
                    <div class="form-group"><label>Créneau</label>
                        <select name="creneau_id" required class="form-select">
                            <?php foreach ($creneaux as $cr): ?>
                                <option value="<?= $cr['id'] ?>" <?= $cr['id'] == $creneauId ? 'selected' : '' ?>><?= htmlspecialchars($cr['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Élève</label>
                        <select name="eleve_id" required class="form-select">
                            <?php foreach ($eleves as $e): ?>
                                <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['nom'] . ' ' . $e['prenom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group"><label>Jours</label>
                    <div class="checkbox-group">
                        <?php foreach (['lundi','mardi','mercredi','jeudi','vendredi'] as $j): ?>
                        <label class="checkbox-label"><input type="checkbox" name="jours[]" value="<?= $j ?>"> <?= ucfirst($j) ?></label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Inscrire</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h3>Inscriptions actives (<?= count($inscriptions) ?>)</h3>
            <form method="get" class="inline-form">
                <select name="creneau" class="form-select" onchange="this.form.submit()">
                    <option value="">Tous les créneaux</option>
                    <?php foreach ($creneaux as $cr): ?>
                        <option value="<?= $cr['id'] ?>" <?= $cr['id'] == $creneauId ? 'selected' : '' ?>><?= htmlspecialchars($cr['nom']) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <div class="card-body">
            <table class="table">
                <thead><tr><th>Élève</th><th>Classe</th><th>Créneau</th><th>Jour</th><?php if ($isGestionnaire): ?><th>Actions</th><?php endif; ?></tr></thead>
                <tbody>
                <?php foreach ($inscriptions as $ins): ?>
                    <tr>
                        <td><?= htmlspecialchars($ins['prenom'] . ' ' . $ins['nom']) ?></td>
                        <td><?= htmlspecialchars($ins['classe'] ?? '') ?></td>
                        <td><?= htmlspecialchars($ins['creneau_nom']) ?></td>
                        <td><?= ucfirst($ins['jour']) ?></td>
                        <?php if ($isGestionnaire): ?>
                        <td>
                            <form method="post" style="display:inline">
                                <input type="hidden" name="desinscrire" value="1">
                                <input type="hidden" name="inscription_id" value="<?= $ins['id'] ?>">
                                <button class="btn btn-sm btn-danger" title="Désinscrire"><i class="fas fa-times"></i></button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
