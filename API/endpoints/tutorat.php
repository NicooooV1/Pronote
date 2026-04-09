<?php
/**
 * API — Tutorat & Entraide
 * GET  ?action=pairs | ?action=leaderboard | ?action=demandes
 * POST action=request | action=match | action=session
 */
require_once __DIR__ . '/../core.php';
header('Content-Type: application/json; charset=utf-8');
requireAuth();

$pdo = getPDO();
$action = $_REQUEST['action'] ?? '';

try {
    switch ($action) {
        case 'pairs':
            $stmt = $pdo->prepare("SELECT tp.*, CONCAT(t1.prenom,' ',t1.nom) AS tuteur, CONCAT(t2.prenom,' ',t2.nom) AS tutore
                FROM tutorat_pairs tp
                JOIN eleves t1 ON tp.tuteur_eleve_id = t1.id
                JOIN eleves t2 ON tp.tutore_eleve_id = t2.id
                WHERE tp.statut = 'actif' ORDER BY tp.created_at DESC");
            $stmt->execute();
            echo json_encode(['success' => true, 'pairs' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'leaderboard':
            $stmt = $pdo->query("SELECT e.id, CONCAT(e.prenom,' ',e.nom) AS eleve, e.classe,
                COUNT(DISTINCT teb.badge_id) AS badges, COALESCE(SUM(tb.xp_reward),0) AS xp_total
                FROM eleves e
                JOIN tutorat_eleve_badges teb ON teb.eleve_id = e.id
                JOIN tutorat_badges tb ON tb.id = teb.badge_id
                GROUP BY e.id ORDER BY xp_total DESC LIMIT 20");
            echo json_encode(['success' => true, 'leaderboard' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'request':
            $data = json_decode(file_get_contents('php://input'), true);
            $pdo->prepare("INSERT INTO tutorat_demandes (etablissement_id, eleve_id, matiere_id, description, urgence)
                VALUES (:etab, :eleve, :mat, :desc, :urg)")
                ->execute([':etab' => $_SESSION['etablissement_id'] ?? 1, ':eleve' => $data['eleve_id'],
                    ':mat' => $data['matiere_id'] ?? null, ':desc' => $data['description'] ?? '', ':urg' => $data['urgence'] ?? 'moyenne']);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
            break;

        case 'demandes':
            $stmt = $pdo->prepare("SELECT td.*, CONCAT(e.prenom,' ',e.nom) AS eleve FROM tutorat_demandes td
                JOIN eleves e ON td.eleve_id = e.id WHERE td.statut = 'ouverte' ORDER BY td.urgence DESC, td.created_at");
            $stmt->execute();
            echo json_encode(['success' => true, 'demandes' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Action inconnue']);
    }
} catch (\Throwable $e) {
    error_log('API tutorat: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur']);
}
