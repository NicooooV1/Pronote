<?php
/**
 * Fonctions utilitaires pour le module Absences
 */

// Vérifier si les fonctions sont déjà définies pour éviter les redéclarations
if (!function_exists('getAbsencesEleve')) {
    /**
     * Récupère la liste des absences d'un élève
     * 
     * @param PDO $pdo Connexion à la base de données
     * @param int $id_eleve ID de l'élève
     * @param string $date_debut Date de début (optionnel)
     * @param string $date_fin Date de fin (optionnel)
     * @return array Liste des absences
     */
    function getAbsencesEleve($pdo, $id_eleve, $date_debut = null, $date_fin = null) {
        $params = [$id_eleve];
        $sql = "SELECT a.*, e.nom, e.prenom, e.classe 
                FROM absences a 
                JOIN eleves e ON a.id_eleve = e.id 
                WHERE a.id_eleve = ?";
        
        if ($date_debut && $date_fin) {
            $sql .= " AND (
                (a.date_debut BETWEEN ? AND ?) OR  /* Commence dans la période */
                (a.date_fin BETWEEN ? AND ?) OR    /* Finit dans la période */
                (a.date_debut <= ? AND a.date_fin >= ?) /* Chevauche complètement la période */
            )";
            $params = array_merge($params, [$date_debut, $date_fin, $date_debut, $date_fin, $date_debut, $date_fin]);
        } else if ($date_debut) {
            $sql .= " AND a.date_fin >= ?";
            $params[] = $date_debut;
        } else if ($date_fin) {
            $sql .= " AND a.date_debut <= ?";
            $params[] = $date_fin;
        }
        
        $sql .= " ORDER BY a.date_debut DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('getAbsencesClasse')) {
    /**
     * Récupère la liste des absences pour une classe
     * 
     * @param PDO $pdo Connexion à la base de données
     * @param string $classe Classe concernée
     * @param string $date_debut Date de début (optionnel)
     * @param string $date_fin Date de fin (optionnel)
     * @param string $justifie Filtre de justification (oui/non/vide)
     * @return array Liste des absences
     */
    function getAbsencesClasse($pdo, $classe, $date_debut = null, $date_fin = null, $justifie = '') {
        $params = [$classe];
        $sql = "SELECT a.*, e.nom, e.prenom, e.classe 
                FROM absences a 
                JOIN eleves e ON a.id_eleve = e.id 
                WHERE e.classe = ?";
        
        if ($date_debut && $date_fin) {
            $sql .= " AND (
                (a.date_debut BETWEEN ? AND ?) OR  /* Commence dans la période */
                (a.date_fin BETWEEN ? AND ?) OR    /* Finit dans la période */
                (a.date_debut <= ? AND a.date_fin >= ?) /* Chevauche complètement la période */
            )";
            $params = array_merge($params, [$date_debut, $date_fin, $date_debut, $date_fin, $date_debut, $date_fin]);
        } else if ($date_debut) {
            $sql .= " AND a.date_fin >= ?";
            $params[] = $date_debut;
        } else if ($date_fin) {
            $sql .= " AND a.date_debut <= ?";
            $params[] = $date_fin;
        }
        
        if ($justifie === 'oui') {
            $sql .= " AND a.justifie = 1";
        } else if ($justifie === 'non') {
            $sql .= " AND a.justifie = 0";
        }
        
        $sql .= " ORDER BY e.nom, e.prenom, a.date_debut DESC";
        
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur dans getAbsencesClasse: " . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('ajouterAbsence')) {
    /**
     * Ajoute une absence dans la base de données
     * 
     * @param PDO $pdo Connexion à la base de données
     * @param array $data Données de l'absence
     * @return bool|int ID de l'absence créée ou false en cas d'erreur
     */
    function ajouterAbsence($pdo, $data) {
        try {
            // Enable PDO error mode for debugging
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Check if all required fields are present
            $required_fields = ['id_eleve', 'date_debut', 'date_fin', 'type_absence', 'signale_par'];
            foreach ($required_fields as $field) {
                if (empty($data[$field])) {
                    error_log("Missing required field for absence: $field");
                    return false;
                }
            }
            
            // Set default values for optional fields
            $data['motif'] = isset($data['motif']) ? $data['motif'] : null;
            $data['justifie'] = isset($data['justifie']) ? $data['justifie'] : false;
            $data['commentaire'] = isset($data['commentaire']) ? $data['commentaire'] : null;
            
            // Verify the SQL table structure and adjust the query accordingly
            $sql = "INSERT INTO absences (
                        id_eleve, 
                        date_debut, 
                        date_fin, 
                        type_absence, 
                        motif, 
                        justifie, 
                        commentaire, 
                        signale_par
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $pdo->prepare($sql);
            
            // Log the data we're trying to insert
            error_log("Attempting to add absence with data: " . json_encode($data));
            
            $success = $stmt->execute([
                $data['id_eleve'],
                $data['date_debut'],
                $data['date_fin'],
                $data['type_absence'],
                $data['motif'],
                $data['justifie'] ? 1 : 0, // Convert boolean to int for MySQL
                $data['commentaire'],
                $data['signale_par']
            ]);
            
            if ($success) {
                $id = $pdo->lastInsertId();
                error_log("Successfully added absence with ID: $id");
                return $id;
            } else {
                error_log("Failed to add absence: " . json_encode($stmt->errorInfo()));
                return false;
            }
        } catch (PDOException $e) {
            error_log("PDO Exception when adding absence: " . $e->getMessage());
            // Display more detailed error for development
            error_log("Error details: " . $e->getTraceAsString());
            return false;
        } catch (Exception $e) {
            error_log("General Exception when adding absence: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('getAbsenceById')) {
    /**
     * Récupère les détails d'une absence
     * 
     * @param PDO $pdo Connexion à la base de données
     * @param int $id ID de l'absence
     * @return array|bool Détails de l'absence ou false si non trouvée
     */
    function getAbsenceById($pdo, $id) {
        $sql = "SELECT a.*, e.nom, e.prenom, e.classe 
                FROM absences a 
                JOIN eleves e ON a.id_eleve = e.id 
                WHERE a.id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('modifierAbsence')) {
    /**
     * Met à jour une absence
     * 
     * @param PDO $pdo Connexion à la base de données
     * @param int $id ID de l'absence
     * @param array $data Nouvelles données
     * @return bool Succès de la mise à jour
     */
    function modifierAbsence($pdo, $id, $data) {
        try {
            $sql = "UPDATE absences SET 
                        date_debut = ?, 
                        date_fin = ?, 
                        type_absence = ?, 
                        motif = ?, 
                        justifie = ?, 
                        commentaire = ? 
                    WHERE id = ?";
            
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([
                $data['date_debut'],
                $data['date_fin'],
                $data['type_absence'],
                $data['motif'] ?? null,
                isset($data['justifie']) ? $data['justifie'] : false,
                $data['commentaire'] ?? null,
                $id
            ]);
        } catch (PDOException $e) {
            error_log("Erreur lors de la modification d'une absence: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Supprime une absence
 * 
 * @param PDO $pdo Connexion à la base de données
 * @param int $id ID de l'absence
 * @return bool Succès de la suppression
 */
function supprimerAbsence($pdo, $id) {
    $sql = "DELETE FROM absences WHERE id = ?";
    
    try {
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$id]);
    } catch (PDOException $e) {
        error_log("Erreur lors de la suppression d'une absence: " . $e->getMessage());
        return false;
    }
}

/**
 * Fonction complète pour récupérer les retards d'un élève
 * @param PDO $pdo Connexion à la base de données
 * @param int $id_eleve ID de l'élève
 * @param string $date_debut Date de début (optionnel)
 * @param string $date_fin Date de fin (optionnel)
 * @return array Liste des retards
 */
function getRetardsEleve($pdo, $id_eleve, $date_debut = null, $date_fin = null) {
    $params = [$id_eleve];
    $sql = "SELECT r.*, e.nom, e.prenom, e.classe 
            FROM retards r 
            JOIN eleves e ON r.id_eleve = e.id 
            WHERE r.id_eleve = ?";
    
    if ($date_debut && $date_fin) {
        // Pour les retards, on cherche ceux qui sont dans la période
        $sql .= " AND ((r.date_retard BETWEEN ? AND ?) OR 
                       (DATE(r.date_retard) BETWEEN ? AND ?))";
        $params = array_merge($params, [$date_debut, $date_fin, $date_debut, $date_fin]);
    } else if ($date_debut) {
        $sql .= " AND DATE(r.date_retard) >= ?";
        $params[] = $date_debut;
    } else if ($date_fin) {
        $sql .= " AND DATE(r.date_retard) <= ?";
        $params[] = $date_fin;
    }
    
    $sql .= " ORDER BY r.date_retard DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Ajout d'un retard
 * @param PDO $pdo Connexion à la base de données
 * @param array $data Données du retard
 * @return bool|int ID du retard créé ou false en cas d'erreur
 */
function ajouterRetard($pdo, $data) {
    try {
        // Enable PDO error mode for debugging
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Check if all required fields are present
        $required_fields = ['id_eleve', 'date_retard', 'duree', 'signale_par'];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                error_log("Missing required field for retard: $field");
                return false;
            }
        }
        
        // Set default values for optional fields
        $data['motif'] = isset($data['motif']) ? $data['motif'] : null;
        $data['justifie'] = isset($data['justifie']) ? $data['justifie'] : false;
        $data['commentaire'] = isset($data['commentaire']) ? $data['commentaire'] : null;
        
        $sql = "INSERT INTO retards (
                    id_eleve,
                    date_retard,
                    duree,
                    motif,
                    justifie,
                    commentaire,
                    signale_par
                ) VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        
        $success = $stmt->execute([
            $data['id_eleve'],
            $data['date_retard'],
            $data['duree'],
            $data['motif'],
            $data['justifie'] ? 1 : 0,
            $data['commentaire'],
            $data['signale_par']
        ]);
        
        if ($success) {
            return $pdo->lastInsertId();
        } else {
            error_log("Failed to add retard: " . json_encode($stmt->errorInfo()));
            return false;
        }
    } catch (PDOException $e) {
        error_log("PDO Exception when adding retard: " . $e->getMessage());
        return false;
    }
}

/**
 * Récupère les retards pour une classe
 * @param PDO $pdo Connexion à la base de données
 * @param string $classe Classe concernée
 * @param string $date_debut Date de début (optionnel)
 * @param string $date_fin Date de fin (optionnel)
 * @return array Liste des retards
 */
function getRetardsClasse($pdo, $classe, $date_debut = null, $date_fin = null) {
    $params = [$classe];
    $sql = "SELECT r.*, e.nom, e.prenom, e.classe 
            FROM retards r 
            JOIN eleves e ON r.id_eleve = e.id 
            WHERE e.classe = ?";
    
    if ($date_debut && $date_fin) {
        // Pour les retards, on cherche ceux qui sont dans la période
        $sql .= " AND ((r.date_retard BETWEEN ? AND ?) OR 
                       (DATE(r.date_retard) BETWEEN ? AND ?))";
        $params = array_merge($params, [$date_debut, $date_fin, $date_debut, $date_fin]);
    } else if ($date_debut) {
        $sql .= " AND DATE(r.date_retard) >= ?";
        $params[] = $date_debut;
    } else if ($date_fin) {
        $sql .= " AND DATE(r.date_retard) <= ?";
        $params[] = $date_fin;
    }
    
    $sql .= " ORDER BY e.nom, e.prenom, r.date_retard DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fonctions pour les justificatifs
 */
if (!function_exists('ajouterJustificatif')) {
    /**
     * Ajoute un justificatif dans la base de données
     * @param PDO $pdo Instance de PDO
     * @param array $data Données du justificatif
     * @return bool|int ID du justificatif créé ou false en cas d'erreur
     */
    function ajouterJustificatif($pdo, $data) {
        try {
            // Vérifier les champs obligatoires
            $required_fields = ['id_eleve', 'date_soumission', 'date_debut_absence', 'date_fin_absence', 'type', 'motif'];
            foreach ($required_fields as $field) {
                if (empty($data[$field])) {
                    error_log("Champ obligatoire manquant pour justificatif: $field");
                    return false;
                }
            }

            $sql = "INSERT INTO justificatifs (
                        id_eleve, 
                        date_soumission, 
                        date_debut_absence,
                        date_fin_absence,
                        type,
                        fichier,
                        motif,
                        commentaire
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $pdo->prepare($sql);
            $success = $stmt->execute([
                $data['id_eleve'],
                $data['date_soumission'],
                $data['date_debut_absence'],
                $data['date_fin_absence'],
                $data['type'],
                isset($data['fichier']) ? $data['fichier'] : null,
                $data['motif'],
                isset($data['commentaire']) ? $data['commentaire'] : null
            ]);

            if ($success) {
                return $pdo->lastInsertId();
            } else {
                error_log("Échec d'ajout de justificatif: " . json_encode($stmt->errorInfo()));
                return false;
            }
        } catch (PDOException $e) {
            error_log("PDOException lors de l'ajout du justificatif: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Récupère un justificatif par son ID
 * @param PDO $pdo Instance PDO
 * @param int $id ID du justificatif
 * @return array|false Données du justificatif ou false si non trouvé
 */
if (!function_exists('getJustificatifById')) {
    function getJustificatifById($pdo, $id) {
        try {
            $sql = "SELECT j.*, e.nom, e.prenom, e.classe 
                    FROM justificatifs j 
                    JOIN eleves e ON j.id_eleve = e.id 
                    WHERE j.id = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération du justificatif: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Met à jour un justificatif
 * @param PDO $pdo Instance PDO
 * @param int $id ID du justificatif
 * @param array $data Données à mettre à jour
 * @return bool Succès ou échec
 */
if (!function_exists('updateJustificatif')) {
    function updateJustificatif($pdo, $id, $data) {
        try {
            $sql = "UPDATE justificatifs SET ";
            $params = [];
            $updates = [];
            
            // Champs pouvant être mis à jour
            $updatableFields = [
                'motif', 'commentaire', 'traite', 'approuve', 
                'commentaire_admin', 'date_traitement', 'traite_par'
            ];
            
            foreach ($updatableFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }
            
            // Si aucun champ à mettre à jour
            if (empty($updates)) {
                return true;
            }
            
            $sql .= implode(', ', $updates) . " WHERE id = ?";
            $params[] = $id;
            
            $stmt = $pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Erreur lors de la mise à jour du justificatif: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Calcule les statistiques d'absences pour un élève
 * 
 * @param PDO $pdo Connexion à la base de données
 * @param int $id_eleve ID de l'élève
 * @param string $periode Période (mois, trimestre, annee)
 * @return array Statistiques
 */
function calculerStatistiquesAbsences($pdo, $id_eleve, $periode = 'annee') {
    // Code pour calculer les statistiques d'absences
    // (nombre d'absences, durée totale, par matière, etc.)
}

/**
 * Crée une table absences dans la base de données si elle n'existe pas
 * 
 * @param PDO|null $pdo Connexion à la base de données
 * @return bool Succès de la création
 */
function createAbsencesTableIfNotExists($pdo = null) {
    try {
        // Vérifier si $pdo est défini, sinon essayer de l'obtenir depuis la variable globale
        if ($pdo === null) {
            global $pdo;
            // Si toujours null, essayer de créer une connexion d'urgence
            if ($pdo === null) {
                error_log("Warning: PDO non disponible dans createAbsencesTableIfNotExists");
                return false;
            }
        }
        
        $sql = "CREATE TABLE IF NOT EXISTS absences (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_eleve INT NOT NULL,
            date_debut DATETIME NOT NULL,
            date_fin DATETIME NOT NULL,
            type_absence VARCHAR(20) NOT NULL,
            motif VARCHAR(100) NULL,
            justifie BOOLEAN DEFAULT FALSE,
            commentaire TEXT NULL,
            signale_par VARCHAR(100) NOT NULL,
            date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        return $pdo->exec($sql) !== false;
    } catch (PDOException $e) {
        error_log("Error creating absences table: " . $e->getMessage());
        return false;
    } catch (Error $e) {
        error_log("PHP Error in createAbsencesTableIfNotExists: " . $e->getMessage());
        return false;
    }
}

/**
 * Crée une table retards dans la base de données si elle n'existe pas
 * 
 * @param PDO|null $pdo Connexion à la base de données
 * @return bool Succès de la création
 */
function createRetardsTableIfNotExists($pdo = null) {
    try {
        // Vérifier si $pdo est défini, sinon essayer de l'obtenir depuis la variable globale
        if ($pdo === null) {
            global $pdo;
            // Si toujours null, essayer de créer une connexion d'urgence
            if ($pdo === null) {
                error_log("Warning: PDO non disponible dans createRetardsTableIfNotExists");
                return false;
            }
        }
        
        $sql = "CREATE TABLE IF NOT EXISTS retards (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_eleve INT NOT NULL,
            date_retard DATETIME NOT NULL,
            duree INT NOT NULL,
            motif VARCHAR(100) NULL,
            justifie BOOLEAN DEFAULT FALSE,
            commentaire TEXT NULL,
            signale_par VARCHAR(100) NOT NULL,
            date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        return $pdo->exec($sql) !== false;
    } catch (PDOException $e) {
        error_log("Error creating retards table: " . $e->getMessage());
        return false;
    } catch (Error $e) {
        error_log("PHP Error in createRetardsTableIfNotExists: " . $e->getMessage());
        return false;
    }
}

/**
 * Crée une table professeur_classes dans la base de données si elle n'existe pas
 * 
 * @param PDO|null $pdo Connexion à la base de données
 * @return bool Succès de la création
 */
function createProfesseurClassesTableIfNotExists($pdo = null) {
    try {
        // Vérifier si $pdo est défini, sinon essayer de l'obtenir depuis la variable globale
        if ($pdo === null) {
            global $pdo;
            // Si toujours null, essayer de créer une connexion d'urgence
            if ($pdo === null) {
                error_log("Warning: PDO non disponible dans createProfesseurClassesTableIfNotExists");
                return false;
            }
        }
        
        $sql = "CREATE TABLE IF NOT EXISTS professeur_classes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_professeur INT NOT NULL,
            nom_classe VARCHAR(50) NOT NULL,
            UNIQUE KEY unique_prof_class (id_professeur, nom_classe)
        )";
        
        return $pdo->exec($sql) !== false;
    } catch (PDOException $e) {
        error_log("Error creating professeur_classes table: " . $e->getMessage());
        return false;
    } catch (Error $e) {
        error_log("PHP Error in createProfesseurClassesTableIfNotExists: " . $e->getMessage());
        return false;
    }
}

/**
 * Crée la table justificatifs avec les colonnes nécessaires
 * @param PDO|null $pdo Instance PDO
 * @return bool Succès ou échec
 */
function createJustificatifsTableIfNotExists($pdo = null) {
    try {
        // Vérifier si $pdo est défini, sinon essayer de l'obtenir depuis la variable globale
        if ($pdo === null) {
            global $pdo;
            // Si toujours null, essayer de créer une connexion d'urgence
            if ($pdo === null) {
                error_log("Warning: PDO non disponible dans createJustificatifsTableIfNotExists");
                return false;
            }
        }
        
        $sql = "CREATE TABLE IF NOT EXISTS justificatifs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_eleve INT NOT NULL,
            date_soumission DATE NOT NULL,
            date_debut_absence DATE NOT NULL,
            date_fin_absence DATE NOT NULL,
            type VARCHAR(50) NOT NULL,
            fichier VARCHAR(255) NULL,
            motif TEXT NULL,
            commentaire TEXT NULL,
            traite BOOLEAN DEFAULT FALSE,
            approuve BOOLEAN DEFAULT FALSE,
            commentaire_admin TEXT NULL,
            date_traitement DATETIME NULL,
            traite_par VARCHAR(100) NULL
        )";
        
        $pdo->exec($sql);
        return true;
    } catch (PDOException $e) {
        error_log("Erreur lors de la création de la table justificatifs: " . $e->getMessage());
        return false;
    } catch (Error $e) {
        error_log("PHP Error in createJustificatifsTableIfNotExists: " . $e->getMessage());
        return false;
    }
}

/**
 * Met à jour la structure de la table justificatifs si nécessaire
 * @param PDO $pdo Instance PDO
 * @return bool Succès ou échec
 */
function updateJustificatifsTable($pdo) {
    try {
        // Vérifier si la colonne date_soumission existe
        $columnCheck = $pdo->query("SHOW COLUMNS FROM justificatifs LIKE 'date_soumission'");
        if ($columnCheck->rowCount() == 0) {
            // Ajouter la colonne date_soumission
            $pdo->exec("ALTER TABLE justificatifs ADD COLUMN date_soumission DATE DEFAULT CURRENT_DATE");
        }
        
        // Vérifier si la colonne date_depot existe (ancienne version)
        $columnCheck = $pdo->query("SHOW COLUMNS FROM justificatifs LIKE 'date_depot'");
        if ($columnCheck->rowCount() > 0) {
            // Migration des données de date_depot vers date_soumission
            $pdo->exec("UPDATE justificatifs SET date_soumission = date_depot WHERE date_soumission IS NULL");
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Erreur lors de la mise à jour de la table justificatifs: " . $e->getMessage());
        return false;
    }
}

/**
 * Récupère les justificatifs pour un élève
 * @param PDO $pdo Instance PDO
 * @param int $id_eleve ID de l'élève
 * @param string $date_debut Date de début (optionnel)
 * @param string $date_fin Date de fin (optionnel)
 * @return array Liste des justificatifs
 */
function getJustificatifsEleve($pdo, $id_eleve, $date_debut = null, $date_fin = null) {
    try {
        // Vérifier quelle colonne de date utiliser
        $columnCheck = $pdo->query("SHOW COLUMNS FROM justificatifs LIKE 'date_soumission'");
        $dateColumn = $columnCheck->rowCount() > 0 ? 'date_soumission' : 'date_depot';
        
        $params = [$id_eleve];
        $sql = "SELECT j.*, e.nom, e.prenom, e.classe 
                FROM justificatifs j 
                JOIN eleves e ON j.id_eleve = e.id 
                WHERE j.id_eleve = ?";
        
        if ($date_debut) {
            $sql .= " AND j.$dateColumn >= ?";
            $params[] = $date_debut;
        }
        
        if ($date_fin) {
            $sql .= " AND j.$dateColumn <= ?";
            $params[] = $date_fin;
        }
        
        $sql .= " ORDER BY j.$dateColumn DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération des justificatifs: " . $e->getMessage());
        return [];
    }
}

/**
 * Initialise les associations professeurs-classes si nécessaire
 * @param PDO $pdo Connexion à la base de données
 * @return bool Succès de l'opération
 */
function initializeProfesseurClasses($pdo) {
    try {
        // Vérifier si des associations existent déjà
        $stmt = $pdo->query("SELECT COUNT(*) FROM professeur_classes");
        $count = $stmt->fetchColumn();
        
        // Si des associations existent déjà, ne rien faire
        if ($count > 0) {
            return true;
        }
        
        // Sinon, charger les données depuis le fichier JSON de l'établissement
        $etablissement_data = json_decode(file_get_contents(__DIR__ . '/../../login/data/etablissement.json'), true);
        if (empty($etablissement_data['professeurs'])) {
            error_log("Aucune donnée de professeur trouvée dans le fichier établissement");
            return false;
        }
        
        // Récupérer les professeurs depuis la base de données
        $stmt = $pdo->query("SELECT id, nom, prenom FROM professeurs");
        $professeurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($professeurs)) {
            error_log("Aucun professeur trouvé dans la base de données");
            return false;
        }
        
        // Récupérer les classes disponibles
        $classes = [];
        if (!empty($etablissement_data['classes'])) {
            foreach ($etablissement_data['classes'] as $niveau => $niveaux) {
                foreach ($niveaux as $cycle => $liste_classes) {
                    foreach ($liste_classes as $nom_classe) {
                        $classes[] = $nom_classe;
                    }
                }
            }
        }
        
        if (empty($classes)) {
            error_log("Aucune classe trouvée dans le fichier établissement");
            return false;
        }
        
        // Préparer la requête d'insertion
        $stmt = $pdo->prepare("INSERT INTO professeur_classes (id_professeur, nom_classe) VALUES (?, ?)");
        
        // Pour chaque professeur, assigner aléatoirement 1 à 3 classes
        foreach ($professeurs as $prof) {
            $nbClasses = rand(1, 3);
            $classesPourProf = array_rand(array_flip($classes), min($nbClasses, count($classes)));
            
            // Si une seule classe est retournée, convertir en tableau
            if (!is_array($classesPourProf)) {
                $classesPourProf = [$classesPourProf];
            }
            
            foreach ($classesPourProf as $classe) {
                try {
                    $stmt->execute([$prof['id'], $classe]);
                } catch (PDOException $e) {
                    // Ignorer les erreurs de doublon (contrainte unique)
                    if ($e->getCode() != '23000') {
                        error_log("Erreur lors de l'association professeur-classe: " . $e->getMessage());
                    }
                }
            }
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Erreur dans initializeProfesseurClasses: " . $e->getMessage());
        return false;
    }
}

// Try to create tables on include
try {
    // Vérifier si la connexion PDO est disponible
    global $pdo;
    if (isset($pdo) && $pdo instanceof PDO) {
        createAbsencesTableIfNotExists($pdo);
        createRetardsTableIfNotExists($pdo);
        createProfesseurClassesTableIfNotExists($pdo);
        createJustificatifsTableIfNotExists($pdo);
        initializeProfesseurClasses($pdo);
    } else {
        error_log("Avertissement: La variable PDO n'est pas disponible dans functions.php - Les tables ne seront pas créées automatiquement");
    }
} catch (Exception $e) {
    error_log("Error initializing tables: " . $e->getMessage());
} catch (Error $e) {
    error_log("PHP Error while initializing tables: " . $e->getMessage());
}

/**
 * Formate une date au format français
 * @param string $date La date au format SQL
 * @return string La date formatée
 */
function formatDate($date) {
    if (empty($date)) {
        return '';
    }
    
    $timestamp = strtotime($date);
    return date('d/m/Y', $timestamp);
}

/**
 * Formate une date et heure au format français
 * @param string $datetime La date et heure au format SQL
 * @return string La date et heure formatée
 */
function formatDateTime($datetime) {
    if (empty($datetime)) {
        return '';
    }
    
    $timestamp = strtotime($datetime);
    return date('d/m/Y à H:i', $timestamp);
}

/**
 * Détermine le trimestre actuel
 * @return string Trimestre actuel
 */
function getTrimestre() {
    $mois = date('n');
    if ($mois >= 9 && $mois <= 12) {
        return "1er trimestre";
    } elseif ($mois >= 1 && $mois <= 3) {
        return "2ème trimestre";
    } elseif ($mois >= 4 && $mois <= 6) {
        return "3ème trimestre";
    } else {
        return "Période estivale";
    }
}
?>