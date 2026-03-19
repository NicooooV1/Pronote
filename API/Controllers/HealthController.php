<?php
declare(strict_types=1);

namespace API\Controllers;

/**
 * Health & Monitoring Controller
 * GET /api/v1/health — état du système
 */
class HealthController extends BaseController
{
	public function index(): void
	{
		$checks = [];
		$overall = 'ok';

		// Database
		$checks['database'] = $this->checkDatabase();
		if ($checks['database']['status'] !== 'ok') $overall = 'degraded';

		// Disk space
		$checks['disk'] = $this->checkDisk();
		if ($checks['disk']['status'] !== 'ok') $overall = 'degraded';

		// Sessions
		$checks['sessions'] = $this->checkSessions();

		// Cache
		$checks['cache'] = $this->checkCache();

		// WebSocket
		$checks['websocket'] = $this->checkWebSocket();

		$this->json([
			'status' => $overall,
			'version' => $this->getVersion(),
			'environment' => getenv('APP_ENV') ?: 'production',
			'timestamp' => date('c'),
			'php_version' => PHP_VERSION,
			'uptime' => $this->getUptime(),
			'checks' => $checks,
		]);
	}

	/**
	 * Endpoint détaillé (admin uniquement)
	 */
	public function detailed(): void
	{
		$this->authenticate();
		$role = $this->currentUser['role'] ?? $this->currentUser['profil'] ?? '';
		if ($role !== 'administrateur') {
			$this->error('Forbidden', 403);
		}

		$checks = [];
		$checks['database'] = $this->checkDatabase(detailed: true);
		$checks['disk'] = $this->checkDisk();
		$checks['sessions'] = $this->checkSessions();
		$checks['cache'] = $this->checkCache();
		$checks['websocket'] = $this->checkWebSocket();
		$checks['modules'] = $this->checkModules();
		$checks['error_rate'] = $this->checkErrorRate();

		$overall = 'ok';
		foreach ($checks as $check) {
			if (($check['status'] ?? 'ok') === 'critical') { $overall = 'critical'; break; }
			if (($check['status'] ?? 'ok') !== 'ok') $overall = 'degraded';
		}

		$this->json([
			'status' => $overall,
			'version' => $this->getVersion(),
			'environment' => getenv('APP_ENV') ?: 'production',
			'timestamp' => date('c'),
			'php_version' => PHP_VERSION,
			'uptime' => $this->getUptime(),
			'memory' => [
				'usage_mb' => round(memory_get_usage(true) / 1048576, 2),
				'peak_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
			],
			'checks' => $checks,
		]);
	}

	private function checkDatabase(bool $detailed = false): array
	{
		try {
			$start = microtime(true);
			$stmt = $this->pdo->query('SELECT 1');
			$latency = round((microtime(true) - $start) * 1000, 2);

			$result = [
				'status' => $latency > 500 ? 'warning' : 'ok',
				'latency_ms' => $latency,
			];

			if ($detailed) {
				$stmt = $this->pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()");
				$result['tables'] = (int) $stmt->fetchColumn();

				// Active connections
				$stmt = $this->pdo->query("SHOW STATUS LIKE 'Threads_connected'");
				$row = $stmt->fetch(\PDO::FETCH_ASSOC);
				$result['connections'] = (int) ($row['Value'] ?? 0);
			}

			return $result;
		} catch (\Throwable $e) {
			return ['status' => 'critical', 'error' => 'Database unreachable'];
		}
	}

	private function checkDisk(): array
	{
		$path = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
		$free = @disk_free_space($path);
		$total = @disk_total_space($path);

		if ($free === false || $total === false) {
			return ['status' => 'unknown'];
		}

		$usedPercent = round((1 - $free / $total) * 100, 1);
		$status = $usedPercent > 95 ? 'critical' : ($usedPercent > 85 ? 'warning' : 'ok');

		return [
			'status' => $status,
			'free_gb' => round($free / 1073741824, 2),
			'total_gb' => round($total / 1073741824, 2),
			'used_percent' => $usedPercent,
		];
	}

	private function checkSessions(): array
	{
		try {
			$stmt = $this->pdo->query("SELECT COUNT(*) FROM session_security WHERE last_activity > DATE_SUB(NOW(), INTERVAL 30 MINUTE)");
			$active = (int) $stmt->fetchColumn();
			return ['status' => 'ok', 'active_sessions' => $active];
		} catch (\Throwable $e) {
			return ['status' => 'ok', 'active_sessions' => 'unknown'];
		}
	}

	private function checkCache(): array
	{
		try {
			$cache = app('cache');
			$cache->put('_health_check', 'ok', 10);
			$value = $cache->get('_health_check');
			$cache->forget('_health_check');

			return [
				'status' => $value === 'ok' ? 'ok' : 'warning',
				'driver' => env('CACHE_DRIVER', 'file'),
			];
		} catch (\Throwable $e) {
			return ['status' => 'warning', 'error' => 'Cache unavailable'];
		}
	}

	private function checkWebSocket(): array
	{
		$wsEnabled = env('WEBSOCKET_ENABLED', 'true');
		if ($wsEnabled === 'false' || $wsEnabled === false) {
			return ['status' => 'disabled'];
		}

		$wsUrl = env('WEBSOCKET_URL', 'http://localhost:3000');

		// Quick HTTP check on the WS server
		$ctx = stream_context_create(['http' => ['timeout' => 2, 'method' => 'GET']]);
		$result = @file_get_contents($wsUrl . '/health', false, $ctx);

		return [
			'status' => $result !== false ? 'ok' : 'warning',
			'url' => $wsUrl,
		];
	}

	private function checkModules(): array
	{
		try {
			$sdk = app('module_sdk');
			$discovered = $sdk->discover();
			return ['status' => 'ok', 'count' => count($discovered)];
		} catch (\Throwable $e) {
			return ['status' => 'warning', 'error' => 'ModuleSDK unavailable'];
		}
	}

	private function checkErrorRate(): array
	{
		try {
			$stmt = $this->pdo->prepare("
				SELECT COUNT(*) FROM audit_log
				WHERE severity IN ('WARNING', 'CRITICAL')
				AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
			");
			$stmt->execute();
			$count = (int) $stmt->fetchColumn();

			return [
				'status' => $count > 50 ? 'warning' : 'ok',
				'errors_last_hour' => $count,
			];
		} catch (\Throwable $e) {
			return ['status' => 'ok', 'errors_last_hour' => 'unknown'];
		}
	}

	private function getVersion(): string
	{
		$versionFile = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/version.json';
		if (file_exists($versionFile)) {
			$data = json_decode(file_get_contents($versionFile), true);
			return $data['version'] ?? '2.0.0';
		}
		return '2.0.0';
	}

	private function getUptime(): ?string
	{
		$start = $_SERVER['REQUEST_TIME'] ?? null;
		if ($start) {
			return gmdate('H:i:s', time() - $start) . ' (request)';
		}
		return null;
	}
}
