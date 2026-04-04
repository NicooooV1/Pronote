<?php

declare(strict_types=1);

namespace Absences\Widgets;

use API\Contracts\WidgetDataProvider;

class AbsenceWidgetProvider implements WidgetDataProvider
{
    public function getData(int $userId, string $userType, ?array $config = null): array
    {
        $pdo = app('db')?->getConnection();
        if (!$pdo) {
            return ['absences' => 0, 'retards' => 0, 'unjustified' => 0];
        }

        $absences = (int) $pdo->query(
            'SELECT COUNT(*) FROM absences WHERE CURDATE() BETWEEN DATE(date_debut) AND DATE(date_fin)'
        )->fetchColumn();

        $retards = (int) $pdo->query(
            'SELECT COUNT(*) FROM retards WHERE DATE(date_retard) = CURDATE()'
        )->fetchColumn();

        $unjustified = (int) $pdo->query(
            'SELECT COUNT(*) FROM absences WHERE justifie = 0'
        )->fetchColumn();

        return [
            'absences'    => $absences,
            'retards'     => $retards,
            'unjustified' => $unjustified,
        ];
    }

    public function getRefreshInterval(): int
    {
        return 300;
    }
}
