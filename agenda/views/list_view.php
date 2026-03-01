<?php
/**
 * Vue liste — pure affichage.
 * Reçoit $events du contrôleur (agenda.php).
 */

$today    = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$nextWeek = date('Y-m-d', strtotime('+1 week'));

$periods = [
    'today'    => ['label' => "Aujourd'hui", 'events' => []],
    'tomorrow' => ['label' => 'Demain',            'events' => []],
    'week'     => ['label' => 'Cette semaine',     'events' => []],
    'future'   => ['label' => 'Prochainement',     'events' => []],
    'past'     => ['label' => 'Événements passés', 'events' => []],
];

foreach ($events as $ev) {
    $d = date('Y-m-d', strtotime($ev['date_debut']));
    if ($d < $today)           $periods['past']['events'][]     = $ev;
    elseif ($d === $today)     $periods['today']['events'][]    = $ev;
    elseif ($d === $tomorrow)  $periods['tomorrow']['events'][] = $ev;
    elseif ($d <= $nextWeek)   $periods['week']['events'][]     = $ev;
    else                       $periods['future']['events'][]   = $ev;
}

$hasAny = !empty($events);
?>

<div class="list-view">
    <div class="list-header">
        <h2>Liste des événements</h2>
    </div>

    <div class="list-content">
        <?php if ($hasAny): ?>
            <?php foreach ($periods as $key => $period): ?>
                <?php if (!empty($period['events'])): ?>
                <div class="list-section">
                    <div class="list-section-header"><h3><?= $period['label'] ?></h3></div>
                    <div class="events-list">
                        <?php foreach ($period['events'] as $ev):
                            $debut = new DateTime($ev['date_debut']);
                            $fin   = new DateTime($ev['date_fin']);
                            $eCls  = 'event-' . strtolower($ev['type_evenement']);
                            if ($ev['statut'] === 'annulé')  $eCls .= ' event-cancelled';
                            elseif ($ev['statut'] === 'reporté') $eCls .= ' event-postponed';
                        ?>
                        <div class="event-list-item <?= $eCls ?>">
                            <div class="event-list-color"></div>
                            <div class="event-list-date">
                                <span><?= $debut->format('d') ?></span>
                                <?= $debut->format('M') ?>
                            </div>
                            <div class="event-list-details">
                                <div class="event-list-title">
                                    <a href="details_evenement.php?id=<?= (int)$ev['id'] ?>"><?= htmlspecialchars($ev['titre']) ?></a>
                                </div>
                                <div class="event-list-meta">
                                    <span><i class="far fa-clock"></i> <?= $debut->format('H:i') ?> - <?= $fin->format('H:i') ?></span>
                                    <?php if (!empty($ev['lieu'])): ?>
                                    <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($ev['lieu']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-events-container">
                <div class="no-events-message">
                    <i class="fas fa-calendar"></i>
                    <p>Aucun événement à afficher.</p>
                    <?php if (in_array($user_role, ['professeur', 'administrateur', 'vie_scolaire'])): ?>
                        <a href="ajouter_evenement.php" class="create-button"><i class="fas fa-plus"></i> Ajouter un événement</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>