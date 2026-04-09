<?php
declare(strict_types=1);

namespace API\Services;

use PDO;

/**
 * ActivityFeedService — Cross-module activity timeline.
 *
 * Aggregates recent actions from the audit_log table to provide
 * a unified activity feed for users and administrators.
 */
class ActivityFeedService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get the activity feed for a user.
     *
     * @return array List of recent activities across all modules
     */
    public function getFeed(int $userId, string $userType, int $limit = 20, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, action, entity_type, entity_id, severity,
                   request_method, request_uri, created_at,
                   CASE
                       WHEN entity_type IN ('note','evaluation') THEN 'pedagogie'
                       WHEN entity_type IN ('absence','appel','incident') THEN 'vie_scolaire'
                       WHEN entity_type IN ('message','annonce','reunion') THEN 'communication'
                       WHEN entity_type IN ('facture','inscription') THEN 'administration'
                       ELSE 'systeme'
                   END AS category
            FROM audit_log
            WHERE user_id = :uid AND user_type = :utype
            ORDER BY created_at DESC
            LIMIT :lim OFFSET :off
        ");
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':utype', $userType);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get recent activity across the whole establishment.
     */
    public function getGlobalFeed(int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare("
            SELECT a.id, a.action, a.entity_type, a.entity_id, a.severity,
                   a.user_id, a.user_type, a.created_at
            FROM audit_log a
            WHERE a.severity IN ('INFO', 'WARNING', 'CRITICAL')
            ORDER BY a.created_at DESC
            LIMIT :lim OFFSET :off
        ");
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get activity summary counts by category for the last N days.
     */
    public function getSummary(int $days = 7): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                CASE
                    WHEN entity_type IN ('note','evaluation','competence') THEN 'pedagogie'
                    WHEN entity_type IN ('absence','appel','incident','sanction') THEN 'vie_scolaire'
                    WHEN entity_type IN ('message','annonce','reunion') THEN 'communication'
                    WHEN entity_type IN ('facture','inscription','diplome') THEN 'administration'
                    ELSE 'systeme'
                END AS category,
                COUNT(*) AS count,
                DATE(created_at) AS date
            FROM audit_log
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
            GROUP BY category, DATE(created_at)
            ORDER BY date DESC, count DESC
        ");
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
