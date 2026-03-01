<?php
/**
 * M27 – Examens — Liste
 */
$pageTitle = 'Examens & Épreuves';
require_once __DIR__ . '/includes/header.php';

$statut = $_GET['statut'] ?? '';
$examens = $examenService->getExamens($statut ?: null);
$types = ExamenService::typesExamen();
$isGestionnaire = isAdmin() || isPersonnelVS();
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-graduation-cap"></i> Examens & Épreuves</h1>
        <?php if ($isGestionnaire): ?><a href="creer.php" class="btn btn-primary"><i class="fas fa-plus"></i> Créer</a><?php endif; ?>
    </div>

    <div class="filter-bar">
        <a href="examens.php" class="btn <?= !$statut ? 'btn-primary' : 'btn-outline' ?>">Tous</a>
        <a href="examens.php?statut=planifie" class="btn <?= $statut === 'planifie' ? 'btn-primary' : 'btn-outline' ?>">Planifiés</a>
        <a href="examens.php?statut=en_cours" class="btn <?= $statut === 'en_cours' ? 'btn-primary' : 'btn-outline' ?>">En cours</a>
        <a href="examens.php?statut=termine" class="btn <?= $statut === 'termine' ? 'btn-primary' : 'btn-outline' ?>">Terminés</a>
    </div>

    <?php if (empty($examens)): ?>
        <div class="empty-state"><i class="fas fa-graduation-cap"></i><p>Aucun examen.</p></div>
    <?php else: ?>
    <div class="examens-list">
        <?php foreach ($examens as $ex): ?>
        <div class="examen-item">
            <div class="examen-icon"><i class="fas fa-graduation-cap"></i></div>
            <div class="examen-info">
                <h3><a href="detail.php?id=<?= $ex['id'] ?>"><?= htmlspecialchars($ex['nom']) ?></a></h3>
                <div class="examen-meta">
                    <span class="badge badge-secondary"><?= $types[$ex['type']] ?? $ex['type'] ?></span>
                    <?= ExamenService::statutBadge($ex['statut']) ?>
                    <span><i class="fas fa-calendar"></i> <?= formatDate($ex['date_debut']) ?><?= $ex['date_fin'] ? ' → ' . formatDate($ex['date_fin']) : '' ?></span>
                    <span><i class="fas fa-file-alt"></i> <?= $ex['nb_epreuves'] ?> épreuve(s)</span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
