<?php
declare(strict_types=1);

namespace API\Services;

use PDO;

/**
 * ThemeService — Gestion des thèmes CSS installables.
 *
 * Gère le CRUD des thèmes, leur activation par établissement ou par utilisateur,
 * et la validation des fichiers CSS uploadés.
 */
class ThemeService
{
    private PDO $pdo;
    private string $basePath;

    /** Thèmes intégrés non supprimables */
    private const BUILTIN_THEMES = ['classic', 'glass'];

    /** Extensions autorisées pour les previews */
    private const PREVIEW_EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp'];

    /** Taille max CSS : 500 Ko */
    private const MAX_CSS_SIZE = 512000;

    public function __construct(PDO $pdo, string $basePath)
    {
        $this->pdo = $pdo;
        $this->basePath = rtrim($basePath, '/\\');
    }

    // ─── CRUD ───────────────────────────────────────────────────────

    /**
     * Liste tous les thèmes (built-in + installés).
     */
    public function getAll(): array
    {
        $themes = [];

        // Built-in themes
        $themes[] = [
            'key' => 'classic',
            'name' => 'Classic',
            'description' => 'Thème par défaut, propre et professionnel.',
            'author' => 'Fronote',
            'version' => '2.0.0',
            'css_file' => 'assets/css/theme-classic.css',
            'preview_image' => null,
            'actif' => 1,
            'is_builtin' => true,
            'installed_at' => null,
        ];
        $themes[] = [
            'key' => 'glass',
            'name' => 'Glass',
            'description' => 'Thème glassmorphism avec effets de transparence.',
            'author' => 'Fronote',
            'version' => '2.0.0',
            'css_file' => 'assets/css/theme-glass.css',
            'preview_image' => null,
            'actif' => 1,
            'is_builtin' => true,
            'installed_at' => null,
        ];

        // DB themes
        try {
            $rows = $this->pdo->query("SELECT * FROM themes ORDER BY installed_at DESC")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $row['is_builtin'] = false;
                $themes[] = $row;
            }
        } catch (\Throwable $e) {
            // Table might not exist yet
        }

        return $themes;
    }

    /**
     * Retourne un thème par sa clé.
     */
    public function get(string $key): ?array
    {
        foreach ($this->getAll() as $theme) {
            if ($theme['key'] === $key) {
                return $theme;
            }
        }
        return null;
    }

    /**
     * Retourne le thème actif par défaut pour l'établissement.
     */
    public function getDefault(): string
    {
        try {
            $stmt = $this->pdo->query("SELECT valeur FROM etablissement_info WHERE cle = 'theme_default' LIMIT 1");
            $val = $stmt->fetchColumn();
            return $val ?: 'classic';
        } catch (\Throwable $e) {
            return 'classic';
        }
    }

    /**
     * Définit le thème par défaut de l'établissement.
     */
    public function setDefault(string $key): bool
    {
        try {
            $this->pdo->prepare(
                "INSERT INTO etablissement_info (cle, valeur) VALUES ('theme_default', ?)
                 ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)"
            )->execute([$key]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    // ─── Upload de thème custom ─────────────────────────────────────

    /**
     * Installe un thème depuis un upload de fichier CSS.
     */
    public function uploadTheme(array $file, string $key, string $name, string $description = '', ?array $previewFile = null): array
    {
        // Validation clé
        if (!preg_match('/^[a-z0-9_-]{2,30}$/', $key)) {
            return ['success' => false, 'error' => 'Clé de thème invalide (a-z, 0-9, -, _, 2-30 chars).'];
        }

        if (in_array($key, self::BUILTIN_THEMES, true)) {
            return ['success' => false, 'error' => 'Impossible d\'écraser un thème intégré.'];
        }

        // Validation fichier CSS
        if (empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'Erreur d\'upload du fichier CSS.'];
        }

        if ($file['size'] > self::MAX_CSS_SIZE) {
            return ['success' => false, 'error' => 'Fichier CSS trop volumineux (max 500 Ko).'];
        }

        $cssContent = file_get_contents($file['tmp_name']);

        // Validation sécurité CSS (pas de JS inline)
        if ($this->containsDangerousCSS($cssContent)) {
            return ['success' => false, 'error' => 'Le fichier CSS contient du code potentiellement dangereux.'];
        }

        // Écrire le fichier CSS
        $cssPath = 'assets/css/theme-' . $key . '.css';
        file_put_contents($this->basePath . '/' . $cssPath, $cssContent);

        // Preview optionnelle
        $previewPath = null;
        if ($previewFile && !empty($previewFile['tmp_name']) && $previewFile['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($previewFile['name'], PATHINFO_EXTENSION));
            if (in_array($ext, self::PREVIEW_EXTENSIONS, true) && $previewFile['size'] <= 2097152) {
                $previewPath = 'assets/css/theme-' . $key . '-preview.' . $ext;
                move_uploaded_file($previewFile['tmp_name'], $this->basePath . '/' . $previewPath);
            }
        }

        // Upsert en base
        $this->pdo->prepare(
            "INSERT INTO themes (`key`, name, description, author, version, css_file, preview_image, actif, installed_at)
             VALUES (?, ?, ?, 'Custom', '1.0.0', ?, ?, 1, NOW())
             ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description),
                css_file = VALUES(css_file), preview_image = COALESCE(VALUES(preview_image), preview_image)"
        )->execute([$key, $name, $description, $cssPath, $previewPath]);

        return ['success' => true, 'message' => "Thème '{$name}' installé."];
    }

    /**
     * Supprime un thème custom.
     */
    public function delete(string $key): array
    {
        if (in_array($key, self::BUILTIN_THEMES, true)) {
            return ['success' => false, 'error' => 'Impossible de supprimer un thème intégré.'];
        }

        // Supprimer les fichiers
        $cssFile = $this->basePath . '/assets/css/theme-' . $key . '.css';
        if (file_exists($cssFile)) {
            @unlink($cssFile);
        }
        foreach (self::PREVIEW_EXTENSIONS as $ext) {
            $preview = $this->basePath . '/assets/css/theme-' . $key . '-preview.' . $ext;
            if (file_exists($preview)) {
                @unlink($preview);
            }
        }

        // Réinitialiser les utilisateurs qui utilisaient ce thème
        $this->pdo->prepare("UPDATE user_settings SET theme = 'classic' WHERE theme = ?")->execute([$key]);

        // Supprimer de la base
        $this->pdo->prepare("DELETE FROM themes WHERE `key` = ?")->execute([$key]);

        return ['success' => true, 'message' => 'Thème supprimé.'];
    }

    // ─── Éditeur de tokens CSS ──────────────────────────────────────

    /**
     * Retourne les variables CSS custom de tokens.css parsées.
     */
    public function getTokens(): array
    {
        $tokensFile = $this->basePath . '/assets/css/tokens.css';
        if (!file_exists($tokensFile)) {
            return [];
        }

        $content = file_get_contents($tokensFile);
        $tokens = [];
        if (preg_match_all('/--([a-z0-9-]+)\s*:\s*([^;]+);/i', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $tokens[] = ['name' => '--' . $m[1], 'value' => trim($m[2])];
            }
        }
        return $tokens;
    }

    /**
     * Sauvegarde un override de tokens pour un thème.
     */
    public function saveTokenOverrides(string $themeKey, array $overrides): bool
    {
        try {
            $this->pdo->prepare(
                "INSERT INTO theme_token_overrides (theme_key, overrides) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE overrides = VALUES(overrides)"
            )->execute([$themeKey, json_encode($overrides)]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    // ─── Helpers ────────────────────────────────────────────────────

    /**
     * Vérifie si un contenu CSS contient des patterns dangereux.
     */
    private function containsDangerousCSS(string $css): bool
    {
        $dangerous = [
            'javascript:',
            'expression(',
            'eval(',
            '<script',
            'url(data:text/html',
            'behavior:',
            '-moz-binding:',
        ];
        $lower = strtolower($css);
        foreach ($dangerous as $pattern) {
            if (str_contains($lower, $pattern)) {
                return true;
            }
        }
        return false;
    }
}
