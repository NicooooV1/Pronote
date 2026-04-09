<?php
declare(strict_types=1);

namespace API\Controllers;

use API\Core\EstablishmentContext;

/**
 * REST Controller for teacher data.
 * GET /API/teachers          — List teachers (paginated)
 * GET /API/teachers/{id}     — Get a teacher by ID
 */
class TeacherController extends BaseController
{
    public function index(): void
    {
        $this->authenticate();
        $this->authorize('admin.users');

        [$page, $perPage, $offset] = $this->pagination();
        $etabId = EstablishmentContext::id();

        $search = $this->query('q');
        $where = 'WHERE p.etablissement_id = ?';
        $params = [$etabId];

        if ($search) {
            $where .= ' AND (p.nom LIKE ? OR p.prenom LIKE ? OR p.matiere LIKE ?)';
            $s = '%' . $search . '%';
            $params[] = $s;
            $params[] = $s;
            $params[] = $s;
        }

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM professeurs p {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $params[] = $perPage;
        $params[] = $offset;
        $stmt = $this->pdo->prepare("
            SELECT p.id, p.nom, p.prenom, p.mail, p.matiere, p.actif, p.date_creation
            FROM professeurs p
            {$where}
            ORDER BY p.nom, p.prenom
            LIMIT ? OFFSET ?
        ");
        $stmt->execute($params);

        $this->paginated($stmt->fetchAll(\PDO::FETCH_ASSOC), $total, $page, $perPage);
    }

    public function show(int $id): void
    {
        $this->authenticate();
        $etabId = EstablishmentContext::id();

        $stmt = $this->pdo->prepare("
            SELECT id, nom, prenom, mail, matiere, telephone, actif, date_creation
            FROM professeurs
            WHERE id = ? AND etablissement_id = ?
        ");
        $stmt->execute([$id, $etabId]);
        $teacher = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$teacher) {
            $this->error('Teacher not found', 404);
        }

        $this->json($teacher);
    }
}
