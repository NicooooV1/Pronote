<?php
/**
 * Rate Limiting — Messagerie Pronote
 * Limite le nombre d'actions par utilisateur par fenêtre de temps.
 */

require_once __DIR__ . '/../config/config.php';

class RateLimiter {

    /** Limites par type d'action : [max_tentatives, fenêtre_en_secondes] */
    const LIMITS = [
        'send_message'    => [10, 60],   // 10 messages / minute
        'send_announcement' => [5, 60],  // 5 annonces / minute
        'api_request'     => [60, 60],   // 60 requêtes API / minute
        'file_upload'     => [10, 60],   // 10 uploads / minute
        'search'          => [20, 60],   // 20 recherches / minute
    ];

    /**
     * Vérifie si l'action est autorisée (rate limit non dépassé)
     *
     * @param int    $userId     ID utilisateur
     * @param string $userType   Type utilisateur
     * @param string $actionType Type d'action (clé de LIMITS)
     * @return bool  true si l'action est autorisée
     */
    public static function check(int $userId, string $userType, string $actionType): bool {
        global $pdo;
        if (!isset($pdo)) return true; // Pas de DB = pas de limite

        $limits = self::LIMITS[$actionType] ?? [60, 60];
        [$maxAttempts, $windowSeconds] = $limits;

        try {
            // Compter les tentatives récentes
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM rate_limits
                WHERE user_id = ? AND user_type = ? AND action_type = ?
                AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
            ");
            $stmt->execute([$userId, $userType, $actionType, $windowSeconds]);
            $count = (int) $stmt->fetchColumn();

            return $count < $maxAttempts;
        } catch (PDOException $e) {
            // En cas d'erreur (table manquante, etc.), autoriser l'action
            error_log("RateLimiter check error: " . $e->getMessage());
            return true;
        }
    }

    /**
     * Enregistre une tentative d'action
     *
     * @param int    $userId     ID utilisateur
     * @param string $userType   Type utilisateur
     * @param string $actionType Type d'action
     */
    public static function hit(int $userId, string $userType, string $actionType): void {
        global $pdo;
        if (!isset($pdo)) return;

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
     * Vérifie le rate limit et enregistre la tentative en une seule opération.
     * Retourne false si la limite est dépassée.
     *
     * @param int    $userId     ID utilisateur
     * @param string $userType   Type utilisateur
     * @param string $actionType Type d'action
     * @return bool  true si autorisé
     */
    public static function attempt(int $userId, string $userType, string $actionType): bool {
        if (!self::check($userId, $userType, $actionType)) {
            return false;
        }
        self::hit($userId, $userType, $actionType);
        return true;
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
