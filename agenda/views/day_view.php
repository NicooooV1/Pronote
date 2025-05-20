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
        $day_events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des événements du jour: " . $e->getMessage());
        $day_events = [];
    }
}

// Formatter la date pour l'affichage
$date_obj = new DateTime($date);
$formatted_date = $date_obj->format('l j F Y');
$is_today = $date === date('Y-m-d');
?>

<div class="day-view">
    <div class="day-header">
        <h2 class="day-title">
            <?= $formatted_date ?>
            <?php if ($is_today): ?>
                <span class="today-badge">Aujourd'hui</span>
            <?php endif; ?>
        </h2>
    </div>
    
    <div class="day-body">
        <div class="day-timeline">
            <?php for ($hour = 8; $hour <= 18; $hour++): ?>
                <div class="timeline-hour"><?= sprintf('%02d:00', $hour) ?></div>
            <?php endfor; ?>
        </div>
        
        <div class="day-events">
            <?php if (!empty($day_events)): ?>
                <?php foreach ($day_events as $event): 
                    $debut = new DateTime($event['date_debut']);
                    $fin = new DateTime($event['date_fin']);
                    $event_class = 'event-' . strtolower($event['type_evenement']);
                    
                    if ($event['statut'] === 'annulé') {
                        $event_class .= ' event-cancelled';
                    } elseif ($event['statut'] === 'reporté') {
                        $event_class .= ' event-postponed';
                    }
                    
                    // Calculer la position et la hauteur de l'événement
                    $start_hour = intval($debut->format('G'));
                    $start_minute = intval($debut->format('i'));
                    $end_hour = intval($fin->format('G'));
                    $end_minute = intval($fin->format('i'));
                    
                    $top = ($start_hour - 8) * 60 + $start_minute;
                    $height = ($end_hour - $start_hour) * 60 + ($end_minute - $start_minute);
                    
                    // Limiter aux heures affichées
                    if ($start_hour < 8) {
                        $top = 0;
                        $height -= (8 - $start_hour) * 60;
                    }
                    if ($end_hour > 18) {
                        $height -= ($end_hour - 18) * 60;
                    }
                    
                    // Position en pourcentage
                    $top_percent = ($top / 600) * 100;
                    $height_percent = ($height / 600) * 100;
                ?>
                <div class="day-event <?= $event_class ?>" 
                     style="top: <?= $top_percent ?>%; height: <?= $height_percent ?>%;"
                     data-event-id="<?= $event['id'] ?>">
                    <div class="event-time">
                        <?= $debut->format('H:i') ?> - <?= $fin->format('H:i') ?>
                    </div>
                    <a href="details_evenement.php?id=<?= $event['id'] ?>" class="event-title">
                        <?= htmlspecialchars($event['titre']) ?>
                    </a>
                    <?php if (!empty($event['lieu'])): ?>
                        <div class="event-location">
                            <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($event['lieu']) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-events">
                    <div class="no-events-message">
                        <i class="fas fa-calendar-day"></i>
                        <p>Aucun événement prévu pour cette journée.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>