<?php
declare(strict_types=1);

namespace API\Services;

/**
 * UpdateService — Vérification et application des mises à jour Fronote.
 *
 * Vérifie les nouvelles versions via GitHub Releases.
 * Gère le cycle : check → download → backup → apply → verify
 *
 * Config .env :
 *   UPDATE_REPO=fronote/fronote     (GitHub repo)
 *   UPDATE_CHANNEL=stable           (stable | beta)
 */
class UpdateService
{
    private string $basePath;
    private string $repo;
    private string $channel;
    private string $currentVersion;

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
        $this->repo = getenv('UPDATE_REPO') ?: 'fronote/fronote';
        $this->channel = getenv('UPDATE_CHANNEL') ?: 'stable';

        $versionFile = $basePath . '/version.json';
        $data = file_exists($versionFile) ? json_decode(file_get_contents($versionFile), true) : [];
        $this->currentVersion = $data['version'] ?? '2.0.0';
    }

    /**
     * Vérifie s'il y a une mise à jour disponible.
     */
    public function checkForUpdate(): ?array
    {
        $cache = app('cache');
        $cached = $cache->get('update_check');
        if ($cached !== null) return $cached;

        $releases = $this->fetchReleases();
        $latest = $this->findLatestRelease($releases);

        if (!$latest) {
            $cache->put('update_check', [], 3600);
            return null;
        }

        $latestVersion = ltrim($latest['tag_name'] ?? '', 'v');
        if (version_compare($latestVersion, $this->currentVersion, '<=')) {
            $cache->put('update_check', [], 3600);
            return null;
        }

        $result = [
            'available' => true,
            'current_version' => $this->currentVersion,
            'new_version' => $latestVersion,
            'release_name' => $latest['name'] ?? '',
            'changelog' => $latest['body'] ?? '',
            'download_url' => $latest['zipball_url'] ?? '',
            'published_at' => $latest['published_at'] ?? '',
        ];

        $cache->put('update_check', $result, 3600);
        return $result;
    }

    /**
     * Télécharge et applique une mise à jour.
     * ATTENTION : Crée un backup automatique avant l'application.
     */
    public function applyUpdate(string $downloadUrl): array
    {
        // 1) Backup
        try {
            $backup = app('backup');
            $backupResult = $backup->createBackup();
            if (empty($backupResult)) {
                return ['success' => false, 'error' => 'Échec du backup pré-mise à jour.'];
            }
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => 'Backup failed: ' . $e->getMessage()];
        }

        // 2) Télécharger
        $tmpDir = $this->basePath . '/storage/tmp';
        if (!is_dir($tmpDir)) mkdir($tmpDir, 0755, true);

        $zipPath = $tmpDir . '/update.zip';
        $ctx = stream_context_create([
            'http' => ['timeout' => 120, 'user_agent' => 'Fronote/' . $this->currentVersion],
        ]);
        $content = @file_get_contents($downloadUrl, false, $ctx);
        if ($content === false) {
            return ['success' => false, 'error' => 'Échec du téléchargement.'];
        }
        file_put_contents($zipPath, $content);

        // 3) Extraire dans un dossier temporaire
        $extractDir = $tmpDir . '/update_extract';
        if (is_dir($extractDir)) {
            $this->removeDir($extractDir);
        }

        if (!class_exists('ZipArchive')) {
            @unlink($zipPath);
            return ['success' => false, 'error' => 'Extension ZipArchive requise.'];
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            @unlink($zipPath);
            return ['success' => false, 'error' => 'Archive ZIP invalide.'];
        }
        $zip->extractTo($extractDir);
        $zip->close();
        @unlink($zipPath);

        // 4) Trouver le dossier racine dans l'extraction
        $entries = array_diff(scandir($extractDir), ['.', '..']);
        $sourceDir = $extractDir;
        if (count($entries) === 1 && is_dir($extractDir . '/' . reset($entries))) {
            $sourceDir = $extractDir . '/' . reset($entries);
        }

        // 5) Copier les fichiers (sans écraser .env, storage/, uploads/)
        $protected = ['.env', 'storage', 'uploads', 'install.lock', 'logs'];
        $this->copyDirectory($sourceDir, $this->basePath, $protected);

        // 6) Nettoyage
        $this->removeDir($extractDir);

        // 7) Vérification
        $newVersionFile = $this->basePath . '/version.json';
        if (file_exists($newVersionFile)) {
            $newData = json_decode(file_get_contents($newVersionFile), true);
            $newVersion = $newData['version'] ?? $this->currentVersion;
        } else {
            $newVersion = $this->currentVersion;
        }

        return [
            'success' => true,
            'message' => 'Mise à jour appliquée.',
            'old_version' => $this->currentVersion,
            'new_version' => $newVersion,
        ];
    }

    /**
     * Retourne la version actuelle.
     */
    public function getCurrentVersion(): string
    {
        return $this->currentVersion;
    }

    // ─── Helpers privés ─────────────────────────────────────────────

    private function fetchReleases(): array
    {
        $url = "https://api.github.com/repos/{$this->repo}/releases";
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 10,
                'user_agent' => 'Fronote/' . $this->currentVersion,
                'header' => "Accept: application/vnd.github.v3+json\r\n",
            ],
        ]);
        $json = @file_get_contents($url, false, $ctx);
        return $json ? (json_decode($json, true) ?? []) : [];
    }

    private function findLatestRelease(array $releases): ?array
    {
        foreach ($releases as $release) {
            if ($this->channel === 'stable' && !empty($release['prerelease'])) {
                continue;
            }
            return $release;
        }
        return null;
    }

    private function copyDirectory(string $src, string $dst, array $protected = []): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($items as $item) {
            $relative = substr($item->getPathname(), strlen($src) + 1);
            $relative = str_replace('\\', '/', $relative);

            // Vérifier les fichiers/dossiers protégés
            $skip = false;
            foreach ($protected as $p) {
                if (str_starts_with($relative, $p)) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) continue;

            $target = $dst . '/' . $relative;
            if ($item->isDir()) {
                if (!is_dir($target)) mkdir($target, 0755, true);
            } else {
                copy($item->getPathname(), $target);
            }
        }
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
