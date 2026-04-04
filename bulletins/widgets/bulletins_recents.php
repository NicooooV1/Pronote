<?php
$bulletins = $data['bulletins'] ?? [];
?>
<?php if (empty($bulletins)): ?>
    <p class="widget-empty">Aucun bulletin disponible.</p>
<?php else: ?>
    <ul class="widget-list">
        <?php foreach ($bulletins as $b): ?>
        <li class="widget-list-item">
            <span class="widget-list-icon">
                <?php if ($b['statut'] === 'publie'): ?>
                    <i class="fas fa-file-alt" style="color:var(--success-color,#48bb78)"></i>
                <?php else: ?>
                    <i class="fas fa-file" style="color:var(--text-muted,#a0aec0)"></i>
                <?php endif; ?>
            </span>
            <div class="widget-list-content">
                <div class="widget-list-title">
                    <?= htmlspecialchars($b['periode_nom'] ?? $b['periode'] ?? 'Période') ?>
                    <?php if ($b['moyenne_generale']): ?>
                        — <strong><?= htmlspecialchars((string)$b['moyenne_generale']) ?>/20</strong>
                    <?php endif; ?>
                </div>
                <div class="widget-list-meta">
                    <?= $b['statut'] === 'publie' ? 'Publié' : 'En cours' ?>
                    <?php if (!empty($b['appreciation_generale'])): ?>
                        &middot; <?= htmlspecialchars(mb_strimwidth($b['appreciation_generale'], 0, 50, '...')) ?>
                    <?php endif; ?>
                </div>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
