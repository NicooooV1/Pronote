<?php
/**
 * Authentification pour le module Cahier de Textes
 * Charge l'API centralisée (toutes les fonctions auth viennent du Bridge)
 * + fonction spécifique canManageCahierTextes
 */
require_once __DIR__ . '/../../API/core.php';

// Fonctions spécifiques au module Cahier de Textes
if (!function_exists('canManageCahierTextes')) {
    function canManageCahierTextes() {
        return in_array(getUserRole(), ['administrateur', 'professeur', 'vie_scolaire'], true);
    }
}