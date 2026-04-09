<?php
/**
 * Endpoint: save cookie consent level.
 * POST /API/endpoints/cookie_consent.php
 * Body: level=all|essential
 */
require_once __DIR__ . '/../bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$level = $_POST['level'] ?? 'essential';
if (!in_array($level, ['all', 'essential'], true)) {
    $level = 'essential';
}

$cc = app('client_cache');
// Store consent for 365 days
$cc->set('cookie_consent', $level, 86400 * 365);

header('Content-Type: application/json');
echo json_encode(['ok' => true, 'level' => $level]);
