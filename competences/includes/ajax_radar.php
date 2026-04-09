<?php
/**
 * AJAX endpoint — Radar chart data for competences.
 * GET: type (eleve|classe), eleve_id|classe_id, periode_id
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../API/core.php';
require_once __DIR__ . '/CompetenceService.php';

try {
    requireAuth();

    $pdo = getPDO();
    $compService = new CompetenceService($pdo);

    $type      = $_GET['type'] ?? 'eleve';
    $eleveId   = (int) ($_GET['eleve_id'] ?? 0);
    $classeId  = (int) ($_GET['classe_id'] ?? 0);
    $periodeId = (int) ($_GET['periode_id'] ?? 0);

    if ($type === 'eleve' && $eleveId > 0) {
        echo json_encode($compService->getRadarData($eleveId, $periodeId ?: null));
    } elseif ($type === 'classe' && $classeId > 0) {
        echo json_encode($compService->getRadarClasseData($classeId, $periodeId ?: null));
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Paramètres manquants']);
    }
} catch (\Exception $e) {
    error_log("ajax_radar error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur']);
}
