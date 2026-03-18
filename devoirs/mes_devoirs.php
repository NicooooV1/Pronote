<?php
/**
 * Devoirs en ligne — Vue élève : liste des devoirs à rendre
 */
require_once __DIR__ . '/includes/RenduService.php';
$currentPage = 'mes_devoirs';
$pageTitle = 'Mes devoirs';
require_once __DIR__ . '/includes/header.php';
requireAuth();

$pdo = getPDO();
$service = new RenduService($pdo);

if ($user_role === 'eleve') {
    $devoirs = $service->getDevoirsARendreEleve($user['id']);
} elseif ($user_role === 'parent') {
    $eleveId = (int)($_GET['eleve'] ?? ($_SESSION['selected_enfant_id'] ?? 0));
    $devoirs = $eleveId ? $service->getDevoirsARendreEleve($eleveId) : [];
} else {
    // Prof: voir les devoirs créés via cahier de textes avec stats rendus
    $stmt = $pdo->prepare("SELECT d.*, (SELECT COUNT(*) FROM devoirs_rendus WHERE devoir_id = d.id) AS nb_rendus FROM devoirs d WHERE d.nom_professeur = ? ORDER BY d.date_rendu DESC");
    $stmt->execute([$user_fullname]);
    $devoirs = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$now = time();
?>

<div class="page-header">
    <h1><i class="fas fa-tasks"></i> <?= $user_role === 'eleve' ? 'Mes devoirs à rendre' : 'Devoirs en ligne' ?></h1>
</div>

<?php if ($user_role === 'eleve'): ?>
<div class="devoirs-grid">
    <?php foreach ($devoirs as $d):
        $dateEcheance = strtotime($d['date_rendu']);
        $isPast = $dateEcheance < $now;
        $isUrgent = !$isPast && ($dateEcheance - $now) < 86400 * 2;
        $hasRendu = !empty($d['rendu_id']);
    ?>
    <div class="devoir-card <?= $hasRendu ? 'devoir-rendu' : ($isPast ? 'devoir-past' : ($isUrgent ? 'devoir-urgent' : '')) ?>">
        <div class="devoir-card-header">
            <span class="devoir-matiere"><?= htmlspecialchars($d['nom_matiere']) ?></span>
            <?php if ($hasRendu): ?>
                <?= RenduService::statutBadge($d['rendu_statut']) ?>
                <?php if ($d['note'] !== null): ?>
                    <span class="badge badge-primary"><?= $d['note'] ?>/20</span>
                <?php endif; ?>
            <?php elseif ($isPast): ?>
                <span class="badge badge-danger">En retard</span>
            <?php elseif ($isUrgent): ?>
                <span class="badge badge-warning">Urgent</span>
            <?php endif; ?>
        </div>
        <h3 class="devoir-titre"><?= htmlspecialchars($d['titre']) ?></h3>
        <p class="devoir-desc"><?= htmlspecialchars(mb_substr($d['description'] ?? '', 0, 120)) ?><?= mb_strlen($d['description'] ?? '') > 120 ? '...' : '' ?></p>
        <div class="devoir-footer">
            <span class="devoir-date"><i class="fas fa-clock"></i> <?= formatDate($d['date_rendu'], 'd/m/Y') ?></span>
            <?php if (!$hasRendu): ?>
                <a href="rendre.php?devoir=<?= $d['id'] ?>" class="btn btn-sm btn-primary"><i class="fas fa-upload"></i> Rendre</a>
            <?php else: ?>
                <a href="voir_rendu.php?devoir=<?= $d['id'] ?>" class="btn btn-sm btn-outline"><i class="fas fa-eye"></i> Voir</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($devoirs)): ?>
    <div class="empty-state"><i class="fas fa-check-circle"></i><p>Aucun devoir en ligne pour le moment.</p></div>
    <?php endif; ?>
</div>

<?php else: /* Prof/Admin */ ?>
<div class="data-table-container">
    <table class="data-table">
        <thead>
            <tr><th>Titre</th><th>Matière</th><th>Classe</th><th class="text-center">Échéance</th><th class="text-center">Rendus</th><th>Actions</th></tr>
        </thead>
        <tbody>
            <?php foreach ($devoirs as $d): ?>
            <tr>
                <td class="fw-500"><?= htmlspecialchars($d['titre']) ?></td>
                <td><?= htmlspecialchars($d['nom_matiere']) ?></td>
                <td><?= htmlspecialchars($d['classe']) ?></td>
                <td class="text-center"><?= formatDate($d['date_rendu']) ?></td>
                <td class="text-center"><span class="badge badge-info"><?= $d['nb_rendus'] ?? 0 ?></span></td>
                <td><a href="corriger.php?devoir=<?= $d['id'] ?>" class="btn btn-sm btn-primary"><i class="fas fa-check-double"></i> Corriger</a></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($devoirs)): ?>
            <tr><td colspan="6" class="text-center text-muted">Aucun devoir trouvé.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
