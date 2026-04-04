<?php

declare(strict_types=1);

namespace EmploiDuTemps\Widgets;

use API\Contracts\WidgetDataProvider;

class EdtWidgetProvider implements WidgetDataProvider
{
    private const JOURS = ['dimanche','lundi','mardi','mercredi','jeudi','vendredi','samedi'];

    public function getData(int $userId, string $userType, ?array $config = null): array
    {
        $pdo = app('db')?->getConnection();
        if (!$pdo) {
            return ['cours' => [], 'jour' => ''];
        }

        $jourNum = (int) date('w'); // 0=dim ... 6=sam
        $jourFr = self::JOURS[$jourNum] ?? 'lundi';
        $today = date('Y-m-d');

        if ($userType === 'eleve') {
            $stmt = $pdo->prepare(
                "SELECT edt.heure_debut, edt.heure_fin, m.nom AS matiere,
                        CONCAT(p.prenom, ' ', p.nom) AS professeur,
                        s.nom AS salle, edt.type_cours,
                        em.type_modification
                 FROM emploi_du_temps edt
                 JOIN matieres m ON m.id = edt.matiere_id
                 JOIN professeurs p ON p.id = edt.professeur_id
                 LEFT JOIN salles s ON s.id = edt.salle_id
                 LEFT JOIN edt_modifications em ON em.edt_id = edt.id AND em.date_cours = ?
                 WHERE edt.classe_id = (SELECT classe_id FROM eleves WHERE id = ? LIMIT 1)
                   AND edt.jour = ? AND edt.actif = 1
                 ORDER BY edt.heure_debut"
            );
            $stmt->execute([$today, $userId, $jourFr]);
        } elseif ($userType === 'professeur') {
            $stmt = $pdo->prepare(
                "SELECT edt.heure_debut, edt.heure_fin, m.nom AS matiere,
                        c.nom AS classe, s.nom AS salle, edt.type_cours,
                        em.type_modification
                 FROM emploi_du_temps edt
                 JOIN matieres m ON m.id = edt.matiere_id
                 JOIN classes c ON c.id = edt.classe_id
                 LEFT JOIN salles s ON s.id = edt.salle_id
                 LEFT JOIN edt_modifications em ON em.edt_id = edt.id AND em.date_cours = ?
                 WHERE edt.professeur_id = ? AND edt.jour = ? AND edt.actif = 1
                 ORDER BY edt.heure_debut"
            );
            $stmt->execute([$today, $userId, $jourFr]);
        } elseif ($userType === 'parent') {
            $childId = $_SESSION['selected_child_id'] ?? null;
            if (!$childId) {
                return ['cours' => [], 'jour' => $jourFr];
            }
            $stmt = $pdo->prepare(
                "SELECT edt.heure_debut, edt.heure_fin, m.nom AS matiere,
                        CONCAT(p.prenom, ' ', p.nom) AS professeur,
                        s.nom AS salle, edt.type_cours,
                        em.type_modification
                 FROM emploi_du_temps edt
                 JOIN matieres m ON m.id = edt.matiere_id
                 JOIN professeurs p ON p.id = edt.professeur_id
                 LEFT JOIN salles s ON s.id = edt.salle_id
                 LEFT JOIN edt_modifications em ON em.edt_id = edt.id AND em.date_cours = ?
                 WHERE edt.classe_id = (SELECT classe_id FROM eleves WHERE id = ? LIMIT 1)
                   AND edt.jour = ? AND edt.actif = 1
                 ORDER BY edt.heure_debut"
            );
            $stmt->execute([$today, $childId, $jourFr]);
        } else {
            return ['cours' => [], 'jour' => $jourFr];
        }

        $cours = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Filter out cancelled courses
        $cours = array_filter($cours, fn($c) => ($c['type_modification'] ?? '') !== 'annulation');
        $cours = array_values($cours);

        return ['cours' => $cours, 'jour' => ucfirst($jourFr)];
    }

    public function getRefreshInterval(): int
    {
        return 900;
    }
}
