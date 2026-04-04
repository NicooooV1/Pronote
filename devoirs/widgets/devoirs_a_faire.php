<?php
$devoirs = $data['devoirs'] ?? [];
$count = $data['count'] ?? 0;
?>
<?php if ($count > 0): ?>
    <div class="widget-stat-mini">
        <strong><?= $count ?></strong> devoir<?= $count > 1 ? 's' : '' ?> à venir
    </div>
<?php endif; ?>
<?php if (empty($devoirs)): ?>
    <p class="widget-empty">Aucun devoir à venir.</p>
<?php else: ?>
    <ul class="widget-list">
        <?php foreach ($devoirs as $d): ?>
        <li class="widget-list-item">
            <span class="widget-list-icon">
                <?php if (!empty($d['fait'])): ?>
                    <i class="fas fa-check-circle" style="color:var(--success-color,#48bb78)"></i>
                <?php else: ?>
                    <i class="fas fa-clock" style="color:var(--warning-color,#ed8936)"></i>
                <?php endif; ?>
            </span>
            <div class="widget-list-content">
                <div class="widget-list-title"><?= htmlspecialchars($d['titre']) ?></div>
                <div class="widget-list-meta">
                    <?= htmlspecialchars($d['nom_matiere']) ?>
                    <?php if (!empty($d['classe'])): ?> — <?= htmlspecialchars($d['classe']) ?><?php endif; ?>
                    &middot; Pour le <?= htmlspecialchars(date('d/m', strtotime($d['date_rendu']))) ?>
                </div>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
