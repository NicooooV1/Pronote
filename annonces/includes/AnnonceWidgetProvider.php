<?php

declare(strict_types=1);

namespace Annonces\Widgets;

use API\Contracts\WidgetDataProvider;

class AnnonceWidgetProvider implements WidgetDataProvider
{
    public function getData(int $userId, string $userType, ?array $config = null): array
    {
        $pdo = app('db')?->getConnection();
        if (!$pdo) {
            return ['annonces' => []];
        }

        $limit = (int) ($config['limit'] ?? 5);
        $limit = min(20, max(1, $limit));

        $stmt = $pdo->prepare(
            'SELECT id, titre, type, epingle, date_publication
             FROM annonces
             WHERE publie = 1
               AND (date_expiration IS NULL OR date_expiration > NOW())
             ORDER BY epingle DESC, date_publication DESC
             LIMIT ?'
        );
        $stmt->execute([$limit]);

        return ['annonces' => $stmt->fetchAll(\PDO::FETCH_ASSOC)];
    }

    public function getRefreshInterval(): int
    {
        return 300;
    }
}
