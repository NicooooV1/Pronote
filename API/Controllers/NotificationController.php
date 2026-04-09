<?php
declare(strict_types=1);

namespace API\Controllers;

/**
 * REST Controller for notifications.
 * GET  /API/notifications            — List user's notifications
 * POST /API/notifications/{id}/read  — Mark as read
 * POST /API/notifications/read-all   — Mark all as read
 */
class NotificationController extends BaseController
{
    public function index(): void
    {
        $user = $this->authenticate();
        [$page, $perPage, $offset] = $this->pagination();

        $userId = $user['id'];
        $userType = $user['type'] ?? $user['role'] ?? '';
        $unreadOnly = $this->query('unread') === '1';

        $where = 'WHERE n.user_id = ? AND n.user_type = ?';
        $params = [$userId, $userType];

        if ($unreadOnly) {
            $where .= ' AND n.is_read = 0';
        }

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM notifications n {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $params[] = $perPage;
        $params[] = $offset;
        $stmt = $this->pdo->prepare("
            SELECT n.id, n.type, n.title, n.message, n.link, n.is_read, n.created_at
            FROM notifications n
            {$where}
            ORDER BY n.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute($params);

        $this->paginated($stmt->fetchAll(\PDO::FETCH_ASSOC), $total, $page, $perPage);
    }

    public function markRead(int $id): void
    {
        $user = $this->authenticate();

        $stmt = $this->pdo->prepare("
            UPDATE notifications SET is_read = 1
            WHERE id = ? AND user_id = ? AND user_type = ?
        ");
        $stmt->execute([$id, $user['id'], $user['type'] ?? '']);

        $this->json(['marked' => $stmt->rowCount()]);
    }

    public function markAllRead(): void
    {
        $user = $this->authenticate();

        $stmt = $this->pdo->prepare("
            UPDATE notifications SET is_read = 1
            WHERE user_id = ? AND user_type = ? AND is_read = 0
        ");
        $stmt->execute([$user['id'], $user['type'] ?? '']);

        $this->json(['marked' => $stmt->rowCount()]);
    }
}
