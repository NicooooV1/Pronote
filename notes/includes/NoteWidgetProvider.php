<?php

declare(strict_types=1);

namespace Notes\Widgets;

use API\Contracts\WidgetDataProvider;

class NoteWidgetProvider implements WidgetDataProvider
{
    public function getData(int $userId, string $userType, ?array $config = null): array
    {
        $pdo = app('db')?->getConnection();
        if (!$pdo) {
            return ['notes' => [], 'average' => null];
        }

        $limit = (int) ($config['limit'] ?? 5);
        $limit = min(20, max(1, $limit));

        if ($userType === 'eleve') {
            $stmt = $pdo->prepare(
                'SELECT n.note, n.note_sur, n.coefficient, n.date_devoir, m.nom AS matiere
                 FROM notes n
                 LEFT JOIN matieres m ON m.id = n.id_matiere
                 WHERE n.id_eleve = ?
                 ORDER BY n.date_devoir DESC
                 LIMIT ?'
            );
            $stmt->execute([$userId, $limit]);
            $notes = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $avg = null;
            if (!empty($notes)) {
                $stmt2 = $pdo->prepare(
                    'SELECT ROUND(AVG(note / note_sur * 20), 2) FROM notes WHERE id_eleve = ?'
                );
                $stmt2->execute([$userId]);
                $avg = $stmt2->fetchColumn() ?: null;
            }

            return ['notes' => $notes, 'average' => $avg];
        }

        // Professeur : dernières notes saisies dans leurs classes
        if ($userType === 'professeur') {
            $stmt = $pdo->prepare(
                'SELECT n.note, n.note_sur, n.coefficient, n.date_devoir,
                        m.nom AS matiere, CONCAT(e.prenom, \' \', e.nom) AS eleve_nom
                 FROM notes n
                 LEFT JOIN matieres m ON m.id = n.id_matiere
                 LEFT JOIN eleves e ON e.id = n.id_eleve
                 WHERE n.id_professeur = ?
                 ORDER BY n.date_devoir DESC
                 LIMIT ?'
            );
            $stmt->execute([$userId, $limit]);
            return ['notes' => $stmt->fetchAll(\PDO::FETCH_ASSOC), 'average' => null];
        }

        return ['notes' => [], 'average' => null];
    }

    public function getRefreshInterval(): int
    {
        return 600;
    }
}
