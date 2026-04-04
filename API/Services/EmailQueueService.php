<?php
declare(strict_types=1);

namespace API\Services;

use PDO;

/**
 * EmailQueueService — File d'attente d'emails avec retry et templates éditables.
 *
 * Utilise la table email_templates pour les templates HTML éditables
 * et email_log pour le suivi d'envoi avec retry automatique.
 */
class EmailQueueService
{
    private PDO $pdo;
    private EmailService $emailService;

    /** Max tentatives d'envoi par email */
    private const MAX_ATTEMPTS = 3;

    /** Backoff exponentiel : délai en secondes entre les tentatives */
    private const BACKOFF_BASE = 60; // 1min, 4min, 9min

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->emailService = new EmailService($pdo);
    }

    // ─── Templates éditables ────────────────────────────────────────

    /**
     * Retourne tous les templates d'email.
     */
    public function getTemplates(): array
    {
        try {
            return $this->pdo->query("SELECT * FROM email_templates ORDER BY `key`")->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Retourne un template par sa clé.
     */
    public function getTemplate(string $key): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM email_templates WHERE `key` = ? AND actif = 1");
        $stmt->execute([$key]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Met à jour un template.
     */
    public function updateTemplate(string $key, string $subject, string $htmlBody): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE email_templates SET subject = ?, html_body = ?, updated_at = NOW() WHERE `key` = ?"
        );
        return $stmt->execute([$subject, $htmlBody, $key]);
    }

    /**
     * Rend un template avec les variables fournies.
     */
    public function renderTemplate(string $key, array $variables = []): ?array
    {
        $tpl = $this->getTemplate($key);
        if (!$tpl) return null;

        $subject = $tpl['subject'];
        $body = $tpl['html_body'];

        foreach ($variables as $k => $v) {
            $placeholder = '{{' . $k . '}}';
            $subject = str_replace($placeholder, (string) $v, $subject);
            $body = str_replace($placeholder, htmlspecialchars((string) $v), $body);
        }

        return ['subject' => $subject, 'body' => $body];
    }

    // ─── Queue d'envoi ──────────────────────────────────────────────

    /**
     * Ajoute un email à la file d'attente.
     */
    public function queue(string $to, string $subject, string $htmlBody, ?string $templateKey = null): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO email_log (to_address, subject, template_key, status, attempts, created_at)
             VALUES (?, ?, ?, 'pending', 0, NOW())"
        );
        $stmt->execute([$to, $subject, $templateKey]);
        $id = (int) $this->pdo->lastInsertId();

        // Stocker le body dans un fichier temporaire (pas en base pour les gros emails)
        $bodyFile = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/storage/email_queue/' . $id . '.html';
        $dir = dirname($bodyFile);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        file_put_contents($bodyFile, $htmlBody);

        return $id;
    }

    /**
     * Enqueue un email basé sur un template avec variables.
     */
    public function queueTemplate(string $to, string $templateKey, array $variables = []): ?int
    {
        $rendered = $this->renderTemplate($templateKey, $variables);
        if (!$rendered) return null;

        return $this->queue($to, $rendered['subject'], $rendered['body'], $templateKey);
    }

    /**
     * Envoie en masse à une liste d'adresses avec un template.
     */
    public function queueBulk(array $recipients, string $templateKey, array $variables = []): int
    {
        $count = 0;
        foreach ($recipients as $to) {
            if ($this->queueTemplate($to, $templateKey, $variables)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Traite la file d'attente (à appeler depuis worker.php).
     * Retourne le nombre d'emails envoyés.
     */
    public function processQueue(int $batchSize = 20): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM email_log
             WHERE status = 'pending' AND attempts < ?
             ORDER BY created_at ASC
             LIMIT ?"
        );
        $stmt->execute([self::MAX_ATTEMPTS, $batchSize]);
        $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sent = 0;
        foreach ($emails as $email) {
            // Vérifier le backoff
            if ($email['attempts'] > 0) {
                $delay = self::BACKOFF_BASE * ($email['attempts'] ** 2);
                $lastAttempt = strtotime($email['sent_at'] ?? $email['created_at']);
                if (time() - $lastAttempt < $delay) {
                    continue; // Pas encore le moment de retry
                }
            }

            // Charger le body depuis le fichier
            $bodyFile = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2))
                . '/storage/email_queue/' . $email['id'] . '.html';
            $body = file_exists($bodyFile) ? file_get_contents($bodyFile) : '';

            if (empty($body)) {
                $this->markFailed($email['id'], 'Email body file missing');
                continue;
            }

            // Tenter l'envoi
            $ok = $this->emailService->send($email['to_address'], $email['subject'], $body);

            if ($ok) {
                $this->markSent($email['id']);
                @unlink($bodyFile); // Nettoyer le fichier
                $sent++;
            } else {
                $attempts = $email['attempts'] + 1;
                if ($attempts >= self::MAX_ATTEMPTS) {
                    $this->markFailed($email['id'], 'Max attempts reached');
                } else {
                    $this->pdo->prepare(
                        "UPDATE email_log SET attempts = ?, sent_at = NOW() WHERE id = ?"
                    )->execute([$attempts, $email['id']]);
                }
            }
        }

        return $sent;
    }

    /**
     * Retourne l'historique d'envoi.
     */
    public function getLog(int $limit = 100, ?string $status = null): array
    {
        $sql = "SELECT * FROM email_log";
        $params = [];
        if ($status) {
            $sql .= " WHERE status = ?";
            $params[] = $status;
        }
        $sql .= " ORDER BY created_at DESC LIMIT ?";
        $params[] = $limit;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Statistiques d'envoi.
     */
    public function getStats(): array
    {
        try {
            $row = $this->pdo->query(
                "SELECT
                    SUM(status = 'sent') as sent,
                    SUM(status = 'pending') as pending,
                    SUM(status = 'failed') as failed
                 FROM email_log
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
            )->fetch(PDO::FETCH_ASSOC);
            return [
                'sent_30d' => (int) ($row['sent'] ?? 0),
                'pending' => (int) ($row['pending'] ?? 0),
                'failed_30d' => (int) ($row['failed'] ?? 0),
            ];
        } catch (\Throwable $e) {
            return ['sent_30d' => 0, 'pending' => 0, 'failed_30d' => 0];
        }
    }

    // ─── Nettoyage ──────────────────────────────────────────────────

    /**
     * Supprime les logs et fichiers d'emails envoyés > 30 jours.
     */
    public function cleanup(): int
    {
        // Supprimer les fichiers body
        $stmt = $this->pdo->query(
            "SELECT id FROM email_log WHERE status IN ('sent','failed') AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $basePath = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/storage/email_queue/';
        foreach ($ids as $id) {
            @unlink($basePath . $id . '.html');
        }

        $del = $this->pdo->prepare(
            "DELETE FROM email_log WHERE status IN ('sent','failed') AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        $del->execute();
        return $del->rowCount();
    }

    // ─── Helpers privés ─────────────────────────────────────────────

    private function markSent(int $id): void
    {
        $this->pdo->prepare(
            "UPDATE email_log SET status = 'sent', attempts = attempts + 1, sent_at = NOW() WHERE id = ?"
        )->execute([$id]);
    }

    private function markFailed(int $id, string $error): void
    {
        $this->pdo->prepare(
            "UPDATE email_log SET status = 'failed', error = ?, attempts = attempts + 1 WHERE id = ?"
        )->execute([$error, $id]);
    }
}
