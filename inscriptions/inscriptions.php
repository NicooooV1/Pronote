<?php
/**
 * M26 – Inscriptions — Liste
 */
$pageTitle = 'Inscriptions';
require_once __DIR__ . '/includes/header.php';

$isGestionnaire = isAdmin() || isPersonnelVS();

if ($isGestionnaire) {
    $filtreStatut = $_GET['statut'] ?? '';
    $filters = $filtreStatut ? ['statut' => $filtreStatut] : [];
    $inscriptions = $inscriptionService->getToutesInscriptions($filters);
    $stats = $inscriptionService->getStats();
} else {
    $inscriptions = $inscriptionService->getInscriptionsParent(getUserId());
    $stats = null;
}

// Action rapide statut (admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken() && $isGestionnaire) {
    $inscriptionService->changerStatut((int)$_POST['id'], $_POST['statut'], getUserId());
    header('Location: inscriptions.php' . ($filtreStatut ? "?statut=$filtreStatut" : ''));
    exit;
}
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-user-plus"></i> <?= $isGestionnaire ? 'Demandes d\'inscription' : 'Mes inscriptions' ?></h1>
        <?php if (isParent()): ?>
        <a href="formulaire.php" class="btn btn-primary"><i class="fas fa-plus"></i> Inscrire un enfant</a>
        <?php endif; ?>
    </div>

    <?php if (!empty($_SESSION['success_message'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success_message'] ?></div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if ($stats): ?>
    <div class="stats-grid">
        <div class="stat-card"><div class="stat-value"><?= $stats['total'] ?></div><div class="stat-label">Total</div></div>
        <div class="stat-card stat-info"><div class="stat-value"><?= $stats['soumises'] ?></div><div class="stat-label">En attente</div></div>
        <div class="stat-card stat-warning"><div class="stat-value"><?= $stats['en_revision'] ?></div><div class="stat-label">En révision</div></div>
        <div class="stat-card stat-success"><div class="stat-value"><?= $stats['acceptees'] ?></div><div class="stat-label">Acceptées</div></div>
    </div>
    <div class="filter-bar">
        <a href="inscriptions.php" class="filter-btn <?= empty($filtreStatut) ? 'active' : '' ?>">Toutes</a>
        <a href="inscriptions.php?statut=soumise" class="filter-btn <?= ($filtreStatut ?? '') === 'soumise' ? 'active' : '' ?>">Soumises</a>
        <a href="inscriptions.php?statut=en_revision" class="filter-btn <?= ($filtreStatut ?? '') === 'en_revision' ? 'active' : '' ?>">En révision</a>
        <a href="inscriptions.php?statut=acceptee" class="filter-btn <?= ($filtreStatut ?? '') === 'acceptee' ? 'active' : '' ?>">Acceptées</a>
        <a href="inscriptions.php?statut=refusee" class="filter-btn <?= ($filtreStatut ?? '') === 'refusee' ? 'active' : '' ?>">Refusées</a>
        <a href="inscriptions.php?statut=liste_attente" class="filter-btn <?= ($filtreStatut ?? '') === 'liste_attente' ? 'active' : '' ?>">Liste d'attente</a>
        <span style="margin-left:auto;">
            <a href="export.php?format=csv<?= $filtreStatut ? '&statut=' . urlencode($filtreStatut) : '' ?>" class="btn btn-sm btn-outline"><i class="fas fa-file-csv"></i> CSV</a>
            <a href="export.php?format=pdf<?= $filtreStatut ? '&statut=' . urlencode($filtreStatut) : '' ?>" class="btn btn-sm btn-outline"><i class="fas fa-file-pdf"></i> PDF</a>
        </span>
    </div>
    <?php endif; ?>

    <?php if (empty($inscriptions)): ?>
        <div class="empty-state"><i class="fas fa-inbox"></i><p>Aucune inscription.</p></div>
    <?php else: ?>
    <div class="inscription-list">
        <?php foreach ($inscriptions as $insc): ?>
        <div class="inscription-item">
            <div class="insc-avatar"><?= strtoupper(substr($insc['prenom_eleve'], 0, 1) . substr($insc['nom_eleve'], 0, 1)) ?></div>
            <div class="insc-info">
                <h3><?= htmlspecialchars($insc['prenom_eleve'] . ' ' . $insc['nom_eleve']) ?></h3>
                <div class="insc-meta">
                    <?= InscriptionService::statutBadge($insc['statut']) ?>
                    <span><i class="fas fa-birthday-cake"></i> <?= formatDate($insc['date_naissance']) ?></span>
                    <?php if ($insc['classe_nom']): ?>
                    <span><i class="fas fa-chalkboard"></i> <?= htmlspecialchars($insc['classe_nom']) ?></span>
                    <?php endif; ?>
                    <span><i class="fas fa-clock"></i> <?= formatDateTime($insc['date_soumission']) ?></span>
                </div>
            </div>
            <div class="insc-actions">
                <a href="detail.php?id=<?= $insc['id'] ?>" class="btn btn-sm btn-outline"><i class="fas fa-eye"></i></a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
