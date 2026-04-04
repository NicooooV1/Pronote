<?php
$reunions = $data['reunions'] ?? [];
?>
<?php if (empty($reunions)): ?>
    <p class="widget-empty">Aucune réunion à venir.</p>
<?php else: ?>
    <ul class="widget-list">
        <?php foreach ($reunions as $r): ?>
        <li class="widget-list-item">
            <span class="widget-list-icon"><i class="fas fa-handshake" style="color:var(--accent-color,#667eea)"></i></span>
            <div class="widget-list-content">
                <div class="widget-list-title"><?= htmlspecialchars($r['titre']) ?></div>
                <div class="widget-list-meta">
                    <?= htmlspecialchars(date('d/m/Y', strtotime($r['date_reunion']))) ?>
                    <?php if (!empty($r['heure_debut'])): ?>
                        à <?= htmlspecialchars(substr($r['heure_debut'], 0, 5)) ?>
                    <?php endif; ?>
                    <?php if (!empty($r['lieu'])): ?> — <?= htmlspecialchars($r['lieu']) ?><?php endif; ?>
                    <?php if (isset($r['nb_creneaux'])): ?>
                        &middot; <?= (int)$r['nb_creneaux'] ?> créneau<?= (int)$r['nb_creneaux'] > 1 ? 'x' : '' ?>
                    <?php endif; ?>
                </div>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
