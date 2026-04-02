<?php

declare(strict_types=1);

namespace API\Services\Scolaire;

use PDO;
use RuntimeException;

class MatiereService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Retrieve all matieres with their note count, ordered by actif DESC then nom.
     *
     * @return array
     */
    public function getAll(): array
    {
        $sql = <<<'SQL'
            SELECT m.*,
                   (SELECT COUNT(*) FROM notes WHERE id_matiere = m.id) AS note_count
            FROM matieres m
            ORDER BY m.actif DESC, m.nom
        SQL;

        $stmt = $this->pdo->query($sql);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retrieve a single matiere by its ID.
     *
     * @param int $id
     * @return array|null
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM matieres WHERE id = ?');
        $stmt->execute([$id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /**
     * Create a new matiere. The code is stored in uppercase.
     *
     * @param array $data Must contain: nom, code, coefficient, couleur
     * @return int The ID of the newly created matiere
     */
    public function create(array $data): int
    {
        $sql = <<<'SQL'
            INSERT INTO matieres (nom, code, coefficient, couleur)
            VALUES (:nom, :code, :coefficient, :couleur)
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':nom'         => $data['nom'],
            ':code'        => strtoupper($data['code']),
            ':coefficient' => $data['coefficient'],
            ':couleur'     => $data['couleur'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Update an existing matiere.
     *
     * @param int   $id
     * @param array $data Fields: nom, code, coefficient, couleur, actif
     * @return bool True if a row was updated
     */
    public function update(int $id, array $data): bool
    {
        $sql = <<<'SQL'
            UPDATE matieres
            SET nom = :nom,
                code = :code,
                coefficient = :coefficient,
                couleur = :couleur,
                actif = :actif
            WHERE id = :id
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':nom'         => $data['nom'],
            ':code'        => strtoupper($data['code']),
            ':coefficient' => $data['coefficient'],
            ':couleur'     => $data['couleur'],
            ':actif'       => $data['actif'],
            ':id'          => $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Delete a matiere. Throws if notes reference it.
     *
     * @param int $id
     * @return bool True if a row was deleted
     * @throws RuntimeException If the matiere still has associated notes
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM notes WHERE id_matiere = ?');
        $stmt->execute([$id]);
        $count = (int) $stmt->fetchColumn();

        if ($count > 0) {
            throw new RuntimeException(
                "Impossible de supprimer la matiere #{$id} : {$count} note(s) associee(s)."
            );
        }

        $stmt = $this->pdo->prepare('DELETE FROM matieres WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Toggle the actif flag on a matiere.
     *
     * @param int $id
     * @return bool True if a row was updated
     */
    public function toggleActive(int $id): bool
    {
        $stmt = $this->pdo->prepare('UPDATE matieres SET actif = NOT actif WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }
}
