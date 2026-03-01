<?php
/**
 * Widget Prochains événements — inclus depuis accueil.php
 * Variables attendues : $prochains_evenements (array)
 */
?>
<div class="widget">
    <div class="widget-header">
        <h3><i class="fas fa-calendar"></i> Prochains événements</h3>
        <a href="../agenda/agenda.php" class="widget-action">Voir tout</a>
    </div>
    <div class="widget-content">
        <?php if (empty($prochains_evenements)): ?>
            <div class="empty-widget-message">
                <i class="fas fa-info-circle"></i>
                <p>Aucun événement à venir</p>
            </div>
        <?php else: ?>
            <ul class="events-list">
                <?php foreach ($prochains_evenements as $event): ?>
                    <li class="event-item event-<?= strtolower($event['type_evenement'] ?? 'autre') ?>">
                        <div class="event-date">
                            <?= date('d/m', strtotime($event['date_debut'])) ?>
                        </div>
                        <div class="event-details">
                            <div class="event-title"><?= htmlspecialchars($event['titre']) ?></div>
                            <div class="event-time"><?= date('H:i', strtotime($event['date_debut'])) ?> - <?= date('H:i', strtotime($event['date_fin'])) ?></div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
