<?php

declare(strict_types=1);

namespace API\Services\Scolaire;

use PDO;

class EvenementService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Retrieve evenements with optional filters and pagination.
     *
     * Supported filters:
     *   - type   : filters on type_evenement
     *   - status : filters on statut
     *
     * @param array $filters
     * @param int   $page    Page number (1-based)
     * @param int   $perPage Items per page
     * @return array{data: array, total: int, pages: int}
     */
    public function getFiltered(array $filters, int $page = 1, int $perPage = 30): array
    {
        $where  = [];
        $params = [];

        if (!empty($filters['type'])) {
            $where[]          = 'type_evenement = :type';
            $params[':type']  = $filters['type'];
        }

        if (!empty($filters['status'])) {
            $where[]            = 'statut = :status';
            $params[':status']  = $filters['status'];
        }

        $whereSql = '';
        if ($where) {
            $whereSql = 'WHERE ' . implode(' AND ', $where);
        }

        // Total count
        $countSql = "SELECT COUNT(*) FROM evenements {$whereSql}";
        $stmt     = $this->pdo->prepare($countSql);
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        $pages  = (int) ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;

        // Data
        $dataSql = "SELECT * FROM evenements {$whereSql} ORDER BY date_debut DESC LIMIT :limit OFFSET :offset";
        $stmt    = $this->pdo->prepare($dataSql);

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
     * Retrieve a single evenement by its ID.
     *
     * @param int $id
     * @return array|null
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM evenements WHERE id = ?');
        $stmt->execute([$id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /**
     * Create a new evenement.
     *
     * @param array $data
     * @return int The ID of the newly created evenement
     */
    public function create(array $data): int
    {
        $sql = <<<'SQL'
            INSERT INTO evenements
                (titre, description, date_debut, date_fin, type_evenement,
                 type_personnalise, statut, createur, visibilite,
                 personnes_concernees, lieu, classes, matieres,
                 date_creation, date_modification)
            VALUES
                (:titre, :description, :date_debut, :date_fin, :type_evenement,
                 :type_personnalise, :statut, :createur, :visibilite,
                 :personnes_concernees, :lieu, :classes, :matieres,
                 NOW(), NOW())
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':titre'                => $data['titre'],
            ':description'          => $data['description'] ?? null,
            ':date_debut'           => $data['date_debut'],
            ':date_fin'             => $data['date_fin'],
            ':type_evenement'       => $data['type_evenement'],
            ':type_personnalise'    => $data['type_personnalise'] ?? null,
            ':statut'               => $data['statut'] ?? 'actif',
            ':createur'             => $data['createur'] ?? null,
            ':visibilite'           => $data['visibilite'] ?? null,
            ':personnes_concernees' => $data['personnes_concernees'] ?? null,
            ':lieu'                 => $data['lieu'] ?? null,
            ':classes'              => $data['classes'] ?? null,
            ':matieres'             => $data['matieres'] ?? null,
        ]);

        $id = (int) $this->pdo->lastInsertId();
        app('hooks')?->dispatch(new \API\Events\EvenementCreated($id, $data));
        return $id;
    }

    /**
     * Update an existing evenement.
     *
     * @param int   $id
     * @param array $data Fields: titre, description, date_debut, date_fin, type_evenement, lieu
     * @return bool True if a row was updated
     */
    public function update(int $id, array $data): bool
    {
        $sql = <<<'SQL'
            UPDATE evenements
            SET titre = :titre,
                description = :description,
                date_debut = :date_debut,
                date_fin = :date_fin,
                type_evenement = :type_evenement,
                lieu = :lieu,
                date_modification = NOW()
            WHERE id = :id
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':titre'          => $data['titre'],
            ':description'    => $data['description'] ?? null,
            ':date_debut'     => $data['date_debut'],
            ':date_fin'       => $data['date_fin'],
            ':type_evenement' => $data['type_evenement'],
            ':lieu'           => $data['lieu'] ?? null,
            ':id'             => $id,
        ]);

        $updated = $stmt->rowCount() > 0;
        if ($updated) {
            app('hooks')?->dispatch(new \API\Events\EvenementUpdated($id, $data));
        }
        return $updated;
    }

    /**
     * Delete an evenement.
     *
     * @param int $id
     * @return bool True if a row was deleted
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM evenements WHERE id = ?');
        $stmt->execute([$id]);

        $deleted = $stmt->rowCount() > 0;
        if ($deleted) {
            app('hooks')?->dispatch(new \API\Events\EvenementDeleted($id));
        }
        return $deleted;
    }

    /**
     * Toggle the statut between 'actif' and 'annule'.
     *
     * @param int $id
     * @return bool True if a row was updated
     */
    public function toggleStatus(int $id): bool
    {
        $sql = <<<'SQL'
            UPDATE evenements
            SET statut = CASE WHEN statut = 'actif' THEN 'annule' ELSE 'actif' END,
                date_modification = NOW()
            WHERE id = ?
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Get all distinct event types.
     *
     * @return array
     */
    public function getDistinctTypes(): array
    {
        $sql = 'SELECT DISTINCT type_evenement FROM evenements ORDER BY type_evenement';
        $stmt = $this->pdo->query($sql);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
