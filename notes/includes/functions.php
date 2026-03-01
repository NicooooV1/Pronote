<?php
/**
 * Fonctions utilitaires pour le module Notes
 * formatDate, sanitizeInput, generateCSRFToken, validateCSRFToken sont fournis par l'API (Bridge)
 *
 * NOTE : calculateGrade, getSubjectColor, formatGrade, getCurrentTrimester, getTrimesterLabel
 *        ont été supprimés car dupliqués par NoteService (getMoyenneGenerale, getMatieres, getTrimestreCourant).
 */

if (!function_exists('validateDate')) {
    /**
     * Valide une date.
     */
    function validateDate(string $date, string $format = 'Y-m-d'): bool
    {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
}

if (!function_exists('getGradeClass')) {
    /**
     * Retourne la classe CSS selon la note (good / average / bad).
     */
    function getGradeClass(float $grade, float $maxGrade = 20): string
    {
        $pct = ($grade / $maxGrade) * 100;
        if ($pct >= 75) return 'good';
        if ($pct >= 50) return 'average';
        return 'bad';
    }
}
