<?php

declare(strict_types=1);

namespace API\Services\Scolaire;

use PDO;

class PeriodeService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Retrieve all periodes ordered by numero.
     *
     * @return array
     */
    public function getAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM periodes ORDER BY numero');

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retrieve a single periode by its ID.
     *
     * @param int $id
     * @return array|null
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM periodes WHERE id = ?');
        $stmt->execute([$id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /**
     * Create a new periode.
     *
     * @param array $data Must contain: nom, numero, type, date_debut, date_fin
     * @return int The ID of the newly created periode
     */
    public function create(array $data): int
    {
        $sql = <<<'SQL'
            INSERT INTO periodes (nom, numero, type, date_debut, date_fin)
            VALUES (:nom, :numero, :type, :date_debut, :date_fin)
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':nom'        => $data['nom'],
            ':numero'     => $data['numero'],
            ':type'       => $data['type'],
            ':date_debut' => $data['date_debut'],
            ':date_fin'   => $data['date_fin'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Update an existing periode.
     *
     * @param int   $id
     * @param array $data Fields: nom, numero, type, date_debut, date_fin
     * @return bool True if a row was updated
     */
    public function update(int $id, array $data): bool
    {
        $sql = <<<'SQL'
            UPDATE periodes
            SET nom = :nom,
                numero = :numero,
                type = :type,
                date_debut = :date_debut,
                date_fin = :date_fin
            WHERE id = :id
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':nom'        => $data['nom'],
            ':numero'     => $data['numero'],
            ':type'       => $data['type'],
            ':date_debut' => $data['date_debut'],
            ':date_fin'   => $data['date_fin'],
            ':id'         => $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Delete a periode.
     *
     * @param int $id
     * @return bool True if a row was deleted
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM periodes WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Detect overlapping periodes by comparing date ranges.
     *
     * Two periodes overlap when one starts before the other ends and vice versa.
     *
     * @return array List of strings describing each overlapping pair, e.g. ["Period A / Period B"]
     */
    public function detectOverlaps(): array
    {
        $sql = <<<'SQL'
            SELECT a.id   AS a_id,
                   a.nom  AS a_nom,
                   b.id   AS b_id,
                   b.nom  AS b_nom
            FROM periodes a
            INNER JOIN periodes b
                ON a.id < b.id
               AND a.date_debut <= b.date_fin
               AND b.date_debut <= a.date_fin
            ORDER BY a.id, b.id
        SQL;

        $stmt = $this->pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $overlaps = [];
        foreach ($rows as $row) {
            $overlaps[] = "{$row['a_nom']} / {$row['b_nom']}";
        }

        return $overlaps;
    }

    /**
     * Get the currently active periode based on today's date.
     *
     * @return array|null
     */
    public function getCurrent(): ?array
    {
        $sql = <<<'SQL'
            SELECT * FROM periodes
            WHERE CURDATE() BETWEEN date_debut AND date_fin
            LIMIT 1
        SQL;

        $stmt = $this->pdo->query($sql);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }
}
