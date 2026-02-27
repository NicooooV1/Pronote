<?php
/**
 * Fonctions utilitaires pour le module Notes
 * Fonctions sécurisées et optimisées
 * formatDate, sanitizeInput, generateCSRFToken, validateCSRFToken sont fournis par l'API (Bridge)
 */

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

// sanitizeInput, generateCSRFToken, validateCSRFToken sont fournis par l'API (Bridge)

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
