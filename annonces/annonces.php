<?php
/**
 * M11 – Annonces : Liste des annonces
 */

require_once __DIR__ . '/includes/AnnonceService.php';

$pageTitle = 'Annonces';
$currentPage = 'annonces';
require_once __DIR__ . '/includes/header.php';
requireAuth();

$pdo = getPDO();
$service = new AnnonceService($pdo);
$user = getCurrentUser();
$role = getUserRole();

// Marquer comme lue (AJAX)
if (isset($_GET['ajax']) && $_GET['ajax'] === 'marquer_lue') {
    header('Content-Type: application/json');
    $annonceId = (int)($_GET['id'] ?? 0);
    $userType = $role;
    $service->marquerLue($annonceId, $user['id'], $userType);
    echo json_encode(['ok' => true]);
    exit;
}

// Charger les annonces
if (isAdmin()) {
    $filtreType = $_GET['type'] ?? '';
    $filters = [];
    if ($filtreType) $filters['type'] = $filtreType;
    $annonces = $service->getAllAnnonces($filters);
} else {
    // Déterminer la classe de l'utilisateur
    $classeId = null;
    $classeNom = null;
    if (isStudent()) {
        $classeNom = $user['classe'] ?? null;
        // Récupérer l'ID de classe
        if ($classeNom) {
            $stmtC = $pdo->prepare("SELECT id FROM classes WHERE nom = ? LIMIT 1");
            $stmtC->execute([$classeNom]);
            $classeId = $stmtC->fetchColumn() ?: null;
        }
    }
    $annonces = $service->getAnnoncesVisibles($role, $classeNom, $classeId);
}

$types = AnnonceService::getTypes();
?>

<h1 class="page-title"><i class="fas fa-bullhorn"></i> Annonces</h1>

<?php if (isAdmin()): ?>
<!-- Filtres (admin) -->
<div class="filter-bar card">
    <form method="GET" class="filter-form">
        <div class="filter-group">
            <label for="type">Type</label>
            <select name="type" id="type">
                <option value="">Tous</option>
                <?php foreach ($types as $key => $label): ?>
                <option value="<?= $key ?>" <?= ($filtreType ?? '') === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filtrer</button>
            <a href="annonces.php" class="btn btn-secondary">Réinitialiser</a>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- Liste des annonces -->
<?php if (empty($annonces)): ?>
    <div class="empty-state">
        <i class="fas fa-bullhorn"></i>
        <p>Aucune annonce pour le moment.</p>
    </div>
<?php else: ?>
<div class="annonces-list">
    <?php foreach ($annonces as $a): 
        $estLue = $service->estLue($a['id'], $user['id'], $role);
        $sondage = ($a['type'] === 'sondage') ? $service->getSondage($a['id']) : null;
    ?>
    <div class="annonce-card <?= $a['epingle'] ? 'annonce-epinglee' : '' ?> <?= !$estLue ? 'annonce-non-lue' : '' ?>"
         data-id="<?= $a['id'] ?>">
        
        <?php if ($a['epingle']): ?>
        <div class="annonce-pin"><i class="fas fa-thumbtack"></i></div>
        <?php endif; ?>

        <div class="annonce-header">
            <span class="badge <?= AnnonceService::getTypeBadgeClass($a['type']) ?>">
                <?= htmlspecialchars($types[$a['type']] ?? $a['type']) ?>
            </span>
            <?php if (!$estLue): ?>
            <span class="badge badge-new">Nouveau</span>
            <?php endif; ?>
            <span class="annonce-date">
                <i class="fas fa-clock"></i> <?= date('d/m/Y à H:i', strtotime($a['date_publication'])) ?>
            </span>
        </div>

        <h2 class="annonce-titre"><?= htmlspecialchars($a['titre']) ?></h2>
        
        <div class="annonce-contenu">
            <?= nl2br(htmlspecialchars(mb_substr($a['contenu'], 0, 300))) ?>
            <?php if (mb_strlen($a['contenu']) > 300): ?>
            <span class="annonce-lire-suite">... <a href="detail_annonce.php?id=<?= $a['id'] ?>">Lire la suite</a></span>
            <?php endif; ?>
        </div>

        <?php if ($sondage && $sondage['actif']): ?>
        <div class="sondage-preview">
            <div class="sondage-question"><i class="fas fa-poll"></i> <?= htmlspecialchars($sondage['question']) ?></div>
            <div class="sondage-meta">
                <?= $sondage['total_votants'] ?> vote(s)
                <?php if ($sondage['date_fin'] && strtotime($sondage['date_fin']) > time()): ?>
                 — Jusqu'au <?= date('d/m/Y', strtotime($sondage['date_fin'])) ?>
                <?php elseif ($sondage['date_fin'] && strtotime($sondage['date_fin']) <= time()): ?>
                 — <span class="text-muted">Terminé</span>
                <?php endif; ?>
            </div>
            <a href="detail_annonce.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-primary">
                <?= $service->aVote($sondage['id'], $user['id'], $role) ? 'Voir les résultats' : 'Voter' ?>
            </a>
        </div>
        <?php endif; ?>

        <div class="annonce-footer">
            <?php if (isAdmin()): ?>
            <span class="annonce-meta"><i class="fas fa-eye"></i> <?= $a['nb_lues'] ?? 0 ?> lecture(s)</span>
            <?php if (!$a['publie']): ?>
            <span class="badge badge-secondary">Brouillon</span>
            <?php endif; ?>
            <?php endif; ?>
            <a href="detail_annonce.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-outline">Lire</a>
            <?php if (isAdmin() || ($a['auteur_id'] == $user['id'] && $a['auteur_type'] === $role)): ?>
            <a href="modifier_annonce.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-secondary">
                <i class="fas fa-edit"></i>
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
// Marquer comme lue au clic
document.querySelectorAll('.annonce-card.annonce-non-lue').forEach(card => {
    card.addEventListener('click', function() {
        const id = this.dataset.id;
        fetch('annonces.php?ajax=marquer_lue&id=' + id).then(() => {
            this.classList.remove('annonce-non-lue');
            this.querySelector('.badge-new')?.remove();
        });
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
