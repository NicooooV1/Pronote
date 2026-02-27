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
    define('LOGIN_URL', BASE_URL . '/login/public/index.php');
}

if (!defined('LOGOUT_URL')) {
    define('LOGOUT_URL', BASE_URL . '/login/public/logout.php');
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

// Types d'utilisateurs
define('USER_TYPE_ELEVE', 'eleve');
define('USER_TYPE_PARENT', 'parent');
define('USER_TYPE_PROFESSEUR', 'professeur');
define('USER_TYPE_VIE_SCOLAIRE', 'vie_scolaire');
define('USER_TYPE_ADMINISTRATEUR', 'administrateur');

// Types de messages
define('MESSAGE_TYPE_STANDARD', 'standard');
define('MESSAGE_TYPE_ANNONCE', 'annonce');
define('MESSAGE_TYPE_INFORMATION', 'information');

// Niveaux d'importance
define('IMPORTANCE_NORMAL', 'normal');
define('IMPORTANCE_IMPORTANT', 'important');
define('IMPORTANCE_URGENT', 'urgent');

// Dossiers de messagerie
define('FOLDER_RECEPTION', 'reception');
define('FOLDER_ENVOYES', 'envoyes');
define('FOLDER_ARCHIVES', 'archives');
define('FOLDER_INFORMATION', 'information');
define('FOLDER_CORBEILLE', 'corbeille');

// Redirection login - suppression de la redéfinition
// define('LOGIN_URL', '/login/public/index.php'); - commenté pour éviter une redéfinition

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