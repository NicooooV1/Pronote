<?php
/**
 * M14 – Réunions — Réserver un créneau (parent)
 */
$pageTitle = 'Réserver un créneau';
require_once __DIR__ . '/includes/header.php';

if (!isParent()) { header('Location: reunions.php'); exit; }

$reunionId = (int)($_GET['reunion_id'] ?? 0);
$reunion = $reunionService->getReunion($reunionId);
if (!$reunion || $reunion['statut'] !== 'planifiee') { header('Location: reunions.php'); exit; }

// Récupérer les enfants du parent
$parentId = getUserId();
$pdo = getPDO();
$stmtEnfants = $pdo->prepare("SELECT e.id, e.nom, e.prenom, e.classe FROM parent_eleve pe JOIN eleves e ON pe.id_eleve = e.id WHERE pe.id_parent = ?");
$stmtEnfants->execute([$parentId]);
$enfants = $stmtEnfants->fetchAll(PDO::FETCH_ASSOC);

$creneaux = $reunionService->getCreneaux($reunionId);

// Regrouper par prof
$parProf = [];
foreach ($creneaux as $c) {
    $key = $c['professeur_id'];
    if (!isset($parProf[$key])) {
        $parProf[$key] = ['nom' => $c['prof_prenom'] . ' ' . $c['prof_nom'], 'matiere' => $c['matiere_nom'] ?? '', 'creneaux' => []];
    }
    $parProf[$key]['creneaux'][] = $c;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken()) {
    $creneauId = (int)$_POST['creneau_id'];
    $eleveId = (int)$_POST['eleve_id'];
    $commentaire = trim($_POST['commentaire'] ?? '');
    try {
        $reunionService->reserver($creneauId, $parentId, $eleveId, $commentaire);
        $_SESSION['success_message'] = 'Créneau réservé avec succès !';
        header('Location: detail.php?id=' . $reunionId);
        exit;
    } catch (\RuntimeException $e) {
        $error = $e->getMessage();
    }
}
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-bookmark"></i> Réserver un créneau</h1>
        <a href="detail.php?id=<?= $reunionId ?>" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>

    <div class="card">
        <div class="card-header"><?= htmlspecialchars($reunion['titre']) ?> — <?= formatDate($reunion['date_debut'], 'd/m/Y') ?></div>
        <div class="card-body">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>

            <?php if (empty($enfants)): ?>
                <div class="alert alert-warning">Aucun enfant associé à votre compte.</div>
            <?php else: ?>
                <?php foreach ($parProf as $profId => $profData): ?>
                <div class="prof-section">
                    <h3><?= htmlspecialchars($profData['nom']) ?> <?= $profData['matiere'] ? '— ' . htmlspecialchars($profData['matiere']) : '' ?></h3>
                    <div class="creneau-grid">
                        <?php foreach ($profData['creneaux'] as $c): ?>
                        <div class="creneau-card <?= $c['reservation_id'] ? 'creneau-taken' : 'creneau-free' ?>">
                            <div class="creneau-time"><?= substr($c['heure_debut'], 0, 5) ?> — <?= substr($c['heure_fin'], 0, 5) ?></div>
                            <?php if ($c['reservation_id']): ?>
                                <span class="badge badge-danger">Occupé</span>
                            <?php else: ?>
                                <form method="post" class="creneau-form">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="creneau_id" value="<?= $c['id'] ?>">
                                    <select name="eleve_id" class="form-control form-control-sm" required>
                                        <?php foreach ($enfants as $enf): ?>
                                        <option value="<?= $enf['id'] ?>"><?= htmlspecialchars($enf['prenom'] . ' ' . $enf['nom']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="text" name="commentaire" class="form-control form-control-sm" placeholder="Motif (optionnel)">
                                    <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-check"></i> Réserver</button>
                                </form>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
