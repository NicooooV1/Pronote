<?php
/**
 * M19 – Internat — Affectations
 */
$activePage = 'affectations';
$pageTitle = 'Internat — Affectations';
require_once __DIR__ . '/includes/header.php';

$message = '';
$chambreId = isset($_GET['chambre']) ? (int)$_GET['chambre'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isGestionnaire) {
    if (isset($_POST['affecter'])) {
        $internatService->affecterEleve((int)$_POST['chambre_id'], (int)$_POST['eleve_id'], $_POST['date_debut']);
        $message = 'Élève affecté.';
    } elseif (isset($_POST['liberer'])) {
        $internatService->libererPlace((int)$_POST['affectation_id']);
        $message = 'Place libérée.';
    }
}

$affectations = $internatService->getAffectations();
$chambres = $internatService->getChambres();

// Charger élèves pour le formulaire
$eleves = [];
if ($isGestionnaire) {
    $eleves = $pdo->query("SELECT id, nom, prenom, classe FROM eleves WHERE actif = 1 ORDER BY nom, prenom")->fetchAll(PDO::FETCH_ASSOC);
}
?>

<div class="main-content">
    <div class="page-header"><h1><i class="fas fa-user-check"></i> Affectations internat</h1></div>

    <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>

    <?php if ($isGestionnaire): ?>
    <div class="card" style="margin-bottom:20px">
        <div class="card-header"><h3>Affecter un élève</h3></div>
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="affecter" value="1">
                <div class="form-row">
                    <div class="form-group"><label>Chambre</label>
                        <select name="chambre_id" required class="form-select">
                            <option value="">— Choisir —</option>
                            <?php foreach ($chambres as $ch): ?>
                                <option value="<?= $ch['id'] ?>" <?= $chambreId == $ch['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($ch['numero'] . ' (' . $ch['nb_occupants'] . '/' . $ch['capacite'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Élève</label>
                        <select name="eleve_id" required class="form-select">
                            <option value="">— Choisir —</option>
                            <?php foreach ($eleves as $e): ?>
                                <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['nom'] . ' ' . $e['prenom'] . ' (' . $e['classe'] . ')') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Date début</label><input type="date" name="date_debut" value="<?= date('Y-m-d') ?>" class="form-control"></div>
                </div>
                <button type="submit" class="btn btn-primary">Affecter</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header"><h3>Liste des internes (<?= count($affectations) ?>)</h3></div>
        <div class="card-body">
            <table class="table">
                <thead><tr><th>Élève</th><th>Classe</th><th>Chambre</th><th>Bâtiment</th><th>Depuis</th><?php if ($isGestionnaire): ?><th>Actions</th><?php endif; ?></tr></thead>
                <tbody>
                <?php foreach ($affectations as $af): ?>
                    <tr>
                        <td><?= htmlspecialchars($af['prenom'] . ' ' . $af['nom']) ?></td>
                        <td><?= htmlspecialchars($af['classe'] ?? '') ?></td>
                        <td><?= htmlspecialchars($af['chambre_numero']) ?></td>
                        <td><?= htmlspecialchars($af['batiment'] ?? '') ?></td>
                        <td><?= date('d/m/Y', strtotime($af['date_debut'])) ?></td>
                        <?php if ($isGestionnaire): ?>
                        <td>
                            <?php if ($af['statut'] === 'actif'): ?>
                            <form method="post" style="display:inline">
                                <input type="hidden" name="liberer" value="1">
                                <input type="hidden" name="affectation_id" value="<?= $af['id'] ?>">
                                <button class="btn btn-sm btn-danger" title="Libérer"><i class="fas fa-sign-out-alt"></i></button>
                            </form>
                            <?php endif; ?>
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
