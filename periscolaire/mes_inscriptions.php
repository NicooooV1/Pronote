<?php
/**
 * M16 – Mes inscriptions périscolaires (parent)
 */
$pageTitle = 'Mes inscriptions';
$activePage = 'mes_inscriptions';
require_once __DIR__ . '/includes/header.php';

if (!isParent() && !isEleve()) { redirect('/periscolaire/services.php'); }

$inscriptions = isParent() ? $periService->getInscriptionsParent(getUserId()) : $periService->getInscriptionsEleve(getUserId());
$types = PeriscolaireService::typesService();
$jours = PeriscolaireService::jours();
?>

<div class="content-wrapper">
    <div class="content-header"><h1><i class="fas fa-clipboard-list"></i> Mes inscriptions</h1></div>

    <?php if (empty($inscriptions)): ?>
        <div class="empty-state"><i class="fas fa-clipboard-list"></i><p>Aucune inscription périscolaire.</p></div>
    <?php else: ?>
    <div class="inscriptions-list">
        <?php foreach ($inscriptions as $i): ?>
        <div class="inscription-card">
            <div class="inscription-icon"><i class="fas fa-<?= PeriscolaireService::iconeType($i['service_type']) ?>"></i></div>
            <div class="inscription-info">
                <h3><?= htmlspecialchars($i['service_nom']) ?></h3>
                <?php if (isset($i['eleve_nom'])): ?><p class="text-muted"><?= htmlspecialchars($i['eleve_nom']) ?></p><?php endif; ?>
                <div class="inscription-meta">
                    <span class="badge badge-secondary"><?= $types[$i['service_type']] ?? '' ?></span>
                    <span><?= $jours[$i['jour']] ?? $i['jour'] ?></span>
                    <span class="badge badge-<?= $i['statut'] === 'active' ? 'success' : 'danger' ?>"><?= ucfirst($i['statut']) ?></span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
