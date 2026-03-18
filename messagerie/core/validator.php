<?php
/**
 * Validation centralisée des entrées utilisateur — Messagerie Fronote
 *
 * Cette classe fournit des méthodes de sanitization typées (id(), text(), folder(),
 * reaction()…) spécifiques au domaine messagerie. Elle complète
 * API\Security\Validator (validation par règles : required|email|min:…).
 * Aucun conflit de namespace.
 */

if (class_exists('Validator', false)) {
    return; // Déjà chargée
}

class Validator {
    
    /** @var array Erreurs accumulées */
    private array $errors = [];

    /** Types d'utilisateurs autorisés */
    const VALID_USER_TYPES = ['eleve', 'parent', 'professeur', 'vie_scolaire', 'administrateur'];

    /** Niveaux d'importance autorisés */
    const VALID_IMPORTANCE = ['normal', 'important', 'urgent'];

    /** Dossiers autorisés */
    const VALID_FOLDERS = ['reception', 'envoyes', 'archives', 'information', 'corbeille'];

    /** Réactions autorisées */
    const VALID_REACTIONS = ['👍', '✅', '❤️', '😂', '😮', '😢'];

    /** Fréquences de digest autorisées */
    const VALID_DIGEST_FREQ = ['never', 'daily', 'weekly'];

    // ─── Validateurs unitaires ────────────────────────────────

    /**
     * Valide un ID (entier strictement positif)
     */
    public static function id($value, string $field = 'id'): ?int {
        $v = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($v === false) {
            return null;
        }
        return $v;
    }

    /**
     * Valide un tableau d'IDs
     * @return int[] IDs valides (les invalides sont filtrés)
     */
    public static function ids(array $values): array {
        return array_values(array_filter(array_map(function($v) {
            return self::id($v);
        }, $values)));
    }

    /**
     * Valide un type d'utilisateur
     */
    public static function userType(?string $type): ?string {
        if ($type === null || !in_array($type, self::VALID_USER_TYPES, true)) {
            return null;
        }
        return $type;
    }

    /**
     * Valide un niveau d'importance
     */
    public static function importance(?string $value): string {
        if ($value === null || !in_array($value, self::VALID_IMPORTANCE, true)) {
            return 'normal';
        }
        return $value;
    }

    /**
     * Valide un nom de dossier
     */
    public static function folder(?string $value): string {
        if ($value === null || !in_array($value, self::VALID_FOLDERS, true)) {
            return 'reception';
        }
        return $value;
    }

    /**
     * Valide un emoji de réaction
     */
    public static function reaction(?string $value): ?string {
        if ($value === null || !in_array($value, self::VALID_REACTIONS, true)) {
            return null;
        }
        return $value;
    }

    /**
     * Valide et nettoie une chaîne de texte
     * @param string|null $value Texte brut
     * @param int $maxLength Longueur maximale
     * @param int $minLength Longueur minimale
     * @return string|null Texte nettoyé ou null si invalide
     */
    public static function text(?string $value, int $maxLength = 10000, int $minLength = 1): ?string {
        if ($value === null) return null;
        $value = trim($value);
        $len = mb_strlen($value);
        if ($len < $minLength || $len > $maxLength) {
            return null;
        }
        return $value;
    }

    /**
     * Valide un sujet de conversation (titre)
     */
    public static function subject(?string $value): ?string {
        return self::text($value, 255, 1);
    }

    /**
     * Valide le contenu d'un message
     */
    public static function messageBody(?string $value): ?string {
        return self::text($value, 10000, 1);
    }

    /**
     * Valide une requête de recherche
     */
    public static function searchQuery(?string $value): ?string {
        return self::text($value, 200, 2);
    }

    /**
     * Valide un entier positif ou zéro (pour les offsets de pagination)
     */
    public static function positiveInt($value, int $default = 0): int {
        $v = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        return $v !== false ? $v : $default;
    }

    /**
     * Valide une limite de pagination
     */
    public static function limit($value, int $default = 20, int $max = 100): int {
        $v = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => $max]]);
        return $v !== false ? $v : $default;
    }

    /**
     * Valide une fréquence de digest
     */
    public static function digestFrequency(?string $value): string {
        if ($value === null || !in_array($value, self::VALID_DIGEST_FREQ, true)) {
            return 'never';
        }
        return $value;
    }

    /**
     * Valide un tableau de participants [{id: int, type: string}, ...]
     * @return array Participants valides
     */
    public static function participants(array $raw): array {
        $valid = [];
        foreach ($raw as $p) {
            $id = self::id($p['id'] ?? null);
            $type = self::userType($p['type'] ?? null);
            if ($id !== null && $type !== null) {
                $valid[] = ['id' => $id, 'type' => $type];
            }
        }
        return $valid;
    }

    /**
     * Vérifie qu'un timestamp UNIX est valide
     */
    public static function timestamp($value): int {
        $v = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        return $v !== false ? $v : 0;
    }

    /**
     * Valide un booléen depuis une entrée
     */
    public static function boolean($value): bool {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
