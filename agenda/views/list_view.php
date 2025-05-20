<?php
/**
 * Vue en liste pour l'agenda
 * Affiche les événements sous forme de liste chronologique
 */

// Récupérer tous les événements pour le mois en cours si pas déjà fait
if (empty($list_events) && $table_exists) {
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
            $where_clauses[] = $user_clause;
            
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
            $where_clauses[] = $user_clause;
            
        } elseif ($user_role === 'parent') {
            // Pour les parents, montrer uniquement les événements publics ou pour parents
            $where_clauses[] = "(visibilite = 'public' OR visibilite = 'parents')";
        }
        
        // Ajouter une condition pour afficher les événements futurs et récents (derniers 30 jours)
        $date_limite = date('Y-m-d', strtotime('-30 days'));
        $where_clauses[] = "(date_debut >= ? OR date_fin >= ?)";
        $params[] = $date_limite;
        $params[] = date('Y-m-d');
        
        // Construction de la requête SQL
        $sql = "SELECT * FROM evenements";
        if (!empty($where_clauses)) {
            $sql .= " WHERE " . implode(" AND ", $where_clauses);
        }
        $sql .= " ORDER BY date_debut";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $list_events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des événements pour la vue liste: " . $e->getMessage());
        $list_events = [];
    }
}

// Regrouper les événements par période
$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$next_week = date('Y-m-d', strtotime('+1 week'));

// Trier les événements par période
$events_by_period = [
    'today' => [],      // Aujourd'hui
    'tomorrow' => [],   // Demain
    'week' => [],       // Cette semaine
    'future' => [],     // Plus tard
    'past' => []        // Événements passés
];

if (!empty($list_events)) {
    foreach ($list_events as $event) {
        $event_date = date('Y-m-d', strtotime($event['date_debut']));
        
        if ($event_date < $today) {
            $events_by_period['past'][] = $event;
        } elseif ($event_date === $today) {
            $events_by_period['today'][] = $event;
        } elseif ($event_date === $tomorrow) {
            $events_by_period['tomorrow'][] = $event;
        } elseif ($event_date <= $next_week) {
            $events_by_period['week'][] = $event;
        } else {
            $events_by_period['future'][] = $event;
        }
    }
}
?>

<div class="list-view">
    <div class="list-header">
        <h2>Liste des événements</h2>
    </div>
    
    <div class="list-content">
        <?php if (!empty($list_events)): ?>
            <!-- Événements d'aujourd'hui -->
            <?php if (!empty($events_by_period['today'])): ?>
                <div class="list-section">
                    <div class="list-section-header">
                        <h3>Aujourd'hui</h3>
                    </div>
                    <div class="events-list">
                        <?php foreach ($events_by_period['today'] as $event): 
                            $debut = new DateTime($event['date_debut']);
                            $fin = new DateTime($event['date_fin']);
                            $event_class = 'event-' . strtolower($event['type_evenement']);
                            
                            if ($event['statut'] === 'annulé') {
                                $event_class .= ' event-cancelled';
                            } elseif ($event['statut'] === 'reporté') {
                                $event_class .= ' event-postponed';
                            }
                        ?>
                            <div class="event-list-item <?= $event_class ?>">
                                <div class="event-list-color"></div>
                                <div class="event-list-date">
                                    <span><?= $debut->format('d') ?></span>
                                    <?= $debut->format('M') ?>
                                </div>
                                <div class="event-list-details">
                                    <div class="event-list-title">
                                        <a href="details_evenement.php?id=<?= $event['id'] ?>">
                                            <?= htmlspecialchars($event['titre']) ?>
                                        </a>
                                    </div>
                                    <div class="event-list-meta">
                                        <span>
                                            <i class="far fa-clock"></i> 
                                            <?= $debut->format('H:i') ?> - <?= $fin->format('H:i') ?>
                                        </span>
                                        <?php if (!empty($event['lieu'])): ?>
                                            <span>
                                                <i class="fas fa-map-marker-alt"></i> 
                                                <?= htmlspecialchars($event['lieu']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Événements de demain -->
            <?php if (!empty($events_by_period['tomorrow'])): ?>
                <div class="list-section">
                    <div class="list-section-header">
                        <h3>Demain</h3>
                    </div>
                    <div class="events-list">
                        <?php foreach ($events_by_period['tomorrow'] as $event): 
                            $debut = new DateTime($event['date_debut']);
                            $fin = new DateTime($event['date_fin']);
                            $event_class = 'event-' . strtolower($event['type_evenement']);
                            
                            if ($event['statut'] === 'annulé') {
                                $event_class .= ' event-cancelled';
                            } elseif ($event['statut'] === 'reporté') {
                                $event_class .= ' event-postponed';
                            }
                        ?>
                            <div class="event-list-item <?= $event_class ?>">
                                <div class="event-list-color"></div>
                                <div class="event-list-date">
                                    <span><?= $debut->format('d') ?></span>
                                    <?= $debut->format('M') ?>
                                </div>
                                <div class="event-list-details">
                                    <div class="event-list-title">
                                        <a href="details_evenement.php?id=<?= $event['id'] ?>">
                                            <?= htmlspecialchars($event['titre']) ?>
                                        </a>
                                    </div>
                                    <div class="event-list-meta">
                                        <span>
                                            <i class="far fa-clock"></i> 
                                            <?= $debut->format('H:i') ?> - <?= $fin->format('H:i') ?>
                                        </span>
                                        <?php if (!empty($event['lieu'])): ?>
                                            <span>
                                                <i class="fas fa-map-marker-alt"></i> 
                                                <?= htmlspecialchars($event['lieu']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Événements de cette semaine -->
            <?php if (!empty($events_by_period['week'])): ?>
                <div class="list-section">
                    <div class="list-section-header">
                        <h3>Cette semaine</h3>
                    </div>
                    <div class="events-list">
                        <?php foreach ($events_by_period['week'] as $event): 
                            $debut = new DateTime($event['date_debut']);
                            $fin = new DateTime($event['date_fin']);
                            $event_class = 'event-' . strtolower($event['type_evenement']);
                            
                            if ($event['statut'] === 'annulé') {
                                $event_class .= ' event-cancelled';
                            } elseif ($event['statut'] === 'reporté') {
                                $event_class .= ' event-postponed';
                            }
                        ?>
                            <div class="event-list-item <?= $event_class ?>">
                                <div class="event-list-color"></div>
                                <div class="event-list-date">
                                    <span><?= $debut->format('d') ?></span>
                                    <?= $debut->format('M') ?>
                                </div>
                                <div class="event-list-details">
                                    <div class="event-list-title">
                                        <a href="details_evenement.php?id=<?= $event['id'] ?>">
                                            <?= htmlspecialchars($event['titre']) ?>
                                        </a>
                                    </div>
                                    <div class="event-list-meta">
                                        <span>
                                            <i class="far fa-clock"></i> 
                                            <?= $debut->format('H:i') ?> - <?= $fin->format('H:i') ?>
                                        </span>
                                        <?php if (!empty($event['lieu'])): ?>
                                            <span>
                                                <i class="fas fa-map-marker-alt"></i> 
                                                <?= htmlspecialchars($event['lieu']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Événements futurs -->
            <?php if (!empty($events_by_period['future'])): ?>
                <div class="list-section">
                    <div class="list-section-header">
                        <h3>Prochainement</h3>
                    </div>
                    <div class="events-list">
                        <?php foreach ($events_by_period['future'] as $event): 
                            $debut = new DateTime($event['date_debut']);
                            $fin = new DateTime($event['date_fin']);
                            $event_class = 'event-' . strtolower($event['type_evenement']);
                            
                            if ($event['statut'] === 'annulé') {
                                $event_class .= ' event-cancelled';
                            } elseif ($event['statut'] === 'reporté') {
                                $event_class .= ' event-postponed';
                            }
                        ?>
                            <div class="event-list-item <?= $event_class ?>">
                                <div class="event-list-color"></div>
                                <div class="event-list-date">
                                    <span><?= $debut->format('d') ?></span>
                                    <?= $debut->format('M') ?>
                                </div>
                                <div class="event-list-details">
                                    <div class="event-list-title">
                                        <a href="details_evenement.php?id=<?= $event['id'] ?>">
                                            <?= htmlspecialchars($event['titre']) ?>
                                        </a>
                                    </div>
                                    <div class="event-list-meta">
                                        <span>
                                            <i class="far fa-clock"></i> 
                                            <?= $debut->format('H:i') ?> - <?= $fin->format('H:i') ?>
                                        </span>
                                        <?php if (!empty($event['lieu'])): ?>
                                            <span>
                                                <i class="fas fa-map-marker-alt"></i> 
                                                <?= htmlspecialchars($event['lieu']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Événements passés -->
            <?php if (!empty($events_by_period['past'])): ?>
                <div class="list-section">
                    <div class="list-section-header">
                        <h3>Événements passés</h3>
                    </div>
                    <div class="events-list">
                        <?php foreach ($events_by_period['past'] as $event): 
                            $debut = new DateTime($event['date_debut']);
                            $fin = new DateTime($event['date_fin']);
                            $event_class = 'event-' . strtolower($event['type_evenement']);
                            
                            if ($event['statut'] === 'annulé') {
                                $event_class .= ' event-cancelled';
                            } elseif ($event['statut'] === 'reporté') {
                                $event_class .= ' event-postponed';
                            }
                        ?>
                            <div class="event-list-item <?= $event_class ?>">
                                <div class="event-list-color"></div>
                                <div class="event-list-date">
                                    <span><?= $debut->format('d') ?></span>
                                    <?= $debut->format('M') ?>
                                </div>
                                <div class="event-list-details">
                                    <div class="event-list-title">
                                        <a href="details_evenement.php?id=<?= $event['id'] ?>">
                                            <?= htmlspecialchars($event['titre']) ?>
                                        </a>
                                    </div>
                                    <div class="event-list-meta">
                                        <span>
                                            <i class="far fa-clock"></i> 
                                            <?= $debut->format('H:i') ?> - <?= $fin->format('H:i') ?>
                                        </span>
                                        <?php if (!empty($event['lieu'])): ?>
                                            <span>
                                                <i class="fas fa-map-marker-alt"></i> 
                                                <?= htmlspecialchars($event['lieu']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="no-events-container">
                <div class="no-events-message">
                    <i class="fas fa-calendar"></i>
                    <p>Aucun événement à afficher.</p>
                    <?php if ($user_role === 'professeur' || $user_role === 'administrateur' || $user_role === 'vie_scolaire'): ?>
                        <a href="ajouter_evenement.php" class="create-button">
                            <i class="fas fa-plus"></i> Ajouter un événement
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>