<?php
/**
 * Rate Limiting — Messagerie Fronote
 * Limite le nombre d'actions par utilisateur par fenêtre de temps.
 *
 * Cette classe est spécifique à la messagerie (user_id + action_type, stockage par ligne).
 * Elle coexiste avec API\Security\RateLimiter (key+IP based, compteur unique) qui
 * gère les limites génériques de l'API. Aucun conflit de namespace.
 */

require_once __DIR__ . '/../config/config.php';

if (class_exists('RateLimiter', false)) {
    return; // Déjà chargée (évite les doublons si inclus plusieurs fois)
}

class RateLimiter {

    /** Limites par type d'action : [max_tentatives, fenêtre_en_secondes] */
    const LIMITS = [
        'send_message'    => [10, 60],   // 10 messages / minute
        'send_announcement' => [5, 60],  // 5 annonces / minute
        'api_request'     => [60, 60],   // 60 requêtes API / minute
        'file_upload'     => [10, 60],   // 10 uploads / minute
        'search'          => [20, 60],   // 20 recherches / minute
    ];

    /** @var bool Indique si la table rate_limits existe */
    private static bool $tableVerified = false;

    /**
     * Vérifie que la table rate_limits existe (une seule fois par requête).
     */
    private static function ensureTable(): bool {
        if (self::$tableVerified) return true;
        global $pdo;
        if (!isset($pdo)) return false;
        try {
            $pdo->query("SELECT 1 FROM rate_limits LIMIT 0");
            self::$tableVerified = true;
            return true;
        } catch (PDOException $e) {
            error_log("RateLimiter: table rate_limits manquante — " . $e->getMessage());
            return false;
        }
    }

    /**
     * Vérifie si l'action est autorisée (rate limit non dépassé)
     */
    public static function check(int $userId, string $userType, string $actionType): bool {
        global $pdo;
        if (!isset($pdo) || !self::ensureTable()) return false;

        $limits = self::LIMITS[$actionType] ?? [60, 60];
        [$maxAttempts, $windowSeconds] = $limits;

        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM rate_limits
                WHERE user_id = ? AND user_type = ? AND action_type = ?
                AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
            ");
            $stmt->execute([$userId, $userType, $actionType, $windowSeconds]);
            return (int) $stmt->fetchColumn() < $maxAttempts;
        } catch (PDOException $e) {
            error_log("RateLimiter check error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Enregistre une tentative d'action
     */
    public static function hit(int $userId, string $userType, string $actionType): void {
        global $pdo;
        if (!isset($pdo) || !self::ensureTable()) return;

        try {
            $stmt = $pdo->prepare("
                INSERT INTO rate_limits (user_id, user_type, action_type, attempted_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$userId, $userType, $actionType]);
        } catch (PDOException $e) {
            error_log("RateLimiter hit error: " . $e->getMessage());
        }
    }

    /**
     * Vérifie le rate limit et enregistre la tentative de façon atomique.
     * INSERT d'abord, puis COUNT dans une transaction pour éviter la race condition.
     *
     * @return bool true si autorisé
     */
    public static function attempt(int $userId, string $userType, string $actionType): bool {
        global $pdo;
        if (!isset($pdo) || !self::ensureTable()) return false;

        $limits = self::LIMITS[$actionType] ?? [60, 60];
        [$maxAttempts, $windowSeconds] = $limits;

        try {
            $pdo->beginTransaction();

            // 1. Insérer la tentative
            $ins = $pdo->prepare("
                INSERT INTO rate_limits (user_id, user_type, action_type, attempted_at)
                VALUES (?, ?, ?, NOW())
            ");
            $ins->execute([$userId, $userType, $actionType]);

            // 2. Compter les tentatives dans la fenêtre (incluant celle-ci)
            // FOR UPDATE verrouille les lignes lues pour bloquer les lectures concurrentes
            $cnt = $pdo->prepare("
                SELECT COUNT(*) FROM rate_limits
                WHERE user_id = ? AND user_type = ? AND action_type = ?
                AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
                FOR UPDATE
            ");
            $cnt->execute([$userId, $userType, $actionType, $windowSeconds]);
            $count = (int) $cnt->fetchColumn();

            if ($count > $maxAttempts) {
                // Limite dépassée → rollback l'INSERT
                $pdo->rollBack();
                return false;
            }

            $pdo->commit();
            return true;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log("RateLimiter attempt error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Refuse la requête avec un code 429 si le rate limit est dépassé.
     * Arrête l'exécution du script.
     *
     * @param int    $userId     ID utilisateur
     * @param string $userType   Type utilisateur
     * @param string $actionType Type d'action
     */
    public static function enforce(int $userId, string $userType, string $actionType): void {
        if (!self::attempt($userId, $userType, $actionType)) {
            http_response_code(429);
            header('Content-Type: application/json');
            $limits = self::LIMITS[$actionType] ?? [60, 60];
            echo json_encode([
                'success' => false,
                'error' => 'Trop de requêtes. Veuillez patienter avant de réessayer.',
                'retry_after' => $limits[1]
            ]);
            exit;
        }
    }

    /**
     * Nettoie les anciennes entrées (à appeler périodiquement)
     */
    public static function cleanup(): void {
        global $pdo;
        if (!isset($pdo)) return;

        try {
            $pdo->exec("DELETE FROM rate_limits WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 2 HOUR)");
        } catch (PDOException $e) {
            error_log("RateLimiter cleanup error: " . $e->getMessage());
        }
    }

    /**
     * Retourne les informations de rate limit restantes pour un utilisateur
     *
     * @return array ['remaining' => int, 'limit' => int, 'reset_in' => int]
     */
    public static function getInfo(int $userId, string $userType, string $actionType): array {
        global $pdo;
        $limits = self::LIMITS[$actionType] ?? [60, 60];
        [$maxAttempts, $windowSeconds] = $limits;

        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count, MIN(attempted_at) as oldest
                FROM rate_limits
                WHERE user_id = ? AND user_type = ? AND action_type = ?
                AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
            ");
            $stmt->execute([$userId, $userType, $actionType, $windowSeconds]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $count = (int) $row['count'];
            $resetIn = $row['oldest'] ? $windowSeconds - (time() - strtotime($row['oldest'])) : 0;

            return [
                'remaining' => max(0, $maxAttempts - $count),
                'limit' => $maxAttempts,
                'reset_in' => max(0, $resetIn)
            ];
        } catch (PDOException $e) {
            return ['remaining' => $maxAttempts, 'limit' => $maxAttempts, 'reset_in' => 0];
        }
    }
}
