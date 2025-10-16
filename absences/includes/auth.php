<?php
/**
 * Module d'authentification pour le module Absences
 * Bridge vers auth_central.php
 */

// Démarrer la session si elle n'est pas déjà active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Essayer d'inclure le fichier d'authentification central
$authCentralPath = __DIR__ . '/../../API/auth_central.php';
if (file_exists($authCentralPath)) {
    require_once $authCentralPath;
} else {
    // Fallback si le fichier central n'est pas disponible
    // Définir une URL de login de repli si nécessaire
    if (!defined('LOGIN_URL')) {
        define('LOGIN_URL', '../login/public/index.php');
    }

    // Vérifions si les fonctions existent déjà pour éviter les redéclarations
    if (!function_exists('isLoggedIn')) {
        /**
         * Vérifie si l'utilisateur est connecté
         * @return bool True si l'utilisateur est connecté
         */
        function isLoggedIn() {
            return isset($_SESSION['user']) && !empty($_SESSION['user']);
        }
    }
    
    if (!function_exists('getCurrentUser')) {
        /**
         * Récupère l'utilisateur connecté
         * @return array|null Données de l'utilisateur ou null
         */
        function getCurrentUser() {
            return $_SESSION['user'] ?? null;
        }
    }
    
    if (!function_exists('getUserRole')) {
        /**
         * Récupère le rôle de l'utilisateur
         * @return string|null Rôle de l'utilisateur ou null
         */
        function getUserRole() {
            $user = getCurrentUser();
            return $user ? ($user['profil'] ?? null) : null;
        }
    }
    
    if (!function_exists('getUserFullName')) {
        /**
         * Récupère le nom complet de l'utilisateur
         * @return string Nom complet de l'utilisateur ou chaîne vide
         */
        function getUserFullName() {
            $user = getCurrentUser();
            if ($user) {
                return ($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? '');
            }
            return '';
        }
    }
    
    if (!function_exists('getUserInitials')) {
        /**
         * Récupère les initiales de l'utilisateur
         * @return string Initiales de l'utilisateur
         */
        function getUserInitials() {
            $user = getCurrentUser();
            if (!$user) return '';
            $p = isset($user['prenom'][0]) ? strtoupper($user['prenom'][0]) : '';
            $n = isset($user['nom'][0]) ? strtoupper($user['nom'][0]) : '';
            return $p . $n;
        }
    }
    
    if (!function_exists('isAdmin')) {
        /**
         * Vérifie si l'utilisateur est administrateur
         * @return bool True si l'utilisateur est administrateur
         */
        function isAdmin() {
            return getUserRole() === 'administrateur';
        }
    }
    
    if (!function_exists('isTeacher')) {
        /**
         * Vérifie si l'utilisateur est professeur
         * @return bool True si l'utilisateur est professeur
         */
        function isTeacher() {
            return getUserRole() === 'professeur';
        }
    }
    
    if (!function_exists('isStudent')) {
        /**
         * Vérifie si l'utilisateur est élève
         * @return bool True si l'utilisateur est élève
         */
        function isStudent() {
            return getUserRole() === 'eleve';
        }
    }
    
    if (!function_exists('isParent')) {
        /**
         * Vérifie si l'utilisateur est parent
         * @return bool True si l'utilisateur est parent
         */
        function isParent() {
            return getUserRole() === 'parent';
        }
    }
    
    if (!function_exists('isVieScolaire')) {
        /**
         * Vérifie si l'utilisateur est membre de la vie scolaire
         * @return bool True si l'utilisateur est membre de la vie scolaire
         */
        function isVieScolaire() {
            return getUserRole() === 'vie_scolaire';
        }
    }
    
    if (!function_exists('canManageAbsences')) {
        /**
         * Vérifie si l'utilisateur peut gérer les absences
         * @return bool True si l'utilisateur peut gérer les absences
         */
        function canManageAbsences() {
            $role = getUserRole();
            return in_array($role, ['administrateur', 'professeur', 'vie_scolaire']);
        }
    }
    
    if (!function_exists('redirect')) {
        /**
         * Redirige vers un chemin donné
         * @param string $path Chemin vers lequel rediriger
         */
        function redirect($path) {
            header('Location: ' . $path);
            exit;
        }
    }
    
    if (!function_exists('requireAuth')) {
        /**
         * Exige une authentification pour accéder à la page
         * Redirige vers la page de login si l'utilisateur n'est pas connecté
         * @return array|null Données utilisateur ou null
         */
        function requireAuth() {
            if (!isLoggedIn()) {
                header('Location: ' . LOGIN_URL);
                exit;
            }
            return getCurrentUser();
        }
    }

    if (!function_exists('requireLogin')) {
        /**
         * Redirige si l'utilisateur n'est pas connecté
         * @return array|null Données utilisateur ou null
         */
        function requireLogin() {
            if (!isLoggedIn()) {
                header('Location: ' . LOGIN_URL);
                exit;
            }
            return getCurrentUser();
        }
    }
}
