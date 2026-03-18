<?php
namespace API\Services;

use PDO;

/**
 * Service de gestion des utilisateurs
 *
 * Centralise : CRUD, authentification, remember-me, rate limiting,
 * réinitialisation de mot de passe, génération d'identifiants.
 */
class UserService
{
    protected $pdo;

    protected $tableMap = [
        'eleve'          => 'eleves',
        'parent'         => 'parents',
        'professeur'     => 'professeurs',
        'vie_scolaire'   => 'vie_scolaire',
        'administrateur' => 'administrateurs',
    ];

    private const VALID_PROFILES = ['eleve', 'parent', 'professeur', 'vie_scolaire', 'administrateur'];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /* ================================================================
     *  CRUD
     * ================================================================ */

    /**
     * Crée un nouvel utilisateur.
     */
    public function create($profil, $userData)
    {
        if (!isset($this->tableMap[$profil])) {
            return ['success' => false, 'message' => 'Type de profil invalide.'];
        }

        $table = $this->tableMap[$profil];

        // Générer l'identifiant si absent
        $identifiant = !empty($userData['identifiant'])
            ? $userData['identifiant']
            : $this->generateIdentifier($userData['nom'], $userData['prenom'], $table);

        // Vérifier unicité
        $stmt = $this->pdo->prepare(
            "SELECT id, identifiant, mail FROM `{$table}` WHERE identifiant = ? OR mail = ? LIMIT 1"
        );
        $stmt->execute([$identifiant, $userData['mail'] ?? '']);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            if ($existing['identifiant'] === $identifiant) {
                return ['success' => false, 'message' => "L'identifiant '{$identifiant}' est déjà utilisé."];
            }
            return ['success' => false, 'message' => "L'adresse email est déjà utilisée."];
        }

        // Mot de passe sécurisé
        $password       = self::generatePassword();
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        // Données de base
        $data = [
            'identifiant'  => $identifiant,
            'nom'          => $userData['nom'],
            'prenom'       => $userData['prenom'],
            'mail'         => $userData['mail'],
            'mot_de_passe' => $hashedPassword,
        ];

        // Champs spécifiques par profil
        if ($profil === 'eleve') {
            $data['date_naissance'] = $userData['date_naissance'] ?? null;
            $data['lieu_naissance'] = $userData['lieu_naissance'] ?? null;
            $data['classe']         = $userData['classe'] ?? null;
            $data['adresse']        = $userData['adresse'] ?? null;
        } elseif ($profil === 'professeur') {
            $data['matiere']               = $userData['matiere'] ?? null;
            $data['professeur_principal']   = $userData['est_pp'] ?? 0;
        } elseif ($profil === 'vie_scolaire') {
            $data['est_CPE']        = $userData['est_CPE'] ?? 0;
            $data['est_infirmerie'] = $userData['est_infirmerie'] ?? 0;
        }

        $columns      = array_keys($data);
        $placeholders = array_fill(0, count($data), '?');

        $sql = sprintf(
            "INSERT INTO `%s` (%s) VALUES (%s)",
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array_values($data));

            return [
                'success'     => true,
                'identifiant' => $identifiant,
                'password'    => $password,
                'message'     => 'Utilisateur créé avec succès.',
            ];
        } catch (\PDOException $e) {
            error_log('Erreur création utilisateur: ' . $e->getMessage());
            return ['success' => false, 'message' => "Erreur lors de l'enregistrement."];
        }
    }

    /**
     * Trouve un utilisateur par son ID.
     * Si $userType est fourni, ne cherche que dans la table correspondante.
     * Sinon, recherche dans toutes les tables (attention : IDs non-uniques entre tables).
     */
    public function findById(int $id, ?string $userType = null): ?array
    {
        // Si le type est connu, recherche ciblée (sûre)
        if ($userType !== null && isset($this->tableMap[$userType])) {
            $table = $this->tableMap[$userType];
            try {
                $stmt = $this->pdo->prepare("SELECT * FROM `{$table}` WHERE id = ? LIMIT 1");
                $stmt->execute([$id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($user) {
                    $user['type']   = $userType;
                    $user['profil'] = $userType;
                    return $user;
                }
            } catch (\PDOException $e) {
                error_log('findById: ' . $e->getMessage());
            }
            return null;
        }

        // Fallback : recherche dans toutes les tables (legacy)
        foreach ($this->tableMap as $profil => $table) {
            try {
                $stmt = $this->pdo->prepare("SELECT * FROM `{$table}` WHERE id = ? LIMIT 1");
                $stmt->execute([$id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($user) {
                    $user['type']   = $profil;
                    $user['profil'] = $profil;
                    return $user;
                }
            } catch (\PDOException $e) {
                continue;
            }
        }
        return null;
    }

    /* ================================================================
     *  MOT DE PASSE
     * ================================================================ */

    /**
     * Change le mot de passe d'un utilisateur.
     * Si $userType est fourni, ne cherche que dans la table correspondante.
     */
    public function changePassword($userId, $newPassword, ?string $userType = null)
    {
        // Validation via PasswordPolicy si disponible
        if (class_exists('\API\Security\PasswordPolicy')) {
            $policy = new \API\Security\PasswordPolicy();
            $policyResult = $policy->validate($newPassword);
            if (!$policyResult['valid']) {
                return ['success' => false, 'message' => implode(' ', $policyResult['errors'])];
            }
        }

        $hash = \API\Security\PasswordPolicy::hash($newPassword);

        $tables = ($userType && isset($this->tableMap[$userType]))
            ? [$userType => $this->tableMap[$userType]]
            : $this->tableMap;

        foreach ($tables as $profil => $table) {
            try {
                $stmt = $this->pdo->prepare("SELECT id FROM `{$table}` WHERE id = ? LIMIT 1");
                $stmt->execute([$userId]);
                if ($stmt->fetch()) {
                    $stmt2 = $this->pdo->prepare("
                        UPDATE `{$table}` 
                        SET mot_de_passe = ?, password_changed_at = NOW() 
                        WHERE id = ?
                    ");
                    $stmt2->execute([$hash, $userId]);
                    return ['success' => true, 'message' => 'Mot de passe changé avec succès.'];
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        return ['success' => false, 'message' => 'Utilisateur non trouvé.'];
    }

    /**
     * Génère un mot de passe aléatoire sécurisé (cryptographiquement sûr).
     */
    public static function generatePassword(int $length = 12): string
    {
        $length = max($length, 12);

        $upper   = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lower   = 'abcdefghijkmnopqrstuvwxyz';
        $digits  = '23456789';
        $special = '!@#$%^&*_-+=';

        $password  = $upper[random_int(0, strlen($upper) - 1)];
        $password .= $lower[random_int(0, strlen($lower) - 1)];
        $password .= $digits[random_int(0, strlen($digits) - 1)];
        $password .= $special[random_int(0, strlen($special) - 1)];

        $all = $upper . $lower . $digits . $special;
        for ($i = 4; $i < $length; $i++) {
            $password .= $all[random_int(0, strlen($all) - 1)];
        }

        return str_shuffle($password);
    }

    /* ================================================================
     *  RÉINITIALISATION
     * ================================================================ */

    /**
     * Trouve un utilisateur par identifiant + email + téléphone.
     */
    public function findByCredentials($username, $email, $phone, $userType)
    {
        if (!isset($this->tableMap[$userType])) {
            return null;
        }

        $table = $this->tableMap[$userType];

        try {
            $stmt = $this->pdo->prepare(
                "SELECT * FROM `{$table}` WHERE identifiant = ? AND mail = ? AND telephone = ? LIMIT 1"
            );
            $stmt->execute([$username, $email, $phone]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (\PDOException $e) {
            error_log('findByCredentials: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Crée une demande de réinitialisation de mot de passe.
     */
    public function createResetRequest($userId, $userType)
    {
        try {
            // Vérifier s'il y a déjà une demande en attente
            $stmt = $this->pdo->prepare(
                "SELECT id FROM demandes_reinitialisation WHERE user_id = ? AND user_type = ? AND status = 'pending' LIMIT 1"
            );
            $stmt->execute([$userId, $userType]);
            if ($stmt->fetch()) {
                return false; // demande déjà en attente
            }

            $stmt = $this->pdo->prepare(
                "INSERT INTO demandes_reinitialisation (user_id, user_type, date_demande, status) VALUES (?, ?, NOW(), 'pending')"
            );
            return $stmt->execute([$userId, $userType]);
        } catch (\PDOException $e) {
            error_log('createResetRequest: ' . $e->getMessage());
            return false;
        }
    }

    /* ================================================================
     *  REMEMBER ME
     * ================================================================ */

    /**
     * Crée un token "Remember Me" pour un utilisateur.
     * Stocke aussi le user_type pour éviter l'ambiguïté d'ID entre tables.
     */
    public function createRememberToken(int $userId, ?string $userType = null): ?string
    {
        try {
            // Résoudre le user_type si non fourni
            if ($userType === null) {
                $user = $this->findById($userId);
                $userType = $user['type'] ?? null;
            }

            $token     = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);

            $this->pdo->prepare("DELETE FROM remember_tokens WHERE user_id = ? AND user_type = ?")->execute([$userId, $userType]);

            $stmt = $this->pdo->prepare(
                "INSERT INTO remember_tokens (user_id, user_type, token_hash, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))"
            );
            $stmt->execute([$userId, $userType, $tokenHash]);

            setcookie('remember_token', $token, [
                'expires'  => time() + 30 * 86400,
                'path'     => '/',
                'secure'   => !empty($_SERVER['HTTPS']),
                'httponly'  => true,
                'samesite' => 'Lax',
            ]);

            return $token;
        } catch (\Throwable $e) {
            error_log('createRememberToken: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Vérifie un token "Remember Me" et restaure la session.
     * Utilise user_type stocké pour une résolution déterministe.
     */
    public function validateRememberToken(string $token): ?array
    {
        try {
            $tokenHash = hash('sha256', $token);
            $stmt = $this->pdo->prepare(
                "SELECT user_id, user_type FROM remember_tokens WHERE token_hash = ? AND expires_at > NOW() LIMIT 1"
            );
            $stmt->execute([$tokenHash]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) return null;

            $user = $this->findById((int) $row['user_id'], $row['user_type'] ?? null);
            if ($user) {
                // Rotation du token
                $this->pdo->prepare("DELETE FROM remember_tokens WHERE token_hash = ?")->execute([$tokenHash]);
                $this->createRememberToken($user['id'], $user['type'] ?? $row['user_type'] ?? null);
            }
            return $user;
        } catch (\Throwable $e) {
            error_log('validateRememberToken: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Supprime un token "Remember Me".
     */
    public function clearRememberToken(int $userId, ?string $userType = null): void
    {
        try {
            if ($userType !== null) {
                $this->pdo->prepare("DELETE FROM remember_tokens WHERE user_id = ? AND user_type = ?")->execute([$userId, $userType]);
            } else {
                $this->pdo->prepare("DELETE FROM remember_tokens WHERE user_id = ?")->execute([$userId]);
            }
        } catch (\Throwable $e) { /* silencieux */ }

        setcookie('remember_token', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => !empty($_SERVER['HTTPS']),
            'httponly'  => true,
            'samesite' => 'Lax',
        ]);
    }

    /* ================================================================
     *  RATE LIMITING (login)
     * ================================================================ */

    /**
     * Vérifie le rate limiting pour les tentatives de connexion (lockout progressif).
     * Retourne le nombre de minutes restantes avant déblocage, ou 0 si autorisé.
     *
     * Seuils :  5 tentatives → 15 min
     *          10 tentatives →  1 h
     *          20 tentatives → 24 h
     */
    public function checkLoginRateLimit(string $ip): int
    {
        // Paliers décroissants : vérifié du plus restrictif au moins restrictif
        $tiers = [
            ['threshold' => 20, 'window_min' => 24 * 60, 'lock_min' => 24 * 60],
            ['threshold' => 10, 'window_min' => 60,       'lock_min' => 60],
            ['threshold' => 5,  'window_min' => 15,       'lock_min' => 15],
        ];

        try {
            foreach ($tiers as $tier) {
                $stmt = $this->pdo->prepare(
                    "SELECT COUNT(*) FROM login_attempts
                     WHERE ip = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)"
                );
                $stmt->execute([$ip, $tier['window_min']]);
                $attempts = (int) $stmt->fetchColumn();

                if ($attempts >= $tier['threshold']) {
                    $stmt2 = $this->pdo->prepare(
                        "SELECT MIN(attempted_at) FROM login_attempts
                         WHERE ip = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)"
                    );
                    $stmt2->execute([$ip, $tier['window_min']]);
                    $first = $stmt2->fetchColumn();

                    if ($first) {
                        $unlocksAt = strtotime($first) + $tier['lock_min'] * 60;
                        return max(1, (int) ceil(($unlocksAt - time()) / 60));
                    }
                    return $tier['lock_min'];
                }
            }
            return 0;
        } catch (\Throwable $e) {
            return 0; // Ne pas bloquer en cas d'erreur DB
        }
    }

    /**
     * Enregistre une tentative de connexion échouée.
     */
    public function recordFailedAttempt(string $ip): void
    {
        try {
            $this->pdo->prepare("INSERT INTO login_attempts (ip, attempted_at) VALUES (?, NOW())")->execute([$ip]);
        } catch (\Throwable $e) { /* table peut ne pas exister */ }
    }

    /**
     * Nettoie les tentatives expirées.
     */
    public function cleanOldAttempts(): void
    {
        try {
            $this->pdo->exec("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        } catch (\Throwable $e) { /* silencieux */ }
    }

    /* ================================================================
     *  UTILITAIRES
     * ================================================================ */

    /**
     * Génère un identifiant unique au format nom.prenom[XX].
     */
    protected function generateIdentifier($nom, $prenom, $table)
    {
        $nom    = $this->normalizeString($nom);
        $prenom = $this->normalizeString($prenom);

        $baseIdentifier = strtolower($nom . '.' . $prenom);

        $stmt = $this->pdo->prepare("SELECT identifiant FROM `{$table}` WHERE identifiant LIKE ?");
        $stmt->execute([$baseIdentifier . '%']);
        $existingIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!in_array($baseIdentifier, $existingIds)) {
            return $baseIdentifier;
        }

        $i = 1;
        do {
            $identifier = $baseIdentifier . sprintf('%02d', $i++);
        } while (in_array($identifier, $existingIds));

        return $identifier;
    }

    /**
     * Normalise une chaîne (supprime accents et caractères spéciaux).
     */
    protected function normalizeString($string)
    {
        $string = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $string);
        return preg_replace('/[^a-zA-Z0-9]/', '', $string);
    }

    /**
     * Retourne le nom de la table pour un profil donné.
     */
    public static function getTableName(string $profil): ?string
    {
        $map = [
            'eleve'          => 'eleves',
            'parent'         => 'parents',
            'professeur'     => 'professeurs',
            'vie_scolaire'   => 'vie_scolaire',
            'administrateur' => 'administrateurs',
        ];
        return $map[$profil] ?? null;
    }
}
