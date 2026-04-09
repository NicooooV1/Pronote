<?php
declare(strict_types=1);

namespace API\Services;

use PDO;

/**
 * CrossModuleAnalyticsService — Correlations and insights across modules.
 *
 * Provides cross-domain analytics: absence/grade correlations, trends,
 * class comparisons, and early-warning indicators.
 */
class CrossModuleAnalyticsService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Correlation between absence rate and grade average per student in a class.
     *
     * @return array{eleves: array, correlation: float}
     */
    public function correlationAbsencesNotes(string $classe, int $trimestre): array
    {
        $stmt = $this->pdo->prepare("
            SELECT e.id AS eleve_id, CONCAT(e.prenom, ' ', e.nom) AS eleve,
                   COALESCE(AVG(n.note), 0) AS moyenne,
                   (SELECT COUNT(*) FROM absences a WHERE a.id_eleve = e.id
                    AND a.justifiee = 0 AND QUARTER(a.date_debut) = :trim) AS nb_absences_nj
            FROM eleves e
            LEFT JOIN notes n ON n.id_eleve = e.id AND n.trimestre = :trim2
            WHERE e.classe = :classe
            GROUP BY e.id, e.prenom, e.nom
            ORDER BY nb_absences_nj DESC
        ");
        $stmt->execute([':classe' => $classe, ':trim' => $trimestre, ':trim2' => $trimestre]);
        $eleves = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate Pearson correlation
        $correlation = $this->pearsonCorrelation(
            array_column($eleves, 'nb_absences_nj'),
            array_column($eleves, 'moyenne')
        );

        return ['eleves' => $eleves, 'correlation' => $correlation];
    }

    /**
     * Grade trend per class over periods.
     */
    public function tendancesNotesClasse(string $classe): array
    {
        $stmt = $this->pdo->prepare("
            SELECT n.trimestre,
                   AVG(n.note) AS moyenne_classe,
                   MIN(n.note) AS note_min,
                   MAX(n.note) AS note_max,
                   COUNT(DISTINCT n.id_eleve) AS nb_eleves
            FROM notes n
            JOIN eleves e ON n.id_eleve = e.id
            WHERE e.classe = :classe
            GROUP BY n.trimestre
            ORDER BY n.trimestre
        ");
        $stmt->execute([':classe' => $classe]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Compare classes on key metrics.
     */
    public function comparerClasses(): array
    {
        $stmt = $this->pdo->query("
            SELECT e.classe,
                   COUNT(DISTINCT e.id) AS nb_eleves,
                   COALESCE(AVG(n.note), 0) AS moyenne_notes,
                   (SELECT COUNT(*) FROM absences a
                    JOIN eleves e2 ON a.id_eleve = e2.id
                    WHERE e2.classe = e.classe AND a.justifiee = 0) AS absences_nj,
                   (SELECT COUNT(*) FROM incidents i
                    JOIN eleves e3 ON i.eleve_id = e3.id
                    WHERE e3.classe = e.classe) AS nb_incidents
            FROM eleves e
            LEFT JOIN notes n ON n.id_eleve = e.id
            GROUP BY e.classe
            ORDER BY moyenne_notes DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Students with declining grades across periods.
     */
    public function elevesEnDeclin(string $classe, float $seuilBaisse = 2.0): array
    {
        $stmt = $this->pdo->prepare("
            SELECT e.id, CONCAT(e.prenom, ' ', e.nom) AS eleve,
                   AVG(CASE WHEN n.trimestre = 1 THEN n.note END) AS moy_t1,
                   AVG(CASE WHEN n.trimestre = 2 THEN n.note END) AS moy_t2,
                   AVG(CASE WHEN n.trimestre = 3 THEN n.note END) AS moy_t3
            FROM eleves e
            JOIN notes n ON n.id_eleve = e.id
            WHERE e.classe = :classe
            GROUP BY e.id, e.prenom, e.nom
            HAVING (moy_t1 IS NOT NULL AND moy_t2 IS NOT NULL AND moy_t1 - moy_t2 > :seuil)
                OR (moy_t2 IS NOT NULL AND moy_t3 IS NOT NULL AND moy_t2 - moy_t3 > :seuil2)
            ORDER BY eleve
        ");
        $stmt->execute([':classe' => $classe, ':seuil' => $seuilBaisse, ':seuil2' => $seuilBaisse]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Discipline impact on grades.
     */
    public function impactDisciplineSurNotes(): array
    {
        $stmt = $this->pdo->query("
            SELECT
                CASE
                    WHEN inc_count = 0 THEN '0 incidents'
                    WHEN inc_count BETWEEN 1 AND 2 THEN '1-2 incidents'
                    WHEN inc_count BETWEEN 3 AND 5 THEN '3-5 incidents'
                    ELSE '6+ incidents'
                END AS tranche_incidents,
                AVG(moyenne) AS moyenne_notes,
                COUNT(*) AS nb_eleves
            FROM (
                SELECT e.id,
                       AVG(n.note) AS moyenne,
                       (SELECT COUNT(*) FROM incidents i WHERE i.eleve_id = e.id) AS inc_count
                FROM eleves e
                LEFT JOIN notes n ON n.id_eleve = e.id
                GROUP BY e.id
            ) sub
            GROUP BY tranche_incidents
            ORDER BY AVG(inc_count)
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Pearson correlation coefficient between two arrays.
     */
    private function pearsonCorrelation(array $x, array $y): float
    {
        $n = count($x);
        if ($n < 2 || $n !== count($y)) return 0.0;

        $sumX = array_sum($x);
        $sumY = array_sum($y);
        $sumXY = 0; $sumX2 = 0; $sumY2 = 0;
        for ($i = 0; $i < $n; $i++) {
            $xi = (float) $x[$i];
            $yi = (float) $y[$i];
            $sumXY += $xi * $yi;
            $sumX2 += $xi * $xi;
            $sumY2 += $yi * $yi;
        }

        $denom = sqrt(($n * $sumX2 - $sumX * $sumX) * ($n * $sumY2 - $sumY * $sumY));
        if ($denom == 0) return 0.0;

        return round(($n * $sumXY - $sumX * $sumY) / $denom, 4);
    }
}
