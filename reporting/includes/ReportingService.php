<?php
/**
 * M22 – Reporting & Exports — Service
 */

class ReportingService {
    private PDO $pdo;

    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

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

    /* ==================== STATISTIQUES ==================== */

    /**
     * Rapport synthétique classe
     */
    public function getRapportClasse(int $classeId): array {
        $pdo = $this->pdo;

        // Classe info
        $stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ?");
        $stmt->execute([$classeId]);
        $classe = $stmt->fetch(PDO::FETCH_ASSOC);

        // Effectif
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM eleves WHERE classe_id = ?");
        $stmt->execute([$classeId]);
        $effectif = (int)$stmt->fetchColumn();

        // Moyenne générale
        $stmt = $pdo->prepare("
            SELECT ROUND(AVG(sub.moy), 2) as moyenne_classe FROM (
                SELECT e.id, ROUND(SUM(n.note * n.coefficient) / SUM(n.coefficient), 2) AS moy
                FROM notes n JOIN eleves e ON n.eleve_id = e.id
                WHERE e.classe_id = ? GROUP BY e.id
            ) sub
        ");
        $stmt->execute([$classeId]);
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

        return [
            'classe' => $classe,
            'effectif' => $effectif,
            'moyenne_classe' => $moyenneClasse,
            'total_absences' => $totalAbsences,
            'total_retards' => $totalRetards,
            'nb_incidents' => $nbIncidents,
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
