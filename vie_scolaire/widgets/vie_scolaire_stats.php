<?php
$stats = $data['stats'] ?? [];
?>
<?php if (empty($stats)): ?>
    <p class="widget-empty">Aucune donnée disponible.</p>
<?php else: ?>
    <div class="widget-stats-grid">
        <?php foreach ($stats as $s): ?>
        <div class="widget-stat-card">
            <div class="widget-stat-icon" style="color:<?= htmlspecialchars($s['color']) ?>">
                <i class="<?= htmlspecialchars($s['icon']) ?>"></i>
            </div>
            <div class="widget-stat-value"><?= (int)$s['value'] ?></div>
            <div class="widget-stat-label"><?= htmlspecialchars($s['label']) ?></div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
