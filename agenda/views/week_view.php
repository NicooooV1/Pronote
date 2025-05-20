<?php
/**
 * Vue hebdomadaire pour l'agenda
 * Affiche les événements d'une semaine
 */

// S'assurer que $date est au bon format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

// Calculer le début et la fin de la semaine
$date_obj = new DateTime($date);
$day_of_week = $date_obj->format('N'); // 1 (lundi) à 7 (dimanche)

// Trouver le premier jour de la semaine (lundi)
$start_of_week = clone $date_obj;
$start_of_week->modify('-' . ($day_of_week - 1) . ' days');

// Dernier jour de la semaine (dimanche)
$end_of_week = clone $start_of_week;
$end_of_week->modify('+6 days');

// Récupérer les événements de la semaine si pas déjà fait
if (empty($week_events) && $table_exists) {
    try {
        // Requête pour récupérer les événements de la semaine
        $sql = "SELECT * FROM evenements WHERE DATE(date_debut) BETWEEN ? AND ? ";
        
        // Ajouter les filtres
        $params = [$start_of_week->format('Y-m-d'), $end_of_week->format('Y-m-d')];
        
        // Filtre par type d'événement
        if (!empty($filter_types)) {
            $type_placeholders = implode(',', array_fill(0, count($filter_types), '?'));
            $sql .= "AND type_evenement IN ($type_placeholders) ";
            $params = array_merge($params, $filter_types);
        }
        
        // Filtre par classe
        if (!empty($filter_classes)) {
            $class_conditions = [];
            foreach ($filter_classes as $class) {
                $class_conditions[] = "classes LIKE ?";
                $params[] = "%$class%";
            }
            if (!empty($class_conditions)) {
                $sql .= "AND (" . implode(" OR ", $class_conditions) . ") ";
            }
        }
        
        // Filtrage en fonction du rôle de l'utilisateur
        if ($user_role === 'eleve') {
            // Pour un élève, récupérer ses événements et ceux de sa classe
            $classe = isset($user['classe']) ? $user['classe'] : ''; 
            
            $user_clause = "(visibilite = 'public' 
                    OR visibilite = 'eleves' 
                    OR visibilite LIKE ?
                    OR classes LIKE ? 
                    OR createur = ?";
                    
            $params[] = "%élèves%";
            $params[] = "%$classe%";
            $params[] = $user_fullname;
            
            // Ajouter la condition personnes_concernees si la colonne existe
            if ($personnes_concernees_exists) {
                $user_clause .= " OR personnes_concernees LIKE ?";
                $params[] = "%$user_fullname%";
            }
            
            $user_clause .= ")";
            $sql .= "AND $user_clause ";
            
        } elseif ($user_role === 'professeur') {
            // Pour un professeur, récupérer ses événements et les événements publics/professeurs
            $user_clause = "(visibilite = 'public' 
                    OR visibilite = 'professeurs' 
                    OR visibilite LIKE ?
                    OR createur = ?";
                    
            $params[] = "%professeurs%";
            $params[] = $user_fullname;
            
            // Ajouter la condition personnes_concernees si la colonne existe
            if ($personnes_concernees_exists) {
                $user_clause .= " OR personnes_concernees LIKE ?";
                $params[] = "%$user_fullname%";
            }
            
            $user_clause .= ")";
            $sql .= "AND $user_clause ";
            
        } elseif ($user_role === 'parent') {
            // Pour les parents, montrer uniquement les événements publics ou pour parents
            $sql .= "AND (visibilite = 'public' OR visibilite = 'parents') ";
        }
        
        $sql .= "ORDER BY date_debut";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $week_events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Organiser les événements par jour
        $events_by_day = [];
        foreach ($week_events as $event) {
            $event_date = date('Y-m-d', strtotime($event['date_debut']));
            if (!isset($events_by_day[$event_date])) {
                $events_by_day[$event_date] = [];
            }
            $events_by_day[$event_date][] = $event;
        }
        
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des événements de la semaine: " . $e->getMessage());
        $week_events = [];
        $events_by_day = [];
    }
}
?>

<div class="week-view">
    <div class="week-header">
        <div class="week-header-spacer"></div>
        <div class="week-header-days">
            <?php
            // Créer un tableau des jours de la semaine
            $weekdays = [];
            $current_day = clone $start_of_week;
            for ($i = 0; $i < 7; $i++) {
                $is_today = $current_day->format('Y-m-d') === date('Y-m-d');
                $weekdays[] = [
                    'date' => $current_day->format('Y-m-d'),
                    'day_name' => $day_names_full[$i],
                    'day_number' => $current_day->format('d'),
                    'month' => $current_day->format('m'),
                    'is_today' => $is_today
                ];
                $current_day->modify('+1 day');
            }
            
            // Afficher les en-têtes des jours
            foreach ($weekdays as $day): 
                $today_class = $day['is_today'] ? ' today' : '';
            ?>
                <div class="week-day-header<?= $today_class ?>" data-date="<?= $day['date'] ?>">
                    <div class="week-day-name"><?= $day['day_name'] ?></div>
                    <div class="week-day-date"><?= $day['day_number'] ?>/<?= $day['month'] ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div class="week-body">
        <div class="week-timeline">
            <?php for ($hour = 8; $hour <= 18; $hour++): ?>
                <div class="timeline-hour"><?= sprintf('%02d:00', $hour) ?></div>
            <?php endfor; ?>
        </div>
        
        <div class="week-grid">
            <?php foreach ($weekdays as $day): 
                $today_class = $day['is_today'] ? ' today' : '';
            ?>
                <div class="week-day-column<?= $today_class ?>" data-date="<?= $day['date'] ?>">
                    <?php 
                    // Afficher les événements du jour
                    if (isset($events_by_day[$day['date']])) {
                        foreach ($events_by_day[$day['date']] as $event) {
                            $debut = new DateTime($event['date_debut']);
                            $event_class = 'event-' . strtolower($event['type_evenement']);
                            
                            if ($event['statut'] === 'annulé') {
                                $event_class .= ' event-cancelled';
                            } elseif ($event['statut'] === 'reporté') {
                                $event_class .= ' event-postponed';
                            }
                    ?>
                        <div class="week-event <?= $event_class ?>" data-event-id="<?= $event['id'] ?>">
                            <div class="event-time"><?= $debut->format('H:i') ?></div>
                            <a href="details_evenement.php?id=<?= $event['id'] ?>" class="event-title">
                                <?= htmlspecialchars($event['titre']) ?>
                            </a>
                        </div>
                    <?php
                        }
                    }
                    ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>