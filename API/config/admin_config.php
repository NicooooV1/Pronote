<?php
/**
 * Configuration de la gestion des comptes administrateurs
 * Ce fichier définit les règles de gestion des comptes administrateurs
 */

// Vérifier si la création de nouveaux comptes administrateurs est autorisée
if (!defined('ALLOW_NEW_ADMIN_ACCOUNTS')) {
    // La présence du fichier admin.lock indique qu'un administrateur a déjà été créé
    // et qu'aucun nouveau compte admin ne peut être créé via l'interface d'inscription
    define('ALLOW_NEW_ADMIN_ACCOUNTS', !file_exists(__DIR__ . '/../../admin.lock'));
}

// Les administrateurs existants peuvent toujours être modifiés ou supprimés
if (!defined('ALLOW_ADMIN_MANAGEMENT')) {
    define('ALLOW_ADMIN_MANAGEMENT', true);
}

/**
 * Vérifie si la création de nouveaux comptes administrateur est autorisée
 * @return bool True si la création est autorisée
 */
function isNewAdminAccountsAllowed() {
    return ALLOW_NEW_ADMIN_ACCOUNTS;
}

/**
 * Vérifie si la gestion des comptes administrateur existants est autorisée
 * @return bool True si la gestion est autorisée
 */
function isAdminManagementAllowed() {
    return ALLOW_ADMIN_MANAGEMENT;
}

/**
 * Vérifie si un mot de passe respecte les critères de sécurité
 * @param string $password Mot de passe à vérifier
 * @return array ['valid' => bool, 'errors' => array] Résultat de la validation
 */
function validateStrongPassword($password) {
    $errors = [];
    $result = ['valid' => true, 'errors' => []];
    
    if (strlen($password) < 12) {
        $errors[] = "Le mot de passe doit contenir au moins 12 caractères";
        $result['valid'] = false;
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Le mot de passe doit contenir au moins une lettre majuscule";
        $result['valid'] = false;
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Le mot de passe doit contenir au moins une lettre minuscule";
        $result['valid'] = false;
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Le mot de passe doit contenir au moins un chiffre";
        $result['valid'] = false;
    }
    
    if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
        $errors[] = "Le mot de passe doit contenir au moins un caractère spécial";
        $result['valid'] = false;
    }
    
    $result['errors'] = $errors;
    return $result;
}

/**
 * Configuration spécifique pour l'administration
 */

if (!defined('PRONOTE_ADMIN_CONFIG_LOADED')) {
    define('PRONOTE_ADMIN_CONFIG_LOADED', true);
    
    // Chemins d'administration
    if (!defined('ADMIN_DIR')) define('ADMIN_DIR', dirname(dirname(__DIR__)) . '/admin');
    if (!defined('ADMIN_URL')) define('ADMIN_URL', BASE_URL . '/admin');
    
    // Paramètres de sécurité admin
    if (!defined('ADMIN_SESSION_TIMEOUT')) define('ADMIN_SESSION_TIMEOUT', 1800); // 30 minutes
    if (!defined('ADMIN_MAX_LOGIN_ATTEMPTS')) define('ADMIN_MAX_LOGIN_ATTEMPTS', 3);
    if (!defined('ADMIN_LOCKOUT_TIME')) define('ADMIN_LOCKOUT_TIME', 1800); // 30 minutes
    
    // Journalisation administrative
    if (!defined('ADMIN_LOG_ENABLED')) define('ADMIN_LOG_ENABLED', true);
    if (!defined('ADMIN_LOG_ALL_ACTIONS')) define('ADMIN_LOG_ALL_ACTIONS', true);
    
    // Modules d'administration
    if (!defined('ADMIN_MODULES')) {
        define('ADMIN_MODULES', [
            'users' => 'Gestion des utilisateurs',
            'database' => 'Gestion de la base de données',
            'security' => 'Configuration de sécurité',
            'logs' => 'Consultation des logs',
            'backup' => 'Sauvegarde et restauration',
            'settings' => 'Paramètres généraux'
        ]);
    }
    
    // Permissions administrateur
    if (!defined('ADMIN_CAN_CREATE_USERS')) define('ADMIN_CAN_CREATE_USERS', true);
    if (!defined('ADMIN_CAN_DELETE_USERS')) define('ADMIN_CAN_DELETE_USERS', true);
    if (!defined('ADMIN_CAN_MODIFY_GRADES')) define('ADMIN_CAN_MODIFY_GRADES', true);
    if (!defined('ADMIN_CAN_VIEW_LOGS')) define('ADMIN_CAN_VIEW_LOGS', true);
    if (!defined('ADMIN_CAN_EXPORT_DATA')) define('ADMIN_CAN_EXPORT_DATA', true);
    
    // Limites
    if (!defined('ADMIN_MAX_BULK_OPERATIONS')) define('ADMIN_MAX_BULK_OPERATIONS', 100);
    if (!defined('ADMIN_SESSION_TIMEOUT')) define('ADMIN_SESSION_TIMEOUT', 7200); // 2 heures
    
    // Notifications
    if (!defined('ADMIN_EMAIL_NOTIFICATIONS')) define('ADMIN_EMAIL_NOTIFICATIONS', true);
    if (!defined('ADMIN_NOTIFICATION_EMAIL')) define('ADMIN_NOTIFICATION_EMAIL', 'admin@pronote.local');
}

/**
 * Configuration spécifique aux administrateurs
 */

if (!defined('PRONOTE_ADMIN_CONFIG_LOADED')) {
    define('PRONOTE_ADMIN_CONFIG_LOADED', true);
}

// Permissions administrateur
if (!defined('ADMIN_CAN_MANAGE_USERS')) define('ADMIN_CAN_MANAGE_USERS', true);
if (!defined('ADMIN_CAN_MANAGE_CLASSES')) define('ADMIN_CAN_MANAGE_CLASSES', true);
if (!defined('ADMIN_CAN_MANAGE_SUBJECTS')) define('ADMIN_CAN_MANAGE_SUBJECTS', true);
if (!defined('ADMIN_CAN_VIEW_LOGS')) define('ADMIN_CAN_VIEW_LOGS', true);
if (!defined('ADMIN_CAN_EXPORT_DATA')) define('ADMIN_CAN_EXPORT_DATA', true);

// Limites administrateur
if (!defined('ADMIN_MAX_UPLOAD_SIZE')) define('ADMIN_MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10 MB
if (!defined('ADMIN_SESSION_TIMEOUT')) define('ADMIN_SESSION_TIMEOUT', 7200); // 2 heures

// Notifications administrateur
if (!defined('ADMIN_EMAIL_NOTIFICATIONS')) define('ADMIN_EMAIL_NOTIFICATIONS', true);
if (!defined('ADMIN_SECURITY_ALERTS')) define('ADMIN_SECURITY_ALERTS', true);
