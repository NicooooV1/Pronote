<?php

declare(strict_types=1);

namespace API\Services\Scolaire;

use PDO;

class DevoirService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Retrieve paginated and filtered devoirs.
     *
     * @param array $filters Supported keys: classe, matiere (nom_matiere), professeur (nom_professeur), date_from, date_to
     * @param int   $page    Page number (1-based)
     * @param int   $perPage Items per page
     * @return array{data: array, total: int, pages: int}
     */
    public function getFiltered(array $filters, int $page = 1, int $perPage = 30): array
    {
        $where  = [];
        $params = [];

        if (!empty($filters['classe'])) {
            $where[]          = 'd.classe = :classe';
            $params[':classe'] = $filters['classe'];
        }

        if (!empty($filters['matiere'])) {
            $where[]           = 'd.nom_matiere = :matiere';
            $params[':matiere'] = $filters['matiere'];
        }

        if (!empty($filters['professeur'])) {
            $where[]              = 'd.nom_professeur = :professeur';
            $params[':professeur'] = $filters['professeur'];
        }

        if (!empty($filters['date_from'])) {
            $where[]              = 'd.date_rendu >= :date_from';
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[]            = 'd.date_rendu <= :date_to';
            $params[':date_to'] = $filters['date_to'];
        }

        $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

        // Count total rows
        $countSql = "SELECT COUNT(*) FROM devoirs d {$whereClause}";

        $stmt = $this->pdo->prepare($countSql);
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        $pages  = $total > 0 ? (int) ceil($total / $perPage) : 1;
        $offset = ($page - 1) * $perPage;

        // Fetch data
        $dataSql = "SELECT d.* FROM devoirs d
            {$whereClause}
            ORDER BY d.date_rendu DESC
            LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($dataSql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'data'  => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
            'pages' => $pages,
        ];
    }

    /**
     * Retrieve a single devoir by ID.
     *
     * @param int $id
     * @return array|null
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM devoirs WHERE id = ?');
        $stmt->execute([$id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /**
     * Create a new devoir.
     *
     * @param array $data Fields: titre, description, classe, nom_matiere, nom_professeur, date_ajout, date_rendu
     * @return int The ID of the newly created devoir
     */
    public function create(array $data): int
    {
        $sql = <<<'SQL'
            INSERT INTO devoirs (titre, description, classe, nom_matiere, nom_professeur, date_ajout, date_rendu)
            VALUES (:titre, :description, :classe, :nom_matiere, :nom_professeur, :date_ajout, :date_rendu)
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':titre'          => $data['titre'],
            ':description'    => $data['description'] ?? null,
            ':classe'         => $data['classe'],
            ':nom_matiere'    => $data['nom_matiere'],
            ':nom_professeur' => $data['nom_professeur'],
            ':date_ajout'     => $data['date_ajout'] ?? date('Y-m-d'),
            ':date_rendu'     => $data['date_rendu'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Update an existing devoir.
     *
     * @param int   $id
     * @param array $data Fields to update
     * @return bool True if a row was updated
     */
    public function update(int $id, array $data): bool
    {
        $allowed = [
            'titre', 'description', 'classe', 'nom_matiere',
            'nom_professeur', 'date_ajout', 'date_rendu',
        ];

        $sets   = [];
        $params = [':id' => $id];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $sets[]            = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }

        if (count($sets) === 0) {
            return false;
        }

        $setClause = implode(', ', $sets);
        $sql       = "UPDATE devoirs SET {$setClause} WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    /**
     * Delete a devoir by ID.
     *
     * @param int $id
     * @return bool True if a row was deleted
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM devoirs WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Count devoirs with a due date today or in the future.
     *
     * @return int
     */
    public function getUpcomingCount(): int
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM devoirs WHERE date_rendu >= CURDATE()');

        return (int) $stmt->fetchColumn();
    }

    /**
     * Count devoirs with a due date in the past.
     *
     * @return int
     */
    public function getOverdueCount(): int
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM devoirs WHERE date_rendu < CURDATE()');

        return (int) $stmt->fetchColumn();
    }
}
