<?php
/**
 * API — Conseils de Classe Numériques
 * GET  ?action=sessions&classe=X | ?action=preparation&session_id=X
 * POST action=vote | action=appreciation | action=synthese
 */
require_once __DIR__ . '/../core.php';
header('Content-Type: application/json; charset=utf-8');
requireAuth();

$pdo = getPDO();
$action = $_REQUEST['action'] ?? '';

try {
    switch ($action) {
        case 'sessions':
            $classe = $_GET['classe'] ?? '';
            $stmt = $pdo->prepare("SELECT * FROM conseil_classe_sessions WHERE classe_id = :c ORDER BY date_conseil DESC");
            $stmt->execute([':c' => $classe]);
            echo json_encode(['success' => true, 'sessions' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'preparation':
            $sessionId = (int)($_GET['session_id'] ?? 0);
            $session = $pdo->prepare("SELECT * FROM conseil_classe_sessions WHERE id = :id");
            $session->execute([':id' => $sessionId]);
            $session = $session->fetch(PDO::FETCH_ASSOC);

            $eleves = $pdo->prepare("SELECT e.id, e.nom, e.prenom,
                (SELECT AVG(n.note) FROM notes n WHERE n.id_eleve = e.id) AS moyenne,
                (SELECT COUNT(*) FROM absences a WHERE a.id_eleve = e.id AND a.justifiee = 0) AS absences_nj,
                (SELECT COUNT(*) FROM incidents i WHERE i.eleve_id = e.id) AS incidents
                FROM eleves e WHERE e.classe = :c ORDER BY e.nom, e.prenom");
            $eleves->execute([':c' => $session['classe_id']]);

            $discussions = $pdo->prepare("SELECT * FROM conseil_classe_eleve_discussions WHERE session_id = :sid ORDER BY ordre");
            $discussions->execute([':sid' => $sessionId]);

            echo json_encode(['success' => true, 'session' => $session, 'eleves' => $eleves->fetchAll(PDO::FETCH_ASSOC), 'discussions' => $discussions->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'vote':
            $data = json_decode(file_get_contents('php://input'), true);
            $pdo->prepare("INSERT INTO conseil_classe_votes (discussion_id, voter_id, voter_type, vote) VALUES (:did, :vid, :vt, :vote)
                ON DUPLICATE KEY UPDATE vote = VALUES(vote)")
                ->execute([':did' => $data['discussion_id'], ':vid' => $_SESSION['user_id'], ':vt' => $_SESSION['user_type'], ':vote' => $data['vote']]);
            // Update vote counts
            $did = (int)$data['discussion_id'];
            foreach (['pour','contre','abstention'] as $v) {
                $cnt = $pdo->prepare("SELECT COUNT(*) FROM conseil_classe_votes WHERE discussion_id = :did AND vote = :v");
                $cnt->execute([':did' => $did, ':v' => $v]);
                $pdo->prepare("UPDATE conseil_classe_eleve_discussions SET avis_vote_{$v} = :cnt WHERE id = :did")
                    ->execute([':cnt' => $cnt->fetchColumn(), ':did' => $did]);
            }
            echo json_encode(['success' => true]);
            break;

        case 'appreciation':
            $data = json_decode(file_get_contents('php://input'), true);
            $pdo->prepare("UPDATE conseil_classe_eleve_discussions SET appreciation = :app, avis_propose = :avis WHERE id = :id")
                ->execute([':app' => $data['appreciation'], ':avis' => $data['avis'] ?? 'aucun', ':id' => $data['discussion_id']]);
            echo json_encode(['success' => true]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Action inconnue']);
    }
} catch (\Throwable $e) {
    error_log('API conseil_classe: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur']);
}
