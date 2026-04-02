<?php

declare(strict_types=1);

namespace API\Services;

use PDO;

/**
 * Service de métriques applicatives (J2).
 * Enregistre compteurs et temps de réponse dans la table app_metrics.
 */
class MetricsService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Enregistre une métrique ponctuelle.
     */
    public function record(string $key, float $value): void
    {
        try {
            $this->pdo->prepare(
                'INSERT INTO app_metrics (metric_key, metric_value) VALUES (?, ?)'
            )->execute([$key, $value]);
        } catch (\Throwable $e) {
            // Silently fail — metrics should never break the app
            error_log("MetricsService: {$e->getMessage()}");
        }
    }

    /**
     * Incrémente un compteur (enregistre +1).
     */
    public function increment(string $key): void
    {
        $this->record($key, 1);
    }

    /**
     * Enregistre un temps de réponse en ms pour un endpoint.
     */
    public function recordResponseTime(string $endpoint, float $milliseconds): void
    {
        $this->record("response_time:{$endpoint}", $milliseconds);
    }

    /**
     * Récupère un résumé des métriques sur la dernière heure.
     */
    public function getSummary(int $lastMinutes = 60): array
    {
        $sql = <<<'SQL'
            SELECT
                metric_key,
                COUNT(*) AS count,
                ROUND(AVG(metric_value), 2) AS avg_value,
                ROUND(MAX(metric_value), 2) AS max_value,
                ROUND(MIN(metric_value), 2) AS min_value,
                ROUND(SUM(metric_value), 2) AS total
            FROM app_metrics
            WHERE recorded_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)
            GROUP BY metric_key
            ORDER BY count DESC
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$lastMinutes]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les métriques de temps de réponse par endpoint.
     */
    public function getResponseTimes(int $lastMinutes = 60): array
    {
        $sql = <<<'SQL'
            SELECT
                metric_key,
                COUNT(*) AS requests,
                ROUND(AVG(metric_value), 1) AS avg_ms,
                ROUND(MAX(metric_value), 1) AS max_ms,
                ROUND(PERCENTILE_CONT(0.95) WITHIN GROUP (ORDER BY metric_value), 1) AS p95_ms
            FROM app_metrics
            WHERE metric_key LIKE 'response_time:%'
              AND recorded_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)
            GROUP BY metric_key
            ORDER BY avg_ms DESC
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$lastMinutes]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            // PERCENTILE_CONT not available on older MySQL — fallback
            return $this->getSummary($lastMinutes);
        }
    }

    /**
     * Purge les métriques plus vieilles que N jours.
     */
    public function purge(int $olderThanDays = 7): int
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM app_metrics WHERE recorded_at < DATE_SUB(NOW(), INTERVAL ? DAY)'
        );
        $stmt->execute([$olderThanDays]);
        return $stmt->rowCount();
    }
}
