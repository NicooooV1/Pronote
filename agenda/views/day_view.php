<?php
/**
 * Vue journalière — pure affichage.
 * Reçoit $events et $date du contrôleur (agenda.php).
 */

$date_obj       = new DateTime($date);
$formatted_date = $date_obj->format('l j F Y');
$is_today       = ($date === date('Y-m-d'));
$day_events     = $events; // already filtered by controller
?>

<div class="day-view">
    <div class="day-header">
        <h2 class="day-title">
            <?= $formatted_date ?>
            <?php if ($is_today): ?>
                <span class="today-badge">Aujourd'hui</span>
            <?php endif; ?>
        </h2>
    </div>

    <div class="day-body">
        <div class="day-timeline">
            <?php for ($h = 8; $h <= 18; $h++): ?>
                <div class="timeline-hour"><?= sprintf('%02d:00', $h) ?></div>
            <?php endfor; ?>
        </div>

        <div class="day-events">
            <?php if (!empty($day_events)): ?>
                <?php foreach ($day_events as $ev):
                    $debut = new DateTime($ev['date_debut']);
                    $fin   = new DateTime($ev['date_fin']);
                    $eCls  = 'event-' . strtolower($ev['type_evenement']);
                    if ($ev['statut'] === 'annulé')  $eCls .= ' event-cancelled';
                    elseif ($ev['statut'] === 'reporté') $eCls .= ' event-postponed';

                    // Position en pixels (60px par heure dans .timeline-hour)
                    $startH = (int) $debut->format('G');
                    $startM = (int) $debut->format('i');
                    $endH   = (int) $fin->format('G');
                    $endM   = (int) $fin->format('i');

                    $topPx    = max(0, ($startH - 8) * 60 + $startM);
                    $heightPx = ($endH - $startH) * 60 + ($endM - $startM);
                    if ($startH < 8)  { $heightPx -= (8 - $startH) * 60 - $startM; $topPx = 0; }
                    if ($endH > 18)   $heightPx -= ($endH - 18) * 60 + $endM;
                    $heightPx = max(20, $heightPx);
                ?>
                <div class="day-event <?= $eCls ?>"
                     style="top: <?= $topPx ?>px; height: <?= $heightPx ?>px;"
                     data-event-id="<?= (int)$ev['id'] ?>">
                    <div class="event-time"><?= $debut->format('H:i') ?> - <?= $fin->format('H:i') ?></div>
                    <div class="event-title">
                        <a href="details_evenement.php?id=<?= (int)$ev['id'] ?>"><?= htmlspecialchars($ev['titre']) ?></a>
                    </div>
                    <?php if (!empty($ev['lieu'])): ?>
                        <div class="event-location"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($ev['lieu']) ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-events">
                    <div class="no-events-message">
                        <i class="fas fa-calendar-day"></i>
                        <p>Aucun événement prévu pour cette journée.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>