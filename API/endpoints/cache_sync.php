<?php
/**
 * API endpoint: Cache synchronization
 * POST /API/endpoints/cache_sync.php
 *
 * Actions :
 *   - flush   : Invalide tout le cache client (session + cookies)
 *   - refresh : Recharge une clé spécifique depuis la DB vers le cache
 *   - get     : Retourne la valeur d'une clé du cache (pour vérification)
 *
 * Body (JSON) :
 *   { "action": "flush|refresh|get", "key": "user_theme", "csrf_token": "..." }
 */
require_once __DIR__ . '/../bootstrap.php';
requireAuth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

// CSRF check
$token = $input['csrf_token'] ?? '';
if ($token !== ($_SESSION['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

$action = $input['action'] ?? '';
$key = $input['key'] ?? '';

$cc = new \API\Core\ClientCache();

switch ($action) {
    case 'flush':
        $cc->flush();
        echo json_encode(['success' => true, 'message' => 'Client cache flushed']);
        break;

    case 'refresh':
        if (empty($key)) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing key parameter']);
            break;
        }
        // Supprime la clé du cache pour forcer un reload DB au prochain accès
        $cc->forget($key);
        echo json_encode(['success' => true, 'message' => "Key '{$key}' invalidated"]);
        break;

    case 'get':
        if (empty($key)) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing key parameter']);
            break;
        }
        $value = $cc->get($key);
        echo json_encode(['success' => true, 'key' => $key, 'value' => $value, 'cached' => $value !== null]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action. Use: flush, refresh, get']);
}
