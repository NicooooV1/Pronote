<?php

declare(strict_types=1);

namespace Competences\Widgets;

use API\Contracts\WidgetDataProvider;

class CompetenceWidgetProvider implements WidgetDataProvider
{
    public function getData(int $userId, string $userType, ?array $config = null): array
    {
        $pdo = app('db')?->getConnection();
        if (!$pdo) {
            return ['evaluations' => []];
        }

        $limit = min(10, max(1, (int) ($config['limit'] ?? 5)));

        if ($userType === 'eleve') {
            $stmt = $pdo->prepare(
                "SELECT ce.niveau, ce.date_evaluation, ce.commentaire,
                        c.nom AS competence, c.domaine
                 FROM competence_evaluations ce
                 JOIN competences c ON c.id = ce.competence_id
                 WHERE ce.eleve_id = ?
                 ORDER BY ce.date_evaluation DESC
                 LIMIT ?"
            );
            $stmt->execute([$userId, $limit]);
        } elseif ($userType === 'parent') {
            $childId = $_SESSION['selected_child_id'] ?? null;
            if (!$childId) {
                return ['evaluations' => []];
            }
            $stmt = $pdo->prepare(
                "SELECT ce.niveau, ce.date_evaluation, ce.commentaire,
                        c.nom AS competence, c.domaine
                 FROM competence_evaluations ce
                 JOIN competences c ON c.id = ce.competence_id
                 WHERE ce.eleve_id = ?
                 ORDER BY ce.date_evaluation DESC
                 LIMIT ?"
            );
            $stmt->execute([$childId, $limit]);
        } elseif ($userType === 'professeur') {
            $stmt = $pdo->prepare(
                "SELECT ce.niveau, ce.date_evaluation,
                        c.nom AS competence, c.domaine,
                        CONCAT(e.prenom, ' ', e.nom) AS eleve_nom
                 FROM competence_evaluations ce
                 JOIN competences c ON c.id = ce.competence_id
                 JOIN eleves e ON e.id = ce.eleve_id
                 WHERE ce.professeur_id = ?
                 ORDER BY ce.date_evaluation DESC
                 LIMIT ?"
            );
            $stmt->execute([$userId, $limit]);
        } else {
            return ['evaluations' => []];
        }

        return ['evaluations' => $stmt->fetchAll(\PDO::FETCH_ASSOC)];
    }

    public function getRefreshInterval(): int
    {
        return 600;
    }
}
