<?php
/**
 * Script pour ajouter les index manquants à la base de données
 * Ce script est appelé depuis le diagnostic.php pour optimiser la base de données
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

// Définir les index à ajouter selon les tables
$indexesToAdd = [
    'notes' => [
        'idx_eleve_matiere' => [
            'columns' => ['id_eleve', 'id_matiere'],
            'type' => '',
            'sql' => 'CREATE INDEX idx_eleve_matiere ON notes (id_eleve, id_matiere)'
        ],
        'idx_trimestre' => [
            'columns' => ['id_trimestre'],
            'type' => '',
            'sql' => 'CREATE INDEX idx_trimestre ON notes (id_trimestre)'
        ]
    ],
    'absences' => [
        'idx_eleve_date' => [
            'columns' => ['id_eleve', 'date_debut'],
            'type' => '',
            'sql' => 'CREATE INDEX idx_eleve_date ON absences (id_eleve, date_debut)'
        ],
        'idx_justifiee' => [
            'columns' => ['justifiee'],
            'type' => '',
            'sql' => 'CREATE INDEX idx_justifiee ON absences (justifiee)'
        ]
    ],
    'evenements' => [
        'idx_date' => [
            'columns' => ['date_debut', 'date_fin'],
            'type' => '',
            'sql' => 'CREATE INDEX idx_date ON evenements (date_debut, date_fin)'
        ]
    ],
    'messages' => [
        'idx_expediteur' => [
            'columns' => ['id_expediteur'],
            'type' => '',
            'sql' => 'CREATE INDEX idx_expediteur ON messages (id_expediteur)'
        ],
        'idx_destinataire' => [
            'columns' => ['id_destinataire'],
            'type' => '',
            'sql' => 'CREATE INDEX idx_destinataire ON messages (id_destinataire)'
        ]
    ]
];

// Fonction pour vérifier si un index existe déjà
function indexExists($pdo, $table, $indexName) {
    $sql = "SHOW INDEX FROM `{$table}` WHERE Key_name = :indexName";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['indexName' => $indexName]);
    return $stmt->rowCount() > 0;
}

// Se connecter à la base de données
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ATTR_ERRMODE,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ];
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    
    // Variables pour suivre les résultats
    $indexesAdded = [];
    $indexesSkipped = [];
    $errors = [];
    
    // Parcourir chaque table et ajouter les index manquants
    foreach ($indexesToAdd as $table => $indexes) {
        // Vérifier si la table existe
        try {
            $tableCheck = $pdo->prepare("SHOW TABLES LIKE :tableName");
            $tableCheck->execute(['tableName' => $table]);
            $tableExists = $tableCheck->rowCount() > 0;
            
            if (!$tableExists) {
                $errors[] = "Table '{$table}' n'existe pas.";
                continue;
            }
            
            // Ajouter les index pour cette table
            foreach ($indexes as $indexName => $indexInfo) {
                try {
                    // Vérifier si l'index existe déjà
                    if (indexExists($pdo, $table, $indexName)) {
                        $indexesSkipped[] = "Index '{$indexName}' existe déjà sur la table '{$table}'.";
                        continue;
                    }
                    
                    // Ajouter l'index
                    $pdo->exec($indexInfo['sql']);
                    $indexesAdded[] = "Index '{$indexName}' ajouté à la table '{$table}'.";
                    
                } catch (PDOException $e) {
                    $errors[] = "Erreur lors de l'ajout de l'index '{$indexName}' à la table '{$table}': " . $e->getMessage();
                }
            }
            
        } catch (PDOException $e) {
            $errors[] = "Erreur lors de la vérification de la table '{$table}': " . $e->getMessage();
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
    <title>Ajout d'index - Résultats</title>
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
    <h1>Résultats de l'ajout d'index</h1>
    
    <?php if (!empty($indexesAdded)): ?>
        <h2 class="success">Index ajoutés (<?= count($indexesAdded) ?>)</h2>
        <ul>
            <?php foreach ($indexesAdded as $message): ?>
                <li class="success"><?= htmlspecialchars($message) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    
    <?php if (!empty($indexesSkipped)): ?>
        <h2 class="warning">Index ignorés (déjà existants) (<?= count($indexesSkipped) ?>)</h2>
        <ul>
            <?php foreach ($indexesSkipped as $message): ?>
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
    
    <?php if (empty($indexesAdded) && empty($errors)): ?>
        <p>Aucun nouvel index n'a été ajouté. La base de données est déjà optimisée.</p>
    <?php endif; ?>
    
    <a href="../../diagnostic.php" class="button">Retour au diagnostic</a>
</body>
</html>
