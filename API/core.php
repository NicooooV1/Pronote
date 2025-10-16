<?php
/**
 * Point d'entrée principal de l'API Pronote
 */

// Charger le bootstrap
require_once __DIR__ . '/bootstrap.php';

// Démarrer l'application
$app->boot();

/**
 * Fonction helper pour vérifier si un utilisateur est connecté
 */
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return app('auth')->check();
    }
}

/**
 * Fonction helper pour récupérer l'utilisateur actuel
 */
if (!function_exists('getCurrentUser')) {
    function getCurrentUser() {
        return app('auth')->user();
    }
}

/**
 * Fonction helper pour vérifier l'authentification
 */
if (!function_exists('requireAuth')) {
    function requireAuth() {
        $user = getCurrentUser();
        
        if (!$user) {
            $loginUrl = '/Pronote/login/public/index.php';
            header('Location: ' . $loginUrl);
            exit;
        }
        
        return $user;
    }
}

/**
 * Fonction helper pour vérifier un rôle spécifique
 */
if (!function_exists('requireRole')) {
    function requireRole($role) {
        $user = requireAuth();
        
        if ($user['type'] !== $role) {
            http_response_code(403);
            die("Accès refusé. Vous n'avez pas les permissions nécessaires.");
        }
        
        return $user;
    }
}

/**
 * Fonction helper pour rediriger
 */
if (!function_exists('redirect')) {
    function redirect($path) {
        // Utiliser APP_URL pour la redirection web
        $baseUrl = rtrim(env('APP_URL', '/Pronote'), '/');
        // S'assurer que $path commence par un /
        if ($path[0] !== '/') {
            $path = '/' . $path;
        }
        header('Location: ' . $baseUrl . $path);
        exit;
    }
}

/**
 * Fonction helper pour authentifier un utilisateur
 */
if (!function_exists('authenticateUser')) {
    function authenticateUser($username, $password, $userType, $rememberMe = false) {
        try {
            $auth = app('auth');
            
            $credentials = [
                'email' => $username,
                'password' => $password,
                'type' => $userType
            ];
            
            if ($auth->attempt($credentials)) {
                $user = $auth->user();
                return [
                    'success' => true,
                    'user' => $user,
                    'message' => 'Connexion réussie'
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Identifiant ou mot de passe incorrect'
            ];
        } catch (Exception $e) {
            error_log("Authentication error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors de l\'authentification'
            ];
        }
    }
}

/**
 * Fonction helper pour déconnecter un utilisateur
 */
if (!function_exists('logoutUser')) {
    function logoutUser() {
        app('auth')->logout();
        redirect('/login/public/index.php');
    }
}

/**
 * Fonction helper pour créer un utilisateur
 */
if (!function_exists('createUser')) {
    function createUser($profil, $userData) {
        try {
            $userService = app()->make('API\Services\UserService');
            return $userService->create($profil, $userData);
        } catch (Exception $e) {
            error_log("User creation error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors de la création de l\'utilisateur'
            ];
        }
    }
}

/**
 * Fonction helper pour changer le mot de passe
 */
if (!function_exists('changePassword')) {
    function changePassword($userId, $newPassword) {
        try {
            $userService = app()->make('API\Services\UserService');
            return $userService->changePassword($userId, $newPassword);
        } catch (Exception $e) {
            error_log("Password change error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors du changement de mot de passe'
            ];
        }
    }
}

/**
 * Fonction helper pour obtenir les données de l'établissement
 */
if (!function_exists('getEtablissementData')) {
    function getEtablissementData() {
        try {
            $etablissementService = app()->make('API\Services\EtablissementService');
            return $etablissementService->getData();
        } catch (Exception $e) {
            error_log("Etablissement data error: " . $e->getMessage());
            return [
                'classes' => [],
                'matieres' => [],
                'periodes' => []
            ];
        }
    }
}

/**
 * Fonction helper pour trouver un utilisateur
 */
if (!function_exists('findUserByCredentials')) {
    function findUserByCredentials($username, $email, $phone, $userType) {
        try {
            $userService = app()->make('API\Services\UserService');
            return $userService->findByCredentials($username, $email, $phone, $userType);
        } catch (Exception $e) {
            error_log("Find user error: " . $e->getMessage());
            return null;
        }
    }
}

/**
 * Fonction helper pour créer une demande de réinitialisation
 */
if (!function_exists('createResetRequest')) {
    function createResetRequest($userId, $userType) {
        try {
            $userService = app()->make('API\Services\UserService');
            return $userService->createResetRequest($userId, $userType);
        } catch (Exception $e) {
            error_log("Reset request error: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Fonction helper pour valider les données utilisateur
 */
if (!function_exists('validateUserData')) {
    function validateUserData() {
        if (!isset($_SESSION['user'])) {
            return false;
        }
        
        try {
            $userProvider = app('auth.provider');
            $user = $userProvider->retrieveById(
                $_SESSION['user']['id'],
                $_SESSION['user']['type']
            );
            
            return $user !== null;
        } catch (Exception $e) {
            error_log("User validation error: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Fonction helper pour obtenir le message d'erreur
 */
if (!function_exists('getErrorMessage')) {
    function getErrorMessage() {
        return $_SESSION['error_message'] ?? 'Une erreur est survenue';
    }
}

/**
 * Fonction helper pour récupérer la connexion à la base de données
 */
if (!function_exists('getDatabaseConnection')) {
    function getDatabaseConnection() {
        return app('db')->getConnection();
    }
}

/**
 * Fonction helper pour logger des erreurs
 */
if (!function_exists('logError')) {
    function logError($message, $context = []) {
        error_log($message . (!empty($context) ? ' ' . json_encode($context) : ''));
    }
}

/**
 * Fonction helper pour valider des données
 */
if (!function_exists('validate')) {
    function validate($data, $rules) {
        $validator = app('validator');
        return $validator->validate($data, $rules);
    }
}

/**
 * Fonction helper pour générer un token CSRF
 */
if (!function_exists('csrf_token')) {
    function csrf_token() {
        return app('csrf')->getToken();
    }
}

/**
 * Fonction helper pour générer un champ CSRF
 */
if (!function_exists('csrf_field')) {
    function csrf_field() {
        return app('csrf')->field();
    }
}
