<?php
declare(strict_types=1);

namespace API\Services;

use PDO;

/**
 * AnalyticsService — Agrégation de données statistiques pour le tableau de bord admin.
 *
 * Fournit les KPIs globaux, par classe, par matière, par période.
 * Toutes les requêtes utilisent des agrégations SQL optimisées.
 */
class AnalyticsService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ─── KPIs globaux ───────────────────────────────────────────────

    /**
     * Statistiques globales de l'établissement.
     */
    public function getGlobalStats(): array
    {
        return [
            'students' => $this->countTable('eleves', 'actif = 1'),
            'teachers' => $this->countTable('professeurs', 'actif = 1'),
            'parents' => $this->countTable('parents', 'actif = 1'),
            'classes' => $this->countTable('classes'),
            'subjects' => $this->countTable('matieres'),
            'absences_today' => $this->countTable('absences', "date_absence = CURDATE()"),
            'absences_month' => $this->countTable('absences', "date_absence >= DATE_FORMAT(NOW(), '%Y-%m-01')"),
            'incidents_open' => $this->countTable('incidents', "statut = 'ouvert'"),
            'messages_today' => $this->countTable('messages', "date_envoi >= CURDATE()"),
        ];
    }

    /**
     * Taux d'absence global et par classe.
     */
    public function getAbsenceStats(?int $periodeId = null): array
    {
        $where = $periodeId ? "AND a.date_absence BETWEEN p.date_debut AND p.date_fin" : "";
        $join = $periodeId ? "CROSS JOIN periodes p WHERE p.id = {$periodeId}" : "";

        // Taux global
        $totalStudents = $this->countTable('eleves', 'actif = 1');
        $totalAbsences = $this->countTable('absences', $periodeId
            ? "date_absence BETWEEN (SELECT date_debut FROM periodes WHERE id = {$periodeId}) AND (SELECT date_fin FROM periodes WHERE id = {$periodeId})"
            : "date_absence >= DATE_FORMAT(NOW(), '%Y-%m-01')"
        );

        // Par classe
        $stmt = $this->pdo->query(
            "SELECT c.id, c.nom AS classe, COUNT(a.id) AS total_absences,
                    COUNT(DISTINCT a.eleve_id) AS eleves_absents
             FROM classes c
             LEFT JOIN eleves e ON e.classe_id = c.id AND e.actif = 1
             LEFT JOIN absences a ON a.eleve_id = e.id AND a.date_absence >= DATE_FORMAT(NOW(), '%Y-%m-01')
             GROUP BY c.id, c.nom
             ORDER BY total_absences DESC"
        );

        return [
            'total_students' => $totalStudents,
            'total_absences' => $totalAbsences,
            'rate' => $totalStudents > 0 ? round($totalAbsences / max($totalStudents, 1) * 100, 1) : 0,
            'by_class' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    /**
     * Moyennes par matière.
     */
    public function getGradeStats(?int $periodeId = null): array
    {
        $periodeFilter = '';
        $params = [];
        if ($periodeId) {
            $periodeFilter = 'AND n.periode_id = ?';
            $params[] = $periodeId;
        }

        $stmt = $this->pdo->prepare(
            "SELECT m.id, m.nom AS matiere,
                    ROUND(AVG(n.note / n.bareme * 20), 2) AS moyenne,
                    MIN(n.note / n.bareme * 20) AS note_min,
                    MAX(n.note / n.bareme * 20) AS note_max,
                    COUNT(n.id) AS total_notes,
                    COUNT(DISTINCT n.eleve_id) AS eleves_notes
             FROM matieres m
             LEFT JOIN notes n ON n.matiere_id = m.id {$periodeFilter}
             GROUP BY m.id, m.nom
             HAVING total_notes > 0
             ORDER BY moyenne DESC"
        );
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Moyennes par classe.
     */
    public function getGradeStatsByClass(?int $periodeId = null): array
    {
        $periodeFilter = '';
        $params = [];
        if ($periodeId) {
            $periodeFilter = 'AND n.periode_id = ?';
            $params[] = $periodeId;
        }

        $stmt = $this->pdo->prepare(
            "SELECT c.id, c.nom AS classe,
                    ROUND(AVG(n.note / n.bareme * 20), 2) AS moyenne,
                    COUNT(DISTINCT n.eleve_id) AS eleves,
                    COUNT(n.id) AS total_notes
             FROM classes c
             JOIN eleves e ON e.classe_id = c.id AND e.actif = 1
             JOIN notes n ON n.eleve_id = e.id {$periodeFilter}
             GROUP BY c.id, c.nom
             ORDER BY moyenne DESC"
        );
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Évolution des moyennes par période.
     */
    public function getGradeEvolution(): array
    {
        $stmt = $this->pdo->query(
            "SELECT p.id, p.nom AS periode,
                    ROUND(AVG(n.note / n.bareme * 20), 2) AS moyenne_generale,
                    COUNT(n.id) AS total_notes
             FROM periodes p
             JOIN notes n ON n.periode_id = p.id
             GROUP BY p.id, p.nom
             ORDER BY p.date_debut"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Statistiques de connexion (taux d'utilisation).
     */
    public function getUsageStats(int $days = 30): array
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT DATE(created_at) AS jour, COUNT(*) AS connexions
                 FROM audit_log
                 WHERE action = 'auth.login' AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                 GROUP BY DATE(created_at)
                 ORDER BY jour"
            );
            $stmt->execute([$days]);
            $daily = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Par rôle
            $stmt = $this->pdo->prepare(
                "SELECT JSON_UNQUOTE(JSON_EXTRACT(context, '$.user_type')) AS role, COUNT(*) AS total
                 FROM audit_log
                 WHERE action = 'auth.login' AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                 GROUP BY role
                 ORDER BY total DESC"
            );
            $stmt->execute([$days]);
            $byRole = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return ['daily' => $daily, 'by_role' => $byRole];
        } catch (\Throwable $e) {
            return ['daily' => [], 'by_role' => []];
        }
    }

    /**
     * Statistiques enseignant : résultats par devoir.
     */
    public function getTeacherStats(int $professeurId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT m.nom AS matiere,
                    ROUND(AVG(n.note / n.bareme * 20), 2) AS moyenne,
                    COUNT(n.id) AS total_notes,
                    COUNT(DISTINCT n.eleve_id) AS eleves
             FROM notes n
             JOIN matieres m ON m.id = n.matiere_id
             WHERE n.professeur_id = ?
             GROUP BY m.id, m.nom"
        );
        $stmt->execute([$professeurId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── Helper ─────────────────────────────────────────────────────

    private function countTable(string $table, string $where = '1=1'): int
    {
        try {
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM `{$table}` WHERE {$where}");
            return (int) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
