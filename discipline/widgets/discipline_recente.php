<?php
$incidents = $data['incidents'] ?? [];
$stats = $data['stats'] ?? [];
$graviteColors = ['mineur' => '#48bb78', 'moyen' => '#ed8936', 'grave' => '#e53e3e', 'tres_grave' => '#9b2c2c'];
?>
<?php if (!empty($stats) && ($stats['total_mois'] ?? 0) > 0): ?>
    <div class="widget-stat-mini">
        <?= (int)$stats['total_mois'] ?> incident<?= (int)$stats['total_mois'] > 1 ? 's' : '' ?> (30j)
        <?php if ((int)($stats['en_attente'] ?? 0) > 0): ?>
            &middot; <strong><?= (int)$stats['en_attente'] ?></strong> en attente
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php if (empty($incidents)): ?>
    <p class="widget-empty">Aucun incident récent.</p>
<?php else: ?>
    <ul class="widget-list">
        <?php foreach ($incidents as $inc): ?>
        <li class="widget-list-item">
            <span class="widget-list-icon">
                <i class="fas fa-exclamation-triangle" style="color:<?= $graviteColors[$inc['gravite']] ?? '#ed8936' ?>"></i>
            </span>
            <div class="widget-list-content">
                <div class="widget-list-title">
                    <?= htmlspecialchars($inc['eleve_nom']) ?>
                    <?php if (!empty($inc['classe'])): ?>(<?= htmlspecialchars($inc['classe']) ?>)<?php endif; ?>
                </div>
                <div class="widget-list-meta">
                    <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $inc['type_incident']))) ?>
                    &middot; <?= htmlspecialchars(date('d/m', strtotime($inc['date_incident']))) ?>
                    &middot; <?= htmlspecialchars(ucfirst($inc['statut'])) ?>
                </div>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
