<?php
declare(strict_types=1);

namespace API\Services;

use PDO;

/**
 * Health check pour tous les sous-systemes.
 */
class HealthCheckService
{
    private PDO $pdo;
    private string $basePath;
    private int $timeout;

    public function __construct(PDO $pdo, string $basePath, int $timeoutMs = 3000)
    {
        $this->pdo = $pdo;
        $this->basePath = $basePath;
        $this->timeout = $timeoutMs;
    }

    public function runAll(): array
    {
        $checks = [
            'database'  => $this->checkDatabase(),
            'disk'      => $this->checkDisk(),
            'cache'     => $this->checkCache(),
            'smtp'      => $this->checkSmtp(),
            'websocket' => $this->checkWebSocket(),
            'php'       => $this->checkPhp(),
            'app'       => $this->checkApp(),
        ];

        $healthy = true;
        foreach ($checks as $check) {
            if ($check['status'] !== 'ok') {
                $healthy = false;
                break;
            }
        }

        return [
            'healthy'    => $healthy,
            'checks'     => $checks,
            'checked_at' => date('c'),
        ];
    }

    public function checkDatabase(): array
    {
        $start = microtime(true);
        try {
            $this->pdo->query('SELECT 1');
            $latency = round((microtime(true) - $start) * 1000, 2);
            return ['status' => 'ok', 'latency_ms' => $latency];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function checkDisk(): array
    {
        $storagePath = $this->basePath . '/storage';
        $free = @disk_free_space($storagePath);
        $total = @disk_total_space($storagePath);

        if ($free === false || $total === false) {
            return ['status' => 'error', 'message' => 'Cannot read disk info'];
        }

        $freeGb = round($free / 1024 / 1024 / 1024, 2);
        $totalGb = round($total / 1024 / 1024 / 1024, 2);
        $usedPercent = round((1 - $free / $total) * 100, 1);

        $status = $usedPercent > 95 ? 'error' : ($usedPercent > 85 ? 'warning' : 'ok');

        return [
            'status'       => $status,
            'free_gb'      => $freeGb,
            'total_gb'     => $totalGb,
            'used_percent' => $usedPercent,
        ];
    }

    public function checkCache(): array
    {
        try {
            $cache = app('cache');
            $testKey = '_health_check_' . time();
            $cache->set($testKey, 'ok', 10);
            $val = $cache->get($testKey);
            $cache->forget($testKey);
            return ['status' => $val === 'ok' ? 'ok' : 'error', 'driver' => get_class($cache)];
        } catch (\Throwable $e) {
            return ['status' => 'warning', 'message' => 'Cache unavailable: ' . $e->getMessage()];
        }
    }

    public function checkSmtp(): array
    {
        $host = getenv('MAIL_HOST');
        $port = (int)(getenv('MAIL_PORT') ?: 587);

        if (!$host) {
            return ['status' => 'warning', 'message' => 'SMTP not configured'];
        }

        $start = microtime(true);
        $errno = 0;
        $errstr = '';
        $fp = @fsockopen($host, $port, $errno, $errstr, $this->timeout / 1000);
        $latency = round((microtime(true) - $start) * 1000, 2);

        if ($fp) {
            fclose($fp);
            return ['status' => 'ok', 'latency_ms' => $latency, 'host' => $host];
        }

        return ['status' => 'error', 'message' => "SMTP unreachable: {$errstr}", 'host' => $host];
    }

    public function checkWebSocket(): array
    {
        $wsEnabled = getenv('WEBSOCKET_ENABLED');
        if (!$wsEnabled || $wsEnabled === 'false') {
            return ['status' => 'warning', 'message' => 'WebSocket disabled'];
        }

        $wsUrl = getenv('WEBSOCKET_CLIENT_URL') ?: 'http://localhost:3000';
        $healthUrl = rtrim($wsUrl, '/') . '/health';

        $start = microtime(true);
        $ctx = stream_context_create(['http' => [
            'timeout' => $this->timeout / 1000,
            'method'  => 'GET',
        ]]);

        $response = @file_get_contents($healthUrl, false, $ctx);
        $latency = round((microtime(true) - $start) * 1000, 2);

        if ($response !== false) {
            return ['status' => 'ok', 'latency_ms' => $latency];
        }

        return ['status' => 'error', 'message' => 'WebSocket server unreachable'];
    }

    public function checkPhp(): array
    {
        $extensions = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'openssl'];
        $missing = [];
        foreach ($extensions as $ext) {
            if (!extension_loaded($ext)) {
                $missing[] = $ext;
            }
        }

        return [
            'status'            => empty($missing) ? 'ok' : 'warning',
            'version'           => PHP_VERSION,
            'memory_limit'      => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'missing_extensions' => $missing,
        ];
    }

    public function checkApp(): array
    {
        $versionFile = $this->basePath . '/version.json';
        $version = 'unknown';

        if (file_exists($versionFile)) {
            $data = json_decode(file_get_contents($versionFile), true);
            $version = $data['version'] ?? 'unknown';
        }

        return [
            'status'      => 'ok',
            'version'     => $version,
            'environment' => getenv('APP_ENV') ?: 'production',
            'uptime'      => $this->getUptime(),
        ];
    }

    private function getUptime(): string
    {
        $lockFile = $this->basePath . '/install.lock';
        if (!file_exists($lockFile)) {
            return 'unknown';
        }
        $installed = filemtime($lockFile);
        $diff = time() - $installed;
        $days = floor($diff / 86400);
        return $days . 'd';
    }
}
