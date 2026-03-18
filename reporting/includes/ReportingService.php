<?php
/**
 * M22 – Reporting & Exports — Service
 * Enhanced with global dashboards, trend analysis, cross-class comparison
 */

class ReportingService {
    private PDO $pdo;

    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    /* ==================== GLOBAL STATS ==================== */

    /**
     * KPI globaux établissement
     */
    public function getStatsGlobales(): array {
        $pdo = $this->pdo;
        $stats = [];

        try {
            $stats['total_eleves'] = (int)$pdo->query("SELECT COUNT(*) FROM eleves WHERE actif = 1")->fetchColumn();
            $stats['total_profs'] = (int)$pdo->query("SELECT COUNT(*) FROM professeurs WHERE actif = 1")->fetchColumn();
            $stats['total_classes'] = (int)$pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();
        } catch (\Exception $e) {
            $stats = array_merge(['total_eleves' => 0, 'total_profs' => 0, 'total_classes' => 0], $stats);
        }

        // Moyenne générale établissement
        try {
            $stats['moyenne_globale'] = (float)$pdo->query("
                SELECT ROUND(AVG(sub.moy), 2) FROM (
                    SELECT e.id, ROUND(SUM(n.note * n.coefficient) / NULLIF(SUM(n.coefficient), 0), 2) AS moy
                    FROM notes n JOIN eleves e ON n.eleve_id = e.id WHERE e.actif = 1 GROUP BY e.id
                ) sub
            ")->fetchColumn() ?: 0;
        } catch (\Exception $e) { $stats['moyenne_globale'] = 0; }

        // Taux d'absentéisme (absences ce mois / nb élèves)
        try {
            $stats['absences_mois'] = (int)$pdo->query("
                SELECT COUNT(*) FROM absences WHERE MONTH(date_absence) = MONTH(CURDATE()) AND YEAR(date_absence) = YEAR(CURDATE())
            ")->fetchColumn();
            $stats['taux_absenteisme'] = $stats['total_eleves'] > 0
                ? round($stats['absences_mois'] / $stats['total_eleves'] * 100, 1) : 0;
        } catch (\Exception $e) {
            $stats['absences_mois'] = 0;
            $stats['taux_absenteisme'] = 0;
        }

        // Incidents en cours
        try {
            $stats['incidents_ouverts'] = (int)$pdo->query("
                SELECT COUNT(*) FROM incidents WHERE statut IN ('signale','en_cours')
            ")->fetchColumn();
        } catch (\Exception $e) { $stats['incidents_ouverts'] = 0; }

        // Taux de recouvrement facturation
        try {
            $row = $pdo->query("
                SELECT COALESCE(SUM(montant_ttc), 0) AS total, COALESCE(SUM(montant_paye), 0) AS paye FROM factures
            ")->fetch(PDO::FETCH_ASSOC);
            $stats['facturation_total'] = (float)$row['total'];
            $stats['facturation_paye'] = (float)$row['paye'];
            $stats['taux_recouvrement'] = $stats['facturation_total'] > 0
                ? round($stats['facturation_paye'] / $stats['facturation_total'] * 100, 1) : 100;
        } catch (\Exception $e) {
            $stats['facturation_total'] = 0;
            $stats['facturation_paye'] = 0;
            $stats['taux_recouvrement'] = 100;
        }

        return $stats;
    }

    /**
     * Moyennes par classe (pour comparaison)
     */
    public function getMoyennesParClasse(?int $periodeId = null): array {
        $sql = "
            SELECT c.id, CONCAT(c.niveau, ' – ', c.nom) AS classe,
                   ROUND(AVG(sub.moy), 2) AS moyenne,
                   COUNT(DISTINCT sub.eleve_id) AS effectif
            FROM (
                SELECT e.id AS eleve_id, e.classe_id,
                       ROUND(SUM(n.note * n.coefficient) / NULLIF(SUM(n.coefficient), 0), 2) AS moy
                FROM notes n
                JOIN eleves e ON n.eleve_id = e.id
                WHERE e.actif = 1
        ";
        $params = [];
        if ($periodeId) { $sql .= " AND n.periode_id = ?"; $params[] = $periodeId; }
        $sql .= " GROUP BY e.id, e.classe_id
            ) sub
            JOIN classes c ON sub.classe_id = c.id
            GROUP BY c.id, c.niveau, c.nom
            ORDER BY moyenne DESC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Évolution mensuelle absences / retards (12 derniers mois)
     */
    public function getEvolutionMensuelle(): array {
        $pdo = $this->pdo;
        $result = [];

        for ($i = 11; $i >= 0; $i--) {
            $month = date('Y-m', strtotime("-{$i} months"));
            $label = strftime('%b %Y', strtotime($month . '-01'));
            // Fallback for systems without strftime locale
            $label = date('M Y', strtotime($month . '-01'));

            $absences = 0;
            $retards = 0;
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM absences WHERE DATE_FORMAT(date_absence, '%Y-%m') = ?");
                $stmt->execute([$month]);
                $absences = (int)$stmt->fetchColumn();
            } catch (\Exception $e) {}

            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM retards WHERE DATE_FORMAT(date_retard, '%Y-%m') = ?");
                $stmt->execute([$month]);
                $retards = (int)$stmt->fetchColumn();
            } catch (\Exception $e) {}

            $result[] = [
                'mois' => $label,
                'absences' => $absences,
                'retards' => $retards,
            ];
        }
        return $result;
    }

    /**
     * Répartition des notes par tranche (histogramme)
     */
    public function getRepartitionNotes(?int $periodeId = null): array {
        $tranches = [
            '0-4' => [0, 4.99],
            '5-7' => [5, 7.99],
            '8-9' => [8, 9.99],
            '10-11' => [10, 11.99],
            '12-13' => [12, 13.99],
            '14-15' => [14, 15.99],
            '16-17' => [16, 17.99],
            '18-20' => [18, 20],
        ];
        $result = [];
        foreach ($tranches as $label => [$min, $max]) {
            $sql = "SELECT COUNT(*) FROM notes WHERE (note / note_sur * 20) BETWEEN ? AND ?";
            $params = [$min, $max];
            if ($periodeId) { $sql .= " AND periode_id = ?"; $params[] = $periodeId; }
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $result[] = ['tranche' => $label, 'count' => (int)$stmt->fetchColumn()];
        }
        return $result;
    }

    /**
     * Top types d'incidents
     */
    public function getTopIncidents(int $limit = 10): array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT type, COUNT(*) AS total, 
                       SUM(CASE WHEN statut = 'resolu' THEN 1 ELSE 0 END) AS resolus
                FROM incidents GROUP BY type ORDER BY total DESC LIMIT ?
            ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Absences par classe (classement)
     */
    public function getAbsencesParClasse(): array {
        try {
            return $this->pdo->query("
                SELECT c.id, CONCAT(c.niveau, ' – ', c.nom) AS classe,
                       COUNT(a.id) AS total_absences,
                       SUM(CASE WHEN a.justifiee = 1 THEN 1 ELSE 0 END) AS justifiees,
                       COUNT(DISTINCT a.eleve_id) AS eleves_concernes
                FROM classes c
                LEFT JOIN eleves e ON e.classe_id = c.id
                LEFT JOIN absences a ON a.eleve_id = e.id
                GROUP BY c.id, c.niveau, c.nom
                ORDER BY total_absences DESC
            ")->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Taux de réussite par classe (% élèves >= 10/20)
     */
    public function getTauxReussiteParClasse(?int $periodeId = null): array {
        $sql = "
            SELECT c.id, CONCAT(c.niveau, ' – ', c.nom) AS classe,
                   COUNT(sub.eleve_id) AS effectif,
                   SUM(CASE WHEN sub.moy >= 10 THEN 1 ELSE 0 END) AS reussite,
                   ROUND(SUM(CASE WHEN sub.moy >= 10 THEN 1 ELSE 0 END) / NULLIF(COUNT(sub.eleve_id), 0) * 100, 1) AS taux
            FROM (
                SELECT e.id AS eleve_id, e.classe_id,
                       ROUND(SUM(n.note * n.coefficient) / NULLIF(SUM(n.coefficient), 0), 2) AS moy
                FROM notes n JOIN eleves e ON n.eleve_id = e.id WHERE e.actif = 1
        ";
        $params = [];
        if ($periodeId) { $sql .= " AND n.periode_id = ?"; $params[] = $periodeId; }
        $sql .= " GROUP BY e.id, e.classe_id
            ) sub
            JOIN classes c ON sub.classe_id = c.id
            GROUP BY c.id, c.niveau, c.nom ORDER BY taux DESC
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ==================== ABSENCES ==================== */

    /**
     * Export absences CSV
     */
    public function exportAbsencesCSV(int $classeId, ?string $dateDebut = null, ?string $dateFin = null): array {
        $sql = "
            SELECT e.nom, e.prenom, c.nom AS classe, a.date_absence, a.motif, a.justifiee,
                   a.heure_debut, a.heure_fin
            FROM absences a
            JOIN eleves e ON a.eleve_id = e.id
            JOIN classes c ON e.classe_id = c.id
            WHERE e.classe_id = ?
        ";
        $params = [$classeId];
        if ($dateDebut) { $sql .= " AND a.date_absence >= ?"; $params[] = $dateDebut; }
        if ($dateFin)   { $sql .= " AND a.date_absence <= ?"; $params[] = $dateFin; }
        $sql .= " ORDER BY a.date_absence DESC, e.nom";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ==================== NOTES ==================== */

    /**
     * Export notes CSV pour une classe
     */
    public function exportNotesCSV(int $classeId, ?int $periodeId = null): array {
        $sql = "
            SELECT e.nom, e.prenom, m.nom AS matiere, n.note, n.note_sur, n.coefficient,
                   n.commentaire, n.date_note
            FROM notes n
            JOIN eleves e ON n.eleve_id = e.id
            JOIN matieres m ON n.matiere_id = m.id
            WHERE e.classe_id = ?
        ";
        $params = [$classeId];
        if ($periodeId) { $sql .= " AND n.periode_id = ?"; $params[] = $periodeId; }
        $sql .= " ORDER BY e.nom, m.nom, n.date_note DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ==================== MOYENNES ==================== */

    /**
     * Export moyennes par matière pour une classe
     */
    public function exportMoyennesClasse(int $classeId, ?int $periodeId = null): array {
        $sql = "
            SELECT e.nom, e.prenom, m.nom AS matiere,
                   ROUND(SUM(n.note * n.coefficient) / SUM(n.coefficient), 2) AS moyenne
            FROM notes n
            JOIN eleves e ON n.eleve_id = e.id
            JOIN matieres m ON n.matiere_id = m.id
            WHERE e.classe_id = ?
        ";
        $params = [$classeId];
        if ($periodeId) { $sql .= " AND n.periode_id = ?"; $params[] = $periodeId; }
        $sql .= " GROUP BY e.id, m.id ORDER BY e.nom, m.nom";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ==================== DISCIPLINE ==================== */

    /**
     * Export incidents
     */
    public function exportIncidents(int $classeId, ?string $dateDebut = null, ?string $dateFin = null): array {
        $sql = "SELECT e.nom, e.prenom, c.nom AS classe, i.type, i.description, i.gravite, i.date_incident, i.statut
                FROM incidents i
                JOIN eleves e ON i.eleve_id = e.id
                JOIN classes c ON e.classe_id = c.id
                WHERE e.classe_id = ?";
        $params = [$classeId];
        if ($dateDebut) { $sql .= " AND i.date_incident >= ?"; $params[] = $dateDebut; }
        if ($dateFin)   { $sql .= " AND i.date_incident <= ?"; $params[] = $dateFin; }
        $sql .= " ORDER BY i.date_incident DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ==================== STATISTIQUES PAR CLASSE ==================== */

    /**
     * Rapport synthétique classe (enhanced with moyennes par matière)
     */
    public function getRapportClasse(int $classeId, ?int $periodeId = null): array {
        $pdo = $this->pdo;

        // Classe info
        $stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ?");
        $stmt->execute([$classeId]);
        $classe = $stmt->fetch(PDO::FETCH_ASSOC);

        // Effectif
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM eleves WHERE classe_id = ? AND actif = 1");
        $stmt->execute([$classeId]);
        $effectif = (int)$stmt->fetchColumn();

        // Moyenne générale
        $sql = "
            SELECT ROUND(AVG(sub.moy), 2) as moyenne_classe FROM (
                SELECT e.id, ROUND(SUM(n.note * n.coefficient) / NULLIF(SUM(n.coefficient), 0), 2) AS moy
                FROM notes n JOIN eleves e ON n.eleve_id = e.id
                WHERE e.classe_id = ?
        ";
        $params = [$classeId];
        if ($periodeId) { $sql .= " AND n.periode_id = ?"; $params[] = $periodeId; }
        $sql .= " GROUP BY e.id) sub";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $moyenneClasse = $stmt->fetchColumn() ?: 0;

        // Absences
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM absences a JOIN eleves e ON a.eleve_id = e.id WHERE e.classe_id = ?");
        $stmt->execute([$classeId]);
        $totalAbsences = (int)$stmt->fetchColumn();

        // Retards
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM retards r JOIN eleves e ON r.eleve_id = e.id WHERE e.classe_id = ?");
        $stmt->execute([$classeId]);
        $totalRetards = (int)$stmt->fetchColumn();

        // Incidents
        $nbIncidents = 0;
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM incidents i JOIN eleves e ON i.eleve_id = e.id WHERE e.classe_id = ?");
            $stmt->execute([$classeId]);
            $nbIncidents = (int)$stmt->fetchColumn();
        } catch (\Exception $e) {}

        // Moyennes par matière
        $sqlMat = "
            SELECT m.nom AS matiere,
                   ROUND(AVG(n.note / n.note_sur * 20), 2) AS moyenne,
                   MIN(n.note / n.note_sur * 20) AS min_note,
                   MAX(n.note / n.note_sur * 20) AS max_note,
                   COUNT(DISTINCT n.id) AS nb_notes
            FROM notes n
            JOIN eleves e ON n.eleve_id = e.id
            JOIN matieres m ON n.matiere_id = m.id
            WHERE e.classe_id = ?
        ";
        $paramsMat = [$classeId];
        if ($periodeId) { $sqlMat .= " AND n.periode_id = ?"; $paramsMat[] = $periodeId; }
        $sqlMat .= " GROUP BY m.id, m.nom ORDER BY m.nom";
        $stmt = $pdo->prepare($sqlMat);
        $stmt->execute($paramsMat);
        $moyennesMatieres = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Classement élèves
        $sqlEleves = "
            SELECT e.id, e.nom, e.prenom,
                   ROUND(SUM(n.note * n.coefficient) / NULLIF(SUM(n.coefficient), 0), 2) AS moyenne
            FROM notes n JOIN eleves e ON n.eleve_id = e.id
            WHERE e.classe_id = ? AND e.actif = 1
        ";
        $paramsEleves = [$classeId];
        if ($periodeId) { $sqlEleves .= " AND n.periode_id = ?"; $paramsEleves[] = $periodeId; }
        $sqlEleves .= " GROUP BY e.id, e.nom, e.prenom ORDER BY moyenne DESC";
        $stmt = $pdo->prepare($sqlEleves);
        $stmt->execute($paramsEleves);
        $classement = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'classe'            => $classe,
            'effectif'          => $effectif,
            'moyenne_classe'    => $moyenneClasse,
            'total_absences'    => $totalAbsences,
            'total_retards'     => $totalRetards,
            'nb_incidents'      => $nbIncidents,
            'moyennes_matieres' => $moyennesMatieres,
            'classement'        => $classement,
        ];
    }

    /* ==================== HELPERS ==================== */

    /**
     * Récupère les classes
     */
    public function getClasses(): array {
        return $this->pdo->query("SELECT * FROM classes ORDER BY niveau, nom")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPeriodes(): array {
        return $this->pdo->query("SELECT * FROM periodes ORDER BY date_debut")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Génère et envoie un CSV
     */
    public static function sendCSV(string $filename, array $headers, array $rows): void {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');
        // BOM UTF-8 pour Excel
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $headers, ';');
        foreach ($rows as $row) {
            fputcsv($out, array_values($row), ';');
        }
        fclose($out);
        exit;
    }
}
