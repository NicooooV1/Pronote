<?php
declare(strict_types=1);

namespace API\Services\Scolaire;

use PDO;

class SessionManagementService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Sessions actives avec résolution des noms.
     */
    public function getActiveSessions(): array
    {
        $stmt = $this->pdo->query("
            SELECT s.*,
                CASE
                    WHEN s.user_type = 'eleve' THEN (SELECT CONCAT(prenom,' ',nom) FROM eleves WHERE id = s.user_id)
                    WHEN s.user_type = 'professeur' THEN (SELECT CONCAT(prenom,' ',nom) FROM professeurs WHERE id = s.user_id)
                    WHEN s.user_type = 'parent' THEN (SELECT CONCAT(prenom,' ',nom) FROM parents WHERE id = s.user_id)
                    WHEN s.user_type = 'vie_scolaire' THEN (SELECT CONCAT(prenom,' ',nom) FROM vie_scolaire WHERE id = s.user_id)
                    WHEN s.user_type = 'administrateur' THEN (SELECT CONCAT(prenom,' ',nom) FROM administrateurs WHERE id = s.user_id)
                    ELSE 'Inconnu'
                END AS nom_complet
            FROM session_security s
            WHERE s.is_active = 1 AND s.expires_at > NOW()
            ORDER BY s.last_activity DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Tue une session spécifique.
     */
    public function killSession(string $sessionId): bool
    {
        $stmt = $this->pdo->prepare("UPDATE session_security SET is_active = 0, expires_at = NOW() WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Tue toutes les sessions d'un utilisateur.
     */
    public function killUserSessions(int $userId, string $userType): int
    {
        $stmt = $this->pdo->prepare("UPDATE session_security SET is_active = 0, expires_at = NOW() WHERE user_id = ? AND user_type = ?");
        $stmt->execute([$userId, $userType]);
        return $stmt->rowCount();
    }

    /**
     * Tue toutes les sessions sauf celle de l'admin courant.
     */
    public function killAllExcept(int $adminId): int
    {
        $stmt = $this->pdo->prepare("UPDATE session_security SET is_active = 0, expires_at = NOW() WHERE NOT (user_id = ? AND user_type = 'administrateur')");
        $stmt->execute([$adminId]);
        return $stmt->rowCount();
    }

    /**
     * Connexions suspectes : utilisateurs avec sessions depuis 2+ IPs distinctes.
     */
    public function getSuspiciousConnections(): array
    {
        $stmt = $this->pdo->query("
            SELECT user_id, user_type, COUNT(DISTINCT ip_address) AS ip_count, GROUP_CONCAT(DISTINCT ip_address) AS ips
            FROM session_security
            WHERE is_active = 1 AND expires_at > NOW()
            GROUP BY user_id, user_type
            HAVING ip_count > 1
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Statistiques des sessions actives.
     */
    public function getStats(): array
    {
        $sessions = $this->getActiveSessions();
        $uniqueUsers = count(array_unique(array_map(
            fn($s) => $s['user_type'] . '_' . $s['user_id'],
            $sessions
        )));

        return [
            'total_active' => count($sessions),
            'unique_users' => $uniqueUsers,
        ];
    }
}
