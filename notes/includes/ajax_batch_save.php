<?php
/**
 * AJAX endpoint — Auto-save batch notes (every 30s from the batch entry grid).
 * POST JSON: { common: {...}, notes: [{id_eleve, note, commentaire}, ...] }
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../API/core.php';
require_once __DIR__ . '/NoteService.php';

try {
    requireAuth();

    if (!canManageNotes()) {
        http_response_code(403);
        echo json_encode(['error' => 'Accès refusé']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Méthode non autorisée']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || empty($input['common']) || empty($input['notes'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Données manquantes']);
        exit;
    }

    // CSRF validation
    $csrfToken = $input['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!validateCSRFToken($csrfToken)) {
        http_response_code(403);
        echo json_encode(['error' => 'Jeton CSRF invalide']);
        exit;
    }

    $user = getCurrentUser();
    $pdo = getPDO();
    $noteService = new NoteService($pdo);

    $common = $input['common'];
    $common['id_professeur'] = $user['id'];

    // Validate common fields
    if (empty($common['id_matiere']) || empty($common['trimestre']) || empty($common['date_note'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Paramètres de l\'évaluation incomplets']);
        exit;
    }

    $result = $noteService->autoSaveBatch($input['notes'], $common);

    echo json_encode([
        'success'  => true,
        'updated'  => $result['updated'],
        'inserted' => $result['inserted'],
        'saved_at' => date('H:i:s'),
    ]);
} catch (\Exception $e) {
    error_log("ajax_batch_save error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur']);
}
