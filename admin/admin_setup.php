<?php
/**
 * Admin setup script
 * This script ensures all necessary tables and columns for admin features exist
 */

// Include database configuration
require_once __DIR__ . '/../login/config/database.php';

// Require admin authentication
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['profil'] !== 'administrateur') {
    die("Accès non autorisé. Vous devez être connecté en tant qu'administrateur.");
}

$messages = [];

try {
    // Check and create demandes_reinitialisation table if it doesn't exist
    $stmt = $pdo->query("SHOW TABLES LIKE 'demandes_reinitialisation'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS demandes_reinitialisation (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            user_type VARCHAR(30) NOT NULL,
            date_demande DATETIME NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            date_traitement DATETIME NULL,
            admin_id INT NULL
        )");
        $messages[] = "Table 'demandes_reinitialisation' créée avec succès.";
    } else {
        $messages[] = "Table 'demandes_reinitialisation' existe déjà.";
    }
    
    // Check if actif column exists in administrateurs table
    $stmt = $pdo->prepare("SHOW COLUMNS FROM administrateurs LIKE 'actif'");
    $stmt->execute();
    $columnExists = $stmt->fetch();
    
    if (!$columnExists) {
        // Add the column with default value 1 (active)
        $pdo->exec("ALTER TABLE administrateurs ADD COLUMN actif TINYINT(1) NOT NULL DEFAULT 1");
        $messages[] = "Colonne 'actif' ajoutée à la table 'administrateurs'.";
    } else {
        $messages[] = "Colonne 'actif' existe déjà dans la table 'administrateurs'.";
    }
    
    // Fix NULL values in actif column
    $pdo->exec("UPDATE administrateurs SET actif = 1 WHERE actif IS NULL");
    $messages[] = "Valeurs NULL corrigées dans la colonne 'actif'.";
    
    // Make sure there's at least one active admin
    $stmt = $pdo->query("SELECT COUNT(*) FROM administrateurs WHERE actif = 1");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("UPDATE administrateurs SET actif = 1 WHERE id = (SELECT id FROM administrateurs LIMIT 1)");
        $messages[] = "Au moins un administrateur a été activé.";
    }
    
    // Success message
    $status = "success";
    $statusMessage = "Configuration terminée avec succès.";
    
} catch (PDOException $e) {
    $status = "error";
    $statusMessage = "Erreur : " . $e->getMessage();
    $messages[] = $e->getMessage();
}

// Display results
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuration Admin - PRONOTE</title>
    <link rel="stylesheet" href="../assets/css/pronote-theme.css">
    <link rel="stylesheet" href="../login/public/assets/css/pronote-login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            display: block;
            padding: 0;
            margin: 0;
            min-height: 100vh;
            background-color: var(--background-color);
        }
        
        .setup-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 30px;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .status {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-weight: bold;
        }
        
        .status-success {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-error {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .message-list {
            list-style-type: none;
            padding: 0;
        }
        
        .message-list li {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .message-list li:last-child {
            border-bottom: none;
        }
        
        .action-buttons {
            margin-top: 20px;
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <h1><i class="fas fa-tools"></i> Configuration Admin PRONOTE</h1>
        
        <div class="status status-<?= $status ?>">
            <i class="fas fa-<?= $status == 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
            <?= htmlspecialchars($statusMessage) ?>
        </div>
        
        <h2>Détails des opérations :</h2>
        
        <ul class="message-list">
            <?php foreach ($messages as $message): ?>
                <li><i class="fas fa-angle-right"></i> <?= htmlspecialchars($message) ?></li>
            <?php endforeach; ?>
        </ul>
        
        <div class="action-buttons">
            <a href="admin_accounts.php" class="btn btn-primary">Gestion des comptes administrateurs</a>
            <a href="../accueil/accueil.php" class="btn btn-secondary">Retour à l'accueil</a>
        </div>
    </div>
</body>
</html>
