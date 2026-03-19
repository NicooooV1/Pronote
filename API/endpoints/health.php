<?php
/**
 * Health Check Endpoint — /api/health
 * Retourne l'état du système : DB, session, WebSocket, versions.
 * Accessible uniquement en local ou par un admin authentifié.
 */
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Vérification accès : admin authentifié OU requête locale
$isLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
$isAdmin = false;
try {
    $isAdmin = app('auth')->check() && (app('auth')->user()['type'] ?? '') === 'administrateur';
} catch (\Throwable $e) {}

if (!$isLocal && !$isAdmin) {
    http_response_code(403);
    echo json_encode(['error' => 'Accès refusé']);
    exit;
}

$checks = [];
$status = 'ok';

// 1) PHP version
$checks['php'] = [
    'status' => version_compare(PHP_VERSION, '8.0.0', '>=') ? 'ok' : 'warning',
    'version' => PHP_VERSION,
];

// 2) Database
try {
    $pdo = app('db')->getConnection();
    $start = microtime(true);
    $pdo->query('SELECT 1');
    $latencyMs = round((microtime(true) - $start) * 1000, 2);
    $checks['database'] = [
        'status' => 'ok',
        'latency_ms' => $latencyMs,
    ];
} catch (\Throwable $e) {
    $checks['database'] = ['status' => 'error', 'message' => 'Connexion échouée'];
    $status = 'error';
}

// 3) Session
$checks['session'] = [
    'status' => session_status() === PHP_SESSION_ACTIVE ? 'ok' : 'warning',
    'handler' => ini_get('session.save_handler'),
];

// 4) WebSocket
$wsUrl = env('WEBSOCKET_CLIENT_URL', '');
if ($wsUrl) {
    try {
        $healthUrl = rtrim($wsUrl, '/') . '/health';
        $ctx = stream_context_create(['http' => ['timeout' => 3, 'method' => 'GET']]);
        $response = @file_get_contents($healthUrl, false, $ctx);
        if ($response !== false) {
            $wsData = json_decode($response, true);
            $checks['websocket'] = [
                'status' => 'ok',
                'connections' => $wsData['connections'] ?? 0,
                'uptime' => round($wsData['uptime'] ?? 0),
            ];
        } else {
            $checks['websocket'] = ['status' => 'warning', 'message' => 'Injoignable'];
        }
    } catch (\Throwable $e) {
        $checks['websocket'] = ['status' => 'warning', 'message' => 'Injoignable'];
    }
} else {
    $checks['websocket'] = ['status' => 'disabled'];
}

// 5) Disk space
$freeBytes = @disk_free_space(BASE_PATH);
$totalBytes = @disk_total_space(BASE_PATH);
if ($freeBytes !== false && $totalBytes !== false) {
    $pctFree = round(($freeBytes / $totalBytes) * 100, 1);
    $checks['disk'] = [
        'status' => $pctFree < 5 ? 'error' : ($pctFree < 15 ? 'warning' : 'ok'),
        'free_pct' => $pctFree,
        'free_gb' => round($freeBytes / 1073741824, 2),
    ];
    if ($pctFree < 5) $status = 'error';
}

// 6) Tables count
try {
    $tableCount = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();
    $checks['schema'] = ['status' => 'ok', 'tables' => $tableCount];
} catch (\Throwable $e) {}

// 7) Active sessions
try {
    $activeSessions = (int) $pdo->query("SELECT COUNT(*) FROM session_security WHERE is_active = 1")->fetchColumn();
    $checks['sessions'] = ['status' => 'ok', 'active' => $activeSessions];
} catch (\Throwable $e) {}

// 8) App version
$versionFile = BASE_PATH . '/version.json';
$version = 'unknown';
if (file_exists($versionFile)) {
    $vData = json_decode(file_get_contents($versionFile), true);
    $version = $vData['version'] ?? 'unknown';
}

echo json_encode([
    'status' => $status,
    'version' => $version,
    'timestamp' => date('c'),
    'checks' => $checks,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
