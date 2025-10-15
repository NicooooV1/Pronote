<?php
/**
 * Wrapper pour la classe Database
 * Utilise le singleton défini dans database.php
 */

require_once __DIR__ . '/../database.php';

// Alias pour compatibilité
if (!class_exists('Database')) {
    class_alias('Database', 'DatabaseWrapper');
}
