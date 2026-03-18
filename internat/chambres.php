<?php
/**
 * M19 – Internat — Gestion des chambres
 */
$activePage = 'chambres';
$pageTitle = 'Internat — Chambres';
require_once __DIR__ . '/includes/header.php';

if (!$isGestionnaire) { header('Location: affectations.php'); exit; }

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'creer') {
        $internatService->creerChambre([
            'numero' => $_POST['numero'], 'batiment' => $_POST['batiment'],
            'etage' => $_POST['etage'], 'capacite' => $_POST['capacite'],
            'type' => $_POST['type'], 'equipements' => $_POST['equipements'],
        ]);
        $message = 'Chambre créée.';
    } elseif ($_POST['action'] === 'modifier' && isset($_POST['id'])) {
        $internatService->modifierChambre((int)$_POST['id'], [
            'numero' => $_POST['numero'], 'batiment' => $_POST['batiment'],
            'etage' => $_POST['etage'], 'capacite' => $_POST['capacite'],
            'type' => $_POST['type'], 'equipements' => $_POST['equipements'],
        ]);
        $message = 'Chambre modifiée.';
    }
}

$chambres = $internatService->getChambres();
$stats = $internatService->getStats();
?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-bed"></i> Chambres de l'internat</h1>
        <button class="btn btn-primary" onclick="document.getElementById('formChambre').style.display='block'"><i class="fas fa-plus"></i> Nouvelle chambre</button>
    </div>

    <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>

    <div class="stats-row">
        <div class="stat-card"><div class="stat-value"><?= $stats['total_chambres'] ?></div><div class="stat-label">Chambres</div></div>
        <div class="stat-card stat-info"><div class="stat-value"><?= $stats['capacite_totale'] ?></div><div class="stat-label">Places totales</div></div>
        <div class="stat-card stat-success"><div class="stat-value"><?= $stats['internes_actifs'] ?></div><div class="stat-label">Internes</div></div>
        <div class="stat-card"><div class="stat-value"><?= $stats['taux_occupation'] ?>%</div><div class="stat-label">Occupation</div></div>
    </div>

    <div id="formChambre" class="card" style="display:none; margin-bottom:20px">
        <div class="card-header"><h3>Ajouter une chambre</h3></div>
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="action" value="creer">
                <div class="form-row">
                    <div class="form-group"><label>Numéro</label><input type="text" name="numero" required class="form-control"></div>
                    <div class="form-group"><label>Bâtiment</label><input type="text" name="batiment" class="form-control"></div>
                    <div class="form-group"><label>Étage</label><input type="number" name="etage" class="form-control"></div>
                    <div class="form-group"><label>Capacité</label><input type="number" name="capacite" value="2" class="form-control"></div>
                    <div class="form-group"><label>Type</label>
                        <select name="type" class="form-select">
                            <option value="simple">Simple</option><option value="double" selected>Double</option>
                            <option value="triple">Triple</option><option value="dortoir">Dortoir</option>
                        </select>
                    </div>
                </div>
                <div class="form-group"><label>Équipements</label><textarea name="equipements" class="form-control" rows="2"></textarea></div>
                <button type="submit" class="btn btn-primary">Créer</button>
            </form>
        </div>
    </div>

    <div class="chambres-grid">
        <?php foreach ($chambres as $ch): $occup = ($ch['nb_occupants'] / max($ch['capacite'], 1)) * 100; ?>
        <div class="chambre-card">
            <div class="chambre-header">
                <span class="chambre-numero"><?= htmlspecialchars($ch['numero']) ?></span>
                <span class="badge badge-<?= $occup >= 100 ? 'danger' : ($occup > 0 ? 'warning' : 'success') ?>">
                    <?= $ch['nb_occupants'] ?>/<?= $ch['capacite'] ?>
                </span>
            </div>
            <div class="chambre-info">
                <?php if ($ch['batiment']): ?><span><i class="fas fa-building"></i> <?= htmlspecialchars($ch['batiment']) ?></span><?php endif; ?>
                <?php if ($ch['etage'] !== null): ?><span><i class="fas fa-layer-group"></i> Étage <?= $ch['etage'] ?></span><?php endif; ?>
                <span><i class="fas fa-tag"></i> <?= ucfirst($ch['type']) ?></span>
            </div>
            <a href="affectations.php?chambre=<?= $ch['id'] ?>" class="btn btn-sm btn-outline">Voir occupants</a>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
