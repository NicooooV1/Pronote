<?php
/**
 * Widget : Prochains événements
 * Variables disponibles : $data (tableau retourné par AgendaWidgetProvider::getData)
 */
$events = $data['events'] ?? [];
?>
<?php if (empty($events)): ?>
    <p class="widget-empty">Aucun événement à venir.</p>
<?php else: ?>
    <ul class="widget-list">
        <?php foreach ($events as $event): ?>
        <li class="widget-list-item">
            <span class="widget-list-icon"><i class="fas fa-calendar-day"></i></span>
            <div class="widget-list-content">
                <div class="widget-list-title"><?= htmlspecialchars($event['titre']) ?></div>
                <div class="widget-list-meta">
                    <?= htmlspecialchars(date('d/m/Y H:i', strtotime($event['date_debut']))) ?>
                    <?php if (!empty($event['lieu'])): ?>
                        &mdash; <?= htmlspecialchars($event['lieu']) ?>
                    <?php endif; ?>
                </div>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
    <a href="agenda/agenda.php" class="widget-link">Voir l'agenda &rarr;</a>
<?php endif; ?>
