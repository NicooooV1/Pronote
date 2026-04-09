<?php
declare(strict_types=1);

namespace API\Controllers;

use API\Core\EstablishmentContext;

/**
 * REST Controller for student data.
 * GET /API/students          — List students (paginated)
 * GET /API/students/{id}     — Get a student by ID
 */
class StudentController extends BaseController
{
    public function index(): void
    {
        $this->authenticate();
        $this->authorize('admin.users');

        [$page, $perPage, $offset] = $this->pagination();
        $etabId = EstablishmentContext::id();

        // Optional filters
        $classe = $this->query('classe');
        $search = $this->query('q');

        $where = 'WHERE e.etablissement_id = ?';
        $params = [$etabId];

        if ($classe) {
            $where .= ' AND e.classe = ?';
            $params[] = $classe;
        }
        if ($search) {
            $where .= ' AND (e.nom LIKE ? OR e.prenom LIKE ? OR e.identifiant LIKE ?)';
            $searchParam = '%' . $search . '%';
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }

        // Count
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM eleves e {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // Fetch
        $params[] = $perPage;
        $params[] = $offset;
        $stmt = $this->pdo->prepare("
            SELECT e.id, e.nom, e.prenom, e.mail, e.classe, e.date_naissance, e.actif, e.date_creation
            FROM eleves e
            {$where}
            ORDER BY e.nom, e.prenom
            LIMIT ? OFFSET ?
        ");
        $stmt->execute($params);
        $students = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->paginated($students, $total, $page, $perPage);
    }

    public function show(int $id): void
    {
        $this->authenticate();
        $etabId = EstablishmentContext::id();

        $stmt = $this->pdo->prepare("
            SELECT id, nom, prenom, mail, classe, date_naissance, lieu_naissance, adresse, telephone, actif, date_creation
            FROM eleves
            WHERE id = ? AND etablissement_id = ?
        ");
        $stmt->execute([$id, $etabId]);
        $student = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$student) {
            $this->error('Student not found', 404);
        }

        $this->json($student);
    }
}
