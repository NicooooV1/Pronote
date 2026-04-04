<?php
declare(strict_types=1);

namespace API\Security;

use PDO;

/**
 * IpFirewall — Protection brute-force avec blocage IP automatique.
 *
 * Fonctionnalités :
 *  - Auto-ban après N échecs d'authentification (configurable)
 *  - Whitelist/Blacklist manuelle
 *  - Bans temporaires (1h, 24h) ou permanents
 *  - Nettoyage automatique des bans expirés
 *
 * Usage :
 *   $fw = new IpFirewall($pdo);
 *   if ($fw->isBlocked($ip)) { /* 403 */ }
 *   $fw->recordFailedAttempt($ip);  // après un login raté
 */
class IpFirewall
{
    private PDO $pdo;

    /** Nombre d'échecs avant auto-ban */
    private int $maxAttempts;

    /** Fenêtre de comptage en secondes */
    private int $windowSeconds;

    /** Durée du ban auto en secondes (1h par défaut, doublé à chaque récidive) */
    private int $banDuration;

    public function __construct(PDO $pdo, int $maxAttempts = 10, int $windowSeconds = 300, int $banDuration = 3600)
    {
        $this->pdo = $pdo;
        $this->maxAttempts = $maxAttempts;
        $this->windowSeconds = $windowSeconds;
        $this->banDuration = $banDuration;
    }

    /**
     * Vérifie si une IP est bloquée.
     */
    public function isBlocked(?string $ip = null): bool
    {
        $ip = $ip ?? $this->getClientIp();
        if (!$ip) return false;

        try {
            $stmt = $this->pdo->prepare(
                "SELECT id FROM ip_blocklist
                 WHERE ip = ? AND (expires_at IS NULL OR expires_at > NOW())
                 LIMIT 1"
            );
            $stmt->execute([$ip]);
            return $stmt->rowCount() > 0;
        } catch (\Throwable $e) {
            return false; // Fail open plutôt que de bloquer tout le monde
        }
    }

    /**
     * Enregistre un échec d'authentification et auto-ban si seuil atteint.
     */
    public function recordFailedAttempt(?string $ip = null): void
    {
        $ip = $ip ?? $this->getClientIp();
        if (!$ip || $this->isWhitelisted($ip)) return;

        try {
            // Compter les échecs récents via rate_limits
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM api_rate_limits
                 WHERE ip = ? AND endpoint = 'auth_failure'
                 AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)"
            );
            $stmt->execute([$ip, $this->windowSeconds]);
            $count = (int) $stmt->fetchColumn();

            // Enregistrer cet échec
            $this->pdo->prepare(
                "INSERT INTO api_rate_limits (ip, endpoint, created_at) VALUES (?, 'auth_failure', NOW())"
            )->execute([$ip]);

            // Auto-ban si seuil atteint
            if ($count + 1 >= $this->maxAttempts) {
                $this->ban($ip, $this->banDuration, 'Auto-ban: ' . ($count + 1) . ' échecs en ' . $this->windowSeconds . 's', true);
            }
        } catch (\Throwable $e) {
            error_log('IpFirewall::recordFailedAttempt: ' . $e->getMessage());
        }
    }

    /**
     * Enregistre une tentative réussie (reset le compteur).
     */
    public function recordSuccess(?string $ip = null): void
    {
        $ip = $ip ?? $this->getClientIp();
        if (!$ip) return;

        try {
            $this->pdo->prepare(
                "DELETE FROM api_rate_limits WHERE ip = ? AND endpoint = 'auth_failure'"
            )->execute([$ip]);
        } catch (\Throwable $e) { /* silent */ }
    }

    // ─── Gestion manuelle ───────────────────────────────────────────

    /**
     * Bloque une IP manuellement.
     * @param int $duration Durée en secondes (0 = permanent)
     */
    public function ban(string $ip, int $duration = 0, string $reason = '', bool $auto = false): bool
    {
        try {
            $expiresAt = $duration > 0 ? date('Y-m-d H:i:s', time() + $duration) : null;
            $this->pdo->prepare(
                "INSERT INTO ip_blocklist (ip, reason, auto_blocked, blocked_at, expires_at)
                 VALUES (?, ?, ?, NOW(), ?)
                 ON DUPLICATE KEY UPDATE reason = VALUES(reason), expires_at = VALUES(expires_at), blocked_at = NOW()"
            )->execute([$ip, $reason, $auto ? 1 : 0, $expiresAt]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Débloque une IP.
     */
    public function unban(string $ip): bool
    {
        try {
            return $this->pdo->prepare("DELETE FROM ip_blocklist WHERE ip = ?")->execute([$ip]);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Vérifie si une IP est dans la whitelist.
     */
    public function isWhitelisted(string $ip): bool
    {
        // IPs locales toujours whitelistées
        $local = ['127.0.0.1', '::1', 'localhost'];
        if (in_array($ip, $local, true)) return true;

        // Whitelist configurable via .env
        $whitelist = getenv('IP_WHITELIST') ?: '';
        if ($whitelist) {
            $ips = array_map('trim', explode(',', $whitelist));
            if (in_array($ip, $ips, true)) return true;
        }

        return false;
    }

    /**
     * Retourne la liste des IPs bloquées.
     */
    public function getBlockedIps(): array
    {
        try {
            return $this->pdo->query(
                "SELECT * FROM ip_blocklist ORDER BY blocked_at DESC"
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Nettoyage des bans expirés et des vieilles entrées rate_limits.
     */
    public function cleanup(): int
    {
        $count = 0;
        try {
            $stmt = $this->pdo->prepare("DELETE FROM ip_blocklist WHERE expires_at IS NOT NULL AND expires_at < NOW()");
            $stmt->execute();
            $count += $stmt->rowCount();

            $stmt = $this->pdo->prepare("DELETE FROM api_rate_limits WHERE endpoint = 'auth_failure' AND created_at < DATE_SUB(NOW(), INTERVAL 1 DAY)");
            $stmt->execute();
            $count += $stmt->rowCount();
        } catch (\Throwable $e) { /* silent */ }

        return $count;
    }

    // ─── Helpers ────────────────────────────────────────────────────

    private function getClientIp(): ?string
    {
        // Support reverse proxy
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
            return $ips[0] ?? null;
        }
        return $_SERVER['REMOTE_ADDR'] ?? null;
    }
}
