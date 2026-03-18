<?php
/**
 * M20 – Garderie — Créneaux d'accueil
 */
$activePage = 'creneaux';
$pageTitle = 'Garderie — Créneaux';
require_once __DIR__ . '/includes/header.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isGestionnaire && isset($_POST['creer'])) {
    $garderieService->creerCreneau([
        'nom' => $_POST['nom'], 'type' => $_POST['type'],
        'heure_debut' => $_POST['heure_debut'], 'heure_fin' => $_POST['heure_fin'],
        'places_max' => $_POST['places_max'] ?: null, 'tarif' => $_POST['tarif'] ?: null,
    ]);
    $message = 'Créneau créé.';
}

$creneaux = $garderieService->getCreneaux();
$stats = $garderieService->getStats();
$typeLabels = ['matin' => 'Matin', 'soir' => 'Soir', 'mercredi' => 'Mercredi', 'vacances' => 'Vacances'];
$typeColors = ['matin' => '#f59e0b', 'soir' => '#6366f1', 'mercredi' => '#10b981', 'vacances' => '#ef4444'];
?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-child"></i> Créneaux de garderie</h1>
        <?php if ($isGestionnaire): ?>
        <button class="btn btn-primary" onclick="document.getElementById('formCreneau').style.display='block'"><i class="fas fa-plus"></i> Nouveau</button>
        <?php endif; ?>
    </div>

    <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>

    <div class="stats-row">
        <div class="stat-card"><div class="stat-value"><?= $stats['total_creneaux'] ?></div><div class="stat-label">Créneaux</div></div>
        <div class="stat-card stat-info"><div class="stat-value"><?= $stats['total_inscrits'] ?></div><div class="stat-label">Inscriptions</div></div>
        <div class="stat-card stat-success"><div class="stat-value"><?= $stats['nb_eleves'] ?></div><div class="stat-label">Élèves</div></div>
    </div>

    <?php if ($isGestionnaire): ?>
    <div id="formCreneau" class="card" style="display:none; margin-bottom:20px">
        <div class="card-header"><h3>Nouveau créneau</h3></div>
        <div class="card-body">
            <form method="post">
                <input type="hidden" name="creer" value="1">
                <div class="form-row">
                    <div class="form-group"><label>Nom</label><input type="text" name="nom" required class="form-control" placeholder="Ex: Garderie du matin"></div>
                    <div class="form-group"><label>Type</label>
                        <select name="type" class="form-select">
                            <option value="matin">Matin</option><option value="soir">Soir</option>
                            <option value="mercredi">Mercredi</option><option value="vacances">Vacances</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Début</label><input type="time" name="heure_debut" required class="form-control"></div>
                    <div class="form-group"><label>Fin</label><input type="time" name="heure_fin" required class="form-control"></div>
                    <div class="form-group"><label>Places max</label><input type="number" name="places_max" class="form-control"></div>
                    <div class="form-group"><label>Tarif (€)</label><input type="number" step="0.01" name="tarif" class="form-control"></div>
                </div>
                <button type="submit" class="btn btn-primary">Créer</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div class="creneaux-grid">
        <?php foreach ($creneaux as $cr): ?>
        <div class="creneau-card" style="border-left: 4px solid <?= $typeColors[$cr['type']] ?? '#94a3b8' ?>">
            <div class="creneau-header">
                <h3><?= htmlspecialchars($cr['nom']) ?></h3>
                <span class="badge" style="background:<?= $typeColors[$cr['type']] ?? '#94a3b8' ?>;color:#fff"><?= $typeLabels[$cr['type']] ?? $cr['type'] ?></span>
            </div>
            <div class="creneau-info">
                <span><i class="fas fa-clock"></i> <?= substr($cr['heure_debut'], 0, 5) ?> — <?= substr($cr['heure_fin'], 0, 5) ?></span>
                <?php if ($cr['places_max']): ?><span><i class="fas fa-users"></i> <?= $cr['places_max'] ?> places</span><?php endif; ?>
                <?php if ($cr['tarif']): ?><span><i class="fas fa-euro-sign"></i> <?= number_format($cr['tarif'], 2, ',', '') ?> €</span><?php endif; ?>
            </div>
            <a href="inscriptions.php?creneau=<?= $cr['id'] ?>" class="btn btn-sm btn-outline">Voir inscriptions</a>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
