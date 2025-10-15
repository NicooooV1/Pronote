<?php
/**
 * Utilitaires centralisés
 */

class Utils {
    
    /**
     * Formate une date
     */
    public static function formatDate($date, $format = DATE_FORMAT_FR) {
        if (empty($date)) return '';
        
        $timestamp = is_numeric($date) ? $date : strtotime($date);
        return date($format, $timestamp);
    }

    /**
     * Redirige
     */
    public static function redirect($path, $message = null, $type = 'info') {
        if ($message) {
            $_SESSION['flash'][$type] = $message;
        }

        $url = BASE_URL . '/' . ltrim($path, '/');
        header('Location: ' . $url);
        exit;
    }

    /**
     * Récupère un message flash
     */
    public static function getFlash($type = null) {
        if ($type === null) {
            $flash = $_SESSION['flash'] ?? [];
            unset($_SESSION['flash']);
            return $flash;
        }

        $message = $_SESSION['flash'][$type] ?? null;
        unset($_SESSION['flash'][$type]);
        return $message;
    }

    /**
     * Génère un identifiant unique
     */
    public static function generateIdentifier($nom, $prenom) {
        $nom = self::removeAccents(strtolower(trim($nom)));
        $prenom = self::removeAccents(strtolower(trim($prenom)));
        
        return preg_replace('/[^a-z0-9]/', '', $nom) . '.' . 
               preg_replace('/[^a-z0-9]/', '', $prenom);
    }

    /**
     * Retire les accents
     */
    private static function removeAccents($str) {
        $str = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str);
        return preg_replace('/[^a-zA-Z0-9]/', '', $str);
    }
}
