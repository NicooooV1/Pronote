<?php
/**
 * Cron Job — Maintenance horaire
 *
 * Taches :
 *   1. Nettoyage du cache expire
 *   2. Health check + alerte si degradation
 *   3. Nettoyage des fichiers temporaires recents
 *   4. Verification espace disque
 *
 * Configurer dans crontab :
 *   0 * * * * php /chemin/vers/fronote/cron/hourly_maintenance.php >> /chemin/vers/fronote/logs/cron_hourly.log 2>&1
 */
declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('This script must be run from the command line.');
}

require_once dirname(__DIR__) . '/API/bootstrap.php';

$startTime = microtime(true);
$log = function(string $msg) {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
};

$log('--- Fronote Hourly Maintenance ---');

// 1. Cache GC
try {
    $cache = app('cache');
    $gcCount = $cache->gc();
    $log("Cache GC: {$gcCount} expired entries removed");
} catch (\Throwable $e) {
    $log("Cache GC error: " . $e->getMessage());
}

// 2. Health check refresh
try {
    $health = app('health');
    $result = $health->runAll();
    $healthy = $result['healthy'] ?? false;
    $log("Health check: " . ($healthy ? 'ALL OK' : 'DEGRADED'));

    if (!$healthy) {
        // Log details of failed checks
        foreach ($result as $name => $check) {
            if ($name === 'healthy') continue;
            if (is_array($check) && empty($check['ok'])) {
                $error = $check['error'] ?? 'Unknown';
                $log("  FAIL: {$name} - {$error}");
            }
        }
    }
} catch (\Throwable $e) {
    $log("Health check error: " . $e->getMessage());
}

// 3. Disk space check
try {
    $freeGb = round(disk_free_space(BASE_PATH) / 1024 / 1024 / 1024, 2);
    $totalGb = round(disk_total_space(BASE_PATH) / 1024 / 1024 / 1024, 2);
    $usedPct = $totalGb > 0 ? round(($totalGb - $freeGb) / $totalGb * 100, 1) : 0;
    $log("Disk: {$freeGb} GB free / {$totalGb} GB total ({$usedPct}% used)");

    if ($usedPct > 90) {
        $log("WARNING: Disk usage above 90%!");
    }
} catch (\Throwable $e) {
    $log("Disk check error: " . $e->getMessage());
}

// 4. Rate limit cleanup (hourly for responsiveness)
try {
    $limiter = new \API\Security\RateLimiter();
    $cleaned = $limiter->cleanup();
    if ($cleaned > 0) {
        $log("Rate limits: cleaned {$cleaned} expired entries");
    }
} catch (\Throwable $e) {
    // Silent — not critical
}

$duration = round(microtime(true) - $startTime, 3);
$log("--- Hourly maintenance completed in {$duration}s ---");
