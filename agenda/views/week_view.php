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
        $sql = "SELECT * FROM evenements 
                WHERE (DATE(date_debut) BETWEEN ? AND ?) 
                OR (DATE(date_fin) BETWEEN ? AND ?) ";
        
        $start_date = $start_of_week->format('Y-m-d');
        $end_date = $end_of_week->format('Y-m-d');
        
        // Paramètres de base
        $params = [$start_date, $end_date, $start_date, $end_date];
        
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
        
        // Filtrage en fonction du rôle de l'utilisateur (comme dans la vue jour)
        if ($user_role === 'eleve') {
            // Pour un élève
            $classe = isset($user['classe']) ? $user['classe'] : '';
            
            $sql .= "AND (visibilite = 'public' 
                    OR visibilite = 'eleves' 
                    OR visibilite LIKE '%élèves%'
                    OR classes LIKE ? 
                    OR createur = ?";
                    
            $params[] = "%$classe%";
            $params[] = $user_fullname;
            
            // Ajouter la condition personnes_concernees si la colonne existe
            if ($personnes_concernees_exists) {
                $sql .= " OR personnes_concernees LIKE ?";
                $params[] = "%$user_fullname%";
            }
            
            $sql .= ")";
            
        } elseif ($user_role === 'professeur') {
            // Pour un professeur
            $sql .= "AND (visibilite = 'public' 
                    OR visibilite = 'professeurs' 
                    OR visibilite LIKE '%professeurs%'
                    OR createur = ?";
                    
            $params[] = $user_fullname;
            
            // Ajouter la condition personnes_concernees si la colonne existe
            if ($personnes_concernees_exists) {
                $sql .= " OR personnes_concernees LIKE ?";
                $params[] = "%$user_fullname%";
            }
            
            $sql .= ")";
        }
        
        $sql .= " ORDER BY date_debut ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $week_events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des événements (vue semaine): " . $e->getMessage());
        // Afficher un message d'erreur élégant à l'utilisateur
        echo '<div class="alert alert-error">Une erreur est survenue lors du chargement des événements.</div>';
        $week_events = [];
    }
}

// Créer un tableau pour organiser les événements par jour et par heure
$days = [];
$current_day = clone $start_of_week;
for ($i = 0; $i < 7; $i++) {
    $day_key = $current_day->format('Y-m-d');
    $days[$day_key] = [
        'date' => clone $current_day,
        'events' => []
    ];
    $current_day->modify('+1 day');
}

// Placer les événements dans les jours correspondants
foreach ($week_events as $event) {
    $event_start = new DateTime($event['date_debut']);
    $event_day = $event_start->format('Y-m-d');
    
    if (isset($days[$event_day])) {
        $days[$event_day]['events'][] = $event;
    }
}

// Heures à afficher
$hours = [];
for ($i = 7; $i <= 19; $i++) { // De 7h à 19h
    $hours[] = sprintf('%02d:00', $i);
}

// Date d'aujourd'hui
$today = date('Y-m-d');
?>

<div class="week-view">
    <div class="week-header">
        <div class="week-header-spacer"></div>
        <div class="week-header-days">
            <?php foreach ($days as $day_key => $day_data): ?>
                <?php
                $day_date = $day_data['date'];
                $is_today = ($day_key === $today);
                ?>
                <div class="week-day-header <?= $is_today ? 'today' : '' ?>">
                    <div class="week-day-name"><?= $day_names_full[$day_date->format('N') - 1] ?></div>
                    <div class="week-day-date"><?= $day_date->format('d') ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div class="week-body">
        <div class="week-timeline">
            <?php foreach ($hours as $hour): ?>
                <div class="timeline-hour"><?= $hour ?></div>
            <?php endforeach; ?>
        </div>
        
        <div class="week-grid">
            <?php foreach ($days as $day_key => $day_data): ?>
                <?php $is_today = ($day_key === $today); ?>
                <div class="week-day-column <?= $is_today ? 'today' : '' ?>">
                    <?php foreach ($day_data['events'] as $event): ?>
                        <?php
                        // Calculer la position et la hauteur de l'événement
                        $start_time = new DateTime($event['date_debut']);
                        $end_time = new DateTime($event['date_fin']);
                        
                        // Heure de début (par exemple 8.5 pour 8h30)
                        $start_hour = (int)$start_time->format('H');
                        $start_minute = (int)$start_time->format('i');
                        $start_decimal = $start_hour + ($start_minute / 60);
                        
                        // Si l'événement commence avant la première heure affichée
                        if ($start_decimal < 7) {
                            $start_decimal = 7;
                        }
                        
                        // Durée en heures
                        $duration_hours = ($end_time->getTimestamp() - $start_time->getTimestamp()) / 3600;
                        
                        // Si la fin dépasse la dernière heure affichée
                        $end_decimal = $start_decimal + $duration_hours;
                        if ($end_decimal > 20) {
                            $end_decimal = 20;
                        }
                        
                        // Recalculer la durée
                        $duration_hours = $end_decimal - $start_decimal;
                        
                        // Position et taille
                        $top_position = ($start_decimal - 7) * 60; // Position en px (1h = 60px)
                        $height = $duration_hours * 60; // Hauteur en px
                        
                        // S'assurer qu'il y a une hauteur minimale
                        if ($height < 30) $height = 30;
                        
                        $event_class = 'event-' . $event['type_evenement'];
                        
                        if ($event['statut'] === 'annulé') {
                            $event_class .= ' event-cancelled';
                        } elseif ($event['statut'] === 'reporté') {
                            $event_class .= ' event-postponed';
                        }
                        ?>
                        <div class="day-event <?= $event_class ?>" 
                             style="top: <?= $top_position ?>px; height: <?= $height ?>px;"
                             onclick="openEventDetails(<?= $event['id'] ?>)">
                            <div class="event-time">
                                <?= $start_time->format('H:i') ?>
                            </div>
                            <div class="event-title"><?= htmlspecialchars($event['titre']) ?></div>
                            <?php if (!empty($event['lieu'])): ?>
                                <div class="event-location">
                                    <i class="fas fa-map-marker-alt"></i> 
                                    <?= htmlspecialchars($event['lieu']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>