<?php
/**
 * agenda.php — Contrôleur principal du module Agenda.
 *
 * Utilise EventRepository pour un filtrage unique et centralisé.
 * Aucun SQL en dur, aucun inline JS/CSS, aucun établissement.json.
 */
ob_start();

require_once __DIR__ . '/../API/core.php';
$pdo = getPDO();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/EventRepository.php';

requireAuth();

$user          = getCurrentUser();
$user_fullname = getUserFullName();
$user_role     = getUserRole();
$user_initials = getUserInitials();

$repo = new EventRepository($pdo);

// ── Paramètres de requête ──
$view          = $_GET['view']  ?? 'month';
$month         = max(1, min(12, intval($_GET['month'] ?? date('m'))));
$year          = intval($_GET['year'] ?? date('Y'));
$date          = (isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'])) ? $_GET['date'] : date('Y-m-d');
$filter_types  = isset($_GET['types']) ? (array) $_GET['types'] : [];

// ── Options de filtrage par rôle (réutilisées partout) ──
$roleOpts = $repo->getUserFilterOptions();

// ── Constantes réutilisées par les vues ──
$month_names    = EventRepository::MONTH_NAMES;
$day_names      = EventRepository::DAY_NAMES;
$day_names_full = EventRepository::DAY_NAMES_FULL;

// ── Calculs calendrier ──
$num_days   = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$first_day  = (int) date('N', mktime(0, 0, 0, $month, 1, $year));
$today_day  = (int) date('j');
$today_month = (int) date('n');
$today_year = (int) date('Y');

// ── Requête centralisée via EventRepository ──
$filterArgs = array_merge($roleOpts, [
    'types' => $filter_types ?: null,
]);

if ($view === 'month') {
    $filterArgs['month'] = $month;
    $filterArgs['year']  = $year;
} elseif ($view === 'day') {
    $filterArgs['date'] = $date;
} elseif ($view === 'week') {
    $dateObj     = new DateTime($date);
    $dow         = (int) $dateObj->format('N');
    $startOfWeek = (clone $dateObj)->modify('-' . ($dow - 1) . ' days');
    $endOfWeek   = (clone $startOfWeek)->modify('+6 days');
    $filterArgs['date_start'] = $startOfWeek->format('Y-m-d');
    $filterArgs['date_end']   = $endOfWeek->format('Y-m-d');
}

// Requête combinée : événements agenda + réunions
$events = $repo->findAllWithReunions($filterArgs);

// Organiser par jour pour la vue mois
$events_by_day = ($view === 'month') ? EventRepository::groupByDay($events) : [];

// Stats rapides
$type_counts = EventRepository::countByType($events);

// Prochains & récents (avec filtrage rôle, incluant réunions)
$upcoming_events    = $repo->findAllWithReunions(array_merge($roleOpts, ['upcoming' => true, 'limit' => 8]));
$past_recent_events = array_slice($repo->findAllWithReunions(array_merge($roleOpts, [
    'date_start' => date('Y-m-d', strtotime('-7 days')),
    'date_end'   => date('Y-m-d', strtotime('-1 day')),
])), 0, 5);

// ── Titre de page ──
$types_evenements = EventRepository::getTypesSimple();

switch ($view) {
    case 'day':
        $pageTitle = "Agenda - Journée du " . date('d/m/Y', strtotime($date));
        break;
    case 'week':
        $d = new DateTime($date);
        $sw = (clone $d)->modify('-' . ((int)$d->format('N') - 1) . ' days');
        $ew = (clone $sw)->modify('+6 days');
        $pageTitle = "Agenda - Semaine du " . $sw->format('d/m') . " au " . $ew->format('d/m/Y');
        break;
    case 'list':
        $pageTitle = "Agenda - Liste des événements";
        break;
    default:
        $pageTitle = "Agenda - " . $month_names[$month] . " " . $year;
}

include 'includes/header.php';
?>

<!-- Navigation -->
<div class="calendar-navigation">
  <div>
    <button class="nav-button" data-nav="prev" aria-label="Précédent"><i class="fas fa-chevron-left"></i></button>
    <button class="nav-button" data-nav="next" aria-label="Suivant"><i class="fas fa-chevron-right"></i></button>
    <button class="today-button" data-nav="today">Aujourd'hui</button>
  </div>
  <h2 class="calendar-title">
    <?php if ($view === 'month'): ?>
      <?= $month_names[$month] . ' ' . $year ?>
    <?php elseif ($view === 'day'): ?>
      <?= date('d', strtotime($date)) . ' ' . $month_names[(int)date('n', strtotime($date))] . ' ' . date('Y', strtotime($date)) ?>
    <?php elseif ($view === 'week'): ?>
      <?php
        $d = new DateTime($date);
        $sw = (clone $d)->modify('-' . ((int)$d->format('N') - 1) . ' days');
        $ew = (clone $sw)->modify('+6 days');
        echo $sw->format('d') . ' - ' . $ew->format('d') . ' ' . $month_names[(int)$sw->format('n')] . ' ' . $sw->format('Y');
      ?>
    <?php elseif ($view === 'list'): ?>
      Liste des événements
    <?php endif; ?>
  </h2>
  <div class="view-toggle" role="tablist">
    <a href="?view=day&date=<?= $date ?>"  class="view-toggle-option <?= $view === 'day'   ? 'active' : '' ?>" role="tab" aria-selected="<?= $view === 'day' ? 'true' : 'false' ?>"><i class="fas fa-calendar-day"></i> Jour</a>
    <a href="?view=week&date=<?= $date ?>" class="view-toggle-option <?= $view === 'week'  ? 'active' : '' ?>" role="tab" aria-selected="<?= $view === 'week' ? 'true' : 'false' ?>"><i class="fas fa-calendar-week"></i> Semaine</a>
    <a href="?view=month&month=<?= $month ?>&year=<?= $year ?>" class="view-toggle-option <?= $view === 'month' ? 'active' : '' ?>" role="tab" aria-selected="<?= $view === 'month' ? 'true' : 'false' ?>"><i class="fas fa-calendar-alt"></i> Mois</a>
    <a href="?view=list&month=<?= $month ?>&year=<?= $year ?>"  class="view-toggle-option <?= $view === 'list'  ? 'active' : '' ?>" role="tab" aria-selected="<?= $view === 'list' ? 'true' : 'false' ?>"><i class="fas fa-list"></i> Liste</a>
  </div>
</div>

<!-- Stats rapides -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value"><?= count($events) ?></div>
        <div class="stat-label">Événements ce mois</div>
    </div>
    <?php foreach (array_slice($type_counts, 0, 4, true) as $type => $cnt): ?>
    <div class="stat-card">
        <div class="stat-value"><?= $cnt ?></div>
        <div class="stat-label"><?= htmlspecialchars($types_evenements[$type] ?? ucfirst($type)) ?></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Calendar Container -->
<div class="calendar-container">
  <?php if ($view === 'month'): ?>
    <div class="calendar">
      <div class="calendar-header">
        <?php foreach ($day_names_full as $d): ?>
          <div class="calendar-header-day"><?= $d ?></div>
        <?php endforeach; ?>
      </div>
      <div class="calendar-body">
        <?php
        // Jours du mois précédent
        $prevM = $month > 1 ? $month - 1 : 12;
        $prevY = $month > 1 ? $year : $year - 1;
        $prevDays = cal_days_in_month(CAL_GREGORIAN, $prevM, $prevY);
        for ($i = 1; $i < $first_day; $i++) {
            $dn = $prevDays - $first_day + $i + 1;
            echo '<div class="calendar-day other-month"><div class="calendar-day-number">' . $dn . '</div></div>';
        }
        // Jours courants
        for ($d = 1; $d <= $num_days; $d++) {
            $ds = sprintf('%04d-%02d-%02d', $year, $month, $d);
            $tc = ($d == $today_day && $month == $today_month && $year == $today_year) ? ' today' : '';
            echo '<div class="calendar-day' . $tc . '" data-date="' . $ds . '">';
            echo '<div class="calendar-day-number">' . $d . '</div>';
            if (!empty($events_by_day[$d])) {
                echo '<div class="calendar-day-events">';
                foreach ($events_by_day[$d] as $ev) {
                    $eTime = date('H:i', strtotime($ev['date_debut']));
                    $eCls  = 'event-' . strtolower($ev['type_evenement']);
                    if ($ev['statut'] === 'annulé')  $eCls .= ' event-cancelled';
                    elseif ($ev['statut'] === 'reporté') $eCls .= ' event-postponed';
                    $evId = is_int($ev['id']) ? $ev['id'] : 0;
                    $isReunion = !empty($ev['is_reunion']);
                    $dataAttr = $isReunion
                        ? 'data-reunion-id="' . (int)$ev['reunion_id'] . '"'
                        : 'data-event-id="' . $evId . '"';
                    echo '<div class="calendar-event ' . $eCls . '" ' . $dataAttr . '>';
                    if ($isReunion) echo '<i class="fas fa-users" style="font-size:9px;margin-right:3px"></i>';
                    echo '<span class="event-time">' . $eTime . '</span> ' . htmlspecialchars($ev['titre']);
                    echo '</div>';
                }
                echo '</div>';
            }
            echo '</div>';
        }
        // Jours mois suivant
        $shown = $first_day - 1 + $num_days;
        $rem   = 7 - ($shown % 7);
        if ($rem < 7) {
            for ($d = 1; $d <= $rem; $d++) {
                echo '<div class="calendar-day other-month"><div class="calendar-day-number">' . $d . '</div></div>';
            }
        }
        ?>
      </div>
    </div>
  <?php elseif ($view === 'day'): ?>
    <?php include 'views/day_view.php'; ?>
  <?php elseif ($view === 'week'): ?>
    <?php include 'views/week_view.php'; ?>
  <?php elseif ($view === 'list'): ?>
    <?php include 'views/list_view.php'; ?>
  <?php endif; ?>
</div>

<?php if ($view === 'month'): ?>
<!-- Panneaux prochains / passés -->
<div class="upcoming-panels">
    <div class="upcoming-card">
        <h3 class="upcoming-card-title"><i class="fas fa-arrow-right"></i> Prochains événements</h3>
        <?php if (empty($upcoming_events)): ?>
            <p class="upcoming-empty">Aucun événement à venir</p>
        <?php else: ?>
            <?php foreach ($upcoming_events as $ue):
                $isReunion = !empty($ue['is_reunion']);
                $detailUrl = $isReunion
                    ? '../reunions/detail.php?id=' . (int)$ue['reunion_id']
                    : 'details_evenement.php?id=' . (int)$ue['id'];
            ?>
            <a href="<?= $detailUrl ?>" class="upcoming-item">
                <div class="upcoming-item-date">
                    <span class="upcoming-day"><?= date('d', strtotime($ue['date_debut'])) ?></span>
                    <span class="upcoming-month"><?= $month_names[(int)date('n', strtotime($ue['date_debut']))] ?></span>
                </div>
                <div class="upcoming-item-info">
                    <div class="upcoming-item-title"><?php if ($isReunion): ?><i class="fas fa-users" style="font-size:11px;margin-right:4px;color:#ff9500"></i><?php endif; ?><?= htmlspecialchars($ue['titre']) ?></div>
                    <div class="upcoming-item-meta">
                        <i class="far fa-clock"></i> <?= date('H:i', strtotime($ue['date_debut'])) ?>
                        <?php if (!empty($ue['lieu'])): ?> · <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($ue['lieu']) ?><?php endif; ?>
                    </div>
                </div>
                <span class="upcoming-type-badge"><?= htmlspecialchars($isReunion ? ($ue['type_personnalise'] ?? 'Réunion') : ($types_evenements[$ue['type_evenement']] ?? ucfirst($ue['type_evenement']))) ?></span>
            </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <div class="upcoming-card">
        <h3 class="upcoming-card-title upcoming-card-title--past"><i class="fas fa-history"></i> Événements récents</h3>
        <?php if (empty($past_recent_events)): ?>
            <p class="upcoming-empty">Aucun événement récent</p>
        <?php else: ?>
            <?php foreach ($past_recent_events as $pe):
                $isReunion = !empty($pe['is_reunion']);
                $detailUrl = $isReunion
                    ? '../reunions/detail.php?id=' . (int)$pe['reunion_id']
                    : 'details_evenement.php?id=' . (int)$pe['id'];
            ?>
            <a href="<?= $detailUrl ?>" class="upcoming-item upcoming-item--past">
                <div class="upcoming-item-date">
                    <span class="upcoming-day"><?= date('d', strtotime($pe['date_debut'])) ?></span>
                    <span class="upcoming-month"><?= $month_names[(int)date('n', strtotime($pe['date_debut']))] ?></span>
                </div>
                <div class="upcoming-item-info">
                    <div class="upcoming-item-title"><?php if ($isReunion): ?><i class="fas fa-users" style="font-size:11px;margin-right:4px;color:#ff9500"></i><?php endif; ?><?= htmlspecialchars($pe['titre']) ?></div>
                    <div class="upcoming-item-meta">
                        <i class="far fa-clock"></i> <?= date('H:i', strtotime($pe['date_debut'])) ?>
                        <?php if (!empty($pe['lieu'])): ?> · <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($pe['lieu']) ?><?php endif; ?>
                    </div>
                </div>
                <span class="upcoming-type-badge upcoming-type-badge--past">Passé</span>
            </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php
include 'includes/footer.php';
ob_end_flush();
?>