<?php
/**
 * API — Analyse Prédictive & IA
 * GET  ?action=dashboard | ?action=alertes | ?action=score&eleve_id=X
 * POST action=action_prise
 */
require_once __DIR__ . '/../core.php';
header('Content-Type: application/json; charset=utf-8');
requireAuth();

$pdo = getPDO();
$action = $_REQUEST['action'] ?? '';

try {
    switch ($action) {
        case 'dashboard':
            $stmt = $pdo->query("SELECT niveau_alerte, COUNT(*) AS nb FROM intelligence_scores
                WHERE annee_scolaire = (SELECT code FROM annees_scolaires WHERE actif = 1 LIMIT 1)
                GROUP BY niveau_alerte ORDER BY FIELD(niveau_alerte, 'rouge','orange','jaune','vert')");
            $distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $topRisque = $pdo->prepare("SELECT s.eleve_id, CONCAT(e.prenom,' ',e.nom) AS eleve, e.classe, s.score_risque, s.niveau_alerte
                FROM intelligence_scores s JOIN eleves e ON s.eleve_id = e.id
                WHERE s.niveau_alerte IN ('rouge','orange') ORDER BY s.score_risque DESC LIMIT 20");
            $topRisque->execute();
            echo json_encode(['success' => true, 'distribution' => $distribution, 'eleves_a_risque' => $topRisque->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'alertes':
            $userId = (int)($_SESSION['user_id'] ?? 0);
            $userType = $_SESSION['user_type'] ?? '';
            $stmt = $pdo->prepare("SELECT a.*, CONCAT(e.prenom,' ',e.nom) AS eleve, e.classe
                FROM intelligence_alertes a JOIN eleves e ON a.eleve_id = e.id
                WHERE a.destinataire_id = :uid AND a.destinataire_type = :utype AND a.lu = 0
                ORDER BY a.date_alerte DESC LIMIT 50");
            $stmt->execute([':uid' => $userId, ':utype' => $userType]);
            echo json_encode(['success' => true, 'alertes' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'score':
            $eleveId = (int)($_GET['eleve_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT * FROM intelligence_scores WHERE eleve_id = :eid ORDER BY date_calcul DESC LIMIT 1");
            $stmt->execute([':eid' => $eleveId]);
            echo json_encode(['success' => true, 'score' => $stmt->fetch(PDO::FETCH_ASSOC)]);
            break;

        case 'action_prise':
            $data = json_decode(file_get_contents('php://input'), true);
            $pdo->prepare("UPDATE intelligence_alertes SET lu = 1, action_prise = :action WHERE id = :id")
                ->execute([':action' => $data['action'] ?? '', ':id' => $data['alerte_id']]);
            echo json_encode(['success' => true]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Action inconnue']);
    }
} catch (\Throwable $e) {
    error_log('API intelligence: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur']);
}
