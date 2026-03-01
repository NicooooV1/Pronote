<?php
/**
 * API centralisée — Agenda : récupérer les personnes par type (AJAX)
 *
 * GET ?visibility=eleves|professeurs|parents|vie_scolaire|administration|classes:NomClasse
 * → JSON { success: true, persons: [{ id, name, info, type }, …] }
 *
 * Déplacé depuis agenda/api/get_persons.php pour centralisation.
 */
require_once __DIR__ . '/../core.php';
requireAuth();

header('Content-Type: application/json; charset=utf-8');

// Seuls admin / prof / vie_scolaire peuvent lister
if (!isAdmin() && !isTeacher() && !isVieScolaire()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit;
}

$visibility = filter_input(INPUT_GET, 'visibility', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
$pdo = getPDO();
$persons = [];

try {
    switch ($visibility) {
        case 'eleves':
            $stmt = $pdo->prepare("SELECT id, nom, prenom, classe FROM eleves ORDER BY nom, prenom");
            $stmt->execute();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $persons[] = [
                    'id'   => $row['id'],
                    'name' => htmlspecialchars($row['prenom'] . ' ' . $row['nom']),
                    'info' => htmlspecialchars($row['classe']),
                    'type' => 'eleve',
                ];
            }
            break;

        case 'professeurs':
            $stmt = $pdo->prepare("SELECT id, nom, prenom, matiere FROM professeurs ORDER BY nom, prenom");
            $stmt->execute();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $persons[] = [
                    'id'   => $row['id'],
                    'name' => htmlspecialchars($row['prenom'] . ' ' . $row['nom']),
                    'info' => htmlspecialchars($row['matiere'] ?? 'Professeur'),
                    'type' => 'professeur',
                ];
            }
            break;

        case 'parents':
            $stmt = $pdo->prepare(
                "SELECT p.id, p.nom, p.prenom,
                        GROUP_CONCAT(DISTINCT e.prenom SEPARATOR ', ') AS enfants
                 FROM parents p
                 LEFT JOIN parents_eleves pe ON p.id = pe.id_parent
                 LEFT JOIN eleves e ON pe.id_eleve = e.id
                 GROUP BY p.id
                 ORDER BY p.nom, p.prenom"
            );
            $stmt->execute();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $persons[] = [
                    'id'   => $row['id'],
                    'name' => htmlspecialchars($row['prenom'] . ' ' . $row['nom']),
                    'info' => 'Parent de : ' . htmlspecialchars($row['enfants'] ?: 'Non défini'),
                    'type' => 'parent',
                ];
            }
            break;

        case 'vie_scolaire':
            $stmt = $pdo->prepare(
                "SELECT id, nom, prenom, fonction FROM personnels
                 WHERE fonction LIKE '%scolaire%' OR service = 'vie scolaire'
                 ORDER BY nom, prenom"
            );
            $stmt->execute();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $persons[] = [
                    'id'   => $row['id'],
                    'name' => htmlspecialchars($row['prenom'] . ' ' . $row['nom']),
                    'info' => htmlspecialchars($row['fonction'] ?? 'Vie scolaire'),
                    'type' => 'personnel',
                ];
            }
            break;

        case 'administration':
            $stmt = $pdo->prepare(
                "SELECT id, nom, prenom, fonction FROM personnels
                 WHERE fonction LIKE '%admin%' OR service = 'administration'
                 ORDER BY nom, prenom"
            );
            $stmt->execute();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $persons[] = [
                    'id'   => $row['id'],
                    'name' => htmlspecialchars($row['prenom'] . ' ' . $row['nom']),
                    'info' => htmlspecialchars($row['fonction'] ?? 'Administration'),
                    'type' => 'personnel',
                ];
            }
            break;

        default:
            // Classes spécifiques (format "classes:NomClasse")
            if (strpos($visibility, 'classes:') === 0) {
                $classe = substr($visibility, 8);
                $stmt = $pdo->prepare("SELECT id, nom, prenom FROM eleves WHERE classe = ? ORDER BY nom, prenom");
                $stmt->execute([$classe]);
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $persons[] = [
                        'id'   => $row['id'],
                        'name' => htmlspecialchars($row['prenom'] . ' ' . $row['nom']),
                        'info' => htmlspecialchars($classe),
                        'type' => 'eleve',
                    ];
                }
            }
            break;
    }

    echo json_encode(['success' => true, 'persons' => $persons]);

} catch (Exception $e) {
    error_log("API agenda/persons: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la récupération des données']);
}
