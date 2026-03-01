<?php
/**
 * API centralisée — Notes : élèves par classe (AJAX)
 *
 * GET ?classe=6A → JSON [{ id, nom, prenom }, …]
 *
 * Déplacé depuis notes/api/eleves.php pour centralisation.
 */
require_once __DIR__ . '/../core.php';

header('Content-Type: application/json; charset=utf-8');

// Auth
requireAuth();
if (!canManageNotes()) {
    http_response_code(403);
    echo json_encode(['error' => 'Accès non autorisé']);
    exit;
}

$classe = trim($_GET['classe'] ?? '');
if ($classe === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Paramètre "classe" requis']);
    exit;
}

try {
    $pdo  = getPDO();
    $stmt = $pdo->prepare('SELECT id, nom, prenom FROM eleves WHERE classe = ? AND actif = 1 ORDER BY nom, prenom');
    $stmt->execute([$classe]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (PDOException $e) {
    error_log('API notes/eleves: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur']);
}
