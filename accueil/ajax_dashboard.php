<?php
/**
 * AJAX endpoint for dashboard widget management (M104).
 * Handles: save_layout, toggle_widget, get_widgets, get_widget_data
 */
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/API/core.php';
require_once __DIR__ . '/includes/DashboardService.php';

requireAuth();

$user     = getCurrentUser();
$userId   = (int) ($user['id'] ?? 0);
$userType = getUserRole();

if (!$userId) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Non autorise']);
    exit;
}

$pdo       = getPDO();
$dashboard = new DashboardService($pdo);

// Parse input
$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? ($_GET['action'] ?? '');

// CSRF validation for write operations
if (in_array($action, ['save_layout', 'toggle_widget'])) {
    $csrfToken = $input['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (!$csrfToken || !hash_equals($sessionToken, $csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Token CSRF invalide']);
        exit;
    }
}

switch ($action) {
    case 'get_widgets':
        $widgets = $dashboard->getUserWidgets($userId, $userType);
        echo json_encode(['success' => true, 'widgets' => $widgets]);
        break;

    case 'get_available':
        $available = $dashboard->getAvailableWidgets($userType);
        $userWidgets = $dashboard->getUserWidgets($userId, $userType);
        $activeKeys = array_column($userWidgets, 'widget_key');
        $visibleMap = [];
        foreach ($userWidgets as $uw) {
            $visibleMap[$uw['widget_key']] = (int) ($uw['visible'] ?? 1);
        }
        foreach ($available as &$w) {
            $w['enabled'] = in_array($w['widget_key'], $activeKeys);
            $w['visible'] = $visibleMap[$w['widget_key']] ?? (int) $w['is_default'];
        }
        echo json_encode(['success' => true, 'widgets' => $available]);
        break;

    case 'save_layout':
        $layout = $input['layout'] ?? [];
        if (!is_array($layout) || empty($layout)) {
            echo json_encode(['success' => false, 'message' => 'Layout invalide']);
            break;
        }
        $ok = $dashboard->saveWidgetLayout($userId, $userType, $layout);
        echo json_encode(['success' => $ok, 'message' => $ok ? 'Layout sauvegarde' : 'Erreur de sauvegarde']);
        break;

    case 'toggle_widget':
        $widgetKey = $input['widget_key'] ?? '';
        $visible   = (bool) ($input['visible'] ?? true);
        if (!$widgetKey) {
            echo json_encode(['success' => false, 'message' => 'widget_key requis']);
            break;
        }
        $ok = $dashboard->toggleWidget($userId, $userType, $widgetKey, $visible);
        echo json_encode(['success' => $ok]);
        break;

    case 'get_widget_data':
        $widgetKey = $input['widget_key'] ?? ($_GET['widget_key'] ?? '');
        if (!$widgetKey) {
            echo json_encode(['success' => false, 'message' => 'widget_key requis']);
            break;
        }
        $data = $dashboard->renderWidgetData($widgetKey, $userId, $userType);
        echo json_encode(['success' => true, 'data' => $data]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Action inconnue']);
        break;
}
