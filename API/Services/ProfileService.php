<?php
declare(strict_types=1);

namespace API\Services;

use PDO;

/**
 * ProfileService — Gestion des profils utilisateurs étendus
 * (citation, réseaux sociaux, photo de profil, bannière, compétences)
 */
class ProfileService
{
    private PDO $pdo;

    private const ALLOWED_AVATAR_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private const MAX_AVATAR_SIZE = 2 * 1024 * 1024; // 2 Mo
    private const AVATAR_DIR = 'uploads/avatars';
    private const BANNER_DIR = 'uploads/banners';

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Récupère le profil étendu d'un utilisateur.
     */
    public function getProfile(int $userId, string $userType): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT up.*, us.avatar_chemin, us.bio, us.banner_color, us.banner_image
            FROM user_profiles up
            LEFT JOIN user_settings us ON us.user_id = up.user_id AND us.user_type = up.user_type
            WHERE up.user_id = ? AND up.user_type = ?
        ");
        $stmt->execute([$userId, $userType]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$profile) {
            // Essayer de récupérer au moins les settings de base
            $stmt2 = $this->pdo->prepare("SELECT avatar_chemin, bio, banner_color, banner_image FROM user_settings WHERE user_id = ? AND user_type = ?");
            $stmt2->execute([$userId, $userType]);
            $settings = $stmt2->fetch(PDO::FETCH_ASSOC);
            return $settings ?: null;
        }

        if (!empty($profile['competences_tags'])) {
            $profile['competences_tags'] = json_decode($profile['competences_tags'], true) ?? [];
        }

        return $profile;
    }

    /**
     * Crée ou met à jour le profil étendu d'un utilisateur.
     */
    public function saveProfile(int $userId, string $userType, array $data): bool
    {
        $fields = [
            'citation', 'site_web', 'lien_linkedin', 'lien_twitter',
            'lien_github', 'lien_instagram', 'lien_autre',
            'disponibilites', 'bureau', 'telephone_pro',
        ];

        $profileData = [];
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $profileData[$field] = $this->sanitizeUrl($field, trim($data[$field]));
            }
        }

        // Boolean fields
        $boolFields = ['date_naissance_visible', 'email_visible', 'profil_public'];
        foreach ($boolFields as $field) {
            if (isset($data[$field])) {
                $profileData[$field] = (int)(bool)$data[$field];
            }
        }

        // Tags de compétences (JSON)
        if (isset($data['competences_tags'])) {
            if (is_string($data['competences_tags'])) {
                $tags = array_filter(array_map('trim', explode(',', $data['competences_tags'])));
                $profileData['competences_tags'] = json_encode(array_values(array_slice($tags, 0, 20)));
            } elseif (is_array($data['competences_tags'])) {
                $profileData['competences_tags'] = json_encode(array_values(array_slice($data['competences_tags'], 0, 20)));
            }
        }

        if (empty($profileData)) return true;

        // Build UPSERT
        $columns = array_merge(['user_id', 'user_type'], array_keys($profileData));
        $placeholders = array_fill(0, count($columns), '?');
        $updates = [];
        foreach (array_keys($profileData) as $col) {
            $updates[] = "{$col} = VALUES({$col})";
        }

        $sql = "INSERT INTO user_profiles (" . implode(', ', $columns) . ")
                VALUES (" . implode(', ', $placeholders) . ")
                ON DUPLICATE KEY UPDATE " . implode(', ', $updates) . ", updated_at = NOW()";

        $values = array_merge([$userId, $userType], array_values($profileData));
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($values);
    }

    /**
     * Met à jour l'avatar de l'utilisateur.
     */
    public function uploadAvatar(int $userId, string $userType, array $file): string
    {
        $this->validateUpload($file, self::ALLOWED_AVATAR_TYPES, self::MAX_AVATAR_SIZE);

        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
        $dir = $basePath . '/' . self::AVATAR_DIR;
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        // Supprimer l'ancien avatar
        $stmt = $this->pdo->prepare("SELECT avatar_chemin FROM user_settings WHERE user_id = ? AND user_type = ?");
        $stmt->execute([$userId, $userType]);
        $old = $stmt->fetchColumn();
        if ($old && file_exists($basePath . '/' . $old)) {
            @unlink($basePath . '/' . $old);
        }

        // Générer un nom unique
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
        $ext = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $ext));
        $filename = "avatar_{$userType}_{$userId}_" . bin2hex(random_bytes(4)) . ".{$ext}";
        $path = self::AVATAR_DIR . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $basePath . '/' . $path)) {
            throw new \RuntimeException('Impossible de sauvegarder le fichier.');
        }

        // Mettre à jour en BDD
        $stmt = $this->pdo->prepare("
            INSERT INTO user_settings (user_id, user_type, avatar_chemin) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE avatar_chemin = VALUES(avatar_chemin)
        ");
        $stmt->execute([$userId, $userType, $path]);

        return $path;
    }

    /**
     * Met à jour la bannière de profil.
     */
    public function uploadBanner(int $userId, string $userType, array $file): string
    {
        $this->validateUpload($file, self::ALLOWED_AVATAR_TYPES, 5 * 1024 * 1024);

        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
        $dir = $basePath . '/' . self::BANNER_DIR;
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) ?: 'jpg';
        $ext = preg_replace('/[^a-zA-Z0-9]/', '', $ext);
        $filename = "banner_{$userType}_{$userId}_" . bin2hex(random_bytes(4)) . ".{$ext}";
        $path = self::BANNER_DIR . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $basePath . '/' . $path)) {
            throw new \RuntimeException('Impossible de sauvegarder la bannière.');
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO user_settings (user_id, user_type, banner_image) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE banner_image = VALUES(banner_image)
        ");
        $stmt->execute([$userId, $userType, $path]);

        return $path;
    }

    /**
     * Met à jour la couleur de bannière.
     */
    public function setBannerColor(int $userId, string $userType, string $color): bool
    {
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            throw new \InvalidArgumentException('Couleur invalide.');
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO user_settings (user_id, user_type, banner_color) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE banner_color = VALUES(banner_color)
        ");
        return $stmt->execute([$userId, $userType, $color]);
    }

    /**
     * Récupère le profil public (pour le trombinoscope).
     */
    public function getPublicProfile(int $userId, string $userType): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT up.citation, up.site_web, up.lien_linkedin, up.lien_twitter,
                   up.lien_github, up.lien_instagram, up.competences_tags,
                   up.disponibilites, up.bureau, up.telephone_pro,
                   us.avatar_chemin, us.bio, us.banner_color, us.banner_image
            FROM user_profiles up
            LEFT JOIN user_settings us ON us.user_id = up.user_id AND us.user_type = up.user_type
            WHERE up.user_id = ? AND up.user_type = ? AND up.profil_public = 1
        ");
        $stmt->execute([$userId, $userType]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Valide un fichier uploadé.
     */
    private function validateUpload(array $file, array $allowedTypes, int $maxSize): void
    {
        if (empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Erreur lors de l\'upload du fichier.');
        }

        if ($file['size'] > $maxSize) {
            $maxMo = round($maxSize / 1024 / 1024, 1);
            throw new \RuntimeException("Le fichier dépasse la taille maximale autorisée ({$maxMo} Mo).");
        }

        $mime = mime_content_type($file['tmp_name']);
        if (!in_array($mime, $allowedTypes, true)) {
            throw new \RuntimeException('Type de fichier non autorisé. Formats acceptés : JPG, PNG, GIF, WebP.');
        }
    }

    /**
     * Sanitize URL fields — only validates actual URL fields.
     */
    private function sanitizeUrl(string $field, string $value): string
    {
        if ($value === '') return '';

        $urlFields = ['site_web', 'lien_linkedin', 'lien_twitter', 'lien_github', 'lien_instagram', 'lien_autre'];
        if (in_array($field, $urlFields, true) && $value !== '') {
            if (!preg_match('#^https?://#i', $value)) {
                $value = 'https://' . $value;
            }
            if (!filter_var($value, FILTER_VALIDATE_URL)) {
                return '';
            }
        }

        return $value;
    }
}
