<?php
/**
 * AJAX endpoint — Statistics data for Canvas graphs.
 * GET params: type (distribution|evolution|boxplot), classe, matiere, trimestre, eleve_id
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../API/core.php';
require_once __DIR__ . '/NoteService.php';

try {
    requireAuth();

    $pdo = getPDO();
    $noteService = new NoteService($pdo);

    $type      = $_GET['type'] ?? '';
    $classe    = $_GET['classe'] ?? '';
    $matiere   = (int) ($_GET['matiere'] ?? 0);
    $trimestre = max(1, min(3, (int) ($_GET['trimestre'] ?? NoteService::getTrimestreCourant())));
    $eleveId   = (int) ($_GET['eleve_id'] ?? 0);

    switch ($type) {
        case 'distribution':
            if (!$classe || !$matiere) {
                http_response_code(400);
                echo json_encode(['error' => 'Classe et matière requises']);
                exit;
            }
            $data = $noteService->getDistribution($classe, $matiere, $trimestre);
            echo json_encode($data);
            break;

        case 'evolution':
            if (!$eleveId) {
                http_response_code(400);
                echo json_encode(['error' => 'ID élève requis']);
                exit;
            }
            $data = $noteService->getEvolutionEleve($eleveId);
            echo json_encode($data);
            break;

        case 'boxplot':
            if (!$classe) {
                http_response_code(400);
                echo json_encode(['error' => 'Classe requise']);
                exit;
            }
            $data = $noteService->getBoxPlotClasse($classe, $trimestre);
            echo json_encode($data);
            break;

        case 'class_stats':
            if (!$classe || !$matiere) {
                http_response_code(400);
                echo json_encode(['error' => 'Classe et matière requises']);
                exit;
            }
            $stats = $noteService->getStatsClasse($classe, $matiere, $trimestre);
            $moyennes = $noteService->getMoyennesParEleve($classe, $matiere, $trimestre);
            echo json_encode(['stats' => $stats, 'moyennes' => $moyennes]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Type de statistique invalide']);
    }
} catch (\Exception $e) {
    error_log("ajax_stats error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur']);
}
