<?php
/**
 * API — Enquêtes & Satisfaction
 * GET  ?action=list | ?action=questions&enquete_id=X | ?action=resultats&enquete_id=X
 * POST action=repondre
 */
require_once __DIR__ . '/../core.php';
header('Content-Type: application/json; charset=utf-8');
requireAuth();

$pdo = getPDO();
$action = $_REQUEST['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            $stmt = $pdo->query("SELECT id, titre, description, type, anonyme, date_ouverture, date_fermeture, statut
                FROM enquetes WHERE statut = 'ouverte' AND (date_fermeture IS NULL OR date_fermeture >= NOW()) ORDER BY date_ouverture DESC");
            echo json_encode(['success' => true, 'enquetes' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'questions':
            $enqueteId = (int)($_GET['enquete_id'] ?? 0);
            $pages = $pdo->prepare("SELECT * FROM enquete_pages WHERE enquete_id = :eid ORDER BY ordre");
            $pages->execute([':eid' => $enqueteId]);
            $pagesData = $pages->fetchAll(PDO::FETCH_ASSOC);
            foreach ($pagesData as &$page) {
                $q = $pdo->prepare("SELECT * FROM enquete_questions WHERE page_id = :pid ORDER BY ordre");
                $q->execute([':pid' => $page['id']]);
                $page['questions'] = $q->fetchAll(PDO::FETCH_ASSOC);
            }
            echo json_encode(['success' => true, 'pages' => $pagesData]);
            break;

        case 'repondre':
            $data = json_decode(file_get_contents('php://input'), true);
            $enqueteId = (int)($data['enquete_id'] ?? 0);
            $userId = (int)($_SESSION['user_id'] ?? 0);
            $userType = $_SESSION['user_type'] ?? '';
            $hash = hash('sha256', $enqueteId . '_' . $userId . '_' . $userType);

            // Check not already submitted
            $check = $pdo->prepare("SELECT id FROM enquete_participations WHERE enquete_id = :eid AND participant_hash = :h");
            $check->execute([':eid' => $enqueteId, ':h' => $hash]);
            if ($check->fetch()) {
                echo json_encode(['error' => 'Déjà répondu']);
                exit;
            }

            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO enquete_participations (enquete_id, participant_hash, user_id, user_type, date_soumission, completed)
                VALUES (:eid, :h, :uid, :ut, NOW(), 1)")
                ->execute([':eid' => $enqueteId, ':h' => $hash, ':uid' => $userId, ':ut' => $userType]);
            $partId = $pdo->lastInsertId();

            foreach ($data['reponses'] ?? [] as $rep) {
                $pdo->prepare("INSERT INTO enquete_reponses (participation_id, question_id, valeur_texte, valeur_numero, valeur_json)
                    VALUES (:pid, :qid, :vt, :vn, :vj)")
                    ->execute([':pid' => $partId, ':qid' => $rep['question_id'],
                        ':vt' => $rep['texte'] ?? null, ':vn' => $rep['numero'] ?? null, ':vj' => isset($rep['json']) ? json_encode($rep['json']) : null]);
            }
            $pdo->commit();
            echo json_encode(['success' => true]);
            break;

        case 'resultats':
            $enqueteId = (int)($_GET['enquete_id'] ?? 0);
            $stats = $pdo->prepare("SELECT eq.id, eq.enonce, eq.type,
                COUNT(DISTINCT er.participation_id) AS nb_reponses,
                AVG(er.valeur_numero) AS moyenne_num
                FROM enquete_questions eq
                JOIN enquete_pages ep ON eq.page_id = ep.id
                LEFT JOIN enquete_reponses er ON er.question_id = eq.id
                WHERE ep.enquete_id = :eid
                GROUP BY eq.id ORDER BY ep.ordre, eq.ordre");
            $stats->execute([':eid' => $enqueteId]);

            $participation = $pdo->prepare("SELECT COUNT(*) AS total, SUM(completed) AS completes FROM enquete_participations WHERE enquete_id = :eid");
            $participation->execute([':eid' => $enqueteId]);

            echo json_encode(['success' => true, 'questions' => $stats->fetchAll(PDO::FETCH_ASSOC), 'participation' => $participation->fetch(PDO::FETCH_ASSOC)]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Action inconnue']);
    }
} catch (\Throwable $e) {
    error_log('API enquetes: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur']);
}
