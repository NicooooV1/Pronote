<?php

declare(strict_types=1);

namespace VieScolaire\Widgets;

use API\Contracts\WidgetDataProvider;

class VieScolaireWidgetProvider implements WidgetDataProvider
{
    public function getData(int $userId, string $userType, ?array $config = null): array
    {
        $pdo = app('db')?->getConnection();
        if (!$pdo) {
            return ['stats' => []];
        }

        if (!in_array($userType, ['administrateur', 'vie_scolaire'], true)) {
            return ['stats' => []];
        }

        $today = date('Y-m-d');

        // Absences du jour
        $stmtAbs = $pdo->prepare(
            "SELECT COUNT(*) FROM absences
             WHERE DATE(date_debut) <= ? AND DATE(date_fin) >= ?"
        );
        $stmtAbs->execute([$today, $today]);
        $absencesToday = (int) $stmtAbs->fetchColumn();

        // Retards du jour
        $stmtRet = $pdo->prepare(
            "SELECT COUNT(*) FROM retards WHERE DATE(date_retard) = ?"
        );
        $stmtRet->execute([$today]);
        $retardsToday = (int) $stmtRet->fetchColumn();

        // Incidents ouverts
        $stmtInc = $pdo->query(
            "SELECT COUNT(*) FROM incidents WHERE statut IN ('signale','en_traitement')"
        );
        $incidentsOuverts = (int) $stmtInc->fetchColumn();

        // Justificatifs en attente
        $stmtJust = $pdo->query(
            "SELECT COUNT(*) FROM justificatifs WHERE traite = 0"
        );
        $justifAttente = (int) $stmtJust->fetchColumn();

        // Appels non validés aujourd'hui
        $stmtAppels = $pdo->prepare(
            "SELECT COUNT(*) FROM appels WHERE date_appel = ? AND statut = 'en_cours'"
        );
        $stmtAppels->execute([$today]);
        $appelsEnCours = (int) $stmtAppels->fetchColumn();

        return [
            'stats' => [
                ['label' => 'Absents aujourd\'hui', 'value' => $absencesToday, 'icon' => 'fas fa-user-times', 'color' => '#e53e3e'],
                ['label' => 'Retards', 'value' => $retardsToday, 'icon' => 'fas fa-clock', 'color' => '#ed8936'],
                ['label' => 'Incidents ouverts', 'value' => $incidentsOuverts, 'icon' => 'fas fa-exclamation-triangle', 'color' => '#d69e2e'],
                ['label' => 'Justificatifs en attente', 'value' => $justifAttente, 'icon' => 'fas fa-file-medical', 'color' => '#667eea'],
                ['label' => 'Appels en cours', 'value' => $appelsEnCours, 'icon' => 'fas fa-check-square', 'color' => '#48bb78'],
            ],
        ];
    }

    public function getRefreshInterval(): int
    {
        return 120;
    }
}
