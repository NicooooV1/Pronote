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

// 7. Temp file cleanup (files older than 24h in storage/tmp)
try {
    $tmpDir = BASE_PATH . '/storage/tmp';
    $cleaned = 0;
    if (is_dir($tmpDir)) {
        $cutoff = time() - 86400;
        foreach (new \DirectoryIterator($tmpDir) as $f) {
            if ($f->isDot() || $f->isDir()) continue;
            if ($f->getMTime() < $cutoff) {
                @unlink($f->getPathname());
                $cleaned++;
            }
        }
    }
    $log("Temp files: removed {$cleaned} old files from storage/tmp");
} catch (\Throwable $e) {
    $log("Temp cleanup error: " . $e->getMessage());
}

// 8. Expired sessions purge
try {
    $pdo = getPDO();
    $sessionLifetime = 1800; // 30 minutes
    $stmt = $pdo->prepare("DELETE FROM sessions WHERE last_activity < ?");
    $stmt->execute([time() - $sessionLifetime]);
    $purged = $stmt->rowCount();
    $log("Sessions: purged {$purged} expired sessions");
} catch (\Throwable $e) {
    $log("Session purge error: " . $e->getMessage());
}

// 9. Old notifications purge (> 90 days)
try {
    $pdo = getPDO();
    $stmt = $pdo->prepare("DELETE FROM notifications WHERE is_read = 1 AND created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
    $stmt->execute();
    $purged = $stmt->rowCount();
    $log("Notifications: purged {$purged} old read notifications");
} catch (\Throwable $e) {
    $log("Notification purge error: " . $e->getMessage());
}

// 10. Orphan upload cleanup (files not referenced in any table)
try {
    $uploadDir = BASE_PATH . '/uploads/tmp';
    $cleaned = 0;
    if (is_dir($uploadDir)) {
        $cutoff = time() - 86400;
        foreach (new \DirectoryIterator($uploadDir) as $f) {
            if ($f->isDot() || $f->isDir()) continue;
            if ($f->getMTime() < $cutoff) {
                @unlink($f->getPathname());
                $cleaned++;
            }
        }
    }
    $log("Orphan uploads: removed {$cleaned} files from uploads/tmp");
} catch (\Throwable $e) {
    $log("Orphan upload cleanup error: " . $e->getMessage());
}

// 11. Translation coverage report
try {
    $langPath = BASE_PATH . '/lang';
    $frDir = $langPath . '/fr/modules';
    $locales = ['en', 'es', 'de', 'ru', 'nl', 'ar', 'th'];
    $frCount = count(glob($frDir . '/*.json'));
    foreach ($locales as $locale) {
        $localeDir = $langPath . '/' . $locale . '/modules';
        $count = is_dir($localeDir) ? count(glob($localeDir . '/*.json')) : 0;
        $pct = $frCount > 0 ? round($count / $frCount * 100) : 0;
        $log("i18n [{$locale}]: {$count}/{$frCount} modules ({$pct}%)");
    }
} catch (\Throwable $e) {
    $log("Translation report error: " . $e->getMessage());
}

$duration = round(microtime(true) - $startTime, 2);
$log("=== Maintenance completed in {$duration}s ===");
