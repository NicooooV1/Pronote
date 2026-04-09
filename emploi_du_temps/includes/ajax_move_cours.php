<?php
/**
 * AJAX endpoint — Move a cours slot via drag-and-drop.
 * POST JSON: { cours_id, new_jour, new_creneau_id, csrf_token }
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../API/core.php';
require_once __DIR__ . '/EdtService.php';

try {
    requireAuth();

    if (!isAdmin() && !isVieScolaire()) {
        http_response_code(403);
        echo json_encode(['error' => 'Accès non autorisé']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Méthode non autorisée']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    $csrfToken = $input['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!validateCSRFToken($csrfToken)) {
        http_response_code(403);
        echo json_encode(['error' => 'CSRF invalide']);
        exit;
    }

    $coursId     = (int) ($input['cours_id'] ?? 0);
    $newJour     = $input['new_jour'] ?? '';
    $newCreneauId = (int) ($input['new_creneau_id'] ?? 0);

    if (!$coursId || !$newJour || !$newCreneauId) {
        http_response_code(400);
        echo json_encode(['error' => 'Paramètres manquants']);
        exit;
    }

    $pdo = getPDO();
    $edtService = new EdtService($pdo);

    // Get current cours
    $stmt = $pdo->prepare("SELECT * FROM emploi_du_temps WHERE id = ?");
    $stmt->execute([$coursId]);
    $cours = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cours) {
        http_response_code(404);
        echo json_encode(['error' => 'Cours introuvable']);
        exit;
    }

    // Check for conflicts at new position
    $conflicts = $edtService->detecterConflits(
        $cours['classe_id'],
        $cours['professeur_id'],
        $cours['salle_id'],
        $newJour,
        $newCreneauId,
        $coursId // Exclude self
    );

    if (!empty($conflicts)) {
        echo json_encode([
            'success'   => false,
            'conflicts' => $conflicts,
            'message'   => 'Conflit détecté : ' . count($conflicts) . ' conflit(s) à cette position.',
        ]);
        exit;
    }

    // Move the cours
    $stmt = $pdo->prepare("UPDATE emploi_du_temps SET jour = ?, creneau_id = ? WHERE id = ?");
    $stmt->execute([$newJour, $newCreneauId, $coursId]);

    echo json_encode([
        'success' => true,
        'message' => 'Cours déplacé avec succès.',
    ]);
} catch (\Exception $e) {
    error_log("ajax_move_cours error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur']);
}
