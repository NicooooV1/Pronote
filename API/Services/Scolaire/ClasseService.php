<?php

declare(strict_types=1);

namespace API\Services\Scolaire;

use PDO;
use RuntimeException;

class ClasseService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Retrieve all classes with student count and principal teacher name.
     *
     * @return array
     */
    public function getAllWithStats(): array
    {
        $sql = <<<'SQL'
            SELECT c.*,
                   (SELECT COUNT(*) FROM eleves WHERE classe = c.nom AND actif = 1) AS effectif,
                   CONCAT(p.prenom, ' ', p.nom) AS pp_nom
            FROM classes c
            LEFT JOIN professeurs p ON c.professeur_principal_id = p.id
            ORDER BY c.nom
        SQL;

        $stmt = $this->pdo->query($sql);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retrieve a single classe by its ID.
     *
     * @param int $id
     * @return array|null
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM classes WHERE id = ?');
        $stmt->execute([$id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /**
     * Create a new classe.
     *
     * @param array $data Keys: nom, niveau, annee_scolaire, professeur_principal_id
     * @return int The ID of the newly created classe
     */
    public function create(array $data): int
    {
        $sql = <<<'SQL'
            INSERT INTO classes (nom, niveau, annee_scolaire, professeur_principal_id)
            VALUES (:nom, :niveau, :annee_scolaire, :professeur_principal_id)
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':nom'                     => $data['nom'],
            ':niveau'                  => $data['niveau'],
            ':annee_scolaire'          => $data['annee_scolaire'],
            ':professeur_principal_id' => $data['professeur_principal_id'] ?? null,
        ]);

        $id = (int) $this->pdo->lastInsertId();
        app('hooks')?->dispatch(new \API\Events\ClasseCreated($id, $data));
        return $id;
    }

    /**
     * Update an existing classe.
     *
     * @param int   $id
     * @param array $data Keys: nom, niveau, annee_scolaire, professeur_principal_id, actif
     * @return bool True if a row was updated
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [':id' => $id];

        foreach (['nom', 'niveau', 'annee_scolaire', 'professeur_principal_id', 'actif'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[]          = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = 'UPDATE classes SET ' . implode(', ', $fields) . ' WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $updated = $stmt->rowCount() > 0;
        if ($updated) {
            app('hooks')?->dispatch(new \API\Events\ClasseUpdated($id, $data));
        }
        return $updated;
    }

    /**
     * Delete a classe. Throws if students are still assigned.
     *
     * @param int $id
     * @return bool True if a row was deleted
     * @throws RuntimeException If students are assigned to this classe
     */
    public function delete(int $id): bool
    {
        // Fetch the classe name to check for assigned students
        $stmt = $this->pdo->prepare('SELECT nom FROM classes WHERE id = ?');
        $stmt->execute([$id]);
        $nom = $stmt->fetchColumn();

        if ($nom === false) {
            return false;
        }

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM eleves WHERE classe = ?');
        $stmt->execute([$nom]);
        $effectif = (int) $stmt->fetchColumn();

        if ($effectif > 0) {
            throw new RuntimeException(
                "Impossible de supprimer la classe #{$id} ({$nom}) : {$effectif} eleve(s) affecte(s)."
            );
        }

        $stmt = $this->pdo->prepare('DELETE FROM classes WHERE id = ?');
        $stmt->execute([$id]);

        $deleted = $stmt->rowCount() > 0;
        if ($deleted) {
            app('hooks')?->dispatch(new \API\Events\ClasseDeleted($id));
        }
        return $deleted;
    }

    /**
     * Assign students to a classe by updating their classe field.
     *
     * @param int    $classeId   The classe ID (for reference)
     * @param string $className  The classe name to set on students
     * @param array  $studentIds Array of student IDs
     * @return int Number of affected rows
     */
    public function assignStudents(int $classeId, string $className, array $studentIds): int
    {
        if (empty($studentIds)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
        $sql          = "UPDATE eleves SET classe = ? WHERE id IN ({$placeholders})";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge([$className], array_map('intval', $studentIds)));

        return $stmt->rowCount();
    }

    /**
     * Retrieve the full professor-class affectation matrix, grouped by professor.
     *
     * @return array Keyed by professeur_id, each value is ['prof_nom' => string, 'classes' => string[]]
     */
    public function getAffectationsMatrix(): array
    {
        $sql = <<<'SQL'
            SELECT pc.professeur_id,
                   pc.classe_nom,
                   CONCAT(p.prenom, ' ', p.nom) AS prof_nom
            FROM professeur_classes pc
            JOIN professeurs p ON pc.professeur_id = p.id
        SQL;

        $stmt = $this->pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $matrix = [];
        foreach ($rows as $row) {
            $profId = (int) $row['professeur_id'];
            if (!isset($matrix[$profId])) {
                $matrix[$profId] = [
                    'prof_nom' => $row['prof_nom'],
                    'classes'  => [],
                ];
            }
            $matrix[$profId]['classes'][] = $row['classe_nom'];
        }

        return $matrix;
    }

    /**
     * Toggle a professor-class affectation: delete if exists, insert if not.
     *
     * @param int    $profId
     * @param string $className
     * @return bool True after toggling
     */
    public function toggleAffectation(int $profId, string $className): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM professeur_classes WHERE professeur_id = ? AND classe_nom = ?'
        );
        $stmt->execute([$profId, $className]);
        $exists = (int) $stmt->fetchColumn() > 0;

        if ($exists) {
            $stmt = $this->pdo->prepare(
                'DELETE FROM professeur_classes WHERE professeur_id = ? AND classe_nom = ?'
            );
            $stmt->execute([$profId, $className]);
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO professeur_classes (professeur_id, classe_nom) VALUES (?, ?)'
            );
            $stmt->execute([$profId, $className]);
        }

        return true;
    }

    /**
     * Replace the entire affectation matrix in a single transaction.
     *
     * @param array $assignments Array of ['professeur_id' => int, 'classe_nom' => string]
     * @return bool True on success
     */
    public function saveFullMatrix(array $assignments): bool
    {
        $this->pdo->beginTransaction();

        try {
            $this->pdo->exec('DELETE FROM professeur_classes');

            $stmt = $this->pdo->prepare(
                'INSERT INTO professeur_classes (professeur_id, classe_nom) VALUES (:professeur_id, :classe_nom)'
            );

            foreach ($assignments as $assignment) {
                $stmt->execute([
                    ':professeur_id' => $assignment['professeur_id'],
                    ':classe_nom'    => $assignment['classe_nom'],
                ]);
            }

            $this->pdo->commit();

            return true;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
