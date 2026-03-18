<?php
$activePage = 'parcours';
require_once __DIR__ . '/includes/header.php';

$user = $_SESSION['user'];
$role  = $user['type'] ?? 'eleve';
$filtres = [
    'type_parcours'  => $_GET['type'] ?? '',
    'annee_scolaire' => $_GET['annee'] ?? '',
];
if ($role === 'eleve') $filtres['eleve_id'] = $user['id'];
if ($role === 'parent' && !empty($_SESSION['enfant_actif_id'])) $filtres['eleve_id'] = $_SESSION['enfant_actif_id'];

$parcours = $parcoursService->getParcours($filtres);
$stats    = $parcoursService->getStatsByType($filtres['eleve_id'] ?? null);
$types    = ParcoursEducatifService::typesLabels();

/* POST valider */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($role, ['admin', 'professeur'])) {
    $parcoursService->valider((int)$_POST['entry_id'], true);
    header('Location: ' . $_SERVER['REQUEST_URI']); exit;
}
?>
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><i class="fas fa-route me-2"></i>Parcours éducatifs</h2>
        <?php if (in_array($role, ['admin', 'professeur'])): ?>
            <a href="ajouter.php" class="btn btn-primary"><i class="fas fa-plus me-1"></i>Ajouter une activité</a>
        <?php endif; ?>
    </div>

    <!-- Stats par type -->
    <div class="row g-3 mb-4">
        <?php foreach ($types as $key => $label): 
            $s = array_filter($stats, fn($r) => $r['type_parcours'] === $key);
            $s = $s ? array_values($s)[0] : ['total' => 0, 'valides' => 0];
        ?>
        <div class="col-md-3">
            <div class="parcours-type-card" style="border-left:4px solid <?= ParcoursEducatifService::typeColor($key) ?>">
                <div class="small fw-bold" style="color:<?= ParcoursEducatifService::typeColor($key) ?>"><?= $label ?></div>
                <div class="d-flex justify-content-between mt-1">
                    <span class="small text-muted"><?= $s['total'] ?> activité(s)</span>
                    <span class="small text-success"><?= $s['valides'] ?> validé(s)</span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Filtres -->
    <form method="get" class="row g-2 mb-4 align-items-end">
        <div class="col-md-3">
            <label class="form-label">Type</label>
            <select name="type" class="form-select form-select-sm">
                <option value="">Tous</option>
                <?php foreach ($types as $k => $v): ?>
                    <option value="<?= $k ?>" <?= ($filtres['type_parcours'] === $k) ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Année scolaire</label>
            <input name="annee" class="form-control form-control-sm" placeholder="ex: 2024/2025" value="<?= htmlspecialchars($filtres['annee_scolaire']) ?>">
        </div>
        <div class="col-md-2"><button class="btn btn-sm btn-outline-primary">Filtrer</button></div>
    </form>

    <!-- Liste -->
    <?php if (empty($parcours)): ?>
        <div class="alert alert-info">Aucune activité enregistrée.</div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>Type</th><th>Titre</th>
                    <?php if (!in_array($role, ['eleve'])): ?><th>Élève</th><?php endif; ?>
                    <th>Date</th><th>Compétences</th><th>Validé</th>
                    <?php if (in_array($role, ['admin', 'professeur'])): ?><th></th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($parcours as $p): ?>
                <tr>
                    <td><span class="parcours-badge" style="background:<?= ParcoursEducatifService::typeColor($p['type_parcours']) ?>"><?= $types[$p['type_parcours']] ?? $p['type_parcours'] ?></span></td>
                    <td>
                        <strong><?= htmlspecialchars($p['titre']) ?></strong>
                        <?php if ($p['description']): ?><br><span class="small text-muted"><?= htmlspecialchars(mb_strimwidth($p['description'], 0, 80, '…')) ?></span><?php endif; ?>
                    </td>
                    <?php if (!in_array($role, ['eleve'])): ?><td class="small"><?= htmlspecialchars($p['eleve_nom'] ?? '#'.$p['eleve_id']) ?></td><?php endif; ?>
                    <td class="small"><?= date('d/m/Y', strtotime($p['date_activite'])) ?></td>
                    <td class="small"><?= htmlspecialchars($p['competences_visees'] ?? '—') ?></td>
                    <td>
                        <?php if ($p['validation']): ?>
                            <span class="badge bg-success"><i class="fas fa-check"></i></span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Non</span>
                        <?php endif; ?>
                    </td>
                    <?php if (in_array($role, ['admin', 'professeur'])): ?>
                    <td>
                        <?php if (!$p['validation']): ?>
                            <form method="post" class="d-inline"><input type="hidden" name="entry_id" value="<?= $p['id'] ?>"><button class="btn btn-sm btn-outline-success">Valider</button></form>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
