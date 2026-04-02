<?php

declare(strict_types=1);

namespace API\Services;

use PDO;

/**
 * Job Queue Service (G4).
 * File d'attente de jobs asynchrones stockée en base (table job_queue).
 *
 * Usage :
 *   app('queue')->dispatch(SendEmailJob::class, ['to' => 'a@b.c', 'body' => '...']);
 *   app('queue')->dispatch(GenerateReport::class, [...], delay: new \DateTime('+5 min'));
 *
 * Le worker (scripts/worker.php) appelle processNext() en boucle.
 */
class QueueService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Ajouter un job dans la file.
     */
    public function dispatch(string $handler, array $payload, ?\DateTime $availableAt = null): int
    {
        $sql = <<<'SQL'
            INSERT INTO job_queue (handler, payload, available_at)
            VALUES (:handler, :payload, :available_at)
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':handler'      => $handler,
            ':payload'      => json_encode($payload),
            ':available_at' => $availableAt ? $availableAt->format('Y-m-d H:i:s') : date('Y-m-d H:i:s'),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Récupère et traite le prochain job disponible.
     * Retourne true si un job a été traité, false si la queue est vide.
     */
    public function processNext(): bool
    {
        $this->pdo->beginTransaction();

        try {
            // Sélectionner le prochain job disponible avec verrouillage
            $sql = <<<'SQL'
                SELECT * FROM job_queue
                WHERE status = 'pending'
                  AND available_at <= NOW()
                  AND attempts < max_attempts
                ORDER BY available_at ASC
                LIMIT 1
                FOR UPDATE SKIP LOCKED
            SQL;

            $stmt = $this->pdo->query($sql);
            $job = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$job) {
                $this->pdo->rollBack();
                return false;
            }

            // Marquer comme en cours
            $this->pdo->prepare(
                "UPDATE job_queue SET status = 'processing', attempts = attempts + 1, started_at = NOW() WHERE id = ?"
            )->execute([$job['id']]);

            $this->pdo->commit();

            // Exécuter le job
            try {
                $handler = $job['handler'];
                $payload = json_decode($job['payload'], true) ?: [];

                if (class_exists($handler)) {
                    $instance = new $handler();
                    $instance->handle($payload);
                } elseif (is_callable($handler)) {
                    call_user_func($handler, $payload);
                } else {
                    throw new \RuntimeException("Job handler not found: {$handler}");
                }

                // Succès
                $this->pdo->prepare(
                    "UPDATE job_queue SET status = 'completed', completed_at = NOW() WHERE id = ?"
                )->execute([$job['id']]);

            } catch (\Throwable $e) {
                // Échec
                $newStatus = ((int) $job['attempts'] + 1 >= (int) $job['max_attempts']) ? 'failed' : 'pending';
                $this->pdo->prepare(
                    "UPDATE job_queue SET status = ?, error_message = ? WHERE id = ?"
                )->execute([$newStatus, $e->getMessage(), $job['id']]);

                error_log("QueueService: Job #{$job['id']} failed: {$e->getMessage()}");
            }

            return true;

        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            error_log("QueueService: Transaction error: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Traite tous les jobs disponibles (pour le worker cron).
     */
    public function processAll(int $maxJobs = 50): int
    {
        $processed = 0;
        while ($processed < $maxJobs && $this->processNext()) {
            $processed++;
        }
        return $processed;
    }

    /**
     * Statistiques de la queue.
     */
    public function getStats(): array
    {
        $sql = <<<'SQL'
            SELECT status, COUNT(*) AS count
            FROM job_queue
            GROUP BY status
        SQL;

        $rows = $this->pdo->query($sql)->fetchAll(PDO::FETCH_KEY_PAIR);

        return [
            'pending'    => (int) ($rows['pending'] ?? 0),
            'processing' => (int) ($rows['processing'] ?? 0),
            'completed'  => (int) ($rows['completed'] ?? 0),
            'failed'     => (int) ($rows['failed'] ?? 0),
        ];
    }

    /**
     * Purge les jobs terminés de plus de N jours.
     */
    public function purge(int $olderThanDays = 7): int
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM job_queue WHERE status IN ('completed', 'failed') AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)"
        );
        $stmt->execute([$olderThanDays]);
        return $stmt->rowCount();
    }
}
