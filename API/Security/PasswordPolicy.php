<?php
declare(strict_types=1);

namespace API\Security;

/**
 * Politique de mot de passe – Validation forte + anti-bruteforce.
 * 
 * Usage :
 *   $policy = new PasswordPolicy();
 *   $result = $policy->validate($password);
 *   if (!$result['valid']) { ... $result['errors'] ... }
 */
class PasswordPolicy
{
    private int $minLength;
    private int $maxLength;
    private bool $requireUpper;
    private bool $requireLower;
    private bool $requireDigit;
    private bool $requireSpecial;
    private int $maxRepeating;
    private array $commonPasswords;

    public function __construct(array $config = [])
    {
        $this->minLength      = $config['min_length'] ?? 10;
        $this->maxLength      = $config['max_length'] ?? 128;
        $this->requireUpper   = $config['require_upper'] ?? true;
        $this->requireLower   = $config['require_lower'] ?? true;
        $this->requireDigit   = $config['require_digit'] ?? true;
        $this->requireSpecial = $config['require_special'] ?? true;
        $this->maxRepeating   = $config['max_repeating'] ?? 3;
        $this->commonPasswords = $config['common_passwords'] ?? self::COMMON_PASSWORDS;
    }

    /**
     * Valide un mot de passe selon la politique
     * @return array{valid: bool, errors: string[], score: int}
     */
    public function validate(string $password): array
    {
        $errors = [];
        $score  = 0;

        // Longueur minimale
        if (mb_strlen($password) < $this->minLength) {
            $errors[] = "Le mot de passe doit contenir au moins {$this->minLength} caractères.";
        } else {
            $score += 1;
        }

        // Longueur maximale
        if (mb_strlen($password) > $this->maxLength) {
            $errors[] = "Le mot de passe ne peut pas dépasser {$this->maxLength} caractères.";
        }

        // Majuscule
        if ($this->requireUpper && !preg_match('/[A-Z]/', $password)) {
            $errors[] = "Le mot de passe doit contenir au moins une lettre majuscule.";
        } else {
            $score += 1;
        }

        // Minuscule
        if ($this->requireLower && !preg_match('/[a-z]/', $password)) {
            $errors[] = "Le mot de passe doit contenir au moins une lettre minuscule.";
        } else {
            $score += 1;
        }

        // Chiffre
        if ($this->requireDigit && !preg_match('/[0-9]/', $password)) {
            $errors[] = "Le mot de passe doit contenir au moins un chiffre.";
        } else {
            $score += 1;
        }

        // Caractère spécial
        if ($this->requireSpecial && !preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = "Le mot de passe doit contenir au moins un caractère spécial (!@#$%&*?..).";
        } else {
            $score += 1;
        }

        // Caractères répétés
        if ($this->maxRepeating > 0 && preg_match('/(.)\1{' . $this->maxRepeating . ',}/', $password)) {
            $errors[] = "Le mot de passe ne peut pas contenir plus de {$this->maxRepeating} caractères identiques consécutifs.";
        }

        // Mot de passe courant
        if (in_array(strtolower($password), $this->commonPasswords, true)) {
            $errors[] = "Ce mot de passe est trop courant. Choisissez un mot de passe plus original.";
        }

        // Bonus longueur
        if (mb_strlen($password) >= 12) $score += 1;
        if (mb_strlen($password) >= 16) $score += 1;

        return [
            'valid'  => empty($errors),
            'errors' => $errors,
            'score'  => min($score, 5), // 0-5
        ];
    }

    /**
     * Vérifie que le nouveau mot de passe est différent de l'ancien
     */
    public function isDifferentFrom(string $newPassword, string $oldHash): bool
    {
        return !password_verify($newPassword, $oldHash);
    }

    /**
     * Hash un mot de passe
     */
    public static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /**
     * Retourne les règles en texte (pour affichage UI)
     */
    public function getRules(): array
    {
        $rules = [];
        $rules[] = "Au moins {$this->minLength} caractères";
        if ($this->requireUpper) $rules[] = "Au moins une lettre majuscule";
        if ($this->requireLower) $rules[] = "Au moins une lettre minuscule";
        if ($this->requireDigit) $rules[] = "Au moins un chiffre";
        if ($this->requireSpecial) $rules[] = "Au moins un caractère spécial";
        if ($this->maxRepeating > 0) $rules[] = "Pas plus de {$this->maxRepeating} caractères identiques d'affilée";
        return $rules;
    }

    // ── Mots de passe les plus courants (blocklist) ──
    private const COMMON_PASSWORDS = [
        'password', '123456', '12345678', '123456789', '1234567890',
        'qwerty', 'azerty', 'abc123', 'password1', 'admin',
        'letmein', 'welcome', 'monkey', 'dragon', 'master',
        'login', 'princess', 'football', 'shadow', 'sunshine',
        'trustno1', 'iloveyou', 'batman', 'access', 'hello',
        'charlie', 'donald', '123123', '654321', 'superman',
        'qwerty123', 'michael', 'password123', 'pronote', 'pronote123',
        'ecole', 'ecole123', 'college', 'lycee', 'education',
        'eleve', 'professeur', 'parent', 'motdepasse', 'changeme',
    ];
}
