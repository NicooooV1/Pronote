<?php
// Inclure UNIQUEMENT l'API centralisée
require_once __DIR__ . '/../API/core.php';

// Vérifier l'authentification
requireAuth();

// Récupérer l'utilisateur actuel
$user = getCurrentUser();

// Récupérer la connexion à la base de données UNIQUEMENT via l'API
try {
    $pdo = getDatabaseConnection();
} catch (Exception $e) {
    logError("Erreur de connexion DB dans notes: " . $e->getMessage());
    die("Erreur de connexion à la base de données");
}

// Redirection vers l'interface principale des notes
header("Location: interface_notes.php");
exit;
?>