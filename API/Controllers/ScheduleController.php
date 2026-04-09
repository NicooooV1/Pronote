<?php
declare(strict_types=1);

namespace API\Controllers;

use API\Core\EstablishmentContext;

/**
 * REST Controller for schedule (emploi du temps).
 * GET /API/schedule?classe_id=X&date=Y   — Get schedule for a class on a given day
 * GET /API/schedule/week?classe_id=X&week=Y — Get weekly schedule
 */
class ScheduleController extends BaseController
{
    public function index(): void
    {
        $this->authenticate();

        $etabId = EstablishmentContext::id();
        $classeId = (int) $this->query('classe_id', 0);
        $profId = (int) $this->query('prof_id', 0);
        $date = $this->query('date', date('Y-m-d'));

        if (!$classeId && !$profId) {
            $this->error('classe_id or prof_id required', 400);
        }

        $dayOfWeek = date('N', strtotime($date)); // 1=Mon, 7=Sun

        $where = 'WHERE edt.etablissement_id = ? AND edt.jour = ?';
        $params = [$etabId, $dayOfWeek];

        if ($classeId) {
            $where .= ' AND edt.classe_id = ?';
            $params[] = $classeId;
        }
        if ($profId) {
            $where .= ' AND edt.professeur_id = ?';
            $params[] = $profId;
        }

        $stmt = $this->pdo->prepare("
            SELECT edt.id, edt.jour, edt.heure_debut, edt.heure_fin,
                   m.nom AS matiere, m.couleur AS matiere_couleur,
                   CONCAT(p.prenom, ' ', p.nom) AS professeur,
                   s.nom AS salle, c.nom AS classe
            FROM emploi_du_temps edt
            LEFT JOIN matieres m ON m.id = edt.matiere_id
            LEFT JOIN professeurs p ON p.id = edt.professeur_id
            LEFT JOIN salles s ON s.id = edt.salle_id
            LEFT JOIN classes c ON c.id = edt.classe_id
            {$where}
            ORDER BY edt.heure_debut
        ");
        $stmt->execute($params);

        $this->json($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function week(): void
    {
        $this->authenticate();

        $etabId = EstablishmentContext::id();
        $classeId = (int) $this->query('classe_id', 0);
        $profId = (int) $this->query('prof_id', 0);

        if (!$classeId && !$profId) {
            $this->error('classe_id or prof_id required', 400);
        }

        $where = 'WHERE edt.etablissement_id = ?';
        $params = [$etabId];

        if ($classeId) {
            $where .= ' AND edt.classe_id = ?';
            $params[] = $classeId;
        }
        if ($profId) {
            $where .= ' AND edt.professeur_id = ?';
            $params[] = $profId;
        }

        $stmt = $this->pdo->prepare("
            SELECT edt.id, edt.jour, edt.heure_debut, edt.heure_fin,
                   m.nom AS matiere, m.couleur AS matiere_couleur,
                   CONCAT(p.prenom, ' ', p.nom) AS professeur,
                   s.nom AS salle, c.nom AS classe
            FROM emploi_du_temps edt
            LEFT JOIN matieres m ON m.id = edt.matiere_id
            LEFT JOIN professeurs p ON p.id = edt.professeur_id
            LEFT JOIN salles s ON s.id = edt.salle_id
            LEFT JOIN classes c ON c.id = edt.classe_id
            {$where}
            ORDER BY edt.jour, edt.heure_debut
        ");
        $stmt->execute($params);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Group by day
        $week = [];
        foreach ($results as $row) {
            $day = (int) $row['jour'];
            $week[$day][] = $row;
        }

        $this->json($week);
    }
}
