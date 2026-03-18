<?php
/**
 * AJAX endpoint for drag-and-drop EDT operations.
 * POST { cours_id, new_jour, new_creneau_id }
 */
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../API/bootstrap.php';
$bridge = new \Pronote\Legacy\Bridge();

// Auth check
if (empty($_SESSION['user']) || !in_array($_SESSION['user']['type'] ?? '', ['administrateur', 'vie_scolaire'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

$pdo = $bridge->getPDO();
require_once __DIR__ . '/includes/EdtService.php';
$edtService = new EdtService($pdo);

$input = json_decode(file_get_contents('php://input'), true);
$coursId     = (int) ($input['cours_id'] ?? 0);
$newJour     = (int) ($input['new_jour'] ?? 0);
$newCreneauId = (int) ($input['new_creneau_id'] ?? 0);

if (!$coursId || !$newJour || !$newCreneauId) {
    echo json_encode(['success' => false, 'message' => 'Paramètres manquants']);
    exit;
}

// Charger le cours actuel
$cours = $edtService->getCours($coursId);
if (!$cours) {
    echo json_encode(['success' => false, 'message' => 'Cours introuvable']);
    exit;
}

// Récupérer les heures du nouveau créneau
$stmt = $pdo->prepare("SELECT heure_debut, heure_fin FROM creneaux WHERE id = ?");
$stmt->execute([$newCreneauId]);
$creneau = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$creneau) {
    echo json_encode(['success' => false, 'message' => 'Créneau introuvable']);
    exit;
}

try {
    $data = [
        'classe_id'     => $cours['classe_id'],
        'matiere_id'    => $cours['matiere_id'],
        'professeur_id' => $cours['professeur_id'],
        'salle_id'      => $cours['salle_id'],
        'jour'          => $newJour,
        'creneau_id'    => $newCreneauId,
        'heure_debut'   => $creneau['heure_debut'],
        'heure_fin'     => $creneau['heure_fin'],
        'groupe'        => $cours['groupe'] ?? null,
        'type_cours'    => $cours['type_cours'] ?? 'cours',
        'recurrence'    => $cours['recurrence'] ?? 'hebdomadaire',
        'couleur'       => $cours['couleur'] ?? null,
    ];

    $ok = $edtService->updateCours($coursId, $data);
    echo json_encode(['success' => $ok, 'message' => $ok ? 'Cours déplacé' : 'Échec']);
} catch (\RuntimeException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
