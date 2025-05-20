<?php
/**
 * Vue journalière pour l'agenda
 * Affiche les événements d'une journée spécifique
 */

// S'assurer que $date est au bon format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

// Récupérer les événements de la journée si pas déjà fait
if (empty($day_events) && $table_exists) {
    try {
        // Requête pour récupérer les événements du jour
        $sql = "SELECT * FROM evenements WHERE DATE(date_debut) = ? OR DATE(date_fin) = ? ";
        
        // Ajouter les filtres
        $params = [$date, $date];
        
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
        $day_events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des événements (vue jour): " . $e->getMessage());
        // Afficher un message d'erreur élégant à l'utilisateur
        echo '<div class="alert alert-error">Une erreur est survenue lors du chargement des événements.</div>';
        $day_events = [];
    }
}

// Récupérer le jour de la semaine et formater la date
$date_obj = new DateTime($date);
$day_name = $day_names_full[$date_obj->format('N') - 1];
$formatted_date = $date_obj->format('d') . ' ' . $month_names[$date_obj->format('n')] . ' ' . $date_obj->format('Y');

// Créer un tableau pour organiser les événements par heure
$hours = [];
for ($i = 7; $i <= 19; $i++) { // De 7h à 19h
    $hours[sprintf('%02d', $i)] = [];
}

// Placer les événements dans les tranches horaires
foreach ($day_events as $event) {
    $start_hour = date('H', strtotime($event['date_debut']));
    
    // Si l'heure est en dehors de la plage affichée, l'ajouter à la première ou dernière heure
    if ($start_hour < 7) $start_hour = '07';
    if ($start_hour > 19) $start_hour = '19';
    
    $hours[$start_hour][] = $event;
}

// Fonction pour calculer la hauteur et la position d'un événement selon sa durée
function calculateEventStyle($event) {
    $start = new DateTime($event['date_debut']);
    $end = new DateTime($event['date_fin']);
    
    // Calculer la durée en minutes
    $duration = ($end->getTimestamp() - $start->getTimestamp()) / 60;
    
    // Limiter la durée pour l'affichage
    if ($duration > 180) $duration = 180; // Max 3 heures
    if ($duration < 30) $duration = 30; // Min 30 minutes
    
    // Calculer la hauteur (1 minute = 1px)
    $height = $duration;
    
    // Position top basée sur les minutes dans l'heure
    $minutes = $start->format('i');
    $top = $minutes;
    
    return [
        'height' => $height . 'px',
        'top' => $top . 'px'
    ];
}
?>

<div class="day-view">
    <div class="day-header">
        <h3 class="day-title"><?= $day_name ?> <?= $formatted_date ?></h3>
    </div>
    
    <div class="day-body">
        <div class="day-timeline">
            <?php foreach ($hours as $hour => $events): ?>
                <div class="timeline-hour"><?= $hour ?>:00</div>
            <?php endforeach; ?>
        </div>
        
        <div class="day-events">
            <?php foreach ($hours as $hour => $events): ?>
                <div class="hour-slot" data-hour="<?= $hour ?>">
                    <?php foreach ($events as $event): ?>
                        <?php
                        $event_style = calculateEventStyle($event);
                        $event_class = 'event-' . $event['type_evenement'];
                        
                        if ($event['statut'] === 'annulé') {
                            $event_class .= ' event-cancelled';
                        } elseif ($event['statut'] === 'reporté') {
                            $event_class .= ' event-postponed';
                        }
                        ?>
                        <div class="day-event <?= $event_class ?>"
                             style="top: <?= $event_style['top'] ?>; height: <?= $event_style['height'] ?>;"
                             onclick="openEventDetails(<?= $event['id'] ?>)">
                            <div class="event-time">
                                <?= date('H:i', strtotime($event['date_debut'])) ?> - 
                                <?= date('H:i', strtotime($event['date_fin'])) ?>
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