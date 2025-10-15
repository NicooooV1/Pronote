<?php
/**
 * Wrapper d'authentification - Redirige vers auth_central.php
 * Conservé pour compatibilité
 */

// Charger le système d'authentification principal
require_once __DIR__ . '/../auth_central.php';

// Ce fichier sert uniquement de wrapper pour la compatibilité
// Toutes les fonctions sont définies dans auth_central.php
