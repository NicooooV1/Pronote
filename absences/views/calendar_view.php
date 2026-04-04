<?php
/**
 * views/calendar_view.php — Vue calendrier des absences
 * Refactorisé : inline CSS (~120 lignes) externalisé vers absences.css.
 * strftime() remplacé par IntlDateFormatter / date().
 * Variables attendues : $absences, $date_debut, $date_fin, $classe, $justifie.
 *
 * v2: Color coding by type, rich tooltips, monthly mini-résumé.
 */

$jours_semaine = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];

// Type → CSS modifier class mapping
$typeColors = [
    'cours'        => 'type-cours',
    'demi-journee' => 'type-demi',
    'journee'      => 'type-journee',
];

$debut_mois = new DateTime(date('Y-m-01', strtotime($date_debut)));
$debut_calendrier = clone $debut_mois;
// Trouver le lundi précédent ou actuel
$dow = (int) $debut_calendrier->format('N'); // 1=lun..7=dim
if ($dow !== 1) {
    $debut_calendrier->modify('last monday');
}

$fin_mois = new DateTime(date('Y-m-t', strtotime($date_debut)));
$fin_calendrier = clone $fin_mois;
if ((int) $fin_calendrier->format('N') !== 7) {
    $fin_calendrier->modify('next sunday');
}

// Nom du mois en français (sans strftime)
$nomsMois = [
    1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
    5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
    9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
];
$moisAnnee = $nomsMois[(int) $debut_mois->format('n')] . ' ' . $debut_mois->format('Y');

// Organiser les absences par jour
$absences_par_jour = [];
foreach ($absences as $absence) {
    $debut = new DateTime($absence['date_debut']);
    $fin = new DateTime($absence['date_fin']);
    $jour_courant = clone $debut;
    while ($jour_courant <= $fin) {
        $key = $jour_courant->format('Y-m-d');
        $absences_par_jour[$key][] = $absence;
        $jour_courant->modify('+1 day');
    }
}

// ── Monthly mini-résumé stats ─────────────────────────────────────────
$moisPrefix = $debut_mois->format('Y-m');
$monthlyStats = ['total' => 0, 'justifiees' => 0, 'non_justifiees' => 0, 'eleves_uniques' => [], 'by_type' => []];
foreach ($absences as $a) {
    // Only count absences that overlap with the displayed month
    $aDebut = substr($a['date_debut'], 0, 7);
    $aFin   = substr($a['date_fin'], 0, 7);
    if ($aDebut > $moisPrefix && $aFin > $moisPrefix) continue;
    if ($aDebut < $moisPrefix && $aFin < $moisPrefix) continue;

    $monthlyStats['total']++;
    if (!empty($a['justifie'])) {
        $monthlyStats['justifiees']++;
    } else {
        $monthlyStats['non_justifiees']++;
    }
    if (!empty($a['id_eleve'])) {
        $monthlyStats['eleves_uniques'][$a['id_eleve']] = true;
    }
    $type = $a['type_absence'] ?? 'autre';
    $monthlyStats['by_type'][$type] = ($monthlyStats['by_type'][$type] ?? 0) + 1;
}
$monthlyStats['nb_eleves'] = count($monthlyStats['eleves_uniques']);

// Générer les semaines
$semaines = [];
$jour_courant = clone $debut_calendrier;
while ($jour_courant <= $fin_calendrier) {
    $semaine = [];
    for ($i = 0; $i < 7; $i++) {
        $key = $jour_courant->format('Y-m-d');
        $semaine[] = [
            'date'     => clone $jour_courant,
            'in_range' => $jour_courant->format('Y-m') === $debut_mois->format('Y-m'),
            'absences' => $absences_par_jour[$key] ?? []
        ];
        $jour_courant->modify('+1 day');
    }
    $semaines[] = $semaine;
}
?>

<!-- Mini-résumé mensuel -->
<div class="calendar-summary">
    <div class="calendar-summary-stat">
        <span class="summary-value"><?= $monthlyStats['total'] ?></span>
        <span class="summary-label">absence<?= $monthlyStats['total'] > 1 ? 's' : '' ?></span>
    </div>
    <div class="calendar-summary-stat summary-justified">
        <span class="summary-value"><?= $monthlyStats['justifiees'] ?></span>
        <span class="summary-label">justifiée<?= $monthlyStats['justifiees'] > 1 ? 's' : '' ?></span>
    </div>
    <div class="calendar-summary-stat summary-unjustified">
        <span class="summary-value"><?= $monthlyStats['non_justifiees'] ?></span>
        <span class="summary-label">non justifiée<?= $monthlyStats['non_justifiees'] > 1 ? 's' : '' ?></span>
    </div>
    <div class="calendar-summary-stat">
        <span class="summary-value"><?= $monthlyStats['nb_eleves'] ?></span>
        <span class="summary-label">élève<?= $monthlyStats['nb_eleves'] > 1 ? 's' : '' ?> concerné<?= $monthlyStats['nb_eleves'] > 1 ? 's' : '' ?></span>
    </div>
    <?php if (!empty($monthlyStats['by_type'])): ?>
    <div class="calendar-summary-types">
        <?php foreach ($monthlyStats['by_type'] as $type => $count): ?>
        <span class="summary-type-badge <?= $typeColors[$type] ?? 'type-autre' ?>"><?= AbsenceHelper::typeLabel($type) ?>: <?= $count ?></span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Légende des couleurs -->
<div class="calendar-legend">
    <span class="legend-item"><span class="legend-dot type-cours"></span> Cours</span>
    <span class="legend-item"><span class="legend-dot type-demi"></span> Demi-journée</span>
    <span class="legend-item"><span class="legend-dot type-journee"></span> Journée</span>
    <span class="legend-item"><span class="legend-dot justified"></span> Justifiée</span>
</div>

<div class="calendar-container">
    <div class="calendar-header">
        <div class="calendar-navigation">
            <a href="?view=calendar&date_debut=<?= date('Y-m-d', strtotime($date_debut . ' -1 month')) ?>&date_fin=<?= date('Y-m-d', strtotime($date_fin . ' -1 month')) ?>&classe=<?= urlencode($classe) ?>&justifie=<?= $justifie ?>" class="btn btn-secondary">
                <i class="fas fa-chevron-left"></i> Mois précédent
            </a>
            <h2><?= $moisAnnee ?></h2>
            <a href="?view=calendar&date_debut=<?= date('Y-m-d', strtotime($date_debut . ' +1 month')) ?>&date_fin=<?= date('Y-m-d', strtotime($date_fin . ' +1 month')) ?>&classe=<?= urlencode($classe) ?>&justifie=<?= $justifie ?>" class="btn btn-secondary">
                Mois suivant <i class="fas fa-chevron-right"></i>
            </a>
        </div>
        <div class="calendar-day-headers">
            <?php foreach ($jours_semaine as $jour): ?>
                <div class="calendar-day-header"><?= $jour ?></div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="calendar-body">
        <?php foreach ($semaines as $semaine): ?>
        <div class="calendar-week">
            <?php foreach ($semaine as $jour): ?>
            <?php
                $is_today      = $jour['date']->format('Y-m-d') === date('Y-m-d');
                $is_weekend    = in_array($jour['date']->format('N'), ['6', '7']);
                $has_absences  = !empty($jour['absences']);
            ?>
            <div class="calendar-day <?= $is_weekend ? 'weekend' : '' ?> <?= $is_today ? 'today' : '' ?> <?= $jour['in_range'] ? '' : 'out-of-range' ?> <?= $has_absences ? 'has-absences' : '' ?>">
                <div class="calendar-day-number"><?= $jour['date']->format('d') ?></div>

                <?php if ($has_absences): ?>
                <div class="calendar-absences">
                    <?php foreach (array_slice($jour['absences'], 0, 3) as $absence): ?>
                    <?php
                        $absType    = $absence['type_absence'] ?? 'autre';
                        $typeCss    = $typeColors[$absType] ?? 'type-autre';
                        $justCss    = !empty($absence['justifie']) ? 'justified' : '';
                        $motifLabel = AbsenceHelper::motifLabel($absence['motif'] ?? '');
                        $typeLabel  = AbsenceHelper::typeLabel($absType);
                        $heureD     = (new DateTime($absence['date_debut']))->format('H:i');
                        $heureF     = (new DateTime($absence['date_fin']))->format('H:i');
                        $tooltipParts = [
                            htmlspecialchars($absence['prenom'] . ' ' . $absence['nom']),
                            $typeLabel,
                            $heureD . ' → ' . $heureF,
                        ];
                        if ($motifLabel !== 'Non spécifié') {
                            $tooltipParts[] = 'Motif : ' . $motifLabel;
                        }
                        $tooltipParts[] = !empty($absence['justifie']) ? '✓ Justifiée' : '✗ Non justifiée';
                        $tooltip = implode("\n", $tooltipParts);
                    ?>
                    <div class="calendar-absence-item <?= $typeCss ?> <?= $justCss ?>"
                         title="<?= htmlspecialchars($tooltip) ?>"
                         data-type="<?= htmlspecialchars($absType) ?>">
                        <?php if (isAdmin() || isVieScolaire() || isTeacher()): ?>
                            <span class="absence-type-dot <?= $typeCss ?>"></span>
                            <?= htmlspecialchars(substr($absence['prenom'], 0, 1) . '. ' . $absence['nom']) ?>
                        <?php else: ?>
                            <span class="absence-type-dot <?= $typeCss ?>"></span>
                            <?= $heureD ?> - <?= $heureF ?>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>

                    <?php if (count($jour['absences']) > 3): ?>
                    <div class="calendar-more-absences">+<?= count($jour['absences']) - 3 ?> autres</div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if (canManageAbsences() && $jour['in_range']): ?>
                <a href="ajouter_absence.php?date=<?= $jour['date']->format('Y-m-d') ?>" class="calendar-add-absence" title="Ajouter une absence">
                    <i class="fas fa-plus"></i>
                </a>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
