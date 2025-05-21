<?php
// Vue calendrier des absences - Style harmonisé
// Inclus depuis absences.php

// Préparer les données du calendrier
$jours_semaine = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];

$debut_mois = new DateTime(date('Y-m-01', strtotime($date_debut)));
$debut_mois->modify('first day of this month');
$debut_calendrier = clone $debut_mois;
$debut_calendrier->modify('last monday');

$fin_mois = new DateTime(date('Y-m-t', strtotime($date_debut)));
$fin_calendrier = clone $fin_mois;
$fin_calendrier->modify('next sunday');

// Organiser les absences par jour
$absences_par_jour = [];
foreach ($absences as $absence) {
    $debut = new DateTime($absence['date_debut']);
    $fin = new DateTime($absence['date_fin']);
    
    $jour_courant = clone $debut;
    while ($jour_courant <= $fin) {
        $jour_key = $jour_courant->format('Y-m-d');
        if (!isset($absences_par_jour[$jour_key])) {
            $absences_par_jour[$jour_key] = [];
        }
        $absences_par_jour[$jour_key][] = $absence;
        $jour_courant->modify('+1 day');
    }
}

// Générer les semaines pour le calendrier
$semaines = [];
$jour_courant = clone $debut_calendrier;
while ($jour_courant <= $fin_calendrier) {
    $semaine = [];
    for ($i = 0; $i < 7; $i++) {
        $jour_key = $jour_courant->format('Y-m-d');
        $semaine[] = [
            'date' => clone $jour_courant,
            'in_range' => $jour_courant->format('Y-m') === $debut_mois->format('Y-m'),
            'absences' => isset($absences_par_jour[$jour_key]) ? $absences_par_jour[$jour_key] : []
        ];
        $jour_courant->modify('+1 day');
    }
    $semaines[] = $semaine;
}
?>

<div class="calendar-container">
    <!-- En-tête du calendrier -->
    <div class="calendar-header">
        <div class="calendar-navigation">
            <a href="?view=calendar&date_debut=<?= date('Y-m-d', strtotime($date_debut . ' -1 month')) ?>&date_fin=<?= date('Y-m-d', strtotime($date_fin . ' -1 month')) ?>&classe=<?= urlencode($classe) ?>&justifie=<?= $justifie ?>" class="btn btn-secondary">
                <i class="fas fa-chevron-left"></i> Mois précédent
            </a>
            <h2><?= strftime('%B %Y', strtotime($date_debut)) ?></h2>
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
    
    <!-- Corps du calendrier -->
    <div class="calendar-body">
        <?php foreach ($semaines as $semaine): ?>
            <div class="calendar-week">
                <?php foreach ($semaine as $jour): ?>
                    <?php 
                    $is_today = $jour['date']->format('Y-m-d') === date('Y-m-d');
                    $is_weekend = in_array($jour['date']->format('N'), [6, 7]);
                    $has_absences = !empty($jour['absences']);
                    ?>
                    <div class="calendar-day <?= $is_weekend ? 'weekend' : '' ?> <?= $is_today ? 'today' : '' ?> <?= $jour['in_range'] ? '' : 'out-of-range' ?> <?= $has_absences ? 'has-absences' : '' ?>">
                        <div class="calendar-day-number"><?= $jour['date']->format('d') ?></div>
                        
                        <?php if ($has_absences): ?>
                            <div class="calendar-absences">
                                <?php foreach ($jour['absences'] as $index => $absence): ?>
                                    <?php if ($index < 3): ?>
                                        <div class="calendar-absence-item <?= $absence['justifie'] ? 'justified' : '' ?>" 
                                             title="<?= htmlspecialchars($absence['prenom'] . ' ' . $absence['nom'] . ' - ' . (new DateTime($absence['date_debut']))->format('H:i') . ' à ' . (new DateTime($absence['date_fin']))->format('H:i')) ?>">
                                            <?php if (isAdmin() || isVieScolaire() || isTeacher()): ?>
                                                <?= htmlspecialchars(substr($absence['prenom'], 0, 1) . '. ' . $absence['nom']) ?>
                                            <?php else: ?>
                                                <?= (new DateTime($absence['date_debut']))->format('H:i') ?> - <?= (new DateTime($absence['date_fin']))->format('H:i') ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
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

<style>
  .calendar-container {
    background-color: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
  }
  
  .calendar-header {
    padding: 15px;
    background-color: #f9f9f9;
    border-bottom: 1px solid #eee;
  }
  
  .calendar-navigation {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
  }
  
  .calendar-navigation h2 {
    margin: 0;
    font-size: 1.2rem;
    color: #333;
    text-transform: capitalize;
  }
  
  .calendar-nav-btn {
    padding: 5px 10px;
    background-color: #f1f3f4;
    border-radius: 4px;
    color: #444;
    text-decoration: none;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 5px;
  }
  
  .calendar-nav-btn:hover {
    background-color: #e5e7e9;
  }
  
  .calendar-day-headers {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 1px;
  }
  
  .calendar-day-header {
    text-align: center;
    padding: 10px;
    font-weight: 500;
    color: #444;
    font-size: 0.9rem;
  }
  
  .calendar-body {
    padding: 1px;
  }
  
  .calendar-week {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 1px;
    margin-bottom: 1px;
  }
  
  .calendar-day {
    min-height: 100px;
    background-color: #f9f9f9;
    padding: 10px;
    position: relative;
  }
  
  .calendar-day.weekend {
    background-color: #f5f5f5;
  }
  
  .calendar-day.today {
    background-color: #e6f3ef;
  }
  
  .calendar-day.out-of-range {
    opacity: 0.5;
  }
  
  .calendar-day.has-absences {
    background-color: #fdf5f5;
  }
  
  .calendar-day-number {
    font-size: 0.9rem;
    font-weight: 500;
    color: #444;
    margin-bottom: 10px;
  }
  
  .today .calendar-day-number {
    background-color: #009b72;
    color: white;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  
  .calendar-absences {
    display: flex;
    flex-direction: column;
    gap: 5px;
  }
  
  .calendar-absence-item {
    background-color: #fadbd8;
    border-left: 3px solid #e74c3c;
    padding: 5px 8px;
    border-radius: 4px;
    font-size: 0.8rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  
  .calendar-absence-item.justified {
    background-color: #e0f2e9;
    border-left-color: #00843d;
  }
  
  .calendar-more-absences {
    text-align: center;
    font-size: 0.8rem;
    color: #666;
    padding: 5px;
  }
  
  .calendar-add-absence {
    position: absolute;
    bottom: 5px;
    right: 5px;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background-color: #009b72;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    font-size: 0.8rem;
    opacity: 0.7;
  }
  
  .calendar-day:hover .calendar-add-absence {
    opacity: 1;
  }
  
  @media (max-width: 992px) {
    .calendar-day {
      min-height: 80px;
    }
    
    .calendar-navigation h2 {
      font-size: 1rem;
    }
    
    .calendar-nav-btn {
      font-size: 0.8rem;
    }
  }
  
  @media (max-width: 768px) {
    .calendar-day-headers {
      display: none;
    }
    
    .calendar-week {
      display: block;
      margin-bottom: 10px;
    }
    
    .calendar-day {
      margin-bottom: 1px;
      display: flex;
      flex-direction: column;
      min-height: auto;
    }
    
    .calendar-day-number:before {
      content: attr(data-day);
      margin-right: 5px;
    }
  }
</style>

<script>
  // Fonction pour afficher un popup avec les détails des absences pour une journée
  document.querySelectorAll('.calendar-more-absences').forEach(function(element) {
    element.addEventListener('click', function() {
      // Ici, vous pourriez implémenter un popup qui affiche toutes les absences
      alert('Fonctionnalité à implémenter : afficher toutes les absences pour cette journée');
    });
  });
</script>