<?php
declare(strict_types=1);

namespace API\Services;

/**
 * BackupService — Sauvegarde et restauration de la base de données et des uploads
 *
 * Usage :
 *   $backup = new BackupService($pdo, BASE_PATH);
 *   $file = $backup->createDatabaseBackup();
 *   $backup->restore($file);
 *   $backup->cleanup(5); // Garder les 5 derniers
 */
class BackupService
{
	protected \PDO $pdo;
	protected string $basePath;
	protected string $backupPath;

	public function __construct(\PDO $pdo, string $basePath)
	{
		$this->pdo = $pdo;
		$this->basePath = $basePath;
		$this->backupPath = $basePath . '/storage/backups';
		$this->ensureDirectory($this->backupPath);
	}

	/**
	 * Crée un dump SQL complet de la base de données
	 *
	 * @return string Chemin absolu du fichier de backup
	 */
	public function createDatabaseBackup(): string
	{
		$timestamp = date('Y-m-d_H-i-s');
		$filename = "backup_db_{$timestamp}.sql";
		$filepath = $this->backupPath . '/' . $filename;

		$tables = $this->getTables();
		$sql = "-- Fronote Database Backup\n";
		$sql .= "-- Date: " . date('Y-m-d H:i:s') . "\n";
		$sql .= "-- Tables: " . count($tables) . "\n\n";
		$sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

		foreach ($tables as $table) {
			// Structure
			$stmt = $this->pdo->query("SHOW CREATE TABLE `{$table}`");
			$row = $stmt->fetch(\PDO::FETCH_ASSOC);
			$sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
			$sql .= ($row['Create Table'] ?? '') . ";\n\n";

			// Data
			$stmt = $this->pdo->query("SELECT * FROM `{$table}`");
			$rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

			if (!empty($rows)) {
				$columns = array_keys($rows[0]);
				$colList = '`' . implode('`, `', $columns) . '`';

				// Batch inserts (500 rows per INSERT)
				$batches = array_chunk($rows, 500);
				foreach ($batches as $batch) {
					$values = [];
					foreach ($batch as $row) {
						$escaped = array_map(function ($v) {
							if ($v === null) return 'NULL';
							return $this->pdo->quote((string) $v);
						}, array_values($row));
						$values[] = '(' . implode(', ', $escaped) . ')';
					}
					$sql .= "INSERT INTO `{$table}` ({$colList}) VALUES\n";
					$sql .= implode(",\n", $values) . ";\n\n";
				}
			}
		}

		$sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";

		// Compress with gzip if available
		if (function_exists('gzencode')) {
			$filepath .= '.gz';
			file_put_contents($filepath, gzencode($sql, 9));
		} else {
			file_put_contents($filepath, $sql);
		}

		return $filepath;
	}

	/**
	 * Crée un backup du dossier uploads
	 *
	 * @return string|null Chemin du fichier ZIP, ou null si zip non disponible
	 */
	public function createUploadsBackup(): ?string
	{
		$uploadsPath = $this->basePath . '/uploads';
		if (!is_dir($uploadsPath)) {
			return null;
		}

		if (!class_exists('ZipArchive')) {
			return null;
		}

		$timestamp = date('Y-m-d_H-i-s');
		$filename = "backup_uploads_{$timestamp}.zip";
		$filepath = $this->backupPath . '/' . $filename;

		$zip = new \ZipArchive();
		if ($zip->open($filepath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
			return null;
		}

		$this->addDirectoryToZip($zip, $uploadsPath, 'uploads');
		$zip->close();

		return $filepath;
	}

	/**
	 * Crée un backup complet (DB + uploads)
	 *
	 * @return array{db: string, uploads: ?string}
	 */
	public function createFullBackup(): array
	{
		return [
			'db' => $this->createDatabaseBackup(),
			'uploads' => $this->createUploadsBackup(),
		];
	}

	/**
	 * Restaure un backup SQL
	 *
	 * @param string $filepath Chemin vers le fichier .sql ou .sql.gz
	 * @return bool
	 */
	public function restoreDatabase(string $filepath): bool
	{
		if (!file_exists($filepath)) {
			throw new \RuntimeException("Backup file not found: {$filepath}");
		}

		// Read (decompress if gzipped)
		if (str_ends_with($filepath, '.gz')) {
			$sql = gzdecode(file_get_contents($filepath));
		} else {
			$sql = file_get_contents($filepath);
		}

		if ($sql === false) {
			throw new \RuntimeException("Failed to read backup file");
		}

		try {
			$this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

			// Split by statement (basic — handles standard mysqldump output)
			$statements = array_filter(
				array_map('trim', explode(";\n", $sql)),
				fn($s) => $s !== '' && !str_starts_with($s, '--')
			);

			foreach ($statements as $stmt) {
				$this->pdo->exec($stmt);
			}

			$this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
			return true;
		} catch (\Throwable $e) {
			$this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
			throw $e;
		}
	}

	/**
	 * Liste les backups disponibles
	 *
	 * @return array Liste triée par date (plus récent en premier)
	 */
	public function listBackups(): array
	{
		$files = glob($this->backupPath . '/backup_*');
		if (!$files) return [];

		$backups = [];
		foreach ($files as $file) {
			$basename = basename($file);
			$type = str_contains($basename, '_db_') ? 'database' : 'uploads';
			$backups[] = [
				'filename' => $basename,
				'path' => $file,
				'type' => $type,
				'size_mb' => round(filesize($file) / 1048576, 2),
				'created_at' => date('Y-m-d H:i:s', filemtime($file)),
			];
		}

		usort($backups, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
		return $backups;
	}

	/**
	 * Supprime les backups anciens, garde les N plus récents par type
	 *
	 * @param int $keep Nombre de backups à conserver par type
	 * @return int Nombre de fichiers supprimés
	 */
	public function cleanup(int $keep = 5): int
	{
		$deleted = 0;
		foreach (['db', 'uploads'] as $type) {
			$pattern = $this->backupPath . "/backup_{$type}_*";
			$files = glob($pattern);
			if (!$files) continue;

			// Trier par date (plus récent en premier)
			usort($files, fn($a, $b) => filemtime($b) - filemtime($a));

			// Supprimer les anciens
			foreach (array_slice($files, $keep) as $file) {
				if (unlink($file)) $deleted++;
			}
		}
		return $deleted;
	}

	/**
	 * Supprime un backup spécifique
	 */
	public function deleteBackup(string $filename): bool
	{
		$filepath = $this->backupPath . '/' . basename($filename);
		if (!file_exists($filepath) || !str_starts_with(basename($filename), 'backup_')) {
			return false;
		}
		return unlink($filepath);
	}

	// ─── Private helpers ────────────────────────────────────────────

	private function getTables(): array
	{
		$stmt = $this->pdo->query('SHOW TABLES');
		return $stmt->fetchAll(\PDO::FETCH_COLUMN);
	}

	private function addDirectoryToZip(\ZipArchive $zip, string $dir, string $prefix): void
	{
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::LEAVES_ONLY
		);

		foreach ($iterator as $file) {
			if ($file->isFile()) {
				$relativePath = $prefix . '/' . substr($file->getRealPath(), strlen($dir) + 1);
				$zip->addFile($file->getRealPath(), str_replace('\\', '/', $relativePath));
			}
		}
	}

	private function ensureDirectory(string $path): void
	{
		if (!is_dir($path)) {
			mkdir($path, 0755, true);
		}
	}
}
