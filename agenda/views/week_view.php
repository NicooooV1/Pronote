<?php
/**
 * Vue hebdomadaire — pure affichage.
 * Reçoit $events et $date du contrôleur (agenda.php).
 */

$dateObj     = new DateTime($date);
$dow         = (int) $dateObj->format('N');
$startOfWeek = (clone $dateObj)->modify('-' . ($dow - 1) . ' days');

// Organiser les événements par date
$eventsByDate = EventRepository::groupByDate($events);

// Construire les 7 jours
$weekdays = [];
$cur = clone $startOfWeek;
for ($i = 0; $i < 7; $i++) {
    $weekdays[] = [
        'date'       => $cur->format('Y-m-d'),
        'day_name'   => $day_names_full[$i],
        'day_number' => $cur->format('d'),
        'month'      => $cur->format('m'),
        'is_today'   => ($cur->format('Y-m-d') === date('Y-m-d')),
    ];
    $cur->modify('+1 day');
}
?>

<div class="week-view">
    <div class="week-header">
        <div class="week-header-spacer"></div>
        <div class="week-header-days">
            <?php foreach ($weekdays as $wd): ?>
                <div class="week-day-header<?= $wd['is_today'] ? ' today' : '' ?>" data-date="<?= $wd['date'] ?>">
                    <div class="week-day-name"><?= $wd['day_name'] ?></div>
                    <div class="week-day-date"><?= $wd['day_number'] ?>/<?= $wd['month'] ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="week-body">
        <div class="week-timeline">
            <?php for ($h = 8; $h <= 18; $h++): ?>
                <div class="timeline-hour"><?= sprintf('%02d:00', $h) ?></div>
            <?php endfor; ?>
        </div>

        <div class="week-grid">
            <?php foreach ($weekdays as $wd): ?>
                <div class="week-day-column<?= $wd['is_today'] ? ' today' : '' ?>" data-date="<?= $wd['date'] ?>">
                    <?php if (!empty($eventsByDate[$wd['date']])): ?>
                        <?php foreach ($eventsByDate[$wd['date']] as $ev):
                            $debut = new DateTime($ev['date_debut']);
                            $fin   = new DateTime($ev['date_fin']);
                            $eCls  = 'event-' . strtolower($ev['type_evenement']);
                            if ($ev['statut'] === 'annulé')  $eCls .= ' event-cancelled';
                            elseif ($ev['statut'] === 'reporté') $eCls .= ' event-postponed';

                            // Pixel positioning (60px per hour)
                            $sH = (int)$debut->format('G'); $sM = (int)$debut->format('i');
                            $eH = (int)$fin->format('G');   $eM = (int)$fin->format('i');
                            $topPx    = max(0, ($sH - 8) * 60 + $sM);
                            $heightPx = max(18, ($eH - $sH) * 60 + ($eM - $sM));
                        ?>
                        <div class="week-event <?= $eCls ?>"
                             style="top:<?= $topPx ?>px; height:<?= $heightPx ?>px;"
                             data-event-id="<?= (int)$ev['id'] ?>">
                            <div class="event-time"><?= $debut->format('H:i') ?></div>
                            <div class="event-title">
                                <a href="details_evenement.php?id=<?= (int)$ev['id'] ?>"><?= htmlspecialchars($ev['titre']) ?></a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>