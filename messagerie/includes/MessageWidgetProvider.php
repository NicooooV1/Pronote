<?php

declare(strict_types=1);

namespace Messagerie\Widgets;

use API\Contracts\WidgetDataProvider;

class MessageWidgetProvider implements WidgetDataProvider
{
    public function getData(int $userId, string $userType, ?array $config = null): array
    {
        $pdo = app('db')?->getConnection();
        if (!$pdo) {
            return ['conversations' => [], 'unread_total' => 0];
        }

        $limit = min(10, max(1, (int) ($config['limit'] ?? 5)));

        $stmt = $pdo->prepare(
            "SELECT c.id, c.subject, c.updated_at, cp.unread_count,
                    m.body AS last_body, m.sender_type AS last_sender_type
             FROM conversation_participants cp
             JOIN conversations c ON c.id = cp.conversation_id
             LEFT JOIN messages m ON m.id = c.last_message_id
             WHERE cp.user_id = ? AND cp.user_type = ?
               AND cp.is_deleted = 0 AND cp.is_archived = 0
               AND cp.unread_count > 0
             ORDER BY c.updated_at DESC
             LIMIT ?"
        );
        $stmt->execute([$userId, $userType, $limit]);
        $conversations = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $stmtTotal = $pdo->prepare(
            "SELECT COALESCE(SUM(unread_count), 0)
             FROM conversation_participants
             WHERE user_id = ? AND user_type = ? AND is_deleted = 0 AND is_archived = 0"
        );
        $stmtTotal->execute([$userId, $userType]);
        $unreadTotal = (int) $stmtTotal->fetchColumn();

        return [
            'conversations' => $conversations,
            'unread_total' => $unreadTotal,
        ];
    }

    public function getRefreshInterval(): int
    {
        return 60;
    }
}
