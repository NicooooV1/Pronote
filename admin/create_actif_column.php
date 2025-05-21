<?php
/**
 * Script to add the 'actif' column to the administrateurs table if it doesn't exist
 * Run this once to ensure the database structure is correct
 */

// Include database configuration
require_once __DIR__ . '/../login/config/database.php';

// Check if user is logged in as admin
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['profil'] !== 'administrateur') {
    die("Accès non autorisé. Vous devez être connecté en tant qu'administrateur.");
}

try {
    // Check if column exists
    $stmt = $pdo->prepare("SHOW COLUMNS FROM administrateurs LIKE 'actif'");
    $stmt->execute();
    $columnExists = $stmt->fetch();
    
    if (!$columnExists) {
        // Add the column with default value 1 (active)
        $pdo->exec("ALTER TABLE administrateurs ADD COLUMN actif TINYINT(1) NOT NULL DEFAULT 1");
        echo "La colonne 'actif' a été ajoutée avec succès à la table 'administrateurs'.";
    } else {
        echo "La colonne 'actif' existe déjà dans la table 'administrateurs'.";
    }
    
    // Check for NULL values in actif column and set them to 1
    $pdo->exec("UPDATE administrateurs SET actif = 1 WHERE actif IS NULL");
    echo "<br>Les valeurs NULL dans la colonne 'actif' ont été mises à jour.";
    
    echo "<br><br><a href='admin_accounts.php'>Retour à la gestion des comptes</a>";
} catch (PDOException $e) {
    die("Erreur lors de la modification de la table : " . $e->getMessage());
}
?>
