<?php
$cours = $data['cours'] ?? [];
$jour = $data['jour'] ?? '';
?>
<?php if ($jour): ?>
    <div class="widget-stat-mini"><?= htmlspecialchars($jour) ?> &middot; <?= count($cours) ?> cours</div>
<?php endif; ?>
<?php if (empty($cours)): ?>
    <p class="widget-empty">Aucun cours aujourd'hui.</p>
<?php else: ?>
    <ul class="widget-list">
        <?php foreach ($cours as $c): ?>
        <li class="widget-list-item">
            <span class="widget-list-icon"><i class="fas fa-clock" style="color:var(--primary-color,#667eea)"></i></span>
            <div class="widget-list-content">
                <div class="widget-list-title">
                    <?= htmlspecialchars(substr($c['heure_debut'], 0, 5)) ?>–<?= htmlspecialchars(substr($c['heure_fin'], 0, 5)) ?>
                    &nbsp;<?= htmlspecialchars($c['matiere']) ?>
                    <?php if (($c['type_modification'] ?? '') === 'deplacement'): ?>
                        <span class="widget-badge" style="background:var(--warning-color,#ed8936)">Modifié</span>
                    <?php endif; ?>
                </div>
                <div class="widget-list-meta">
                    <?= htmlspecialchars($c['professeur'] ?? $c['classe'] ?? '') ?>
                    <?php if (!empty($c['salle'])): ?> — Salle <?= htmlspecialchars($c['salle']) ?><?php endif; ?>
                </div>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
