<?php
/**
 * Script de déconnexion pour Pronote - Version intégrée
 */

// Inclure l'API centralisée - chemin uniformisé
require_once __DIR__ . '/../../API/core.php';

// Effectuer la déconnexion via l'API centralisée
logoutUser();

// Redirection automatique gérée par logoutUser()
?>