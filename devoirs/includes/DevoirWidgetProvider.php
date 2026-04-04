<?php

declare(strict_types=1);

namespace Devoirs\Widgets;

use API\Contracts\WidgetDataProvider;

class DevoirWidgetProvider implements WidgetDataProvider
{
    public function getData(int $userId, string $userType, ?array $config = null): array
    {
        $pdo = app('db')?->getConnection();
        if (!$pdo) {
            return ['devoirs' => [], 'count' => 0];
        }

        $limit = min(20, max(1, (int) ($config['limit'] ?? 5)));

        if ($userType === 'eleve') {
            $stmt = $pdo->prepare(
                "SELECT d.id, d.titre, d.nom_matiere, d.date_rendu,
                        COALESCE(ds.fait, 0) AS fait
                 FROM devoirs d
                 LEFT JOIN devoirs_statuts_eleve ds ON ds.devoir_id = d.id AND ds.eleve_id = ?
                 WHERE d.classe = (SELECT classe FROM eleves WHERE id = ? LIMIT 1)
                   AND d.date_rendu >= CURDATE()
                 ORDER BY d.date_rendu ASC
                 LIMIT ?"
            );
            $stmt->execute([$userId, $userId, $limit]);
            $devoirs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return ['devoirs' => $devoirs, 'count' => count($devoirs)];
        }

        if ($userType === 'parent') {
            $childId = $_SESSION['selected_child_id'] ?? null;
            if (!$childId) {
                return ['devoirs' => [], 'count' => 0];
            }
            $stmt = $pdo->prepare(
                "SELECT d.id, d.titre, d.nom_matiere, d.date_rendu,
                        COALESCE(ds.fait, 0) AS fait
                 FROM devoirs d
                 LEFT JOIN devoirs_statuts_eleve ds ON ds.devoir_id = d.id AND ds.eleve_id = ?
                 WHERE d.classe = (SELECT classe FROM eleves WHERE id = ? LIMIT 1)
                   AND d.date_rendu >= CURDATE()
                 ORDER BY d.date_rendu ASC
                 LIMIT ?"
            );
            $stmt->execute([$childId, $childId, $limit]);
            $devoirs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return ['devoirs' => $devoirs, 'count' => count($devoirs)];
        }

        if ($userType === 'professeur') {
            $stmt = $pdo->prepare(
                "SELECT d.id, d.titre, d.nom_matiere, d.classe, d.date_rendu
                 FROM devoirs d
                 WHERE d.nom_professeur = (SELECT CONCAT(prenom, ' ', nom) FROM professeurs WHERE id = ? LIMIT 1)
                   AND d.date_rendu >= CURDATE()
                 ORDER BY d.date_rendu ASC
                 LIMIT ?"
            );
            $stmt->execute([$userId, $limit]);
            $devoirs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return ['devoirs' => $devoirs, 'count' => count($devoirs)];
        }

        return ['devoirs' => [], 'count' => 0];
    }

    public function getRefreshInterval(): int
    {
        return 300;
    }
}
