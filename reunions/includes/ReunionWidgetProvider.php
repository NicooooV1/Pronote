<?php

declare(strict_types=1);

namespace Reunions\Widgets;

use API\Contracts\WidgetDataProvider;

class ReunionWidgetProvider implements WidgetDataProvider
{
    public function getData(int $userId, string $userType, ?array $config = null): array
    {
        $pdo = app('db')?->getConnection();
        if (!$pdo) {
            return ['reunions' => [], 'count' => 0];
        }

        $limit = min(10, max(1, (int) ($config['limit'] ?? 5)));

        if ($userType === 'parent') {
            // Prochaines réunions avec des réservations pour ce parent
            $stmt = $pdo->prepare(
                "SELECT r.titre, r.date_reunion, r.lieu,
                        rc.heure_debut, rc.heure_fin
                 FROM reunion_reservations rr
                 JOIN reunion_creneaux rc ON rc.id = rr.creneau_id
                 JOIN reunions r ON r.id = rc.reunion_id
                 WHERE rr.parent_id = ? AND r.date_reunion >= CURDATE()
                 ORDER BY r.date_reunion ASC, rc.heure_debut ASC
                 LIMIT ?"
            );
            $stmt->execute([$userId, $limit]);
        } elseif ($userType === 'professeur') {
            $stmt = $pdo->prepare(
                "SELECT r.titre, r.date_reunion, r.lieu,
                        COUNT(rc.id) AS nb_creneaux
                 FROM reunions r
                 LEFT JOIN reunion_creneaux rc ON rc.reunion_id = r.id
                 WHERE r.organisateur_id = ? AND r.organisateur_type = 'professeur'
                   AND r.date_reunion >= CURDATE()
                 GROUP BY r.id
                 ORDER BY r.date_reunion ASC
                 LIMIT ?"
            );
            $stmt->execute([$userId, $limit]);
        } else {
            $stmt = $pdo->prepare(
                "SELECT r.titre, r.date_reunion, r.lieu
                 FROM reunions r
                 WHERE r.date_reunion >= CURDATE()
                 ORDER BY r.date_reunion ASC
                 LIMIT ?"
            );
            $stmt->execute([$limit]);
        }

        $reunions = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return ['reunions' => $reunions, 'count' => count($reunions)];
    }

    public function getRefreshInterval(): int
    {
        return 1800;
    }
}
