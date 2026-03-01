<?php
/**
 * M27 – Mes convocations (élève)
 */
$pageTitle = 'Mes convocations';
$activePage = 'mes_convocations';
require_once __DIR__ . '/includes/header.php';

if (!isEleve()) { redirect('/examens/examens.php'); }

$convocations = $examenService->getConvocationsEleve(getUserId());
$typesEpreuve = ExamenService::typesEpreuve();
?>

<div class="content-wrapper">
    <div class="content-header"><h1><i class="fas fa-file-alt"></i> Mes convocations</h1></div>

    <?php if (empty($convocations)): ?>
        <div class="empty-state"><i class="fas fa-graduation-cap"></i><p>Aucune convocation.</p></div>
    <?php else: ?>
    <div class="convocations-list">
        <?php foreach ($convocations as $c): ?>
        <div class="convocation-card">
            <div class="convocation-header">
                <strong><?= htmlspecialchars($c['examen_nom']) ?></strong>
                <span class="badge badge-secondary"><?= $typesEpreuve[$c['type_epreuve']] ?? '' ?></span>
            </div>
            <h3><?= htmlspecialchars($c['intitule']) ?></h3>
            <div class="convocation-meta">
                <span><i class="fas fa-calendar"></i> <?= formatDateTime($c['date_epreuve']) ?></span>
                <span><i class="fas fa-clock"></i> <?= $c['duree_minutes'] ?> min</span>
                <?php if ($c['salle_nom']): ?><span><i class="fas fa-door-open"></i> <?= htmlspecialchars($c['salle_nom']) ?></span><?php endif; ?>
                <?php if ($c['place']): ?><span><i class="fas fa-chair"></i> Place <?= htmlspecialchars($c['place']) ?></span><?php endif; ?>
            </div>
            <?php if ($c['note'] !== null): ?>
            <div class="convocation-note">Note : <strong><?= $c['note'] ?>/20</strong></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
