<?php
/**
 * Vue en liste pour l'agenda
 * Affiche les événements sous forme de liste chronologique
 */

// Récupérer tous les événements pour le mois en cours si pas déjà fait
if (empty($events) && $table_exists) {
    try {
        // Paramètres de filtrage pour la vue liste
        $params = [];
        $where_clauses = [];
        
        // Filtre par type d'événement
        if (!empty($filter_types)) {
            $type_placeholders = implode(',', array_fill(0, count($filter_types), '?'));
            $where_clauses[] = "type_evenement IN ($type_placeholders)";
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
                $where_clauses[] = "(" . implode(" OR ", $class_conditions) . ")";
            }
        }
        
        // Filtrer par date (événements à venir uniquement)
        $where_clauses[] = "date_debut >= ?";
        $params[] = date('Y-m-d');
        
        // Filtrage en fonction du rôle de l'utilisateur comme dans agenda.php
        // ...existant code de filtrage par rôle...
        
        // Construire la requête SQL
        $sql = "SELECT * FROM evenements";
        if (!empty($where_clauses)) {
            $sql .= " WHERE " . implode(" AND ", $where_clauses);
        }
        $sql .= " ORDER BY date_debut ASC LIMIT 50"; // Limiter à 50 événements pour la performance
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des événements (vue liste): " . $e->getMessage());
        // Afficher un message d'erreur élégant à l'utilisateur
        echo '<div class="alert alert-error">Une erreur est survenue lors du chargement des événements.</div>';
    }
}

// Organiser les événements par mois puis par jour
$events_by_month = [];
foreach ($events as $event) {
    $month = date('Y-m', strtotime($event['date_debut']));
    if (!isset($events_by_month[$month])) {
        $events_by_month[$month] = [];
    }
    $events_by_month[$month][] = $event;
}

// Trier les mois par ordre chronologique
ksort($events_by_month);
?>

<div class="list-view">
    <?php if (empty($events)): ?>
        <div class="no-data-message">
            <i class="fas fa-calendar-day"></i>
            <p>Aucun événement à venir pour la période sélectionnée.</p>
        </div>
    <?php else: ?>
        <?php foreach ($events_by_month as $month => $month_events): ?>
            <div class="list-month-section">
                <h3 class="list-month-title">
                    <?php
                    $month_obj = DateTime::createFromFormat('Y-m', $month);
                    echo $month_names[$month_obj->format('n')] . ' ' . $month_obj->format('Y');
                    ?>
                </h3>
                
                <div class="events-list">
                    <?php foreach ($month_events as $event): ?>
                        <?php
                        $event_date = new DateTime($event['date_debut']);
                        $event_type = $event['type_evenement'];
                        ?>
                        <div class="event-list-item">
                            <div class="event-list-color" style="background-color: var(--color-<?= $event_type ?>);"></div>
                            
                            <div class="event-list-date">
                                <span><?= $event_date->format('d') ?></span>
                                <?= $day_names[$event_date->format('N') - 1] ?>
                            </div>
                            
                            <div class="event-list-details">
                                <div class="event-list-title">
                                    <?= htmlspecialchars($event['titre']) ?>
                                    <?php if ($event['statut'] === 'annulé'): ?>
                                        <span class="badge badge-danger">Annulé</span>
                                    <?php elseif ($event['statut'] === 'reporté'): ?>
                                        <span class="badge badge-warning">Reporté</span>
                                    <?php endif; ?>
                                </div>
                                <div class="event-list-meta">
                                    <span><i class="far fa-clock"></i> <?= $event_date->format('H:i') ?></span>
                                    <?php if (!empty($event['lieu'])): ?>
                                        <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($event['lieu']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($event['matieres'])): ?>
                                        <span><i class="fas fa-book"></i> <?= htmlspecialchars($event['matieres']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <a href="details_evenement.php?id=<?= $event['id'] ?>" class="btn-icon">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>