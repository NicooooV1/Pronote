<?php
/**
 * M38 – Compétences — Référentiel (arborescence)
 */
$pageTitle = 'Référentiel de compétences';
require_once __DIR__ . '/includes/header.php';

$arbre = $compService->getArbreCompetences();
$niveaux = CompetenceService::niveauxLabels();
?>

<div class="main-content">
    <div class="page-header" style="display:flex;justify-content:space-between;align-items:flex-start;">
        <div>
            <h1><i class="fas fa-clipboard-list"></i> Référentiel de compétences</h1>
            <p class="page-subtitle">Socle commun de connaissances et de compétences</p>
        </div>
        <?php if (isAdmin()): ?>
        <a href="referentiel_admin.php" class="btn btn-primary"><i class="fas fa-cogs"></i> Gérer le référentiel</a>
        <?php endif; ?>
    </div>

    <!-- Légende -->
    <div class="comp-legend">
        <?php foreach ($niveaux as $k => $v): ?>
            <?= CompetenceService::niveauDot($k) ?> <span><?= $v ?></span>
        <?php endforeach; ?>
    </div>

    <div class="comp-tree">
        <?php foreach ($arbre as $domaine): ?>
            <div class="comp-domain">
                <div class="comp-domain-header">
                    <span class="comp-code"><?= htmlspecialchars($domaine['code']) ?></span>
                    <span class="comp-name"><?= htmlspecialchars($domaine['nom']) ?></span>
                </div>
                <?php if (!empty($domaine['description'])): ?>
                    <p class="comp-desc"><?= htmlspecialchars($domaine['description']) ?></p>
                <?php endif; ?>

                <?php if (!empty($domaine['children'])): ?>
                    <div class="comp-children">
                        <?php foreach ($domaine['children'] as $child): ?>
                            <div class="comp-item">
                                <span class="comp-code-sm"><?= htmlspecialchars($child['code']) ?></span>
                                <span><?= htmlspecialchars($child['nom']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
