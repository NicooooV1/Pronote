<?php
/**
 * DEPRECATED: This file is no longer used
 * Database connections are now handled through the centralized API
 */

// Redirection vers l'API centralisée qui lit maintenant le .env
require_once __DIR__ . '/../../API/core.php';

// La variable $pdo est maintenant disponible via l'API centralisée
// Toute la configuration vient du fichier .env à la racine
?>
