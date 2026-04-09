<?php
/**
 * API — Portail Parents Avancé
 * GET  ?action=resume&eleve_id=X | ?action=documents | ?action=autorisations
 * POST action=signer | action=autorisation
 */
require_once __DIR__ . '/../core.php';
header('Content-Type: application/json; charset=utf-8');
requireAuth();

$pdo = getPDO();
$action = $_REQUEST['action'] ?? '';
$parentId = (int)($_SESSION['user_id'] ?? 0);

try {
    switch ($action) {
        case 'resume':
            $eleveId = (int)($_GET['eleve_id'] ?? 0);
            // Consolidated child data
            $eleve = $pdo->prepare("SELECT id, nom, prenom, classe FROM eleves WHERE id = :id")->execute([':id' => $eleveId]);
            $eleve = $pdo->prepare("SELECT id, nom, prenom, classe FROM eleves WHERE id = :id");
            $eleve->execute([':id' => $eleveId]);
            $eleve = $eleve->fetch(PDO::FETCH_ASSOC);

            $notes = $pdo->prepare("SELECT n.note, n.note_sur, n.date_evaluation, m.nom AS matiere FROM notes n LEFT JOIN matieres m ON n.id_matiere = m.id WHERE n.id_eleve = :eid ORDER BY n.date_evaluation DESC LIMIT 10");
            $notes->execute([':eid' => $eleveId]);

            $absences = $pdo->prepare("SELECT date_debut, date_fin, motif, justifiee FROM absences WHERE id_eleve = :eid ORDER BY date_debut DESC LIMIT 10");
            $absences->execute([':eid' => $eleveId]);

            echo json_encode(['success' => true, 'eleve' => $eleve, 'notes' => $notes->fetchAll(PDO::FETCH_ASSOC), 'absences' => $absences->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'documents':
            $stmt = $pdo->prepare("SELECT d.*, (SELECT COUNT(*) FROM portail_parents_signatures_doc s WHERE s.document_id = d.id AND s.parent_id = :pid) AS signe
                FROM portail_parents_documents_a_signer d WHERE d.date_limite >= CURDATE() OR d.date_limite IS NULL ORDER BY d.obligatoire DESC, d.date_limite");
            $stmt->execute([':pid' => $parentId]);
            echo json_encode(['success' => true, 'documents' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'signer':
            $data = json_decode(file_get_contents('php://input'), true);
            $pdo->prepare("INSERT INTO portail_parents_signatures_doc (document_id, parent_id, eleve_id, signe_le) VALUES (:doc, :par, :eleve, NOW())
                ON DUPLICATE KEY UPDATE signe_le = NOW()")
                ->execute([':doc' => $data['document_id'], ':par' => $parentId, ':eleve' => $data['eleve_id']]);
            echo json_encode(['success' => true]);
            break;

        case 'autorisation':
            $data = json_decode(file_get_contents('php://input'), true);
            $token = bin2hex(random_bytes(16));
            $pdo->prepare("INSERT INTO portail_parents_autorisations (etablissement_id, parent_id, eleve_id, type, motif, date_debut, date_fin, qr_token)
                VALUES (:etab, :par, :eleve, :type, :motif, :debut, :fin, :token)")
                ->execute([':etab' => $_SESSION['etablissement_id'] ?? 1, ':par' => $parentId, ':eleve' => $data['eleve_id'],
                    ':type' => $data['type'] ?? 'sortie_anticipee', ':motif' => $data['motif'] ?? '', ':debut' => $data['date_debut'], ':fin' => $data['date_fin'], ':token' => $token]);
            echo json_encode(['success' => true, 'qr_token' => $token]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Action inconnue']);
    }
} catch (\Throwable $e) {
    error_log('API portail_parents: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur']);
}
