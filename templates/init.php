<?php
/**
 * Initialisation pour les templates
 * À inclure au début de chaque page utilisant les templates
 */

// Charger l'API si pas déjà chargée
if (!class_exists('Pronote\Core\Application')) {
    require_once __DIR__ . '/../API/bootstrap.php';
}

// Importer les facades utilisées dans les templates
use Pronote\Core\Facades\Auth;
use Pronote\Core\Facades\CSRF;
use Pronote\Core\Facades\DB;

// Vérifier l'authentification
Auth::requireAuth();

// Démarrer la session si pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
