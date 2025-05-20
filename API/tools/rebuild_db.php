<?php
/**
 * Outil de reconstruction des tables manquantes dans la base de données
 * Cet outil est appelé depuis le diagnostic.php pour recréer les tables essentielles
 */

// Ne pas afficher les erreurs directement
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Démarrer la session pour vérifier l'authentification
session_start();

// Vérifier si l'utilisateur est administrateur
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['profil']) || $_SESSION['user']['profil'] !== 'administrateur') {
    http_response_code(403);
    die('Accès refusé. Seuls les administrateurs peuvent exécuter cet outil.');
}

// Charger la configuration
$configFile = __DIR__ . '/../../API/config/env.php';
if (!file_exists($configFile)) {
    die('Fichier de configuration non trouvé. Veuillez d\'abord installer l\'application.');
}

require_once $configFile;

// Vérifier si les constantes de base de données sont définies
if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER') || !defined('DB_PASS')) {
    die('Configuration de base de données incomplète.');
}

// Liste des tables essentielles avec leurs structures SQL
$essentialTables = [
    'administrateurs' => "CREATE TABLE IF NOT EXISTS `administrateurs` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `nom` varchar(50) NOT NULL,
        `prenom` varchar(50) NOT NULL,
        `mail` varchar(100) NOT NULL,
        `identifiant` varchar(50) NOT NULL,
        `mot_de_passe` varchar(255) NOT NULL,
        `date_creation` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `adresse` varchar(255) DEFAULT NULL,
        `role` varchar(50) NOT NULL DEFAULT 'administration',
        `actif` tinyint(1) NOT NULL DEFAULT '1',
        PRIMARY KEY (`id`),
        UNIQUE KEY `identifiant` (`identifiant`),
        UNIQUE KEY `mail` (`mail`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    
    'eleves' => "CREATE TABLE IF NOT EXISTS `eleves` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `nom` varchar(50) NOT NULL,
        `prenom` varchar(50) NOT NULL,
        `date_naissance` date DEFAULT NULL,
        `mail` varchar(100) DEFAULT NULL,
        `identifiant` varchar(50) NOT NULL,
        `mot_de_passe` varchar(255) NOT NULL,
        `id_classe` int(11) DEFAULT NULL,
        `actif` tinyint(1) NOT NULL DEFAULT '1',
        `date_creation` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `identifiant` (`identifiant`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    
    'professeurs' => "CREATE TABLE IF NOT EXISTS `professeurs` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `nom` varchar(50) NOT NULL,
        `prenom` varchar(50) NOT NULL,
        `mail` varchar(100) DEFAULT NULL,
        `identifiant` varchar(50) NOT NULL,
        `mot_de_passe` varchar(255) NOT NULL,
        `actif` tinyint(1) NOT NULL DEFAULT '1',
        `date_creation` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `identifiant` (`identifiant`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    
    'vie_scolaire' => "CREATE TABLE IF NOT EXISTS `vie_scolaire` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `nom` varchar(50) NOT NULL,
        `prenom` varchar(50) NOT NULL,
        `mail` varchar(100) DEFAULT NULL,
        `identifiant` varchar(50) NOT NULL,
        `mot_de_passe` varchar(255) NOT NULL,
        `role` varchar(50) NOT NULL DEFAULT 'vie_scolaire',
        `actif` tinyint(1) NOT NULL DEFAULT '1',
        `date_creation` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `identifiant` (`identifiant`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    
    'classes' => "CREATE TABLE IF NOT EXISTS `classes` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `nom` varchar(50) NOT NULL,
        `niveau` varchar(20) DEFAULT NULL,
        `annee_scolaire` varchar(9) DEFAULT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    
    'matieres' => "CREATE TABLE IF NOT EXISTS `matieres` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `code` varchar(10) NOT NULL,
        `nom` varchar(50) NOT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    
    'notes' => "CREATE TABLE IF NOT EXISTS `notes` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `id_eleve` int(11) NOT NULL,
        `id_matiere` int(11) NOT NULL,
        `id_professeur` int(11) NOT NULL,
        `note` decimal(5,2) NOT NULL,
        `note_sur` decimal(5,2) NOT NULL DEFAULT '20.00',
        `coefficient` decimal(3,1) NOT NULL DEFAULT '1.0',
        `commentaire` text,
        `date_creation` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `date_evaluation` date DEFAULT NULL,
        `id_trimestre` int(11) DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_eleve_matiere` (`id_eleve`, `id_matiere`),
        KEY `idx_trimestre` (`id_trimestre`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    
    'absences' => "CREATE TABLE IF NOT EXISTS `absences` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `id_eleve` int(11) NOT NULL,
        `date_debut` datetime NOT NULL,
        `date_fin` datetime DEFAULT NULL,
        `motif` varchar(255) DEFAULT NULL,
        `justifiee` tinyint(1) NOT NULL DEFAULT '0',
        `id_justificatif` int(11) DEFAULT NULL,
        `id_declarant` int(11) DEFAULT NULL,
        `type_declarant` varchar(20) DEFAULT NULL,
        `commentaire` text,
        `date_creation` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_eleve_date` (`id_eleve`, `date_debut`),
        KEY `idx_justifiee` (`justifiee`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    
    'evenements' => "CREATE TABLE IF NOT EXISTS `evenements` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `titre` varchar(100) NOT NULL,
        `description` text,
        `date_debut` datetime NOT NULL,
        `date_fin` datetime NOT NULL,
        `lieu` varchar(100) DEFAULT NULL,
        `type` varchar(50) DEFAULT NULL,
        `id_createur` int(11) DEFAULT NULL,
        `type_createur` varchar(20) DEFAULT NULL,
        `public` tinyint(1) NOT NULL DEFAULT '1',
        `date_creation` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_date` (`date_debut`, `date_fin`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    
    'messages' => "CREATE TABLE IF NOT EXISTS `messages` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `objet` varchar(255) NOT NULL,
        `contenu` text NOT NULL,
        `id_expediteur` int(11) NOT NULL,
        `type_expediteur` varchar(20) NOT NULL,
        `id_destinataire` int(11) NOT NULL,
        `type_destinataire` varchar(20) NOT NULL,
        `lu` tinyint(1) NOT NULL DEFAULT '0',
        `date_envoi` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `date_lecture` datetime DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_expediteur` (`id_expediteur`),
        KEY `idx_destinataire` (`id_destinataire`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    
    'justificatifs' => "CREATE TABLE IF NOT EXISTS `justificatifs` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `id_absence` int(11) NOT NULL,
        `id_eleve` int(11) NOT NULL,
        `motif` varchar(255) NOT NULL,
        `chemin_fichier` varchar(255) DEFAULT NULL,
        `date_creation` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `validee` tinyint(1) NOT NULL DEFAULT '0',
        `date_validation` datetime DEFAULT NULL,
        `id_validateur` int(11) DEFAULT NULL,
        `type_validateur` varchar(20) DEFAULT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
];

// Se connecter à la base de données
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ];
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    
    // Variables pour suivre les résultats
    $tablesCreated = [];
    $tablesSkipped = [];
    $errors = [];
    
    // Parcourir chaque table et la créer si nécessaire
    foreach ($essentialTables as $tableName => $createTableSQL) {
        try {
            // Vérifier si la table existe
            $tableCheck = $pdo->prepare("SHOW TABLES LIKE :tableName");
            $tableCheck->execute(['tableName' => $tableName]);
            $tableExists = $tableCheck->rowCount() > 0;
            
            if ($tableExists) {
                $tablesSkipped[] = "La table '{$tableName}' existe déjà.";
                continue;
            }
            
            // Créer la table
            $pdo->exec($createTableSQL);
            $tablesCreated[] = "Table '{$tableName}' créée avec succès.";
            
        } catch (PDOException $e) {
            $errors[] = "Erreur lors de la création de la table '{$tableName}': " . $e->getMessage();
        }
    }
    
} catch (PDOException $e) {
    die('Erreur de connexion à la base de données: ' . $e->getMessage());
}

// Afficher les résultats au format HTML
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reconstruction de Base de Données - Résultats</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            line-height: 1.6;
        }
        h1, h2 {
            color: #333;
        }
        .success {
            color: green;
        }
        .warning {
            color: orange;
        }
        .error {
            color: red;
        }
        ul {
            padding-left: 20px;
        }
        a.button {
            display: inline-block;
            background-color: #4CAF50;
            color: white;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 20px;
        }
        a.button:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
    <h1>Résultats de la reconstruction de la base de données</h1>
    
    <?php if (!empty($tablesCreated)): ?>
        <h2 class="success">Tables créées (<?= count($tablesCreated) ?>)</h2>
        <ul>
            <?php foreach ($tablesCreated as $message): ?>
                <li class="success"><?= htmlspecialchars($message) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    
    <?php if (!empty($tablesSkipped)): ?>
        <h2 class="warning">Tables ignorées (déjà existantes) (<?= count($tablesSkipped) ?>)</h2>
        <ul>
            <?php foreach ($tablesSkipped as $message): ?>
                <li class="warning"><?= htmlspecialchars($message) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    
    <?php if (!empty($errors)): ?>
        <h2 class="error">Erreurs (<?= count($errors) ?>)</h2>
        <ul>
            <?php foreach ($errors as $message): ?>
                <li class="error"><?= htmlspecialchars($message) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    
    <?php if (empty($tablesCreated) && empty($errors)): ?>
        <p>Aucune table n'a été créée. Toutes les tables nécessaires existent déjà.</p>
    <?php endif; ?>
    
    <a href="../../diagnostic.php" class="button">Retour au diagnostic</a>
</body>
</html>
