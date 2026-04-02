<?php

declare(strict_types=1);

namespace API\Services\Scolaire;

use PDO;

class AbsenceService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Retrieve paginated absences with student info.
     *
     * @param array $filters Keys: classe, eleve_id, justifie, date_from, date_to
     * @param int   $page
     * @param int   $perPage
     * @return array ['data' => rows, 'total' => int, 'pages' => int]
     */
    public function getAbsences(array $filters, int $page = 1, int $perPage = 30): array
    {
        $where  = [];
        $params = [];

        if (!empty($filters['classe'])) {
            $where[]          = 'e.classe = :classe';
            $params[':classe'] = $filters['classe'];
        }
        if (!empty($filters['eleve_id'])) {
            $where[]            = 'a.id_eleve = :eleve_id';
            $params[':eleve_id'] = (int) $filters['eleve_id'];
        }
        if (isset($filters['justifie']) && $filters['justifie'] !== '') {
            $where[]            = 'a.justifie = :justifie';
            $params[':justifie'] = (int) $filters['justifie'];
        }
        if (!empty($filters['date_from'])) {
            $where[]              = 'a.date_debut >= :date_from';
            $params[':date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[]            = 'a.date_fin <= :date_to';
            $params[':date_to'] = $filters['date_to'];
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countSql = "SELECT COUNT(*) FROM absences a
                     JOIN eleves e ON a.id_eleve = e.id
                     {$whereClause}";

        $stmt = $this->pdo->prepare($countSql);
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        $pages  = (int) ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT a.*, e.nom AS eleve_nom, e.prenom AS eleve_prenom, e.classe
                FROM absences a
                JOIN eleves e ON a.id_eleve = e.id
                {$whereClause}
                ORDER BY a.date_debut DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
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
     * Retrieve paginated retards with student info.
     *
     * @param array $filters Keys: classe, eleve_id, justifie, date_from, date_to
     * @param int   $page
     * @param int   $perPage
     * @return array ['data' => rows, 'total' => int, 'pages' => int]
     */
    public function getRetards(array $filters, int $page = 1, int $perPage = 30): array
    {
        $where  = [];
        $params = [];

        if (!empty($filters['classe'])) {
            $where[]          = 'e.classe = :classe';
            $params[':classe'] = $filters['classe'];
        }
        if (!empty($filters['eleve_id'])) {
            $where[]            = 'r.id_eleve = :eleve_id';
            $params[':eleve_id'] = (int) $filters['eleve_id'];
        }
        if (isset($filters['justifie']) && $filters['justifie'] !== '') {
            $where[]            = 'r.justifie = :justifie';
            $params[':justifie'] = (int) $filters['justifie'];
        }
        if (!empty($filters['date_from'])) {
            $where[]              = 'r.date_retard >= :date_from';
            $params[':date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[]            = 'r.date_retard <= :date_to';
            $params[':date_to'] = $filters['date_to'];
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countSql = "SELECT COUNT(*) FROM retards r
                     JOIN eleves e ON r.id_eleve = e.id
                     {$whereClause}";

        $stmt = $this->pdo->prepare($countSql);
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        $pages  = (int) ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT r.*, e.nom AS eleve_nom, e.prenom AS eleve_prenom, e.classe
                FROM retards r
                JOIN eleves e ON r.id_eleve = e.id
                {$whereClause}
                ORDER BY r.date_retard DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
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
     * Create a new absence record.
     *
     * @param array $data Required: id_eleve, date_debut, date_fin, type_absence, signale_par
     * @return int The ID of the newly created absence
     */
    public function createAbsence(array $data): int
    {
        $sql = <<<'SQL'
            INSERT INTO absences (id_eleve, date_debut, date_fin, type_absence, motif, justifie, commentaire, signale_par, date_signalement)
            VALUES (:id_eleve, :date_debut, :date_fin, :type_absence, :motif, :justifie, :commentaire, :signale_par, NOW())
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id_eleve'     => $data['id_eleve'],
            ':date_debut'   => $data['date_debut'],
            ':date_fin'     => $data['date_fin'],
            ':type_absence' => $data['type_absence'],
            ':motif'        => $data['motif'] ?? null,
            ':justifie'     => $data['justifie'] ?? 0,
            ':commentaire'  => $data['commentaire'] ?? null,
            ':signale_par'  => $data['signale_par'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Create a new retard record.
     *
     * @param array $data Required: id_eleve, date_retard, duree_minutes, signale_par
     * @return int The ID of the newly created retard
     */
    public function createRetard(array $data): int
    {
        $sql = <<<'SQL'
            INSERT INTO retards (id_eleve, date_retard, duree_minutes, motif, justifie, commentaire, signale_par, date_signalement)
            VALUES (:id_eleve, :date_retard, :duree_minutes, :motif, :justifie, :commentaire, :signale_par, NOW())
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id_eleve'       => $data['id_eleve'],
            ':date_retard'    => $data['date_retard'],
            ':duree_minutes'  => $data['duree_minutes'],
            ':motif'          => $data['motif'] ?? null,
            ':justifie'       => $data['justifie'] ?? 0,
            ':commentaire'    => $data['commentaire'] ?? null,
            ':signale_par'    => $data['signale_par'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Toggle the justifie flag on an absence.
     *
     * @param int $id
     * @return bool True if a row was updated
     */
    public function toggleJustificationAbsence(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE absences SET justifie = NOT justifie, date_modification = NOW() WHERE id = ?'
        );
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Toggle the justifie flag on a retard.
     *
     * @param int $id
     * @return bool True if a row was updated
     */
    public function toggleJustificationRetard(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE retards SET justifie = NOT justifie, date_modification = NOW() WHERE id = ?'
        );
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Delete an absence by ID.
     *
     * @param int $id
     * @return bool True if a row was deleted
     */
    public function deleteAbsence(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM absences WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Delete a retard by ID.
     *
     * @param int $id
     * @return bool True if a row was deleted
     */
    public function deleteRetard(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM retards WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Get today's absence and retard counts.
     *
     * @return array ['absences' => int, 'retards' => int]
     */
    public function getStatsToday(): array
    {
        $stmt = $this->pdo->query(
            'SELECT COUNT(*) FROM absences WHERE CURDATE() BETWEEN DATE(date_debut) AND DATE(date_fin)'
        );
        $absences = (int) $stmt->fetchColumn();

        $stmt = $this->pdo->query(
            'SELECT COUNT(*) FROM retards WHERE DATE(date_retard) = CURDATE()'
        );
        $retards = (int) $stmt->fetchColumn();

        return [
            'absences' => $absences,
            'retards'  => $retards,
        ];
    }

    /**
     * Count unjustified absences.
     *
     * @return int
     */
    public function getUnjustifiedCount(): int
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM absences WHERE justifie = 0');

        return (int) $stmt->fetchColumn();
    }

    /**
     * Approve a justificatif and optionally mark the linked absence as justified.
     * Uses a transaction to ensure consistency.
     *
     * @param int    $id
     * @param int    $adminId
     * @param string $comment
     * @return bool True if the justificatif was updated
     */
    public function approveJustificatif(int $id, int $adminId, string $comment = ''): bool
    {
        $this->pdo->beginTransaction();

        try {
            $sql = <<<'SQL'
                UPDATE justificatifs
                SET traite = 1,
                    approuve = 1,
                    commentaire_admin = :comment,
                    date_traitement = NOW(),
                    traite_par = :admin_id
                WHERE id = :id
            SQL;

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':comment'  => $comment,
                ':admin_id' => $adminId,
                ':id'       => $id,
            ]);

            $updated = $stmt->rowCount() > 0;

            // If the justificatif references an absence, mark it justified
            $stmt = $this->pdo->prepare('SELECT id_absence FROM justificatifs WHERE id = ?');
            $stmt->execute([$id]);
            $idAbsence = $stmt->fetchColumn();

            if ($idAbsence) {
                $stmt = $this->pdo->prepare(
                    'UPDATE absences SET justifie = 1, date_modification = NOW() WHERE id = ?'
                );
                $stmt->execute([$idAbsence]);
            }

            $this->pdo->commit();

            return $updated;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Reject a justificatif.
     *
     * @param int    $id
     * @param int    $adminId
     * @param string $comment
     * @return bool True if the justificatif was updated
     */
    public function rejectJustificatif(int $id, int $adminId, string $comment = ''): bool
    {
        $sql = <<<'SQL'
            UPDATE justificatifs
            SET traite = 1,
                approuve = 0,
                commentaire_admin = :comment,
                date_traitement = NOW(),
                traite_par = :admin_id
            WHERE id = :id
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':comment'  => $comment,
            ':admin_id' => $adminId,
            ':id'       => $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Retrieve paginated justificatifs filtered by status.
     *
     * @param string $status One of: pending, approved, rejected
     * @param int    $page
     * @param int    $perPage
     * @return array ['data' => rows, 'total' => int, 'pages' => int]
     */
    public function getPendingJustificatifs(string $status = 'pending', int $page = 1, int $perPage = 30): array
    {
        switch ($status) {
            case 'approved':
                $whereClause = 'WHERE j.traite = 1 AND j.approuve = 1';
                break;
            case 'rejected':
                $whereClause = 'WHERE j.traite = 1 AND j.approuve = 0';
                break;
            case 'pending':
            default:
                $whereClause = 'WHERE j.traite = 0';
                break;
        }

        $countSql = "SELECT COUNT(*) FROM justificatifs j
                     JOIN eleves e ON j.id_eleve = e.id
                     {$whereClause}";

        $stmt  = $this->pdo->query($countSql);
        $total = (int) $stmt->fetchColumn();

        $pages  = (int) ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT j.*, e.nom AS eleve_nom, e.prenom AS eleve_prenom, e.classe
                FROM justificatifs j
                JOIN eleves e ON j.id_eleve = e.id
                {$whereClause}
                ORDER BY j.date_soumission DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'data'  => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
            'pages' => $pages,
        ];
    }
}
