<?php
/**
 * API endpoint: Save theme preference via AJAX
 * POST /parametres/api_theme.php
 * Body: theme=light|dark|auto  &  csrf_token=...
 */
require_once __DIR__ . '/../API/bootstrap.php';
requireAuth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// CSRF check
$token = $_POST['csrf_token'] ?? '';
if ($token !== ($_SESSION['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

$theme = $_POST['theme'] ?? 'light';
$allowed = ['light', 'dark', 'auto'];
if (!in_array($theme, $allowed)) {
    $theme = 'light';
}

$userId   = getUserId();
$userType = getUserRole();
$pdo      = getPDO();

try {
    // Upsert theme into user_settings
    $stmt = $pdo->prepare("
        INSERT INTO user_settings (user_id, user_type, theme, date_modification)
        VALUES (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE theme = VALUES(theme), date_modification = NOW()
    ");
    $stmt->execute([$userId, $userType, $theme]);

    // Invalider le cache client pour forcer le reload du thème
    $cc = new \API\Core\ClientCache();
    $cc->set('user_theme', $theme, 3600);

    echo json_encode(['success' => true, 'theme' => $theme]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
