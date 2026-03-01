<?php
/**
 * M11 – Annonces : Gestion (admin — vue d'ensemble)
 */

require_once __DIR__ . '/includes/AnnonceService.php';

$pageTitle = 'Gestion des annonces';
$currentPage = 'gestion';
require_once __DIR__ . '/includes/header.php';
requireAuth();

if (!isAdmin()) {
    echo '<div class="alert alert-danger">Accès réservé aux administrateurs.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$pdo = getPDO();
$service = new AnnonceService($pdo);

// Statistiques rapides
$totalAnnonces = $pdo->query("SELECT COUNT(*) FROM annonces")->fetchColumn();
$totalPubliees = $pdo->query("SELECT COUNT(*) FROM annonces WHERE publie = 1")->fetchColumn();
$totalBrouillons = $pdo->query("SELECT COUNT(*) FROM annonces WHERE publie = 0")->fetchColumn();
$totalSondages = $pdo->query("SELECT COUNT(*) FROM sondages")->fetchColumn();
$totalVotes = $pdo->query("SELECT COUNT(*) FROM sondage_votes")->fetchColumn();

$annonces = $service->getAllAnnonces();
$types = AnnonceService::getTypes();
?>

<h1 class="page-title"><i class="fas fa-cog"></i> Gestion des annonces</h1>

<div class="stats-row">
    <div class="stat-card stat-total">
        <div class="stat-number"><?= $totalAnnonces ?></div>
        <div class="stat-label">Annonces</div>
    </div>
    <div class="stat-card" style="border-left-color: #10b981;">
        <div class="stat-number" style="color:#10b981;"><?= $totalPubliees ?></div>
        <div class="stat-label">Publiées</div>
    </div>
    <div class="stat-card stat-warning">
        <div class="stat-number"><?= $totalBrouillons ?></div>
        <div class="stat-label">Brouillons</div>
    </div>
    <div class="stat-card" style="border-left-color: #8b5cf6;">
        <div class="stat-number" style="color:#8b5cf6;"><?= $totalSondages ?></div>
        <div class="stat-label">Sondages</div>
    </div>
</div>

<div class="table-responsive">
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Titre</th>
                <th>Type</th>
                <th>Publiée</th>
                <th>Épinglée</th>
                <th>Lectures</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($annonces as $a): ?>
            <tr>
                <td>#<?= $a['id'] ?></td>
                <td><?= htmlspecialchars(mb_substr($a['titre'], 0, 50)) ?></td>
                <td><span class="badge <?= AnnonceService::getTypeBadgeClass($a['type']) ?>"><?= $types[$a['type']] ?? $a['type'] ?></span></td>
                <td><?= $a['publie'] ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-times text-muted"></i>' ?></td>
                <td><?= $a['epingle'] ? '<i class="fas fa-thumbtack text-primary"></i>' : '-' ?></td>
                <td><?= $a['nb_lues'] ?? 0 ?></td>
                <td><?= date('d/m/Y', strtotime($a['date_publication'])) ?></td>
                <td class="actions-cell">
                    <a href="detail_annonce.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-outline"><i class="fas fa-eye"></i></a>
                    <a href="modifier_annonce.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-secondary"><i class="fas fa-edit"></i></a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
