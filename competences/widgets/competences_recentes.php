<?php
$evaluations = $data['evaluations'] ?? [];
$niveauLabels = ['non_atteint' => 'Non atteint', 'en_cours' => 'En cours', 'atteint' => 'Atteint', 'depasse' => 'Dépassé'];
$niveauColors = ['non_atteint' => '#e53e3e', 'en_cours' => '#ed8936', 'atteint' => '#48bb78', 'depasse' => '#667eea'];
?>
<?php if (empty($evaluations)): ?>
    <p class="widget-empty">Aucune évaluation récente.</p>
<?php else: ?>
    <ul class="widget-list">
        <?php foreach ($evaluations as $ev): ?>
        <li class="widget-list-item">
            <span class="widget-list-icon">
                <i class="fas fa-award" style="color:<?= $niveauColors[$ev['niveau']] ?? '#667eea' ?>"></i>
            </span>
            <div class="widget-list-content">
                <div class="widget-list-title">
                    <?= htmlspecialchars($ev['competence']) ?>
                    <span class="widget-badge" style="background:<?= $niveauColors[$ev['niveau']] ?? '#667eea' ?>">
                        <?= htmlspecialchars($niveauLabels[$ev['niveau']] ?? $ev['niveau']) ?>
                    </span>
                </div>
                <div class="widget-list-meta">
                    <?= htmlspecialchars($ev['domaine'] ?? '') ?>
                    <?php if (!empty($ev['eleve_nom'])): ?> — <?= htmlspecialchars($ev['eleve_nom']) ?><?php endif; ?>
                    &middot; <?= htmlspecialchars(date('d/m', strtotime($ev['date_evaluation']))) ?>
                </div>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
