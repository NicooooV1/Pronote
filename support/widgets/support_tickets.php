<?php
$tickets = $data['tickets'] ?? [];
$count = $data['count'] ?? 0;
$prioColors = ['urgent' => '#e53e3e', 'haute' => '#ed8936', 'normale' => '#667eea', 'basse' => '#a0aec0'];
?>
<?php if ($count > 0): ?>
    <div class="widget-stat-mini"><strong><?= $count ?></strong> ticket<?= $count > 1 ? 's' : '' ?> ouvert<?= $count > 1 ? 's' : '' ?></div>
<?php endif; ?>
<?php if (empty($tickets)): ?>
    <p class="widget-empty">Aucun ticket ouvert.</p>
<?php else: ?>
    <ul class="widget-list">
        <?php foreach ($tickets as $t): ?>
        <li class="widget-list-item">
            <span class="widget-list-icon">
                <i class="fas fa-ticket-alt" style="color:<?= $prioColors[$t['priorite']] ?? '#667eea' ?>"></i>
            </span>
            <div class="widget-list-content">
                <div class="widget-list-title">
                    <?= htmlspecialchars($t['sujet']) ?>
                    <?php if ($t['priorite'] === 'urgent'): ?>
                        <span class="widget-badge" style="background:#e53e3e">Urgent</span>
                    <?php endif; ?>
                </div>
                <div class="widget-list-meta">
                    <?= htmlspecialchars(ucfirst($t['categorie'] ?? '')) ?>
                    &middot; <?= htmlspecialchars(ucfirst($t['statut'])) ?>
                    &middot; <?= htmlspecialchars(date('d/m', strtotime($t['created_at']))) ?>
                </div>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
