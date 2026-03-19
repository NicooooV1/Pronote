<?php
/**
 * Cron Job — Maintenance quotidienne
 *
 * Tâches :
 *   1. Nettoyage des audit logs expirés (respect AUDIT_RETENTION_DAYS)
 *   2. Backup automatique de la base de données
 *   3. Rotation des backups (garder les N derniers)
 *   4. Nettoyage du cache expiré
 *   5. Purge des tokens API expirés
 *   6. Nettoyage des entrées de rate limiting expirées
 *
 * Configurer dans crontab :
 *   0 2 * * * php /chemin/vers/fronote/cron/daily_maintenance.php >> /chemin/vers/fronote/logs/cron.log 2>&1
 */
declare(strict_types=1);

// Ne pas exécuter depuis le web
if (php_sapi_name() !== 'cli') {
	http_response_code(403);
	exit('This script must be run from the command line.');
}

require_once dirname(__DIR__) . '/API/bootstrap.php';

$startTime = microtime(true);
$log = function(string $msg) {
	echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
};

$log('=== Fronote Daily Maintenance ===');

// 1. Audit log cleanup
try {
	$audit = app('audit');
	$deleted = $audit->cleanup();
	$log("Audit: cleaned up {$deleted} expired entries");
} catch (\Throwable $e) {
	$log("Audit cleanup error: " . $e->getMessage());
}

// 2. Database backup
try {
	$backup = app('backup');
	$file = $backup->createDatabaseBackup();
	$size = round(filesize($file) / 1048576, 2);
	$log("Backup: created {$file} ({$size} MB)");
} catch (\Throwable $e) {
	$log("Backup error: " . $e->getMessage());
}

// 3. Backup rotation
try {
	$keep = (int) (env('BACKUP_RETENTION', '5') ?: 5);
	$cleaned = $backup->cleanup($keep);
	$log("Backup rotation: removed {$cleaned} old backups (keeping {$keep})");
} catch (\Throwable $e) {
	$log("Backup rotation error: " . $e->getMessage());
}

// 4. Cache GC
try {
	$cache = app('cache');
	$gcCount = $cache->gc();
	$log("Cache: garbage collected {$gcCount} expired entries");
} catch (\Throwable $e) {
	$log("Cache GC error: " . $e->getMessage());
}

// 5. API token purge
try {
	$tokenGuard = new \API\Auth\TokenGuard(getPDO());
	$purged = $tokenGuard->purgeExpired();
	$log("Tokens: purged {$purged} expired API tokens");
} catch (\Throwable $e) {
	$log("Token purge error: " . $e->getMessage());
}

// 6. Rate limit cleanup
try {
	$limiter = new \API\Security\RateLimiter();
	$cleaned = $limiter->cleanup();
	$log("Rate limits: cleaned {$cleaned} expired entries");
} catch (\Throwable $e) {
	$log("Rate limit cleanup error: " . $e->getMessage());
}

$duration = round(microtime(true) - $startTime, 2);
$log("=== Maintenance completed in {$duration}s ===");
