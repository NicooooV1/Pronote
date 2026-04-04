<?php

declare(strict_types=1);

namespace Agenda\Widgets;

use API\Contracts\WidgetDataProvider;

class AgendaWidgetProvider implements WidgetDataProvider
{
    public function getData(int $userId, string $userType, ?array $config = null): array
    {
        $pdo = app('db')?->getConnection();
        if (!$pdo) {
            return ['events' => []];
        }

        $limit = (int) ($config['limit'] ?? 5);
        $limit = min(20, max(1, $limit));

        $stmt = $pdo->prepare(
            'SELECT id, titre, date_debut, date_fin, type_evenement, lieu
             FROM evenements
             WHERE statut = \'actif\'
               AND date_debut >= NOW()
             ORDER BY date_debut ASC
             LIMIT ?'
        );
        $stmt->execute([$limit]);

        return ['events' => $stmt->fetchAll(\PDO::FETCH_ASSOC)];
    }

    public function getRefreshInterval(): int
    {
        return 600;
    }
}
