<?php
$conversations = $data['conversations'] ?? [];
$unreadTotal = $data['unread_total'] ?? 0;
?>
<?php if ($unreadTotal > 0): ?>
    <div class="widget-stat-mini">
        <strong><?= $unreadTotal ?></strong> message<?= $unreadTotal > 1 ? 's' : '' ?> non lu<?= $unreadTotal > 1 ? 's' : '' ?>
    </div>
<?php endif; ?>
<?php if (empty($conversations)): ?>
    <p class="widget-empty">Aucun message non lu.</p>
<?php else: ?>
    <ul class="widget-list">
        <?php foreach ($conversations as $conv): ?>
        <li class="widget-list-item">
            <span class="widget-list-icon"><i class="fas fa-envelope" style="color:var(--primary-color,#667eea)"></i></span>
            <div class="widget-list-content">
                <div class="widget-list-title">
                    <?= htmlspecialchars($conv['subject']) ?>
                    <?php if ((int)$conv['unread_count'] > 1): ?>
                        <span class="widget-badge"><?= (int)$conv['unread_count'] ?></span>
                    <?php endif; ?>
                </div>
                <div class="widget-list-meta">
                    <?= htmlspecialchars(mb_strimwidth(strip_tags($conv['last_body'] ?? ''), 0, 60, '...')) ?>
                </div>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
