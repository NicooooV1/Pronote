<?php
declare(strict_types=1);

namespace API\Services;

use PDO;

/**
 * SmsService — Envoi de SMS multi-provider.
 *
 * Providers supportés : twilio, ovh, free_mobile
 * Configuration stockée dans la table sms_config.
 * Quota mensuel avec reset automatique.
 */
class SmsService
{
    private PDO $pdo;
    private ?array $config = null;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Vérifie si le service SMS est activé et configuré.
     */
    public function isEnabled(): bool
    {
        $config = $this->getConfig();
        return $config && !empty($config['actif']) && !empty($config['api_key']);
    }

    /**
     * Envoie un SMS.
     */
    public function send(string $recipient, string $message): array
    {
        $config = $this->getConfig();
        if (!$config || !$config['actif']) {
            return ['success' => false, 'error' => 'Service SMS non activé.'];
        }

        // Vérifier le quota
        $this->resetQuotaIfNeeded($config);
        if ($config['used_this_month'] >= $config['monthly_quota']) {
            return ['success' => false, 'error' => 'Quota SMS mensuel atteint.'];
        }

        // Normaliser le numéro
        $recipient = $this->normalizePhone($recipient);
        if (!$recipient) {
            return ['success' => false, 'error' => 'Numéro de téléphone invalide.'];
        }

        // Tronquer le message (160 chars SMS standard)
        $message = mb_substr($message, 0, 160);

        // Dispatch au provider
        $result = match ($config['provider']) {
            'twilio' => $this->sendViaTwilio($config, $recipient, $message),
            'ovh' => $this->sendViaOvh($config, $recipient, $message),
            'free_mobile' => $this->sendViaFreeMobile($config, $recipient, $message),
            default => ['success' => false, 'error' => 'Provider SMS inconnu: ' . $config['provider']],
        };

        // Logger
        $this->logSms($recipient, $message, $result);

        // Incrémenter le quota
        if ($result['success']) {
            $this->pdo->prepare("UPDATE sms_config SET used_this_month = used_this_month + 1 WHERE id = ?")->execute([$config['id']]);
        }

        return $result;
    }

    /**
     * Envoie un SMS à plusieurs destinataires.
     */
    public function sendBulk(array $recipients, string $message): array
    {
        $results = ['sent' => 0, 'failed' => 0, 'errors' => []];
        foreach ($recipients as $phone) {
            $r = $this->send($phone, $message);
            if ($r['success']) {
                $results['sent']++;
            } else {
                $results['failed']++;
                $results['errors'][] = $phone . ': ' . ($r['error'] ?? 'unknown');
            }
        }
        return $results;
    }

    /**
     * Retourne les statistiques d'utilisation.
     */
    public function getStats(): array
    {
        $config = $this->getConfig();
        $logStats = $this->pdo->query(
            "SELECT status, COUNT(*) as cnt FROM sms_log WHERE sent_at >= DATE_FORMAT(NOW(), '%Y-%m-01') GROUP BY status"
        )->fetchAll(PDO::FETCH_KEY_PAIR);

        return [
            'enabled' => $config && $config['actif'],
            'provider' => $config['provider'] ?? 'none',
            'quota' => $config['monthly_quota'] ?? 0,
            'used' => $config['used_this_month'] ?? 0,
            'sent_this_month' => (int) ($logStats['sent'] ?? 0),
            'failed_this_month' => (int) ($logStats['failed'] ?? 0),
        ];
    }

    /**
     * Retourne l'historique d'envoi.
     */
    public function getLog(int $limit = 50): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM sms_log ORDER BY sent_at DESC LIMIT ?");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── Providers ──────────────────────────────────────────────────

    private function sendViaTwilio(array $config, string $to, string $body): array
    {
        $sid = $config['api_key'];
        $token = $config['api_secret'];
        $from = $config['sender_name'] ?: 'Fronote';

        $url = "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json";
        $data = http_build_query(['To' => $to, 'From' => $from, 'Body' => $body]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_USERPWD => "{$sid}:{$token}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code >= 200 && $code < 300) {
            $json = json_decode($response, true);
            return ['success' => true, 'provider_id' => $json['sid'] ?? ''];
        }

        return ['success' => false, 'error' => 'Twilio HTTP ' . $code];
    }

    private function sendViaOvh(array $config, string $to, string $body): array
    {
        $url = "https://www.ovh.com/cgi-bin/sms/http2sms.cgi";
        $params = http_build_query([
            'account' => $config['api_key'],
            'login' => $config['sender_name'],
            'password' => $config['api_secret'],
            'from' => $config['sender_name'] ?: 'Fronote',
            'to' => $to,
            'message' => $body,
        ]);

        $response = @file_get_contents($url . '?' . $params);
        if ($response !== false && str_contains($response, 'OK')) {
            return ['success' => true, 'provider_id' => ''];
        }

        return ['success' => false, 'error' => 'OVH SMS error: ' . ($response ?: 'no response')];
    }

    private function sendViaFreeMobile(array $config, string $to, string $body): array
    {
        $url = 'https://smsapi.free-mobile.fr/sendmsg';
        $params = http_build_query([
            'user' => $config['api_key'],
            'pass' => $config['api_secret'],
            'msg' => $body,
        ]);

        $code = 0;
        $ctx = stream_context_create(['http' => ['timeout' => 10, 'ignore_errors' => true]]);
        $response = @file_get_contents($url . '?' . $params, false, $ctx);
        if (isset($http_response_header[0])) {
            preg_match('/(\d{3})/', $http_response_header[0], $m);
            $code = (int) ($m[1] ?? 0);
        }

        return $code === 200
            ? ['success' => true, 'provider_id' => '']
            : ['success' => false, 'error' => 'Free Mobile HTTP ' . $code];
    }

    // ─── Helpers ────────────────────────────────────────────────────

    private function getConfig(): ?array
    {
        if ($this->config !== null) return $this->config;

        try {
            $stmt = $this->pdo->query("SELECT * FROM sms_config LIMIT 1");
            $this->config = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (\Throwable $e) {
            $this->config = null;
        }
        return $this->config;
    }

    private function resetQuotaIfNeeded(array &$config): void
    {
        $resetAt = $config['quota_reset_at'] ?? null;
        $firstOfMonth = date('Y-m-01');
        if (!$resetAt || $resetAt < $firstOfMonth) {
            $this->pdo->prepare("UPDATE sms_config SET used_this_month = 0, quota_reset_at = ? WHERE id = ?")
                ->execute([$firstOfMonth, $config['id']]);
            $config['used_this_month'] = 0;
        }
    }

    private function normalizePhone(string $phone): ?string
    {
        $phone = preg_replace('/[^+0-9]/', '', $phone);
        if (strlen($phone) < 8) return null;
        // Convertir 06... → +336...
        if (str_starts_with($phone, '0') && strlen($phone) === 10) {
            $phone = '+33' . substr($phone, 1);
        }
        return $phone;
    }

    private function logSms(string $recipient, string $message, array $result): void
    {
        try {
            $this->pdo->prepare(
                "INSERT INTO sms_log (recipient, message, status, provider_id, error, sent_at)
                 VALUES (?, ?, ?, ?, ?, NOW())"
            )->execute([
                $recipient,
                $message,
                $result['success'] ? 'sent' : 'failed',
                $result['provider_id'] ?? null,
                $result['error'] ?? null,
            ]);
        } catch (\Throwable $e) { /* silent */ }
    }
}
