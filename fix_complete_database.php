<?php
/**
 * Script de correction complète de la base de données Pronote
 * Ce script vérifie et corrige toutes les structures de tables
 * SUPPRIMER après utilisation
 */

header('Content-Type: text/html; charset=UTF-8');

echo "<!DOCTYPE html><html><head><title>Correction Complète Base de Données</title><meta charset='UTF-8'></head><body>";
echo "<h1>Correction complète de la base de données Pronote</h1>";

// Configuration - MODIFIER avec vos paramètres
$config = [
    'host' => 'localhost',
    'dbname' => 'pronote',
    'user' => 'pronote_user',
    'pass' => 'VOTRE_MOT_DE_PASSE_ICI' // REMPLACER par votre mot de passe
];

try {
    $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['user'], $config['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "<p style='color: green;'>✓ Connexion à la base de données réussie</p>";
    
    // Structures de tables attendues
    $expectedTables = [
        'administrateurs' => [
            'structure' => "CREATE TABLE IF NOT EXISTS `administrateurs` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `nom` varchar(50) NOT NULL,
                `prenom` varchar(50) NOT NULL,
                `mail` varchar(100) NOT NULL,
                `identifiant` varchar(50) NOT NULL,
                `mot_de_passe` varchar(255) NOT NULL,
                `date_creation` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `adresse` varchar(255) DEFAULT NULL,
                `role` varchar(50) NOT NULL DEFAULT 'administrateur',
                `actif` tinyint(1) NOT NULL DEFAULT '1',
                PRIMARY KEY (`id`),
                UNIQUE KEY `identifiant` (`identifiant`),
                UNIQUE KEY `mail` (`mail`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
            'required_columns' => ['id', 'nom', 'prenom', 'mail', 'identifiant', 'mot_de_passe', 'date_creation', 'adresse', 'role', 'actif']
        ],
        'eleves' => [
            'structure' => "CREATE TABLE IF NOT EXISTS `eleves` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `nom` varchar(100) NOT NULL,
                `prenom` varchar(100) NOT NULL,
                `date_naissance` date NOT NULL,
                `classe` varchar(50) NOT NULL,
                `lieu_naissance` varchar(100) NOT NULL,
                `adresse` varchar(255) NOT NULL,
                `mail` varchar(150) NOT NULL,
                `telephone` varchar(20) DEFAULT NULL,
                `identifiant` varchar(50) NOT NULL,
                `mot_de_passe` varchar(255) NOT NULL,
                `date_creation` datetime DEFAULT current_timestamp(),
                PRIMARY KEY (`id`),
                UNIQUE KEY `mail` (`mail`),
                UNIQUE KEY `identifiant` (`identifiant`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
            'required_columns' => ['id', 'nom', 'prenom', 'date_naissance', 'classe', 'lieu_naissance', 'adresse', 'mail', 'identifiant', 'mot_de_passe', 'date_creation']
        ],
        'professeurs' => [
            'structure' => "CREATE TABLE IF NOT EXISTS `professeurs` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `nom` varchar(100) NOT NULL,
                `prenom` varchar(100) NOT NULL,
                `mail` varchar(150) NOT NULL,
                `adresse` varchar(255) NOT NULL,
                `telephone` varchar(20) DEFAULT NULL,
                `identifiant` varchar(50) NOT NULL,
                `mot_de_passe` varchar(255) NOT NULL,
                `professeur_principal` varchar(50) NOT NULL DEFAULT 'non',
                `matiere` varchar(100) NOT NULL,
                `date_creation` datetime DEFAULT current_timestamp(),
                PRIMARY KEY (`id`),
                UNIQUE KEY `mail` (`mail`),
                UNIQUE KEY `identifiant` (`identifiant`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
            'required_columns' => ['id', 'nom', 'prenom', 'mail', 'adresse', 'identifiant', 'mot_de_passe', 'professeur_principal', 'matiere', 'date_creation']
        ],
        'parents' => [
            'structure' => "CREATE TABLE IF NOT EXISTS `parents` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `nom` varchar(100) NOT NULL,
                `prenom` varchar(100) NOT NULL,
                `mail` varchar(150) NOT NULL,
                `adresse` varchar(255) NOT NULL,
                `telephone` varchar(20) DEFAULT NULL,
                `metier` varchar(100) DEFAULT NULL,
                `identifiant` varchar(50) NOT NULL,
                `mot_de_passe` varchar(255) NOT NULL,
                `est_parent_eleve` enum('oui','non') NOT NULL DEFAULT 'non',
                `date_creation` datetime DEFAULT current_timestamp(),
                PRIMARY KEY (`id`),
                UNIQUE KEY `mail` (`mail`),
                UNIQUE KEY `identifiant` (`identifiant`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
            'required_columns' => ['id', 'nom', 'prenom', 'mail', 'adresse', 'identifiant', 'mot_de_passe', 'est_parent_eleve', 'date_creation']
        ],
        'vie_scolaire' => [
            'structure' => "CREATE TABLE IF NOT EXISTS `vie_scolaire` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `nom` varchar(100) NOT NULL,
                `prenom` varchar(100) NOT NULL,
                `mail` varchar(150) NOT NULL,
                `telephone` varchar(20) DEFAULT NULL,
                `identifiant` varchar(50) NOT NULL,
                `mot_de_passe` varchar(255) NOT NULL,
                `est_CPE` enum('oui','non') NOT NULL DEFAULT 'non',
                `est_infirmerie` enum('oui','non') NOT NULL DEFAULT 'non',
                `date_creation` datetime DEFAULT current_timestamp(),
                PRIMARY KEY (`id`),
                UNIQUE KEY `mail` (`mail`),
                UNIQUE KEY `identifiant` (`identifiant`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;",
            'required_columns' => ['id', 'nom', 'prenom', 'mail', 'identifiant', 'mot_de_passe', 'est_CPE', 'est_infirmerie', 'date_creation']
        ]
    ];
    
    $results = [];
    $corrections = [];
    
    foreach ($expectedTables as $tableName => $tableInfo) {
        echo "<h2>Vérification de la table: {$tableName}</h2>";
        
        // Vérifier si la table existe
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$tableName]);
        $tableExists = $stmt->rowCount() > 0;
        
        if (!$tableExists) {
            // Créer la table complète
            try {
                $pdo->exec($tableInfo['structure']);
                $corrections[] = "✓ Table '{$tableName}' créée";
                echo "<p style='color: green;'>✓ Table '{$tableName}' créée avec succès</p>";
            } catch (PDOException $e) {
                $corrections[] = "✗ Erreur création table '{$tableName}': " . $e->getMessage();
                echo "<p style='color: red;'>✗ Erreur lors de la création de '{$tableName}': " . $e->getMessage() . "</p>";
            }
        } else {
            // Table existe, vérifier les colonnes
            $stmt = $pdo->prepare("DESCRIBE {$tableName}");
            $stmt->execute();
            $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            echo "<p>Colonnes existantes: " . implode(', ', $existingColumns) . "</p>";
            
            $missingColumns = array_diff($tableInfo['required_columns'], $existingColumns);
            
            if (!empty($missingColumns)) {
                echo "<p style='color: orange;'>Colonnes manquantes: " . implode(', ', $missingColumns) . "</p>";
                
                // Ajouter les colonnes manquantes selon la table
                if ($tableName === 'administrateurs') {
                    foreach ($missingColumns as $column) {
                        try {
                            switch ($column) {
                                case 'adresse':
                                    $pdo->exec("ALTER TABLE administrateurs ADD COLUMN `adresse` varchar(255) DEFAULT NULL");
                                    $corrections[] = "✓ Colonne 'adresse' ajoutée à administrateurs";
                                    break;
                                case 'role':
                                    $pdo->exec("ALTER TABLE administrateurs ADD COLUMN `role` varchar(50) NOT NULL DEFAULT 'administrateur'");
                                    $corrections[] = "✓ Colonne 'role' ajoutée à administrateurs";
                                    break;
                                case 'actif':
                                    $pdo->exec("ALTER TABLE administrateurs ADD COLUMN `actif` tinyint(1) NOT NULL DEFAULT '1'");
                                    $corrections[] = "✓ Colonne 'actif' ajoutée à administrateurs";
                                    break;
                            }
                        } catch (PDOException $e) {
                            $corrections[] = "✗ Erreur ajout colonne '{$column}' à '{$tableName}': " . $e->getMessage();
                        }
                    }
                }
                
                // Ajuster les types de colonnes si nécessaire
                if ($tableName === 'administrateurs') {
                    try {
                        // Modifier les colonnes pour correspondre au script d'installation
                        $pdo->exec("ALTER TABLE administrateurs 
                            MODIFY COLUMN `nom` varchar(50) NOT NULL,
                            MODIFY COLUMN `prenom` varchar(50) NOT NULL,
                            MODIFY COLUMN `mail` varchar(100) NOT NULL,
                            MODIFY COLUMN `date_creation` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP");
                        $corrections[] = "✓ Types de colonnes ajustés pour administrateurs";
                    } catch (PDOException $e) {
                        $corrections[] = "Note: Ajustement des types de colonnes échoué (peut être ignoré): " . $e->getMessage();
                    }
                }
            } else {
                echo "<p style='color: green;'>✓ Toutes les colonnes requises sont présentes</p>";
            }
        }
    }
    
    // Vérifier les autres tables importantes
    $otherTables = ['notes', 'absences', 'devoirs', 'cahier_texte', 'evenements', 'messages'];
    
    echo "<h2>Vérification des autres tables importantes</h2>";
    foreach ($otherTables as $table) {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        $exists = $stmt->rowCount() > 0;
        
        if ($exists) {
            echo "<p style='color: green;'>✓ Table '{$table}' existe</p>";
        } else {
            echo "<p style='color: orange;'>⚠ Table '{$table}' manquante (sera créée au besoin par l'application)</p>";
        }
    }
    
    echo "<div style='background-color: #d4edda; padding: 15px; border-radius: 5px; margin-top: 20px;'>";
    echo "<h3 style='color: #155724;'>Résumé des corrections</h3>";
    if (!empty($corrections)) {
        echo "<ul>";
        foreach ($corrections as $correction) {
            $color = strpos($correction, '✗') === 0 ? 'red' : 'green';
            echo "<li style='color: {$color};'>{$correction}</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>Aucune correction nécessaire. La base de données est correcte.</p>";
    }
    echo "<p><strong>Vous pouvez maintenant retourner à <a href='install.php'>l'installation</a></strong></p>";
    echo "<p><strong>Important :</strong> Supprimez ce fichier après utilisation.</p>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div style='background-color: #f8d7da; padding: 15px; border-radius: 5px;'>";
    echo "<h3 style='color: #721c24;'>Erreur de base de données</h3>";
    echo "<p>Erreur : " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Vérifiez les paramètres de connexion dans ce script.</p>";
    echo "</div>";
}

echo "</body></html>";
?>
