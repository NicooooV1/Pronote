<?php

declare(strict_types=1);

namespace Bulletins\Widgets;

use API\Contracts\WidgetDataProvider;

class BulletinWidgetProvider implements WidgetDataProvider
{
    public function getData(int $userId, string $userType, ?array $config = null): array
    {
        $pdo = app('db')?->getConnection();
        if (!$pdo) {
            return ['bulletins' => []];
        }

        $limit = min(5, max(1, (int) ($config['limit'] ?? 3)));

        if ($userType === 'eleve') {
            $stmt = $pdo->prepare(
                "SELECT b.id, b.periode, p.nom AS periode_nom,
                        b.moyenne_generale, b.appreciation_generale, b.statut
                 FROM bulletins b
                 LEFT JOIN periodes p ON p.id = b.periode_id
                 WHERE b.eleve_id = ?
                 ORDER BY b.created_at DESC
                 LIMIT ?"
            );
            $stmt->execute([$userId, $limit]);
        } elseif ($userType === 'parent') {
            $childId = $_SESSION['selected_child_id'] ?? null;
            if (!$childId) {
                return ['bulletins' => []];
            }
            $stmt = $pdo->prepare(
                "SELECT b.id, b.periode, p.nom AS periode_nom,
                        b.moyenne_generale, b.appreciation_generale, b.statut
                 FROM bulletins b
                 LEFT JOIN periodes p ON p.id = b.periode_id
                 WHERE b.eleve_id = ?
                 ORDER BY b.created_at DESC
                 LIMIT ?"
            );
            $stmt->execute([$childId, $limit]);
        } else {
            return ['bulletins' => []];
        }

        return ['bulletins' => $stmt->fetchAll(\PDO::FETCH_ASSOC)];
    }

    public function getRefreshInterval(): int
    {
        return 3600;
    }
}
