<?php
// Fichier inclus depuis absences.php
// Les variables $absences, $user_role, etc. sont déjà définies

// Initialiser les statistiques
$total_absences = count($absences);
$total_justifiees = 0;
$total_non_justifiees = 0;
$total_cours = 0;
$total_demi_journee = 0;
$total_journee = 0;
$duree_totale_minutes = 0;
$absences_par_jour = [];
$absences_par_mois = [];
$eleves_absents = [];
$absences_par_classe = [];

// Analyser les absences
foreach ($absences as $absence) {
    // Comptage par justification
    if ($absence['justifie']) {
        $total_justifiees++;
    } else {
        $total_non_justifiees++;
    }
    
    // Comptage par type
    if ($absence['type_absence'] === 'cours') {
        $total_cours++;
    } elseif ($absence['type_absence'] === 'demi-journee') {
        $total_demi_journee++;
    } elseif ($absence['type_absence'] === 'journee') {
        $total_journee++;
    }
    
    // Calcul de la durée
    $debut = new DateTime($absence['date_debut']);
    $fin = new DateTime($absence['date_fin']);
    $duree = $debut->diff($fin);
    $duree_minutes = ($duree->days * 24 * 60) + ($duree->h * 60) + $duree->i;
    $duree_totale_minutes += $duree_minutes;
    
    // Regroupement par jour
    $jour = $debut->format('Y-m-d');
    if (!isset($absences_par_jour[$jour])) {
        $absences_par_jour[$jour] = 0;
    }
    $absences_par_jour[$jour]++;
    
    // Regroupement par mois
    $mois = $debut->format('Y-m');
    if (!isset($absences_par_mois[$mois])) {
        $absences_par_mois[$mois] = 0;
    }
    $absences_par_mois[$mois]++;
    
    // Regroupement par élève
    $id_eleve = $absence['id_eleve'];
    if (!isset($eleves_absents[$id_eleve])) {
        $eleves_absents[$id_eleve] = [
            'nom' => $absence['nom'],
            'prenom' => $absence['prenom'],
            'classe' => $absence['classe'],
            'count' => 0,
            'duree' => 0
        ];
    }
    $eleves_absents[$id_eleve]['count']++;
    $eleves_absents[$id_eleve]['duree'] += $duree_minutes;
    
    // Regroupement par classe (pour les admins et vie scolaire)
    if (isAdmin() || isVieScolaire() || isTeacher()) {
        $classe = $absence['classe'];
        if (!isset($absences_par_classe[$classe])) {
            $absences_par_classe[$classe] = 0;
        }
        $absences_par_classe[$classe]++;
    }
}

// Trier les élèves par nombre d'absences
uasort($eleves_absents, function($a, $b) {
    return $b['count'] - $a['count'];
});

// Limiter aux 10 premiers élèves pour le graphique
$top_eleves = array_slice($eleves_absents, 0, 10, true);

// Convertir les timestamps en dates lisibles
$absences_par_jour_formatte = [];
foreach ($absences_par_jour as $jour => $count) {
    $date = new DateTime($jour);
    $absences_par_jour_formatte[$date->format('d/m/Y')] = $count;
}

$absences_par_mois_formatte = [];
foreach ($absences_par_mois as $mois => $count) {
    $date = new DateTime($mois . '-01');
    $absences_par_mois_formatte[$date->format('M Y')] = $count;
}

// Calculer les pourcentages
$pourcentage_justifiees = $total_absences > 0 ? round(($total_justifiees / $total_absences) * 100) : 0;
$pourcentage_cours = $total_absences > 0 ? round(($total_cours / $total_absences) * 100) : 0;
$pourcentage_demi_journee = $total_absences > 0 ? round(($total_demi_journee / $total_absences) * 100) : 0;
$pourcentage_journee = $total_absences > 0 ? round(($total_journee / $total_absences) * 100) : 0;

// Convertir la durée totale en heures et minutes
$duree_heures = floor($duree_totale_minutes / 60);
$duree_minutes = $duree_totale_minutes % 60;
?>

<div class="stats-container">
  <!-- Résumé des statistiques -->
  <div class="stats-section">
    <h2>Résumé des absences</h2>
    
    <div class="stats-cards">
      <div class="stats-card">
        <div class="stats-icon">
          <i class="fas fa-calendar-xmark"></i>
        </div>
        <div class="stats-info">
          <h3>Total des absences</h3>
          <div class="stats-value"><?= $total_absences ?></div>
        </div>
      </div>
      
      <div class="stats-card">
        <div class="stats-icon">
          <i class="fas fa-check-circle"></i>
        </div>
        <div class="stats-info">
          <h3>Absences justifiées</h3>
          <div class="stats-value"><?= $total_justifiees ?> (<?= $pourcentage_justifiees ?>%)</div>
        </div>
      </div>
      
      <div class="stats-card">
        <div class="stats-icon">
          <i class="fas fa-clock"></i>
        </div>
        <div class="stats-info">
          <h3>Durée totale</h3>
          <div class="stats-value"><?= $duree_heures ?>h <?= $duree_minutes ?>min</div>
        </div>
      </div>
      
      <div class="stats-card">
        <div class="stats-icon">
          <i class="fas fa-users"></i>
        </div>
        <div class="stats-info">
          <h3>Élèves concernés</h3>
          <div class="stats-value"><?= count($eleves_absents) ?></div>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Graphiques -->
  <div class="stats-section">
    <h2>Analyse graphique</h2>
    
    <div class="stats-charts">
      <!-- Graphique de répartition des types d'absences -->
      <div class="stats-chart">
        <h3>Répartition par type</h3>
        <canvas id="typeChart"></canvas>
      </div>
      
      <!-- Graphique de l'évolution des absences -->
      <div class="stats-chart">
        <h3>Évolution des absences</h3>
        <canvas id="evolutionChart"></canvas>
      </div>
      
      <?php if (isAdmin() || isVieScolaire() || isTeacher()): ?>
      <!-- Graphique par classe -->
      <div class="stats-chart">
        <h3>Absences par classe</h3>
        <canvas id="classeChart"></canvas>
      </div>
      
      <!-- Graphique des élèves les plus absents -->
      <div class="stats-chart">
        <h3>Top des élèves absents</h3>
        <canvas id="elevesChart"></canvas>
      </div>
      <?php endif; ?>
    </div>
  </div>
  
  <?php if (isAdmin() || isVieScolaire() || isTeacher()): ?>
  <!-- Tableau des élèves les plus absents -->
  <div class="stats-section">
    <h2>Élèves les plus absents</h2>
    
    <div class="stats-table">
      <table>
        <thead>
          <tr>
            <th>Élève</th>
            <th>Classe</th>
            <th>Nombre d'absences</th>
            <th>Durée totale</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach (array_slice($eleves_absents, 0, 15) as $id_eleve => $eleve): ?>
            <?php
            $eleve_duree_heures = floor($eleve['duree'] / 60);
            $eleve_duree_minutes = $eleve['duree'] % 60;
            ?>
            <tr>
              <td><?= htmlspecialchars($eleve['prenom'] . ' ' . $eleve['nom']) ?></td>
              <td><?= htmlspecialchars($eleve['classe']) ?></td>
              <td><?= $eleve['count'] ?></td>
              <td><?= $eleve_duree_heures ?>h <?= $eleve_duree_minutes ?>min</td>
              <td>
                <a href="absences.php?eleve=<?= $id_eleve ?>" class="btn-icon" title="Voir les absences de cet élève">
                  <i class="fas fa-eye"></i>
                </a>
                <?php if (canManageAbsences()): ?>
                <a href="ajouter_absence.php?eleve=<?= $id_eleve ?>" class="btn-icon" title="Ajouter une absence">
                  <i class="fas fa-plus"></i>
                </a>
                <a href="contact_parent.php?eleve=<?= $id_eleve ?>" class="btn-icon" title="Contacter les parents">
                  <i class="fas fa-envelope"></i>
                </a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
  
  <!-- Vue statistiques des absences - Style harmonisé -->
  <div class="stats-section">
    <h2>Statistiques globales</h2>
    <div class="stats-cards">
        <div class="stats-card">
            <div class="stats-icon">
                <i class="fas fa-calendar-times"></i>
            </div>
            <div class="stats-info">
                <div class="stats-label">Total absences</div>
                <div class="stats-value"><?= $total_absences ?></div>
            </div>
        </div>
        
        <div class="stats-card">
            <div class="stats-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stats-info">
                <div class="stats-label">Absences justifiées</div>
                <div class="stats-value"><?= $nbJustifiees ?></div>
                <div class="stats-percent"><?= $total_absences > 0 ? round(($nbJustifiees / $total_absences) * 100) : 0 ?>%</div>
            </div>
        </div>
        
        <div class="stats-card">
            <div class="stats-icon">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="stats-info">
                <div class="stats-label">Absences non justifiées</div>
                <div class="stats-value"><?= $nbNonJustifiees ?></div>
                <div class="stats-percent"><?= $total_absences > 0 ? round(($nbNonJustifiees / $total_absences) * 100) : 0 ?>%</div>
            </div>
        </div>
        
        <div class="stats-card">
            <div class="stats-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stats-info">
                <div class="stats-label">Temps d'enseignement perdu</div>
                <div class="stats-value"><?= $heuresPerdues ?> heures</div>
                <div class="stats-percent">(env. <?= $joursPerdus ?> jours)</div>
            </div>
        </div>
    </div>
  </div>

  <div class="stats-section">
    <h2>Répartition par type d'absence</h2>
    <div class="stats-cards">
        <?php foreach ($statsByType as $type => $count): ?>
            <div class="stats-card">
                <div class="stats-icon">
                    <?php if ($type === 'cours'): ?>
                        <i class="fas fa-book"></i>
                    <?php elseif ($type === 'demi-journee'): ?>
                        <i class="fas fa-sun"></i>
                    <?php elseif ($type === 'journee'): ?>
                        <i class="fas fa-calendar-day"></i>
                    <?php else: ?>
                        <i class="fas fa-calendar-minus"></i>
                    <?php endif; ?>
                </div>
                <div class="stats-info">
                    <div class="stats-label">
                        <?php
                        switch ($type) {
                            case 'cours':
                                echo 'Cours';
                                break;
                            case 'demi-journee':
                                echo 'Demi-journée';
                                break;
                            case 'journee':
                                echo 'Journée complète';
                                break;
                            default:
                                echo htmlspecialchars(ucfirst($type));
                        }
                        ?>
                    </div>
                    <div class="stats-value"><?= $count ?></div>
                    <div class="stats-percent"><?= $total_absences > 0 ? round(($count / $total_absences) * 100) : 0 ?>%</div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
  </div>

  <?php if (isAdmin() || isVieScolaire()): ?>
  <div class="stats-section">
    <h2>Répartition par classe</h2>
    <div class="stats-cards">
        <?php foreach (array_slice($statsByClasse, 0, 8, true) as $classe => $count): ?>
            <div class="stats-card">
                <div class="stats-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stats-info">
                    <div class="stats-label"><?= htmlspecialchars($classe) ?></div>
                    <div class="stats-value"><?= $count ?></div>
                    <div class="stats-percent"><?= $total_absences > 0 ? round(($count / $total_absences) * 100) : 0 ?>%</div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<style>
  .stats-container {
    display: flex;
    flex-direction: column;
    gap: 20px;
  }
  
  .stats-section {
    background-color: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
  }
  
  .stats-section h2 {
    margin-top: 0;
    margin-bottom: 20px;
    font-size: 1.2rem;
    color: #333;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
  }
  
  .stats-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
  }
  
  .stats-card {
    background-color: #f9f9f9;
    border-radius: 8px;
    padding: 15px;
    display: flex;
    align-items: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.02);
  }
  
  .stats-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background-color: #e6f3ef;
    color: #009b72;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
  }
  
  .stats-icon i {
    font-size: 20px;
  }
  
  .stats-info h3 {
    margin: 0;
    font-size: 14px;
    color: #666;
    font-weight: normal;
  }
  
  .stats-value {
    font-size: 22px;
    font-weight: 500;
    color: #333;
    margin-top: 5px;
  }
  
  .stats-charts {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
  }
  
  .stats-chart {
    background-color: #f9f9f9;
    border-radius: 8px;
    padding: 15px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.02);
  }
  
  .stats-chart h3 {
    margin-top: 0;
    margin-bottom: 15px;
    font-size: 1rem;
    color: #444;
  }
  
  .stats-table {
    overflow-x: auto;
  }
  
  .stats-table table {
    width: 100%;
    border-collapse: collapse;
  }
  
  .stats-table th, .stats-table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #eee;
  }

  .stats-table th {
    background-color: #f5f5f5;
    font-weight: 500;
    color: #444;
  }
  
  .stats-table tr:hover {
    background-color: #f9f9f9;
  }
  
  .btn-icon {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    background-color: #f1f3f4;
    color: #444;
    margin: 0 2px;
  }
  
  .btn-icon:hover {
    background-color: #e5e7e9;
  }
  
  @media (max-width: 768px) {
    .stats-cards {
      grid-template-columns: 1fr;
    }
    
    .stats-charts {
      grid-template-columns: 1fr;
    }
  }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  // Configuration des couleurs
  const colors = {
    primary: '#009b72',
    secondary: '#e74c3c',
    tertiary: '#f39c12',
    quaternary: '#3498db',
    background: [
      'rgba(0, 155, 114, 0.7)',
      'rgba(231, 76, 60, 0.7)',
      'rgba(243, 156, 18, 0.7)',
      'rgba(52, 152, 219, 0.7)',
      'rgba(155, 89, 182, 0.7)',
      'rgba(22, 160, 133, 0.7)',
      'rgba(192, 57, 43, 0.7)',
      'rgba(211, 84, 0, 0.7)',
      'rgba(41, 128, 185, 0.7)',
      'rgba(142, 68, 173, 0.7)'
    ]
  };

  // Graphique de répartition par type
  const typeCtx = document.getElementById('typeChart').getContext('2d');
  new Chart(typeCtx, {
    type: 'doughnut',
    data: {
      labels: ['Cours', 'Demi-journée', 'Journée complète'],
      datasets: [{
        data: [<?= $total_cours ?>, <?= $total_demi_journee ?>, <?= $total_journee ?>],
        backgroundColor: [colors.primary, colors.tertiary, colors.secondary],
        borderWidth: 0
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'bottom'
        }
      }
    }
  });

  // Graphique d'évolution des absences par mois
  const evolutionCtx = document.getElementById('evolutionChart').getContext('2d');
  new Chart(evolutionCtx, {
    type: 'line',
    data: {
      labels: <?= json_encode(array_keys($absences_par_mois_formatte)) ?>,
      datasets: [{
        label: 'Nombre d\'absences',
        data: <?= json_encode(array_values($absences_par_mois_formatte)) ?>,
        borderColor: colors.primary,
        backgroundColor: 'rgba(0, 155, 114, 0.1)',
        tension: 0.3,
        fill: true
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            precision: 0
          }
        }
      }
    }
  });

  <?php if (isAdmin() || isVieScolaire() || isTeacher()): ?>
  // Graphique par classe
  const classeCtx = document.getElementById('classeChart').getContext('2d');
  new Chart(classeCtx, {
    type: 'bar',
    data: {
      labels: <?= json_encode(array_keys($absences_par_classe)) ?>,
      datasets: [{
        label: 'Nombre d\'absences',
        data: <?= json_encode(array_values($absences_par_classe)) ?>,
        backgroundColor: colors.primary,
        borderWidth: 0
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            precision: 0
          }
        }
      }
    }
  });

  // Graphique des élèves les plus absents
  const elevesCtx = document.getElementById('elevesChart').getContext('2d');
  new Chart(elevesCtx, {
    type: 'horizontalBar',
    data: {
      labels: <?= json_encode(array_map(function($eleve) { return $eleve['prenom'] . ' ' . $eleve['nom']; }, $top_eleves)) ?>,
      datasets: [{
        label: 'Nombre d\'absences',
        data: <?= json_encode(array_map(function($eleve) { return $eleve['count']; }, $top_eleves)) ?>,
        backgroundColor: colors.background,
        borderWidth: 0
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      indexAxis: 'y',
      scales: {
        x: {
          beginAtZero: true,
          ticks: {
            precision: 0
          }
        }
      }
    }
  });
  <?php endif; ?>
</script>