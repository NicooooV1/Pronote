<?php
declare(strict_types=1);

namespace API\Services;

use PDO;

/**
 * MarketplaceService — Catalogue, téléchargement et installation de modules/thèmes.
 *
 * Cycle : browse → download → extract → validate → install → enable
 * Source : registre JSON distant (GitHub releases ou API custom)
 */
class MarketplaceService
{
    private PDO $pdo;
    private string $basePath;
    private string $registryUrl;
    private string $tempDir;

    public function __construct(PDO $pdo, string $basePath)
    {
        $this->pdo = $pdo;
        $this->basePath = rtrim($basePath, '/\\');
        $this->registryUrl = getenv('MARKETPLACE_REGISTRY_URL') ?: 'https://raw.githubusercontent.com/fronote/marketplace/main/registry.json';
        $this->tempDir = $this->basePath . '/storage/tmp';
        if (!is_dir($this->tempDir)) {
            @mkdir($this->tempDir, 0755, true);
        }
    }

    // ─── Catalogue ──────────────────────────────────────────────────

    /**
     * Récupère le catalogue distant des modules/thèmes disponibles.
     * Cache le résultat 1h via CacheManager.
     */
    public function getCatalog(string $type = 'module'): array
    {
        $cache = app('cache');
        $cacheKey = 'marketplace_catalog_' . $type;
        $cached = $cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $catalog = $this->fetchRegistry();
        $items = array_filter($catalog['items'] ?? [], fn($i) => ($i['type'] ?? 'module') === $type);
        $items = array_values($items);

        $cache->put($cacheKey, $items, 3600);
        return $items;
    }

    /**
     * Recherche dans le catalogue.
     */
    public function search(string $query, string $type = 'module'): array
    {
        $catalog = $this->getCatalog($type);
        if (empty($query)) {
            return $catalog;
        }

        $q = mb_strtolower($query);
        return array_values(array_filter($catalog, function ($item) use ($q) {
            $haystack = mb_strtolower(
                ($item['name'] ?? '') . ' ' .
                ($item['description'] ?? '') . ' ' .
                ($item['author'] ?? '') . ' ' .
                implode(' ', $item['tags'] ?? [])
            );
            return str_contains($haystack, $q);
        }));
    }

    /**
     * Détail d'un item du catalogue.
     */
    public function getItem(string $key, string $type = 'module'): ?array
    {
        $catalog = $this->getCatalog($type);
        foreach ($catalog as $item) {
            if (($item['key'] ?? '') === $key) {
                return $item;
            }
        }
        return null;
    }

    // ─── Installation ───────────────────────────────────────────────

    /**
     * Installe un module depuis le catalogue distant.
     * 1) Télécharge le ZIP  2) Extrait  3) Valide module.json  4) Sync DB
     */
    public function installModule(string $key): array
    {
        $item = $this->getItem($key, 'module');
        if (!$item) {
            return ['success' => false, 'error' => "Module '{$key}' introuvable dans le catalogue."];
        }

        // Vérifier que le module n'est pas déjà installé
        $targetDir = $this->basePath . '/' . $key;
        if (is_dir($targetDir) && file_exists($targetDir . '/module.json')) {
            return ['success' => false, 'error' => "Le module '{$key}' est déjà installé."];
        }

        // Télécharger
        $zipUrl = $item['download_url'] ?? '';
        if (empty($zipUrl)) {
            return ['success' => false, 'error' => 'URL de téléchargement manquante.'];
        }

        $zipPath = $this->tempDir . '/' . $key . '.zip';
        $downloaded = $this->downloadFile($zipUrl, $zipPath);
        if (!$downloaded) {
            return ['success' => false, 'error' => 'Échec du téléchargement.'];
        }

        // ─── SHA-256 integrity check ────────────────────────────────
        $expectedHash = $item['sha256'] ?? null;
        if ($expectedHash) {
            $actualHash = hash_file('sha256', $zipPath);
            if (!hash_equals($expectedHash, $actualHash)) {
                @unlink($zipPath);
                return ['success' => false, 'error' => 'Verification d\'integrite echouee (SHA-256 mismatch).'];
            }
        }

        // Extraire
        $extracted = $this->extractZip($zipPath, $targetDir);
        @unlink($zipPath);
        if (!$extracted) {
            return ['success' => false, 'error' => "Échec de l'extraction ZIP."];
        }

        // Valider le module.json
        if (!file_exists($targetDir . '/module.json')) {
            $this->removeDirectory($targetDir);
            return ['success' => false, 'error' => 'module.json absent du package.'];
        }

        $manifest = json_decode(file_get_contents($targetDir . '/module.json'), true);
        if (!$manifest || empty($manifest['key'])) {
            $this->removeDirectory($targetDir);
            return ['success' => false, 'error' => 'module.json invalide.'];
        }

        // ─── Security scan ─────────────────────────────────────────
        $modulePerms = $manifest['required_permissions'] ?? [];
        $scanner = new \API\Security\ModuleScanner($modulePerms);
        $scanResult = $scanner->scanDirectory($targetDir);

        if (!$scanResult['safe']) {
            // Critical violations → quarantine
            $quarantine = new QuarantineService($this->basePath);
            $quarantine->quarantine($key, $targetDir, $scanResult);
            return [
                'success' => false,
                'error' => 'Module mis en quarantaine : code potentiellement dangereux detecte.',
                'violations' => $scanResult['violations'],
                'quarantined' => true,
            ];
        }

        // ─── Backup existing module before overwrite ────────────────
        $backupDir = $this->basePath . '/storage/backups/modules';
        if (!is_dir($backupDir)) @mkdir($backupDir, 0755, true);
        if (is_dir($this->basePath . '/' . $key)) {
            $backupPath = $backupDir . '/' . $key . '_' . date('Ymd_His');
            @rename($this->basePath . '/' . $key, $backupPath);
        }

        // Sync avec la base de données
        try {
            $sdk = app('module_sdk');
            $sdk->syncModule($manifest);
        } catch (\Throwable $e) {
            error_log('Marketplace install sync error: ' . $e->getMessage());
        }

        // Enregistrer dans la table marketplace
        $this->recordInstall($key, 'module', $item['version'] ?? '1.0.0', $item);

        return [
            'success' => true,
            'message' => "Module '{$key}' installé avec succès.",
            'module' => $manifest,
        ];
    }

    /**
     * Installe un thème depuis le catalogue distant.
     */
    public function installTheme(string $key): array
    {
        $item = $this->getItem($key, 'theme');
        if (!$item) {
            return ['success' => false, 'error' => "Thème '{$key}' introuvable."];
        }

        $cssUrl = $item['download_url'] ?? '';
        if (empty($cssUrl)) {
            return ['success' => false, 'error' => 'URL de téléchargement manquante.'];
        }

        // Télécharger le ZIP du thème
        $zipPath = $this->tempDir . '/' . $key . '_theme.zip';
        if (!$this->downloadFile($cssUrl, $zipPath)) {
            return ['success' => false, 'error' => 'Échec du téléchargement.'];
        }

        $extractDir = $this->tempDir . '/' . $key . '_theme';
        $this->extractZip($zipPath, $extractDir);
        @unlink($zipPath);

        // Trouver le fichier CSS principal
        $cssFiles = glob($extractDir . '/*.css') ?: glob($extractDir . '/*/*.css') ?: [];
        if (empty($cssFiles)) {
            $this->removeDirectory($extractDir);
            return ['success' => false, 'error' => 'Aucun fichier CSS trouvé dans le package.'];
        }

        // Copier le CSS dans assets/css/
        $cssTarget = $this->basePath . '/assets/css/theme-' . $key . '.css';
        copy($cssFiles[0], $cssTarget);

        // Copier la preview si présente
        $previewSrc = glob($extractDir . '/preview.{png,jpg,webp}', GLOB_BRACE);
        $previewPath = null;
        if (!empty($previewSrc)) {
            $ext = pathinfo($previewSrc[0], PATHINFO_EXTENSION);
            $previewPath = 'assets/css/theme-' . $key . '-preview.' . $ext;
            copy($previewSrc[0], $this->basePath . '/' . $previewPath);
        }

        $this->removeDirectory($extractDir);

        // Enregistrer en base
        $this->pdo->prepare(
            "INSERT INTO themes (`key`, name, description, author, version, css_file, preview_image, actif, installed_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 0, NOW())
             ON DUPLICATE KEY UPDATE version = VALUES(version), css_file = VALUES(css_file), preview_image = VALUES(preview_image)"
        )->execute([
            $key,
            $item['name'] ?? $key,
            $item['description'] ?? '',
            $item['author'] ?? 'Communauté',
            $item['version'] ?? '1.0.0',
            'assets/css/theme-' . $key . '.css',
            $previewPath,
        ]);

        $this->recordInstall($key, 'theme', $item['version'] ?? '1.0.0', $item);

        return ['success' => true, 'message' => "Thème '{$key}' installé."];
    }

    // ─── Désinstallation ────────────────────────────────────────────

    /**
     * Désinstalle un module (supprime les fichiers + désactive en base).
     */
    public function uninstallModule(string $key): array
    {
        $targetDir = $this->basePath . '/' . $key;

        // Vérifier que ce n'est pas un module core
        $moduleService = app('modules');
        if ($moduleService->isCore($key)) {
            return ['success' => false, 'error' => 'Impossible de désinstaller un module système.'];
        }

        // Désactiver d'abord
        $moduleService->setEnabled($key, false);

        // Supprimer les fichiers
        if (is_dir($targetDir)) {
            $this->removeDirectory($targetDir);
        }

        // Marquer comme désinstallé en base
        $this->pdo->prepare("DELETE FROM marketplace_installs WHERE item_key = ? AND item_type = 'module'")->execute([$key]);

        return ['success' => true, 'message' => "Module '{$key}' désinstallé."];
    }

    /**
     * Désinstalle un thème.
     */
    public function uninstallTheme(string $key): array
    {
        // Ne pas permettre la suppression des thèmes built-in
        if (in_array($key, ['classic', 'glass'], true)) {
            return ['success' => false, 'error' => 'Impossible de désinstaller un thème intégré.'];
        }

        $cssFile = $this->basePath . '/assets/css/theme-' . $key . '.css';
        if (file_exists($cssFile)) {
            @unlink($cssFile);
        }

        // Supprimer la preview
        foreach (['png', 'jpg', 'webp'] as $ext) {
            $preview = $this->basePath . '/assets/css/theme-' . $key . '-preview.' . $ext;
            if (file_exists($preview)) {
                @unlink($preview);
            }
        }

        $this->pdo->prepare("DELETE FROM themes WHERE `key` = ?")->execute([$key]);
        $this->pdo->prepare("DELETE FROM marketplace_installs WHERE item_key = ? AND item_type = 'theme'")->execute([$key]);

        return ['success' => true, 'message' => "Thème '{$key}' désinstallé."];
    }

    // ─── Mise à jour ────────────────────────────────────────────────

    /**
     * Vérifie les mises à jour disponibles pour les items installés.
     */
    public function checkUpdates(): array
    {
        $installed = $this->getInstalled();
        $updates = [];

        foreach ($installed as $row) {
            $item = $this->getItem($row['item_key'], $row['item_type']);
            if ($item && version_compare($item['version'] ?? '0', $row['version'] ?? '0', '>')) {
                $updates[] = [
                    'key' => $row['item_key'],
                    'type' => $row['item_type'],
                    'current_version' => $row['version'],
                    'new_version' => $item['version'],
                    'changelog' => $item['changelog'] ?? '',
                ];
            }
        }

        return $updates;
    }

    /**
     * Retourne les items installés via le marketplace.
     */
    public function getInstalled(): array
    {
        try {
            $stmt = $this->pdo->query("SELECT * FROM marketplace_installs ORDER BY installed_at DESC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    // ─── Helpers privés ─────────────────────────────────────────────

    private function fetchRegistry(): array
    {
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 10,
                'user_agent' => 'Fronote/' . ($this->getVersion()),
            ],
            'ssl' => ['verify_peer' => true],
        ]);

        $json = @file_get_contents($this->registryUrl, false, $ctx);
        if ($json === false) {
            return ['items' => []];
        }

        $data = json_decode($json, true);
        return is_array($data) ? $data : ['items' => []];
    }

    private function downloadFile(string $url, string $dest): bool
    {
        $ctx = stream_context_create([
            'http' => ['timeout' => 60, 'user_agent' => 'Fronote/' . $this->getVersion()],
            'ssl' => ['verify_peer' => true],
        ]);

        $content = @file_get_contents($url, false, $ctx);
        if ($content === false) {
            return false;
        }

        return file_put_contents($dest, $content) !== false;
    }

    private function extractZip(string $zipPath, string $destDir): bool
    {
        if (!class_exists('ZipArchive')) {
            error_log('MarketplaceService: ZipArchive extension required');
            return false;
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return false;
        }

        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        $zip->extractTo($destDir);
        $zip->close();

        // Si le ZIP contient un seul dossier racine, remonter son contenu
        $entries = array_diff(scandir($destDir), ['.', '..']);
        if (count($entries) === 1) {
            $innerDir = $destDir . '/' . reset($entries);
            if (is_dir($innerDir)) {
                foreach (array_diff(scandir($innerDir), ['.', '..']) as $file) {
                    rename($innerDir . '/' . $file, $destDir . '/' . $file);
                }
                rmdir($innerDir);
            }
        }

        return true;
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }
        rmdir($dir);
    }

    private function recordInstall(string $key, string $type, string $version, array $meta): void
    {
        try {
            $this->pdo->prepare(
                "INSERT INTO marketplace_installs (item_key, item_type, version, author, installed_at)
                 VALUES (?, ?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE version = VALUES(version)"
            )->execute([$key, $type, $version, $meta['author'] ?? '']);
        } catch (\Throwable $e) {
            error_log('MarketplaceService::recordInstall: ' . $e->getMessage());
        }
    }

    private function getVersion(): string
    {
        static $version = null;
        if ($version === null) {
            $file = $this->basePath . '/version.json';
            if (file_exists($file)) {
                $data = json_decode(file_get_contents($file), true);
                $version = $data['version'] ?? '2.0.0';
            } else {
                $version = '2.0.0';
            }
        }
        return $version;
    }
}
