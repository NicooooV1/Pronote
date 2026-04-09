<?php
/**
 * API — Recherche globale cross-module
 * GET ?q=terme_recherche
 */
require_once __DIR__ . '/../core.php';
header('Content-Type: application/json; charset=utf-8');
requireAuth();

$query = trim($_GET['q'] ?? '');
if (mb_strlen($query) < 2) {
    echo json_encode(['error' => 'Terme de recherche trop court (min 2 caractères)']);
    exit;
}

try {
    $service = app('global_search');
    $userType = $_SESSION['user_type'] ?? 'eleve';
    $results = $service->search($query, $userType, (int)($_GET['limit'] ?? 10));
    echo json_encode(['success' => true, 'results' => $results]);
} catch (\Throwable $e) {
    error_log('API global_search: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur']);
}
