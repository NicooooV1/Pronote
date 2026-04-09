<?php
declare(strict_types=1);

namespace API\Services;

/**
 * Gere les modules en quarantaine (suspects mais non bloques).
 * Les modules quarantaines sont dans storage/quarantine/{key}/
 * et ne sont pas routables tant qu'un admin ne les approuve pas.
 */
class QuarantineService
{
    private string $quarantinePath;
    private string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
        $this->quarantinePath = $basePath . '/storage/quarantine';

        if (!is_dir($this->quarantinePath)) {
            mkdir($this->quarantinePath, 0755, true);
        }
    }

    /**
     * Deplace un module vers la quarantaine.
     */
    public function quarantine(string $key, string $sourcePath, array $scanResults = []): bool
    {
        $targetDir = $this->quarantinePath . '/' . $key;

        if (is_dir($targetDir)) {
            $this->removeDir($targetDir);
        }

        rename($sourcePath, $targetDir);

        // Save scan results
        file_put_contents(
            $targetDir . '/_quarantine_report.json',
            json_encode([
                'key'           => $key,
                'quarantined_at' => date('c'),
                'scan_results'  => $scanResults,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        return true;
    }

    /**
     * Approuver un module quarantine (deplacer vers le repertoire principal).
     */
    public function approve(string $key): bool
    {
        $quarantineDir = $this->quarantinePath . '/' . $key;
        $targetDir = $this->basePath . '/' . $key;

        if (!is_dir($quarantineDir)) {
            return false;
        }

        // Remove quarantine report
        $reportFile = $quarantineDir . '/_quarantine_report.json';
        if (file_exists($reportFile)) {
            unlink($reportFile);
        }

        if (is_dir($targetDir)) {
            $this->removeDir($targetDir);
        }

        return rename($quarantineDir, $targetDir);
    }

    /**
     * Rejeter un module quarantine (supprimer).
     */
    public function reject(string $key): bool
    {
        $quarantineDir = $this->quarantinePath . '/' . $key;
        if (!is_dir($quarantineDir)) {
            return false;
        }
        $this->removeDir($quarantineDir);
        return true;
    }

    /**
     * Lister tous les modules en quarantaine.
     */
    public function getAll(): array
    {
        $modules = [];

        if (!is_dir($this->quarantinePath)) {
            return [];
        }

        $dirs = glob($this->quarantinePath . '/*', GLOB_ONLYDIR);
        foreach ($dirs as $dir) {
            $key = basename($dir);
            $report = [];
            $reportFile = $dir . '/_quarantine_report.json';
            if (file_exists($reportFile)) {
                $report = json_decode(file_get_contents($reportFile), true) ?: [];
            }

            $moduleJson = $dir . '/module.json';
            $meta = file_exists($moduleJson) ? (json_decode(file_get_contents($moduleJson), true) ?: []) : [];

            $modules[] = [
                'key'           => $key,
                'name'          => $meta['name'] ?? $key,
                'version'       => $meta['version'] ?? '?',
                'author'        => $meta['author'] ?? 'Unknown',
                'quarantined_at' => $report['quarantined_at'] ?? null,
                'violations'    => $report['scan_results']['violations'] ?? [],
                'warnings'      => $report['scan_results']['warnings'] ?? [],
            ];
        }

        return $modules;
    }

    private function removeDir(string $dir): void
    {
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
