<?php
/**
 * Système d'authentification centralisé UNIQUE
 */

class Auth {
    
    /**
     * Authentifie un utilisateur
     */
    public static function login($username, $password, $userType) {
        // Validation
        if (empty($username) || empty($password) || empty($userType)) {
            return ['success' => false, 'message' => 'Identifiants manquants'];
        }

        // Rate limiting
        $key = 'login_' . md5($username . $userType);
        if (!self::checkRateLimit($key, MAX_LOGIN_ATTEMPTS, LOGIN_LOCKOUT_TIME)) {
            Security::logEvent('rate_limit_exceeded', ['username' => $username]);
            return ['success' => false, 'message' => 'Trop de tentatives'];
        }

        try {
            $db = Database::getInstance();
            
            // Gestion type "personnel"
            if ($userType === 'personnel') {
                $result = self::login($username, $password, 'vie_scolaire');
                if (!$result['success']) {
                    $result = self::login($username, $password, 'administrateur');
                }
                return $result;
            }

            if (!isset(TABLES[$userType])) {
                return ['success' => false, 'message' => 'Type invalide'];
            }

            $table = TABLES[$userType];
            $sql = "SELECT * FROM `{$table}` WHERE identifiant = ? AND actif = 1 LIMIT 1";
            $user = $db->queryOne($sql, [$username]);

            if (!$user || !password_verify($password, $user['mot_de_passe'])) {
                Security::logEvent('login_failed', ['username' => $username]);
                return ['success' => false, 'message' => 'Identifiants incorrects'];
            }

            // Créer la session
            $_SESSION['user'] = [
                'id' => (int)$user['id'],
                'identifiant' => $user['identifiant'],
                'nom' => $user['nom'],
                'prenom' => $user['prenom'],
                'mail' => $user['mail'],
                'profil' => $userType,
                'classe' => $user['classe'] ?? null
            ];

            $_SESSION['auth_time'] = time();
            session_regenerate_id(true);

            Security::logEvent('login_success', ['user_id' => $user['id']]);

            return ['success' => true, 'user' => $_SESSION['user']];

        } catch (Exception $e) {
            error_log('Auth error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Erreur système'];
        }
    }

    /**
     * Vérifie si l'utilisateur est connecté
     */
    public static function check() {
        if (!isset($_SESSION['user'])) {
            return false;
        }

        // Vérifier l'expiration
        if (isset($_SESSION['auth_time'])) {
            if (time() - $_SESSION['auth_time'] > SESSION_LIFETIME) {
                self::logout();
                return false;
            }
        }

        return true;
    }

    /**
     * Récupère l'utilisateur actuel
     */
    public static function user() {
        return self::check() ? $_SESSION['user'] : null;
    }

    /**
     * Déconnexion
     */
    public static function logout() {
        if (isset($_SESSION['user'])) {
            Security::logEvent('logout', ['user_id' => $_SESSION['user']['id']]);
        }
        
        $_SESSION = [];
        session_destroy();
    }

    /**
     * Vérifie le rôle
     */
    public static function hasRole($role) {
        $user = self::user();
        return $user && $user['profil'] === $role;
    }

    /**
     * Rate limiting
     */
    private static function checkRateLimit($key, $max, $window) {
        $key = 'rate_' . $key;
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 1, 'time' => time()];
            return true;
        }

        if (time() - $_SESSION[$key]['time'] > $window) {
            $_SESSION[$key] = ['count' => 1, 'time' => time()];
            return true;
        }

        $_SESSION[$key]['count']++;
        return $_SESSION[$key]['count'] <= $max;
    }
}

// Fonctions globales pour compatibilité
function isLoggedIn() { return Auth::check(); }
function getCurrentUser() { return Auth::user(); }
function requireAuth() {
    if (!Auth::check()) {
        header('Location: ' . LOGIN_URL);
        exit;
    }
}
function logout() { Auth::logout(); }
