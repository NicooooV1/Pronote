<?php
/**
 * views/calendar_view.php — Vue calendrier des absences
 * Refactorisé : inline CSS (~120 lignes) externalisé vers absences.css.
 * strftime() remplacé par IntlDateFormatter / date().
 * Variables attendues : $absences, $date_debut, $date_fin, $classe, $justifie.
 */

$jours_semaine = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];

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
                    <div class="calendar-absence-item <?= $absence['justifie'] ? 'justified' : '' ?>"
                         title="<?= htmlspecialchars($absence['prenom'] . ' ' . $absence['nom'] . ' - ' . (new DateTime($absence['date_debut']))->format('H:i') . ' à ' . (new DateTime($absence['date_fin']))->format('H:i')) ?>">
                        <?php if (isAdmin() || isVieScolaire() || isTeacher()): ?>
                            <?= htmlspecialchars(substr($absence['prenom'], 0, 1) . '. ' . $absence['nom']) ?>
                        <?php else: ?>
                            <?= (new DateTime($absence['date_debut']))->format('H:i') ?> - <?= (new DateTime($absence['date_fin']))->format('H:i') ?>
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
