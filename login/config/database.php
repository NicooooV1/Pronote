<?php
/**
 * DEPRECATED: This file is no longer used
 * Database connections are now handled through the centralized API
 * Please use the centralized API system instead
 */

// Redirection vers l'API centralisée - chemin uniformisé
$apiCorePath = __DIR__ . '/../../API/core.php';
if (file_exists($apiCorePath)) {
    require_once $apiCorePath;
    
    // S'assurer que la connexion est disponible
    try {
        $pdo = getDatabaseConnection();
    } catch (Exception $e) {
        error_log("Erreur de connexion via API centralisée: " . $e->getMessage());
        die("Erreur de configuration. Veuillez contacter l'administrateur.");
    }
} else {
    error_log("API centralisée non trouvée: " . $apiCorePath);
    die("Configuration système manquante. Veuillez réinstaller l'application.");
}
?>