<?php
/**
 * Constantes pour le module de messagerie
 */

// Vérifier si les constantes sont déjà définies dans le système central
if (!defined('BASE_URL')) {
    define('BASE_URL', '/~u22405372/SAE/Pronote'); // URL de base de l'application
}

if (!defined('HOME_URL')) {
    define('HOME_URL', BASE_URL . '/accueil/accueil.php');
}

if (!defined('LOGIN_URL')) {
    define('LOGIN_URL', BASE_URL . '/login/index.php');
}

if (!defined('LOGOUT_URL')) {
    define('LOGOUT_URL', BASE_URL . '/login/logout.php');
}

// Constantes spécifiques à la messagerie
$baseUrl = BASE_URL . '/messagerie'; // URL de base du module messagerie

// Dossiers de messagerie
$folders = [
    'reception' => 'Boîte de réception',
    'envoyes' => 'Messages envoyés',
    'archives' => 'Archives',
    'information' => 'Informations',
    'corbeille' => 'Corbeille'
];

// Types de messages
$messageTypes = [
    'standard' => 'Standard',
    'annonce' => 'Annonce',
    'information' => 'Information',
    'question' => 'Question',
    'reponse' => 'Réponse',
    'sondage' => 'Sondage'
];

// Statuts de message
$messageStatuses = [
    'normal' => 'Normal',
    'important' => 'Important',
    'urgent' => 'Urgent'
];

// Types de participants
$participantTypes = [
    'eleve' => 'Élèves',
    'parent' => 'Parents',
    'professeur' => 'Professeurs', 
    'vie_scolaire' => 'Vie Scolaire',
    'administrateur' => 'Administration'
];

// Chemins
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__) . '/');
}
define('UPLOAD_DIR', BASE_PATH . 'assets/uploads/');
define('TEMPLATES_DIR', BASE_PATH . 'templates/');
define('ASSETS_DIR', BASE_PATH . 'assets/');
define('LOGS_DIR', BASE_PATH . 'logs/');

// Créer les répertoires importants s'ils n'existent pas
$directories = [
    UPLOAD_DIR, 
    LOGS_DIR
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}