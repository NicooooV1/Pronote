<?php
/**
 * views/stats_view.php — Vue statistiques des absences
 * Refactorisé : variables undefined corrigées, CSS externalisé → absences.css,
 * calculs unifiés (suppression des doublons), Chart.js conservé.
 * Variables attendues : $absences, $user_role (depuis absences.php).
 */

// ─── Calculs statistiques ───────────────────────────────────────
$total_absences       = count($absences);
$total_justifiees     = 0;
$total_non_justifiees = 0;
$total_cours          = 0;
$total_demi_journee   = 0;
$total_journee        = 0;
$duree_totale_minutes = 0;
$absences_par_jour    = [];
$absences_par_mois    = [];
$eleves_absents       = [];
$absences_par_classe  = [];

foreach ($absences as $absence) {
    // Justification
    if ($absence['justifie']) {
        $total_justifiees++;
    } else {
        $total_non_justifiees++;
    }

    // Type
    switch ($absence['type_absence']) {
        case 'cours':        $total_cours++; break;
        case 'demi-journee': $total_demi_journee++; break;
        case 'journee':      $total_journee++; break;
    }

    // Durée
    $debut = new DateTime($absence['date_debut']);
    $fin   = new DateTime($absence['date_fin']);
    $duree = $debut->diff($fin);
    $duree_minutes = ($duree->days * 24 * 60) + ($duree->h * 60) + $duree->i;
    $duree_totale_minutes += $duree_minutes;

    // Par jour
    $jour = $debut->format('Y-m-d');
    $absences_par_jour[$jour] = ($absences_par_jour[$jour] ?? 0) + 1;

    // Par mois
    $mois = $debut->format('Y-m');
    $absences_par_mois[$mois] = ($absences_par_mois[$mois] ?? 0) + 1;

    // Par élève
    $id_eleve = $absence['id_eleve'];
    if (!isset($eleves_absents[$id_eleve])) {
        $eleves_absents[$id_eleve] = [
            'nom'    => $absence['nom'],
            'prenom' => $absence['prenom'],
            'classe' => $absence['classe'],
            'count'  => 0,
            'duree'  => 0
        ];
    }
    $eleves_absents[$id_eleve]['count']++;
    $eleves_absents[$id_eleve]['duree'] += $duree_minutes;

    // Par classe
    if (isAdmin() || isVieScolaire() || isTeacher()) {
        $classe_name = $absence['classe'];
        $absences_par_classe[$classe_name] = ($absences_par_classe[$classe_name] ?? 0) + 1;
    }
}

// Trier élèves par nombre d'absences
uasort($eleves_absents, fn($a, $b) => $b['count'] - $a['count']);
$top_eleves = array_slice($eleves_absents, 0, 10, true);

// Labels mois formatés
$absences_par_mois_formatte = [];
foreach ($absences_par_mois as $m => $count) {
    $nomsMois = [1=>'Jan',2=>'Fév',3=>'Mar',4=>'Avr',5=>'Mai',6=>'Juin',7=>'Juil',8=>'Aoû',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Déc'];
    $dt = new DateTime($m . '-01');
    $absences_par_mois_formatte[$nomsMois[(int)$dt->format('n')] . ' ' . $dt->format('Y')] = $count;
}

// Pourcentages
$pct_justifiees   = $total_absences > 0 ? round(($total_justifiees / $total_absences) * 100) : 0;
$pct_cours        = $total_absences > 0 ? round(($total_cours / $total_absences) * 100) : 0;
$pct_demi_journee = $total_absences > 0 ? round(($total_demi_journee / $total_absences) * 100) : 0;
$pct_journee      = $total_absences > 0 ? round(($total_journee / $total_absences) * 100) : 0;

// Durée formatée
$duree_heures  = floor($duree_totale_minutes / 60);
$duree_minutes = $duree_totale_minutes % 60;
$jours_perdus  = round($duree_totale_minutes / (60 * 7), 1); // ~7h de cours/jour

// Stats par type (pour les cards)
$statsByType = [];
if ($total_cours > 0) $statsByType['cours'] = $total_cours;
if ($total_demi_journee > 0) $statsByType['demi-journee'] = $total_demi_journee;
if ($total_journee > 0) $statsByType['journee'] = $total_journee;

// Stats par classe triées (descending)
arsort($absences_par_classe);
?>

<div class="stats-container">
    <!-- Résumé -->
    <div class="stats-section">
        <h2>Résumé des absences</h2>
        <div class="stats-cards">
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-calendar-xmark"></i></div>
                <div class="stats-info">
                    <div class="stats-label">Total absences</div>
                    <div class="stats-value"><?= $total_absences ?></div>
                </div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stats-info">
                    <div class="stats-label">Justifiées</div>
                    <div class="stats-value"><?= $total_justifiees ?></div>
                    <div class="stats-percent"><?= $pct_justifiees ?>%</div>
                </div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-times-circle"></i></div>
                <div class="stats-info">
                    <div class="stats-label">Non justifiées</div>
                    <div class="stats-value"><?= $total_non_justifiees ?></div>
                    <div class="stats-percent"><?= 100 - $pct_justifiees ?>%</div>
                </div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-clock"></i></div>
                <div class="stats-info">
                    <div class="stats-label">Temps perdu</div>
                    <div class="stats-value"><?= $duree_heures ?>h <?= $duree_minutes ?>min</div>
                    <div class="stats-percent">(env. <?= $jours_perdus ?> jours)</div>
                </div>
            </div>
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-users"></i></div>
                <div class="stats-info">
                    <div class="stats-label">Élèves concernés</div>
                    <div class="stats-value"><?= count($eleves_absents) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Répartition par type -->
    <div class="stats-section">
        <h2>Répartition par type d'absence</h2>
        <div class="stats-cards">
            <?php foreach ($statsByType as $type => $count): ?>
            <div class="stats-card">
                <div class="stats-icon">
                    <?php if ($type === 'cours'): ?><i class="fas fa-book"></i>
                    <?php elseif ($type === 'demi-journee'): ?><i class="fas fa-sun"></i>
                    <?php else: ?><i class="fas fa-calendar-day"></i>
                    <?php endif; ?>
                </div>
                <div class="stats-info">
                    <div class="stats-label"><?= AbsenceHelper::typeLabel($type) ?></div>
                    <div class="stats-value"><?= $count ?></div>
                    <div class="stats-percent"><?= $total_absences > 0 ? round(($count / $total_absences) * 100) : 0 ?>%</div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Graphiques -->
    <div class="stats-section">
        <h2>Analyse graphique</h2>
        <div class="stats-charts">
            <div class="stats-chart">
                <h3>Répartition par type</h3>
                <canvas id="typeChart"></canvas>
            </div>
            <div class="stats-chart">
                <h3>Évolution des absences</h3>
                <canvas id="evolutionChart"></canvas>
            </div>
            <?php if (isAdmin() || isVieScolaire() || isTeacher()): ?>
            <div class="stats-chart">
                <h3>Absences par classe</h3>
                <canvas id="classeChart"></canvas>
            </div>
            <div class="stats-chart">
                <h3>Top des élèves absents</h3>
                <canvas id="elevesChart"></canvas>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ((isAdmin() || isVieScolaire()) && !empty($absences_par_classe)): ?>
    <!-- Répartition par classe -->
    <div class="stats-section">
        <h2>Répartition par classe</h2>
        <div class="stats-cards">
            <?php foreach (array_slice($absences_par_classe, 0, 8, true) as $c => $count): ?>
            <div class="stats-card">
                <div class="stats-icon"><i class="fas fa-users"></i></div>
                <div class="stats-info">
                    <div class="stats-label"><?= htmlspecialchars($c) ?></div>
                    <div class="stats-value"><?= $count ?></div>
                    <div class="stats-percent"><?= $total_absences > 0 ? round(($count / $total_absences) * 100) : 0 ?>%</div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ((isAdmin() || isVieScolaire() || isTeacher()) && !empty($eleves_absents)): ?>
    <!-- Tableau des élèves les plus absents -->
    <div class="stats-section">
        <h2>Élèves les plus absents</h2>
        <div class="stats-table">
            <table>
                <thead>
                    <tr>
                        <th>Élève</th>
                        <th>Classe</th>
                        <th>Absences</th>
                        <th>Durée totale</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($eleves_absents, 0, 15, true) as $id_e => $eleve): ?>
                    <tr>
                        <td><?= htmlspecialchars($eleve['prenom'] . ' ' . $eleve['nom']) ?></td>
                        <td><?= htmlspecialchars($eleve['classe']) ?></td>
                        <td><?= $eleve['count'] ?></td>
                        <td><?= AbsenceHelper::formatDuration($eleve['duree']) ?></td>
                        <td>
                            <a href="absences.php?eleve=<?= $id_e ?>" class="btn-icon" title="Voir les absences"><i class="fas fa-eye"></i></a>
                            <?php if (canManageAbsences()): ?>
                            <a href="ajouter_absence.php?eleve=<?= $id_e ?>" class="btn-icon" title="Ajouter une absence"><i class="fas fa-plus"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const colors = {
    primary: '#009b72',
    secondary: '#e74c3c',
    tertiary: '#f39c12',
    quaternary: '#3498db',
    bg: [
        'rgba(0,155,114,0.7)','rgba(231,76,60,0.7)','rgba(243,156,18,0.7)',
        'rgba(52,152,219,0.7)','rgba(155,89,182,0.7)','rgba(22,160,133,0.7)',
        'rgba(192,57,43,0.7)','rgba(211,84,0,0.7)','rgba(41,128,185,0.7)',
        'rgba(142,68,173,0.7)'
    ]
};

// Type doughnut
new Chart(document.getElementById('typeChart').getContext('2d'), {
    type: 'doughnut',
    data: {
        labels: ['Cours', 'Demi-journée', 'Journée'],
        datasets: [{
            data: [<?= $total_cours ?>, <?= $total_demi_journee ?>, <?= $total_journee ?>],
            backgroundColor: [colors.primary, colors.tertiary, colors.secondary],
            borderWidth: 0
        }]
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
});

// Évolution
new Chart(document.getElementById('evolutionChart').getContext('2d'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_keys($absences_par_mois_formatte)) ?>,
        datasets: [{
            label: "Nombre d'absences",
            data: <?= json_encode(array_values($absences_par_mois_formatte)) ?>,
            borderColor: colors.primary,
            backgroundColor: 'rgba(0,155,114,0.1)',
            tension: 0.3,
            fill: true
        }]
    },
    options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
});

<?php if (isAdmin() || isVieScolaire() || isTeacher()): ?>
// Par classe
new Chart(document.getElementById('classeChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_keys($absences_par_classe)) ?>,
        datasets: [{ label: "Absences", data: <?= json_encode(array_values($absences_par_classe)) ?>, backgroundColor: colors.primary, borderWidth: 0 }]
    },
    options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
});

// Top élèves
new Chart(document.getElementById('elevesChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_map(fn($e) => $e['prenom'] . ' ' . $e['nom'], $top_eleves)) ?>,
        datasets: [{ label: "Absences", data: <?= json_encode(array_map(fn($e) => $e['count'], $top_eleves)) ?>, backgroundColor: colors.bg, borderWidth: 0 }]
    },
    options: { responsive: true, maintainAspectRatio: false, indexAxis: 'y', scales: { x: { beginAtZero: true, ticks: { precision: 0 } } } }
});
<?php endif; ?>
</script>
