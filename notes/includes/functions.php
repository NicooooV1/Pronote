<?php
/**
 * Fonctions utilitaires pour le module Notes
 * Fonctions sécurisées et optimisées
 */

if (!function_exists('formatDate')) {
    /**
     * Formate une date de manière sécurisée
     * @param string $date Date à formater
     * @param string $format Format de sortie
     * @return string Date formatée
     */
    function formatDate($date, $format = 'd/m/Y') {
        if (empty($date)) {
            return '';
        }
        
        try {
            $dateObj = new DateTime($date);
            return $dateObj->format($format);
        } catch (Exception $e) {
            return '';
        }
    }
}

if (!function_exists('validateDate')) {
    /**
     * Valide une date
     * @param string $date Date à valider
     * @param string $format Format attendu
     * @return bool True si la date est valide
     */
    function validateDate($date, $format = 'Y-m-d') {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
}

if (!function_exists('calculateGrade')) {
    /**
     * Calcule la moyenne d'une liste de notes avec coefficients
     * @param array $notes Tableau des notes avec coefficients
     * @return float|null Moyenne calculée ou null si pas de notes
     */
    function calculateGrade($notes) {
        if (empty($notes)) {
            return null;
        }
        
        $totalPoints = 0;
        $totalCoefficients = 0;
        
        foreach ($notes as $note) {
            if (isset($note['note']) && isset($note['coefficient'])) {
                $noteValue = floatval($note['note']);
                $coefficient = floatval($note['coefficient']);
                
                if ($coefficient > 0) {
                    $totalPoints += $noteValue * $coefficient;
                    $totalCoefficients += $coefficient;
                }
            }
        }
        
        return $totalCoefficients > 0 ? round($totalPoints / $totalCoefficients, 2) : null;
    }
}

if (!function_exists('getGradeClass')) {
    /**
     * Retourne la classe CSS selon la note
     * @param float $grade Note à classer
     * @param float $maxGrade Note maximale (défaut 20)
     * @return string Classe CSS
     */
    function getGradeClass($grade, $maxGrade = 20) {
        $percentage = ($grade / $maxGrade) * 100;
        
        if ($percentage >= 75) {
            return 'good';
        } elseif ($percentage >= 50) {
            return 'average';
        } else {
            return 'bad';
        }
    }
}

if (!function_exists('sanitizeInput')) {
    /**
     * Nettoie et sécurise une entrée utilisateur
     * @param mixed $input Entrée à nettoyer
     * @param string $type Type de nettoyage
     * @return mixed Entrée nettoyée
     */
    function sanitizeInput($input, $type = 'string') {
        if ($input === null) {
            return null;
        }
        
        switch ($type) {
            case 'string':
                return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
            case 'email':
                return filter_var(trim($input), FILTER_SANITIZE_EMAIL);
            case 'int':
                return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
            case 'float':
                return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            case 'url':
                return filter_var(trim($input), FILTER_SANITIZE_URL);
            default:
                return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
        }
    }
}

if (!function_exists('generateCSRFToken')) {
    /**
     * Génère un token CSRF sécurisé (fallback si pas dans auth_central)
     * @return string Token CSRF
     */
    function generateCSRFToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            try {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            } catch (Exception $e) {
                $_SESSION['csrf_token'] = hash('sha256', uniqid(mt_rand(), true));
            }
            $_SESSION['csrf_token_time'] = time();
        }
        
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('validateCSRFToken')) {
    /**
     * Valide un token CSRF (fallback si pas dans auth_central)
     * @param string $token Token à valider
     * @return bool True si valide
     */
    function validateCSRFToken($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (empty($token) || empty($_SESSION['csrf_token'])) {
            return false;
        }
        
        // Vérifier l'expiration (30 minutes)
        if (isset($_SESSION['csrf_token_time']) && 
            time() - $_SESSION['csrf_token_time'] > 1800) {
            unset($_SESSION['csrf_token']);
            unset($_SESSION['csrf_token_time']);
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

if (!function_exists('getSubjectColor')) {
    /**
     * Retourne la couleur associée à une matière
     * @param string $subject Nom de la matière
     * @return string Classe CSS de couleur
     */
    function getSubjectColor($subject) {
        $colors = [
            'Français' => 'francais',
            'Mathématiques' => 'mathematiques',
            'Histoire-Géographie' => 'histoire-geo',
            'Anglais' => 'anglais',
            'Espagnol' => 'espagnol',
            'Allemand' => 'allemand',
            'Physique-Chimie' => 'physique-chimie',
            'SVT' => 'svt',
            'Technologie' => 'technologie',
            'Arts Plastiques' => 'arts',
            'Musique' => 'musique',
            'EPS' => 'eps'
        ];
        
        return $colors[$subject] ?? 'default';
    }
}

if (!function_exists('formatGrade')) {
    /**
     * Formate une note pour l'affichage
     * @param float $grade Note à formater
     * @param int $decimals Nombre de décimales
     * @return string Note formatée
     */
    function formatGrade($grade, $decimals = 2) {
        if ($grade === null) {
            return '-';
        }
        
        return number_format($grade, $decimals, ',', '');
    }
}

if (!function_exists('getCurrentTrimester')) {
    /**
     * Détermine le trimestre actuel
     * @return int Numéro du trimestre (1, 2 ou 3)
     */
    function getCurrentTrimester() {
        $month = date('n');
        
        if ($month >= 9 && $month <= 12) {
            return 1; // 1er trimestre
        } elseif ($month >= 1 && $month <= 3) {
            return 2; // 2ème trimestre
        } elseif ($month >= 4 && $month <= 6) {
            return 3; // 3ème trimestre
        } else {
            return 1; // Période estivale, par défaut 1er trimestre
        }
    }
}

if (!function_exists('getTrimesterLabel')) {
    /**
     * Retourne le libellé d'un trimestre
     * @param int $trimester Numéro du trimestre
     * @return string Libellé du trimestre
     */
    function getTrimesterLabel($trimester) {
        $labels = [
            1 => '1er trimestre',
            2 => '2ème trimestre',
            3 => '3ème trimestre'
        ];
        
        return $labels[$trimester] ?? 'Trimestre inconnu';
    }
}
