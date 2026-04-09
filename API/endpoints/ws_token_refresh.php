<?php
/**
 * WebSocket Token Refresh Endpoint
 * GET /API/endpoints/ws_token_refresh.php
 *
 * Returns a fresh JWT for the authenticated user.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

try {
    $token = \API\Core\WebSocket::generateToken(
        (int) $_SESSION['user_id'],
        $_SESSION['user_type'] ?? $_SESSION['role'] ?? ''
    );

    if (!$token) {
        http_response_code(500);
        echo json_encode(['error' => 'Token generation failed']);
        exit;
    }

    echo json_encode(['token' => $token]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal error']);
}
