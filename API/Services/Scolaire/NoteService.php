<?php

declare(strict_types=1);

namespace API\Services\Scolaire;

use PDO;

class NoteService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Retrieve paginated and filtered notes with joined eleve, matiere and professeur data.
     *
     * @param array $filters Supported keys: classe, matiere_id, professeur_id, trimestre, date_from, date_to, eleve_id
     * @param int   $page    Page number (1-based)
     * @param int   $perPage Items per page
     * @return array{data: array, total: int, pages: int}
     */
    public function getFiltered(array $filters, int $page = 1, int $perPage = 50): array
    {
        $where  = [];
        $params = [];

        if (!empty($filters['classe'])) {
            $where[]          = 'e.classe = :classe';
            $params[':classe'] = $filters['classe'];
        }

        if (!empty($filters['matiere_id'])) {
            $where[]              = 'n.id_matiere = :matiere_id';
            $params[':matiere_id'] = (int) $filters['matiere_id'];
        }

        if (!empty($filters['professeur_id'])) {
            $where[]                 = 'n.id_professeur = :professeur_id';
            $params[':professeur_id'] = (int) $filters['professeur_id'];
        }

        if (!empty($filters['trimestre'])) {
            $where[]              = 'n.trimestre = :trimestre';
            $params[':trimestre'] = (int) $filters['trimestre'];
        }

        if (!empty($filters['date_from'])) {
            $where[]              = 'n.date_note >= :date_from';
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[]            = 'n.date_note <= :date_to';
            $params[':date_to'] = $filters['date_to'];
        }

        if (!empty($filters['eleve_id'])) {
            $where[]             = 'n.id_eleve = :eleve_id';
            $params[':eleve_id'] = (int) $filters['eleve_id'];
        }

        $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

        // Count total rows
        $countSql = "SELECT COUNT(*) FROM notes n
            JOIN eleves e ON n.id_eleve = e.id
            JOIN matieres m ON n.id_matiere = m.id
            JOIN professeurs p ON n.id_professeur = p.id
            {$whereClause}";

        $stmt = $this->pdo->prepare($countSql);
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        $pages  = $total > 0 ? (int) ceil($total / $perPage) : 1;
        $offset = ($page - 1) * $perPage;

        // Fetch data
        $dataSql = "SELECT n.*,
                e.nom AS eleve_nom, e.prenom AS eleve_prenom,
                m.nom AS matiere_nom,
                CONCAT(p.nom, ' ', p.prenom) AS professeur_nom
            FROM notes n
            JOIN eleves e ON n.id_eleve = e.id
            JOIN matieres m ON n.id_matiere = m.id
            JOIN professeurs p ON n.id_professeur = p.id
            {$whereClause}
            ORDER BY n.date_note DESC
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
     * Retrieve a single note by ID with joined data.
     *
     * @param int $id
     * @return array|null
     */
    public function getById(int $id): ?array
    {
        $sql = <<<'SQL'
            SELECT n.*,
                   e.nom AS eleve_nom, e.prenom AS eleve_prenom,
                   m.nom AS matiere_nom,
                   CONCAT(p.nom, ' ', p.prenom) AS professeur_nom
            FROM notes n
            JOIN eleves e ON n.id_eleve = e.id
            JOIN matieres m ON n.id_matiere = m.id
            JOIN professeurs p ON n.id_professeur = p.id
            WHERE n.id = ?
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /**
     * Create a new note.
     *
     * @param array $data Required: id_eleve, id_matiere, id_professeur, note, date_note, trimestre.
     *                     Optional: note_sur, coefficient, type_evaluation, commentaire.
     * @return int The ID of the newly created note
     */
    public function create(array $data): int
    {
        $sql = <<<'SQL'
            INSERT INTO notes (id_eleve, id_matiere, id_professeur, note, date_note, trimestre,
                               note_sur, coefficient, type_evaluation, commentaire)
            VALUES (:id_eleve, :id_matiere, :id_professeur, :note, :date_note, :trimestre,
                    :note_sur, :coefficient, :type_evaluation, :commentaire)
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id_eleve'        => (int) $data['id_eleve'],
            ':id_matiere'      => (int) $data['id_matiere'],
            ':id_professeur'   => (int) $data['id_professeur'],
            ':note'            => $data['note'],
            ':date_note'       => $data['date_note'],
            ':trimestre'       => (int) $data['trimestre'],
            ':note_sur'        => $data['note_sur'] ?? 20,
            ':coefficient'     => $data['coefficient'] ?? 1,
            ':type_evaluation' => $data['type_evaluation'] ?? null,
            ':commentaire'     => $data['commentaire'] ?? null,
        ]);

        $id = (int) $this->pdo->lastInsertId();
        app('hooks')?->dispatch(new \API\Events\NoteCreated($id, $data));
        return $id;
    }

    /**
     * Update an existing note.
     *
     * @param int   $id
     * @param array $data Fields to update
     * @return bool True if a row was updated
     */
    public function update(int $id, array $data): bool
    {
        $allowed = [
            'id_eleve', 'id_matiere', 'id_professeur', 'note', 'note_sur',
            'coefficient', 'type_evaluation', 'date_note', 'commentaire', 'trimestre',
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
        $sql       = "UPDATE notes SET {$setClause}, date_modification = NOW() WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $updated = $stmt->rowCount() > 0;
        if ($updated) {
            app('hooks')?->dispatch(new \API\Events\NoteUpdated($id, $data));
        }
        return $updated;
    }

    /**
     * Delete a note by ID.
     *
     * @param int $id
     * @return bool True if a row was deleted
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM notes WHERE id = ?');
        $stmt->execute([$id]);

        $deleted = $stmt->rowCount() > 0;
        if ($deleted) {
            app('hooks')?->dispatch(new \API\Events\NoteDeleted($id));
        }
        return $deleted;
    }

    /**
     * Get average note per matiere for a given class and trimestre.
     *
     * @param string $classe
     * @param int    $trimestre
     * @return array
     */
    public function getStatsByClasse(string $classe, int $trimestre): array
    {
        $sql = <<<'SQL'
            SELECT m.nom AS matiere,
                   AVG(n.note * 20 / n.note_sur) AS moyenne,
                   COUNT(*) AS nb_notes
            FROM notes n
            JOIN eleves e ON n.id_eleve = e.id
            JOIN matieres m ON n.id_matiere = m.id
            WHERE e.classe = ?
              AND n.trimestre = ?
            GROUP BY m.id, m.nom
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$classe, $trimestre]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get average note per matiere per trimestre for a given eleve.
     *
     * @param int $eleveId
     * @return array
     */
    public function getStatsByEleve(int $eleveId): array
    {
        $sql = <<<'SQL'
            SELECT n.trimestre,
                   m.nom AS matiere,
                   AVG(n.note * 20 / n.note_sur) AS moyenne,
                   COUNT(*) AS nb_notes
            FROM notes n
            JOIN matieres m ON n.id_matiere = m.id
            WHERE n.id_eleve = ?
            GROUP BY n.trimestre, m.id, m.nom
            ORDER BY n.trimestre, m.nom
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$eleveId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get the overall average for a given trimestre (normalized to /20).
     *
     * @param int $trimestre
     * @return float|null
     */
    public function getMoyenneGenerale(int $trimestre): ?float
    {
        $sql = <<<'SQL'
            SELECT AVG(note * 20 / note_sur) AS moyenne
            FROM notes
            WHERE trimestre = ?
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$trimestre]);

        $result = $stmt->fetchColumn();

        return $result !== null && $result !== false ? (float) $result : null;
    }

    /**
     * Get distinct eleves belonging to a class.
     *
     * @param string $classe
     * @return array
     */
    public function getElevesByClasse(string $classe): array
    {
        $sql = <<<'SQL'
            SELECT DISTINCT e.id, e.nom, e.prenom
            FROM eleves e
            WHERE e.classe = ?
            ORDER BY e.nom, e.prenom
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$classe]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
