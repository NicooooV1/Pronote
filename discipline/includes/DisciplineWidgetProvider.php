<?php

declare(strict_types=1);

namespace Discipline\Widgets;

use API\Contracts\WidgetDataProvider;

class DisciplineWidgetProvider implements WidgetDataProvider
{
    public function getData(int $userId, string $userType, ?array $config = null): array
    {
        $pdo = app('db')?->getConnection();
        if (!$pdo) {
            return ['incidents' => [], 'stats' => []];
        }

        $limit = min(10, max(1, (int) ($config['limit'] ?? 5)));

        if (in_array($userType, ['administrateur', 'vie_scolaire'], true)) {
            // Derniers incidents non traités
            $stmt = $pdo->prepare(
                "SELECT i.id, i.type_incident, i.gravite, i.date_incident, i.statut,
                        CONCAT(e.prenom, ' ', e.nom) AS eleve_nom, c.nom AS classe
                 FROM incidents i
                 JOIN eleves e ON e.id = i.eleve_id
                 LEFT JOIN classes c ON c.id = i.classe_id
                 WHERE i.statut IN ('signale','en_traitement')
                 ORDER BY i.date_incident DESC
                 LIMIT ?"
            );
            $stmt->execute([$limit]);
            $incidents = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Stats rapides
            $stmtStats = $pdo->query(
                "SELECT
                    COUNT(*) AS total_mois,
                    SUM(statut IN ('signale','en_traitement')) AS en_attente,
                    SUM(gravite IN ('grave','tres_grave')) AS graves
                 FROM incidents
                 WHERE date_incident >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)"
            );
            $stats = $stmtStats->fetch(\PDO::FETCH_ASSOC);

            return ['incidents' => $incidents, 'stats' => $stats];
        }

        if ($userType === 'professeur') {
            $stmt = $pdo->prepare(
                "SELECT i.id, i.type_incident, i.gravite, i.date_incident, i.statut,
                        CONCAT(e.prenom, ' ', e.nom) AS eleve_nom
                 FROM incidents i
                 JOIN eleves e ON e.id = i.eleve_id
                 WHERE i.signale_par_id = ? AND i.signale_par_type = 'professeur'
                 ORDER BY i.date_incident DESC
                 LIMIT ?"
            );
            $stmt->execute([$userId, $limit]);
            return ['incidents' => $stmt->fetchAll(\PDO::FETCH_ASSOC), 'stats' => []];
        }

        return ['incidents' => [], 'stats' => []];
    }

    public function getRefreshInterval(): int
    {
        return 300;
    }
}
