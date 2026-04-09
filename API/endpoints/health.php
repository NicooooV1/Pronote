<?php
/**
 * Health Check Endpoint
 * GET /API/endpoints/health.php
 *
 * Returns JSON with all subsystem statuses.
 * No authentication required (for monitoring tools).
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

try {
    $pdo = app('db')->getConnection();
    $health = new \API\Services\HealthCheckService($pdo, BASE_PATH);
    $result = $health->runAll();

    http_response_code($result['healthy'] ? 200 : 503);
    echo json_encode($result, JSON_PRETTY_PRINT);
} catch (\Throwable $e) {
    http_response_code(503);
    echo json_encode([
        'healthy' => false,
        'error'   => 'Health check failed: ' . $e->getMessage(),
    ]);
}
