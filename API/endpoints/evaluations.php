<?php
/**
 * API — Evaluations en ligne
 * GET  ?action=list&classe=X | ?action=session&evaluation_id=X&eleve_id=X
 * POST action=create | action=submit | action=correct
 */
require_once __DIR__ . '/../core.php';
header('Content-Type: application/json; charset=utf-8');
requireAuth();

$pdo = getPDO();
$action = $_REQUEST['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            $classe = $_GET['classe'] ?? '';
            $stmt = $pdo->prepare("SELECT id, titre, matiere_id, duree_minutes, date_ouverture, date_fermeture, mode, statut
                FROM evaluations_en_ligne WHERE classe = :c AND statut IN ('ouverte','fermee') ORDER BY date_ouverture DESC");
            $stmt->execute([':c' => $classe]);
            echo json_encode(['success' => true, 'evaluations' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'session':
            $evalId = (int)($_GET['evaluation_id'] ?? 0);
            $eleveId = (int)($_GET['eleve_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT * FROM evaluation_sessions WHERE evaluation_id = :eid AND eleve_id = :sid");
            $stmt->execute([':eid' => $evalId, ':sid' => $eleveId]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$session) {
                // Start new session
                $pdo->prepare("INSERT INTO evaluation_sessions (evaluation_id, eleve_id, date_debut, statut) VALUES (:eid, :sid, NOW(), 'en_cours')")
                    ->execute([':eid' => $evalId, ':sid' => $eleveId]);
                $session = ['id' => $pdo->lastInsertId(), 'statut' => 'en_cours'];
            }
            echo json_encode(['success' => true, 'session' => $session]);
            break;

        case 'submit':
            $data = json_decode(file_get_contents('php://input'), true);
            $sessionId = (int)($data['session_id'] ?? 0);
            $questionId = (int)($data['question_id'] ?? 0);
            $reponse = $data['reponse'] ?? null;
            $pdo->prepare("INSERT INTO evaluation_reponses (session_id, question_id, reponse_donnee) VALUES (:s, :q, :r)
                ON DUPLICATE KEY UPDATE reponse_donnee = VALUES(reponse_donnee)")
                ->execute([':s' => $sessionId, ':q' => $questionId, ':r' => json_encode($reponse)]);
            echo json_encode(['success' => true]);
            break;

        case 'create':
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $pdo->prepare("INSERT INTO evaluations_en_ligne (etablissement_id, titre, professeur_id, matiere_id, classe, questions_config, duree_minutes, date_ouverture, date_fermeture, mode)
                VALUES (:etab, :titre, :prof, :mat, :classe, :qc, :dur, :ouv, :ferm, :mode)");
            $stmt->execute([
                ':etab' => $_SESSION['etablissement_id'] ?? 1, ':titre' => $data['titre'], ':prof' => $_SESSION['user_id'],
                ':mat' => $data['matiere_id'] ?? null, ':classe' => $data['classe'], ':qc' => json_encode($data['questions'] ?? []),
                ':dur' => $data['duree_minutes'] ?? 60, ':ouv' => $data['date_ouverture'], ':ferm' => $data['date_fermeture'],
                ':mode' => $data['mode'] ?? 'examen'
            ]);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Action inconnue']);
    }
} catch (\Throwable $e) {
    error_log('API evaluations: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur']);
}
