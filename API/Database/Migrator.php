<?php
declare(strict_types=1);

namespace API\Database;

use PDO;

/**
 * Système de migrations SQL versionnées.
 * 
 * Chaque fichier dans migrations/ préfixé par un numéro séquentiel (ex: 003_xxx.sql)
 * est exécuté une seule fois. L'état est stocké dans la table `migrations`.
 *
 * Usage :
 *   $migrator = new Migrator($pdo, BASE_PATH . '/migrations');
 *   $migrator->run();       // Lancer les migrations pendantes
 *   $migrator->status();    // Voir l'état
 *   $migrator->rollback();  // Annuler la dernière migration
 */
class Migrator
{
    private PDO $pdo;
    private string $migrationsPath;
    private string $tableName = 'migrations';

    public function __construct(PDO $pdo, string $migrationsPath)
    {
        $this->pdo = $pdo;
        $this->migrationsPath = rtrim($migrationsPath, '/\\');
        $this->ensureMigrationsTable();
    }

    /**
     * Crée la table migrations si elle n'existe pas
     */
    private function ensureMigrationsTable(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `{$this->tableName}` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `migration` VARCHAR(255) NOT NULL UNIQUE,
                `batch` INT NOT NULL DEFAULT 1,
                `executed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_batch (`batch`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    /**
     * Exécute toutes les migrations pendantes
     * @return array{executed: string[], errors: array}
     */
    public function run(): array
    {
        $executed = [];
        $errors   = [];
        $pending  = $this->getPending();

        if (empty($pending)) {
            return ['executed' => [], 'errors' => []];
        }

        $batch = $this->getNextBatch();

        foreach ($pending as $file) {
            $name = basename($file);
            try {
                $sql = file_get_contents($file);
                if (empty(trim($sql))) {
                    continue;
                }
                
                // Exécuter le SQL (supporte multi-statements)
                $this->pdo->exec($sql);
                
                // Enregistrer comme exécuté
                $stmt = $this->pdo->prepare("INSERT INTO `{$this->tableName}` (migration, batch) VALUES (?, ?)");
                $stmt->execute([$name, $batch]);
                
                $executed[] = $name;
            } catch (\PDOException $e) {
                $errors[] = ['migration' => $name, 'error' => $e->getMessage()];
                error_log("Migration error [{$name}]: " . $e->getMessage());
                // Continuer les autres migrations (pas de rollback global)
            }
        }

        return ['executed' => $executed, 'errors' => $errors];
    }

    /**
     * Retourne les migrations pendantes (non exécutées)
     */
    public function getPending(): array
    {
        $all = $this->getAllFiles();
        $ran = $this->getRan();

        return array_filter($all, function ($file) use ($ran) {
            return !in_array(basename($file), $ran, true);
        });
    }

    /**
     * Retourne l'état complet
     */
    public function status(): array
    {
        $all = $this->getAllFiles();
        $ran = $this->getRan();
        $status = [];

        foreach ($all as $file) {
            $name = basename($file);
            $status[] = [
                'migration' => $name,
                'status'    => in_array($name, $ran, true) ? 'executed' : 'pending',
            ];
        }

        return $status;
    }

    /**
     * Retourne les noms des migrations déjà exécutées
     */
    public function getRan(): array
    {
        try {
            $stmt = $this->pdo->query("SELECT migration FROM `{$this->tableName}` ORDER BY id");
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (\PDOException $e) {
            return [];
        }
    }

    /**
     * Retourne tous les fichiers de migration triés
     */
    private function getAllFiles(): array
    {
        if (!is_dir($this->migrationsPath)) {
            return [];
        }

        $files = glob($this->migrationsPath . '/*.sql');
        sort($files);
        return $files ?: [];
    }

    /**
     * Numéro de batch suivant
     */
    private function getNextBatch(): int
    {
        try {
            $stmt = $this->pdo->query("SELECT MAX(batch) FROM `{$this->tableName}`");
            return ((int) $stmt->fetchColumn()) + 1;
        } catch (\PDOException $e) {
            return 1;
        }
    }
}
