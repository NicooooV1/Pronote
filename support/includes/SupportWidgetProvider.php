<?php

declare(strict_types=1);

namespace Support\Widgets;

use API\Contracts\WidgetDataProvider;

class SupportWidgetProvider implements WidgetDataProvider
{
    public function getData(int $userId, string $userType, ?array $config = null): array
    {
        $pdo = app('db')?->getConnection();
        if (!$pdo) {
            return ['tickets' => [], 'count' => 0];
        }

        $limit = min(10, max(1, (int) ($config['limit'] ?? 5)));

        if (in_array($userType, ['administrateur', 'vie_scolaire'], true)) {
            // Admin : tickets ouverts de tous les utilisateurs
            $stmt = $pdo->prepare(
                "SELECT id, sujet, categorie, priorite, statut, created_at
                 FROM tickets_support
                 WHERE statut IN ('ouvert','en_cours')
                 ORDER BY FIELD(priorite, 'urgent', 'haute', 'normale', 'basse'), created_at DESC
                 LIMIT ?"
            );
            $stmt->execute([$limit]);
        } else {
            // Utilisateur : ses propres tickets
            $stmt = $pdo->prepare(
                "SELECT id, sujet, categorie, priorite, statut, created_at
                 FROM tickets_support
                 WHERE auteur_id = ? AND auteur_type = ?
                   AND statut IN ('ouvert','en_cours')
                 ORDER BY created_at DESC
                 LIMIT ?"
            );
            $stmt->execute([$userId, $userType, $limit]);
        }

        $tickets = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return ['tickets' => $tickets, 'count' => count($tickets)];
    }

    public function getRefreshInterval(): int
    {
        return 600;
    }
}
