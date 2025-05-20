<?php
/**
 * Fonctions auxiliaires pour la gestion des événements
 */

/**
 * Valide les données d'un événement
 * 
 * @param array $data Données à valider
 * @return array Tableau contenant le statut de validation et les messages d'erreur
 */
function validateEventData($data) {
    $errors = [];
    
    // Vérifier les champs obligatoires
    $required_fields = ['titre', 'date_debut', 'heure_debut', 'date_fin', 'heure_fin', 'type_evenement', 'visibilite'];
    
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            $errors[] = "Le champ '$field' est obligatoire.";
        }
    }
    
    // Si des erreurs ont déjà été trouvées, inutile de continuer
    if (!empty($errors)) {
        return ['valid' => false, 'errors' => $errors];
    }
    
    // Vérifier les dates
    $date_debut = $data['date_debut'] . ' ' . $data['heure_debut'] . ':00';
    $date_fin = $data['date_fin'] . ' ' . $data['heure_fin'] . ':00';
    
    if (strtotime($date_fin) <= strtotime($date_debut)) {
        $errors[] = "La date/heure de fin doit être après la date/heure de début.";
    }
    
    // Vérifier la visibilité
    if ($data['visibilite'] === 'classes_specifiques' && empty($data['classes'])) {
        $errors[] = "Vous devez sélectionner au moins une classe lorsque la visibilité est 'Classes spécifiques'.";
    }
    
    return ['valid' => empty($errors), 'errors' => $errors];
}

/**
 * Prépare les données d'un événement pour l'insertion ou la mise à jour dans la base de données
 * 
 * @param array $data Données du formulaire
 * @param string $createur Nom du créateur de l'événement
 * @return array Données préparées pour la base de données
 */
function prepareEventData($data, $createur) {
    // Formatage des dates
    $date_debut = $data['date_debut'] . ' ' . $data['heure_debut'] . ':00';
    $date_fin = $data['date_fin'] . ' ' . $data['heure_fin'] . ':00';
    
    // Traitement de la visibilité et des classes sélectionnées
    $visibilite = $data['visibilite'];
    $classes_selectionnees = '';
    
    if ($visibilite === 'classes_specifiques' && !empty($data['classes'])) {
        $classes_selectionnees = is_array($data['classes']) ? implode(',', $data['classes']) : $data['classes'];
        $visibilite = 'classes:' . $classes_selectionnees;
    }
    
    // Préparer les données pour la base de données
    $prepared_data = [
        'titre' => trim($data['titre']),
        'description' => isset($data['description']) ? trim($data['description']) : '',
        'date_debut' => $date_debut,
        'date_fin' => $date_fin,
        'type_evenement' => $data['type_evenement'],
        'statut' => isset($data['statut']) ? $data['statut'] : 'actif',
        'createur' => $createur,
        'visibilite' => $visibilite,
        'lieu' => isset($data['lieu']) ? trim($data['lieu']) : '',
        'classes' => $classes_selectionnees,
        'matieres' => isset($data['matieres']) ? trim($data['matieres']) : ''
    ];
    
    return $prepared_data;
}

/**
 * Crée un nouvel événement dans la base de données
 * 
 * @param PDO $pdo Connexion à la base de données
 * @param array $data Données préparées
 * @return array Résultat de l'opération
 */
function createEvent($pdo, $data) {
    try {
        $sql = 'INSERT INTO evenements (titre, description, date_debut, date_fin, type_evenement, statut, createur, visibilite, lieu, classes, matieres) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $data['titre'],
            $data['description'],
            $data['date_debut'],
            $data['date_fin'],
            $data['type_evenement'],
            $data['statut'],
            $data['createur'],
            $data['visibilite'],
            $data['lieu'],
            $data['classes'],
            $data['matieres']
        ]);
        
        if ($result) {
            return ['success' => true, 'id' => $pdo->lastInsertId()];
        } else {
            return ['success' => false, 'message' => 'Erreur lors de la création de l\'événement.'];
        }
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Erreur lors de la création de l\'événement: ' . $e->getMessage()];
    }
}

/**
 * Met à jour un événement existant dans la base de données
 * 
 * @param PDO $pdo Connexion à la base de données
 * @param int $id ID de l'événement à mettre à jour
 * @param array $data Données préparées
 * @return array Résultat de l'opération
 */
function updateEvent($pdo, $id, $data) {
    try {
        $sql = 'UPDATE evenements SET 
                titre = ?, 
                description = ?, 
                date_debut = ?, 
                date_fin = ?, 
                type_evenement = ?, 
                statut = ?, 
                visibilite = ?, 
                lieu = ?, 
                classes = ?, 
                matieres = ? 
                WHERE id = ?';
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $data['titre'],
            $data['description'],
            $data['date_debut'],
            $data['date_fin'],
            $data['type_evenement'],
            $data['statut'],
            $data['visibilite'],
            $data['lieu'],
            $data['classes'],
            $data['matieres'],
            $id
        ]);
        
        if ($result) {
            return ['success' => true];
        } else {
            return ['success' => false, 'message' => 'Erreur lors de la mise à jour de l\'événement.'];
        }
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Erreur lors de la mise à jour de l\'événement: ' . $e->getMessage()];
    }
}

/**
 * Supprime un événement de la base de données
 * 
 * @param PDO $pdo Connexion à la base de données
 * @param int $id ID de l'événement à supprimer
 * @return array Résultat de l'opération
 */
function deleteEvent($pdo, $id) {
    try {
        $sql = 'DELETE FROM evenements WHERE id = ?';
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([$id]);
        
        if ($result) {
            return ['success' => true];
        } else {
            return ['success' => false, 'message' => 'Erreur lors de la suppression de l\'événement.'];
        }
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Erreur lors de la suppression de l\'événement: ' . $e->getMessage()];
    }
}

/**
 * Récupère un événement par son ID
 * 
 * @param PDO $pdo Connexion à la base de données
 * @param int $id ID de l'événement
 * @return array|bool Données de l'événement ou false si non trouvé
 */
function getEventById($pdo, $id) {
    $sql = 'SELECT * FROM evenements WHERE id = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Récupère tous les événements correspondant à certains critères
 * 
 * @param PDO $pdo Connexion à la base de données
 * @param array $filters Critères de filtrage (optionnel)
 * @return array Liste des événements
 */
function getEvents($pdo, $filters = []) {
    $sql = 'SELECT * FROM evenements WHERE 1=1';
    $params = [];
    
    // Ajouter les filtres si présents
    if (isset($filters['date_debut']) && !empty($filters['date_debut'])) {
        $sql .= ' AND date_debut >= ?';
        $params[] = $filters['date_debut'];
    }
    
    if (isset($filters['date_fin']) && !empty($filters['date_fin'])) {
        $sql .= ' AND date_debut <= ?';
        $params[] = $filters['date_fin'];
    }
    
    if (isset($filters['type_evenement']) && !empty($filters['type_evenement'])) {
        $sql .= ' AND type_evenement = ?';
        $params[] = $filters['type_evenement'];
    }
    
    if (isset($filters['createur']) && !empty($filters['createur'])) {
        $sql .= ' AND createur = ?';
        $params[] = $filters['createur'];
    }
    
    if (isset($filters['visibilite']) && !empty($filters['visibilite'])) {
        $sql .= ' AND (visibilite = ? OR visibilite LIKE ?)';
        $params[] = $filters['visibilite'];
        $params[] = '%' . $filters['visibilite'] . '%';
    }
    
    if (isset($filters['classe']) && !empty($filters['classe'])) {
        $sql .= ' AND (classes LIKE ? OR visibilite = "public")';
        $params[] = '%' . $filters['classe'] . '%';
    }
    
    // Ajouter le tri
    $sql .= ' ORDER BY date_debut';
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les événements pour un mois spécifique
 * 
 * @param PDO $pdo Connexion à la base de données
 * @param int $month Le mois (1-12)
 * @param int $year L'année
 * @param array $additional_filters Filtres additionnels (optionnel)
 * @return array Liste des événements
 */
function getEventsForMonth($pdo, $month, $year, $additional_filters = []) {
    // Construire les dates de début et de fin du mois
    $date_debut = sprintf('%04d-%02d-01 00:00:00', $year, $month);
    $date_fin = sprintf('%04d-%02d-%02d 23:59:59', $year, $month, cal_days_in_month(CAL_GREGORIAN, $month, $year));
    
    // Préparer les filtres
    $filters = array_merge([
        'date_debut' => $date_debut,
        'date_fin' => $date_fin
    ], $additional_filters);
    
    return getEvents($pdo, $filters);
}

/**
 * Récupère les événements à venir pour un utilisateur
 * 
 * @param PDO $pdo Connexion à la base de données
 * @param array $user Données de l'utilisateur
 * @param int $limit Nombre maximum d'événements à récupérer (0 = sans limite)
 * @return array Liste des événements
 */
function getUpcomingEventsForUser($pdo, $user, $limit = 5) {
    $now = date('Y-m-d H:i:s');
    $user_fullname = $user['prenom'] . ' ' . $user['nom'];
    $user_role = $user['profil'];
    
    // Construire la requête de base
    $sql = "SELECT * FROM evenements WHERE date_debut >= ?";
    $params = [$now];
    
    // Filtrer selon le rôle de l'utilisateur
    if ($user_role === 'eleve') {
        // Pour un élève, récupérer ses événements et ceux de sa classe
        $classe = ''; // Récupérer la classe de l'élève (à implémenter)
        
        $sql .= " AND (visibilite = 'public' 
                OR visibilite = 'eleves' 
                OR visibilite LIKE ? 
                OR createur = ?)";
        
        $params[] = "%$classe%";
        $params[] = $user_fullname;
        
    } elseif ($user_role === 'professeur') {
        // Pour un professeur, récupérer ses événements et les événements publics/professeurs
        $sql .= " AND (visibilite = 'public' 
                OR visibilite = 'professeurs' 
                OR visibilite LIKE '%professeurs%'
                OR createur = ?)";
        
        $params[] = $user_fullname;
        
    } elseif ($user_role === 'parent') {
        // Pour un parent, récupérer les événements de ses enfants et les événements publics
        // (à adapter selon votre modèle de données pour les relations parent-enfant)
        $sql .= " AND (visibilite = 'public' OR visibilite = 'parents')";
    }
    // Pour les administrateurs, vie scolaire et autres rôles, montrer tous les événements (pas de filtre)
    
    // Trier par date de début et limiter le nombre de résultats
    $sql .= " ORDER BY date_debut";
    
    if ($limit > 0) {
        $sql .= " LIMIT ?";
        $params[] = $limit;
    }
    
    // Exécuter la requête
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Formate un événement pour l'affichage
 * 
 * @param array $event Données de l'événement
 * @return array Événement formaté
 */
function formatEventForDisplay($event) {
    // Préparer les dates
    $date_debut = new DateTime($event['date_debut']);
    $date_fin = new DateTime($event['date_fin']);
    
    // Types d'événements
    $types_evenements = [
        'cours' => 'Cours',
        'devoirs' => 'Devoirs',
        'reunion' => 'Réunion',
        'examen' => 'Examen',
        'sortie' => 'Sortie scolaire',
        'autre' => 'Autre'
    ];
    
    // Statuts d'événements
    $statuts_evenements = [
        'actif' => 'Actif',
        'annulé' => 'Annulé',
        'reporté' => 'Reporté'
    ];
    
    // Options de visibilité
    $options_visibilite = [
        'public' => 'Public (visible par tous)',
        'professeurs' => 'Professeurs uniquement',
        'eleves' => 'Élèves uniquement',
        'classes_specifiques' => 'Classes spécifiques',
        'participants' => 'Participants sélectionnés uniquement'
    ];
    
    // Préparer la visibilité
    $visibilite_texte = '';
    if (isset($options_visibilite[$event['visibilite']])) {
        $visibilite_texte = $options_visibilite[$event['visibilite']];
    } elseif (strpos($event['visibilite'], 'classes:') === 0) {
        $classes = substr($event['visibilite'], 8);
        $visibilite_texte = 'Classes spécifiques: ' . $classes;
    } else {
        $visibilite_texte = $event['visibilite'];
    }
    
    // Préparer l'événement formaté
    $formatted_event = [
        'id' => $event['id'],
        'titre' => $event['titre'],
        'description' => $event['description'],
        'date_debut' => $date_debut->format('Y-m-d'),
        'heure_debut' => $date_debut->format('H:i'),
        'date_fin' => $date_fin->format('Y-m-d'),
        'heure_fin' => $date_fin->format('H:i'),
        'type_evenement' => $event['type_evenement'],
        'type_libelle' => isset($types_evenements[$event['type_evenement']]) ? $types_evenements[$event['type_evenement']] : $event['type_evenement'],
        'statut' => $event['statut'],
        'statut_libelle' => isset($statuts_evenements[$event['statut']]) ? $statuts_evenements[$event['statut']] : $event['statut'],
        'createur' => $event['createur'],
        'visibilite' => $event['visibilite'],
        'visibilite_texte' => $visibilite_texte,
        'lieu' => $event['lieu'],
        'classes' => $event['classes'],
        'matieres' => $event['matieres'],
        'date_creation' => isset($event['date_creation']) ? $event['date_creation'] : '',
        'date_modification' => isset($event['date_modification']) ? $event['date_modification'] : '',
        'date_debut_formattee' => $date_debut->format('d/m/Y à H:i'),
        'date_fin_formattee' => $date_fin->format('d/m/Y à H:i')
    ];
    
    // Ajouter une information sur la date (aujourd'hui, demain, etc.)
    $aujourd_hui = new DateTime();
    $today_str = $aujourd_hui->format('Y-m-d');
    $tomorrow_str = (new DateTime('tomorrow'))->format('Y-m-d');
    
    if ($date_debut->format('Y-m-d') === $today_str) {
        $formatted_event['date_relative'] = 'Aujourd\'hui';
    } elseif ($date_debut->format('Y-m-d') === $tomorrow_str) {
        $formatted_event['date_relative'] = 'Demain';
    } elseif ($date_debut > $aujourd_hui) {
        $diff = $aujourd_hui->diff($date_debut);
        $formatted_event['date_relative'] = 'Dans ' . $diff->days . ' jour' . ($diff->days > 1 ? 's' : '');
    } else {
        $diff = $aujourd_hui->diff($date_debut);
        $formatted_event['date_relative'] = 'Il y a ' . $diff->days . ' jour' . ($diff->days > 1 ? 's' : '');
    }
    
    return $formatted_event;
}
?>