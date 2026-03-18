<?php
/**
 * M17 – Paramètres utilisateur — Service
 */

class SettingsService {
    private PDO $pdo;

    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    /**
     * Récupère les paramètres d'un utilisateur
     */
    public function getSettings(int $userId, string $userType): array {
        $stmt = $this->pdo->prepare("SELECT * FROM user_settings WHERE user_id = ? AND user_type = ?");
        $stmt->execute([$userId, $userType]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return self::defaults($userId, $userType);
        }
        return $row;
    }

    /**
     * Sauvegarder les paramètres
     */
    public function save(int $userId, string $userType, array $data): bool {
        $stmt = $this->pdo->prepare("
            INSERT INTO user_settings (user_id, user_type, theme, langue, notifications_email, notifications_web,
                                       taille_police, sidebar_collapsed, bio, date_modification)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                theme = VALUES(theme),
                langue = VALUES(langue),
                notifications_email = VALUES(notifications_email),
                notifications_web = VALUES(notifications_web),
                taille_police = VALUES(taille_police),
                sidebar_collapsed = VALUES(sidebar_collapsed),
                bio = VALUES(bio),
                date_modification = NOW()
        ");
        return $stmt->execute([
            $userId,
            $userType,
            $data['theme'] ?? 'light',
            $data['langue'] ?? 'fr',
            isset($data['notifications_email']) ? 1 : 0,
            isset($data['notifications_web']) ? 1 : 0,
            $data['taille_police'] ?? 'normal',
            isset($data['sidebar_collapsed']) ? 1 : 0,
            $data['bio'] ?? '',
        ]);
    }

    /**
     * Upload avatar
     */
    public function uploadAvatar(int $userId, string $userType, array $file): string {
        $uploadDir = __DIR__ . '/../uploads/avatars/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($ext, $allowed)) {
            throw new Exception('Format non autorisé. Utilisez JPG, PNG, GIF ou WebP.');
        }
        if ($file['size'] > 2 * 1024 * 1024) {
            throw new Exception('La photo ne doit pas dépasser 2 Mo.');
        }

        $filename = 'avatar_' . $userType . '_' . $userId . '_' . uniqid() . '.' . $ext;
        move_uploaded_file($file['tmp_name'], $uploadDir . $filename);

        $chemin = 'uploads/avatars/' . $filename;
        $stmt = $this->pdo->prepare("
            INSERT INTO user_settings (user_id, user_type, avatar_chemin, date_modification)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE avatar_chemin = VALUES(avatar_chemin), date_modification = NOW()
        ");
        $stmt->execute([$userId, $userType, $chemin]);
        return $chemin;
    }

    /**
     * Modifier le mot de passe
     */
    public function changerMotDePasse(int $userId, string $userType, string $ancien, string $nouveau): bool {
        // Password policy enforcement
        if (strlen($nouveau) < 8 || !preg_match('/[A-Z]/', $nouveau) || !preg_match('/[a-z]/', $nouveau)
            || !preg_match('/[0-9]/', $nouveau) || !preg_match('/[^A-Za-z0-9]/', $nouveau)) {
            return false;
        }

        $tables = [
            'administrateur' => 'administrateurs',
            'professeur' => 'professeurs',
            'eleve' => 'eleves',
            'parent' => 'parents',
            'vie_scolaire' => 'vie_scolaire',
        ];
        $table = $tables[$userType] ?? null;
        if (!$table) return false;

        $stmt = $this->pdo->prepare("SELECT mot_de_passe FROM {$table} WHERE id = ?");
        $stmt->execute([$userId]);
        $hash = $stmt->fetchColumn();

        if (!$hash || !password_verify($ancien, $hash)) {
            return false;
        }

        $newHash = password_hash($nouveau, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare("UPDATE {$table} SET mot_de_passe = ? WHERE id = ?");
        return $stmt->execute([$newHash, $userId]);
    }

    /**
     * Récupère les infos du profil depuis la table rôle
     */
    public function getProfile(int $userId, string $userType): ?array {
        $tables = [
            'administrateur' => 'administrateurs',
            'professeur' => 'professeurs',
            'eleve' => 'eleves',
            'parent' => 'parents',
            'vie_scolaire' => 'vie_scolaire',
        ];
        $table = $tables[$userType] ?? null;
        if (!$table) return null;

        $stmt = $this->pdo->prepare("SELECT * FROM {$table} WHERE id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Valeurs par défaut
     */
    public static function defaults(int $userId, string $userType): array {
        return [
            'user_id' => $userId,
            'user_type' => $userType,
            'theme' => 'light',
            'langue' => 'fr',
            'notifications_email' => 1,
            'notifications_web' => 1,
            'taille_police' => 'normal',
            'sidebar_collapsed' => 0,
            'avatar_chemin' => null,
            'bio' => '',
        ];
    }

    // ─── Accueil config ──────────────────────────────────────────────

    /**
     * Récupère la configuration du tableau de bord de l'utilisateur.
     * @return array|null — List of widget keys, or null (=use defaults)
     */
    public function getAccueilConfig(int $userId, string $userType): ?array {
        try {
            $stmt = $this->pdo->prepare("SELECT accueil_config FROM user_settings WHERE user_id = ? AND user_type = ?");
            $stmt->execute([$userId, $userType]);
            $json = $stmt->fetchColumn();
            if ($json) {
                $decoded = json_decode($json, true);
                return is_array($decoded) ? $decoded : null;
            }
        } catch (\PDOException $e) { /* accueil_config column may not exist yet */ }
        return null;
    }

    /**
     * Sauvegarde la configuration du tableau de bord.
     * @param array $widgets — List of widget keys e.g. ['evenements','devoirs']
     */
    public function saveAccueilConfig(int $userId, string $userType, array $widgets): bool {
        $json = json_encode(array_values($widgets), JSON_UNESCAPED_UNICODE);
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO user_settings (user_id, user_type, accueil_config, date_modification)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE accueil_config = VALUES(accueil_config), date_modification = NOW()
            ");
            return $stmt->execute([$userId, $userType, $json]);
        } catch (\PDOException $e) {
            error_log("SettingsService::saveAccueilConfig error: " . $e->getMessage());
            return false;
        }
    }

    // ─── Static enums ────────────────────────────────────────────────

    public static function themes(): array {
        return ['light' => 'Clair', 'dark' => 'Sombre', 'auto' => 'Automatique'];
    }

    public static function fontSizes(): array {
        return ['small' => 'Petit', 'normal' => 'Normal', 'large' => 'Grand', 'xlarge' => 'Très grand'];
    }
}
