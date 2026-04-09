<?php
/**
 * VieScolaireService — Tableau de bord consolidé pour la vie scolaire (M10)
 * Agrège les données absences, retards, incidents, appels 
 */
class VieScolaireService {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    // ─── STATISTIQUES GLOBALES ───
    public function getStatsJour(string $date = null): array {
        $date = $date ?? date('Y-m-d');
        $stats = [];

        // Absences du jour
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM absences WHERE DATE(date_debut) <= ? AND DATE(date_fin) >= ?");
        $stmt->execute([$date, $date]);
        $stats['absences_jour'] = (int)$stmt->fetchColumn();

        // Retards du jour
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM retards WHERE DATE(date_retard) = ?");
        $stmt->execute([$date]);
        $stats['retards_jour'] = (int)$stmt->fetchColumn();

        // Incidents non traités
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM incidents WHERE statut IN ('signale','en_traitement')");
        $stats['incidents_ouverts'] = (int)$stmt->fetchColumn();

        // Justificatifs en attente
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM justificatifs WHERE traite = 0");
        $stats['justificatifs_attente'] = (int)$stmt->fetchColumn();

        // Appels non validés aujourd'hui
        if ($this->tableExists('appels')) {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM appels WHERE date_appel = ? AND statut = 'en_cours'");
            $stmt->execute([$date]);
            $stats['appels_en_cours'] = (int)$stmt->fetchColumn();
        }

        // Sanctions en cours
        if ($this->tableExists('sanctions')) {
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM sanctions WHERE statut = 'prononcee'");
            $stats['sanctions_actives'] = (int)$stmt->fetchColumn();
        }

        // Retenues planifiées
        if ($this->tableExists('retenues')) {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM retenues WHERE date_retenue >= ? AND statut = 'planifiee'");
            $stmt->execute([$date]);
            $stats['retenues_planifiees'] = (int)$stmt->fetchColumn();
        }

        return $stats;
    }

    // ─── ÉLÈVES À SURVEILLER ───
    public function getElevesASurveiller(int $limit = 10): array {
        $stmt = $this->pdo->prepare("
            SELECT e.id, e.nom, e.prenom, e.classe,
                (SELECT COUNT(*) FROM absences WHERE id_eleve = e.id AND justifie = 0) AS abs_injustifiees,
                (SELECT COUNT(*) FROM retards WHERE id_eleve = e.id) AS nb_retards,
                (SELECT COUNT(*) FROM incidents WHERE eleve_id = e.id) AS nb_incidents
            FROM eleves e
            WHERE e.actif = 1
            HAVING abs_injustifiees > 3 OR nb_retards > 5 OR nb_incidents > 2
            ORDER BY (abs_injustifiees * 3 + nb_retards + nb_incidents * 2) DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── ABSENCES RÉCENTES ───
    public function getAbsencesRecentes(int $limit = 20): array {
        $stmt = $this->pdo->prepare("
            SELECT a.*, e.nom, e.prenom, e.classe
            FROM absences a
            JOIN eleves e ON a.id_eleve = e.id
            ORDER BY a.date_signalement DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── INCIDENTS RÉCENTS ───
    public function getIncidentsRecents(int $limit = 10): array {
        if (!$this->tableExists('incidents')) return [];
        $stmt = $this->pdo->prepare("
            SELECT i.*, e.nom, e.prenom, e.classe
            FROM incidents i
            JOIN eleves e ON i.eleve_id = e.id
            ORDER BY i.date_creation DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── STATS PAR CLASSE ───
    public function getStatsParClasse(): array {
        $stmt = $this->pdo->query("
            SELECT e.classe,
                COUNT(DISTINCT e.id) AS nb_eleves,
                (SELECT COUNT(*) FROM absences a WHERE a.id_eleve IN (SELECT id FROM eleves WHERE classe = e.classe)) AS nb_absences,
                (SELECT COUNT(*) FROM retards r WHERE r.id_eleve IN (SELECT id FROM eleves WHERE classe = e.classe)) AS nb_retards
            FROM eleves e
            WHERE e.actif = 1
            GROUP BY e.classe
            ORDER BY e.classe
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── FICHE ÉLÈVE CONSOLIDÉE ───
    public function getFicheEleve(int $eleveId): array {
        $fiche = [];

        // Info élève
        $stmt = $this->pdo->prepare("SELECT * FROM eleves WHERE id = ?");
        $stmt->execute([$eleveId]);
        $fiche['eleve'] = $stmt->fetch(PDO::FETCH_ASSOC);

        // Absences
        $stmt = $this->pdo->prepare("SELECT * FROM absences WHERE id_eleve = ? ORDER BY date_debut DESC");
        $stmt->execute([$eleveId]);
        $fiche['absences'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Retards
        $stmt = $this->pdo->prepare("SELECT * FROM retards WHERE id_eleve = ? ORDER BY date_retard DESC");
        $stmt->execute([$eleveId]);
        $fiche['retards'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Incidents
        if ($this->tableExists('incidents')) {
            $stmt = $this->pdo->prepare("SELECT * FROM incidents WHERE eleve_id = ? ORDER BY date_incident DESC");
            $stmt->execute([$eleveId]);
            $fiche['incidents'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Sanctions
        if ($this->tableExists('sanctions')) {
            $stmt = $this->pdo->prepare("SELECT * FROM sanctions WHERE eleve_id = ? ORDER BY date_sanction DESC");
            $stmt->execute([$eleveId]);
            $fiche['sanctions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Compteurs
        $fiche['stats'] = [
            'absences' => count($fiche['absences']),
            'abs_injustifiees' => count(array_filter($fiche['absences'], fn($a) => !$a['justifie'])),
            'retards' => count($fiche['retards']),
            'incidents' => count($fiche['incidents'] ?? []),
            'sanctions' => count($fiche['sanctions'] ?? []),
        ];

        return $fiche;
    }

    // ─── RECHERCHE ÉLÈVE ───
    public function rechercherEleves(string $q): array {
        $like = "%{$q}%";
        $stmt = $this->pdo->prepare("
            SELECT id, nom, prenom, classe FROM eleves
            WHERE actif = 1 AND (nom LIKE ? OR prenom LIKE ? OR classe LIKE ?)
            ORDER BY nom LIMIT 20
        ");
        $stmt->execute([$like, $like, $like]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── TIMELINE ACTIVITÉ ───
    public function getTimeline(int $limit = 30): array {
        $events = [];

        // Absences récentes
        $stmt = $this->pdo->prepare("SELECT a.id, 'absence' AS type, a.date_signalement AS date_event, CONCAT(e.prenom, ' ', e.nom) AS eleve, e.classe, a.type_absence AS detail FROM absences a JOIN eleves e ON a.id_eleve = e.id ORDER BY a.date_signalement DESC LIMIT ?");
        $stmt->execute([$limit]);
        $events = array_merge($events, $stmt->fetchAll(PDO::FETCH_ASSOC));

        // Retards
        $stmt = $this->pdo->prepare("SELECT r.id, 'retard' AS type, r.date_signalement AS date_event, CONCAT(e.prenom, ' ', e.nom) AS eleve, e.classe, CONCAT(r.duree_minutes, ' min') AS detail FROM retards r JOIN eleves e ON r.id_eleve = e.id ORDER BY r.date_signalement DESC LIMIT ?");
        $stmt->execute([$limit]);
        $events = array_merge($events, $stmt->fetchAll(PDO::FETCH_ASSOC));

        // Incidents
        if ($this->tableExists('incidents')) {
            $stmt = $this->pdo->prepare("SELECT i.id, 'incident' AS type, i.date_creation AS date_event, CONCAT(e.prenom, ' ', e.nom) AS eleve, e.classe, i.type_incident AS detail FROM incidents i JOIN eleves e ON i.eleve_id = e.id ORDER BY i.date_creation DESC LIMIT ?");
            $stmt->execute([$limit]);
            $events = array_merge($events, $stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        // Trier par date
        usort($events, fn($a, $b) => strtotime($b['date_event']) - strtotime($a['date_event']));
        return array_slice($events, 0, $limit);
    }

    // ─── DROPOUT DETECTION ───

    /**
     * Detect students at risk of dropout based on multiple indicators.
     * Criteria: absences >20%, declining grades, multiple incidents.
     */
    public function detecterRisqueDecrochage(): array
    {
        $alertes = [];
        $now = date('Y-m-d');
        $debutTrimestre = date('Y-m-d', strtotime('-3 months'));

        $stmt = $this->pdo->query("SELECT id, nom, prenom, classe FROM eleves WHERE actif = 1");
        $eleves = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($eleves as $e) {
            $score = 0;
            $indicators = [];

            // 1. Absenteeism rate
            $absStmt = $this->pdo->prepare("SELECT COUNT(*) FROM absences WHERE id_eleve = ? AND date_debut >= ?");
            $absStmt->execute([$e['id'], $debutTrimestre]);
            $nbAbsences = (int)$absStmt->fetchColumn();

            // Estimate total school days (~60 per trimester)
            $tauxAbsence = ($nbAbsences / 60) * 100;
            if ($tauxAbsence > 20) {
                $score += 3;
                $indicators[] = "Absences: {$tauxAbsence}%";
            } elseif ($tauxAbsence > 10) {
                $score += 1;
            }

            // 2. Incidents
            if ($this->tableExists('incidents')) {
                $incStmt = $this->pdo->prepare("SELECT COUNT(*) FROM incidents WHERE eleve_id = ? AND date_incident >= ?");
                $incStmt->execute([$e['id'], $debutTrimestre]);
                $nbIncidents = (int)$incStmt->fetchColumn();
                if ($nbIncidents >= 3) {
                    $score += 2;
                    $indicators[] = "Incidents: {$nbIncidents}";
                }
            }

            // 3. Declining grades (current period avg < previous period avg)
            try {
                $gradeStmt = $this->pdo->prepare("
                    SELECT p.id AS pid,
                           ROUND(SUM(n.note * n.coefficient) / NULLIF(SUM(n.coefficient), 0), 2) AS moy
                    FROM notes n
                    JOIN periodes p ON n.periode_id = p.id
                    WHERE n.eleve_id = ?
                    GROUP BY p.id
                    ORDER BY p.date_debut DESC
                    LIMIT 2
                ");
                $gradeStmt->execute([$e['id']]);
                $periodes = $gradeStmt->fetchAll(\PDO::FETCH_ASSOC);
                if (count($periodes) >= 2) {
                    $current = (float)$periodes[0]['moy'];
                    $previous = (float)$periodes[1]['moy'];
                    if ($previous > 0 && ($previous - $current) > 2) {
                        $score += 2;
                        $indicators[] = "Notes en baisse: {$previous} → {$current}";
                    }
                    if ($current < 8) {
                        $score += 1;
                        $indicators[] = "Moyenne < 8: {$current}";
                    }
                }
            } catch (\Exception $e2) {}

            // 4. Unjustified absences
            $injStmt = $this->pdo->prepare("SELECT COUNT(*) FROM absences WHERE id_eleve = ? AND justifie = 0 AND date_debut >= ?");
            $injStmt->execute([$e['id'], $debutTrimestre]);
            $nbInjustifiees = (int)$injStmt->fetchColumn();
            if ($nbInjustifiees > 5) {
                $score += 2;
                $indicators[] = "Absences injustifiées: {$nbInjustifiees}";
            }

            if ($score >= 3) {
                $alertes[] = [
                    'eleve_id' => $e['id'],
                    'nom' => $e['nom'],
                    'prenom' => $e['prenom'],
                    'classe' => $e['classe'],
                    'score_risque' => $score,
                    'niveau' => $score >= 6 ? 'critique' : ($score >= 4 ? 'eleve' : 'modere'),
                    'indicateurs' => $indicators,
                ];
            }
        }

        // Sort by risk score descending
        usort($alertes, fn($a, $b) => $b['score_risque'] - $a['score_risque']);
        return $alertes;
    }

    /**
     * Save dropout detection results to suivi_eleves table.
     */
    public function sauvegarderAnalyseDecrochage(array $alertes): int
    {
        $count = 0;
        $stmt = $this->pdo->prepare("
            INSERT INTO suivi_eleves (eleve_id, risque_decrochage, derniere_analyse, notes_json)
            VALUES (?, ?, CURDATE(), ?)
            ON DUPLICATE KEY UPDATE risque_decrochage = VALUES(risque_decrochage),
                derniere_analyse = VALUES(derniere_analyse), notes_json = VALUES(notes_json)
        ");
        foreach ($alertes as $a) {
            $stmt->execute([
                $a['eleve_id'],
                $a['score_risque'] / 10,
                json_encode($a['indicateurs'])
            ]);
            $count++;
        }
        return $count;
    }

    private function tableExists(string $name): bool {
        try {
            $this->pdo->query("SELECT 1 FROM `{$name}` LIMIT 1");
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    // ─── BRIEFING QUOTIDIEN ───

    public function getBriefingQuotidien(int $etabId): array
    {
        $absJour = $this->pdo->prepare("SELECT COUNT(*) FROM absences WHERE date_absence = CURDATE()");
        $absJour->execute();

        $retards = $this->pdo->prepare("SELECT COUNT(*) FROM appel_details WHERE statut = 'retard' AND DATE(created_at) = CURDATE()");
        $retards->execute();

        $incidents = $this->pdo->prepare("SELECT COUNT(*) FROM incidents WHERE DATE(date_incident) = CURDATE() AND etablissement_id = :eid");
        $incidents->execute([':eid' => $etabId]);

        $passagesInf = $this->pdo->prepare("SELECT COUNT(*) FROM infirmerie_passages WHERE DATE(date_passage) = CURDATE()");
        $passagesInf->execute();

        return [
            'date' => date('Y-m-d'),
            'absences_jour' => (int)$absJour->fetchColumn(),
            'retards_jour' => (int)$retards->fetchColumn(),
            'incidents_jour' => (int)$incidents->fetchColumn(),
            'passages_infirmerie' => (int)$passagesInf->fetchColumn()
        ];
    }

    // ─── FICHE ÉLÈVE RAPIDE ───

    public function getFicheEleve(int $eleveId): array
    {
        $eleve = $this->pdo->prepare("SELECT * FROM eleves WHERE id = :id");
        $eleve->execute([':id' => $eleveId]);
        $e = $eleve->fetch(\PDO::FETCH_ASSOC);

        $absences = $this->pdo->prepare("SELECT COUNT(*) AS total, SUM(CASE WHEN justifiee=0 THEN 1 ELSE 0 END) AS nj FROM absences WHERE id_eleve = :eid");
        $absences->execute([':eid' => $eleveId]);

        $incidents = $this->pdo->prepare("SELECT COUNT(*) FROM incidents WHERE eleve_id = :eid");
        $incidents->execute([':eid' => $eleveId]);

        $moyenne = $this->pdo->prepare("SELECT ROUND(AVG(note/note_sur*20),2) FROM notes WHERE id_eleve = :eid");
        $moyenne->execute([':eid' => $eleveId]);

        return [
            'eleve' => $e,
            'absences' => $absences->fetch(\PDO::FETCH_ASSOC),
            'nb_incidents' => (int)$incidents->fetchColumn(),
            'moyenne_generale' => $moyenne->fetchColumn()
        ];
    }

    // ─── TIMELINE ÉLÈVE ───

    public function getTimelineEleve(int $eleveId, int $limit = 50): array
    {
        $events = [];

        $abs = $this->pdo->prepare("SELECT 'absence' AS type, date_absence AS date, motif AS detail FROM absences WHERE id_eleve = :eid ORDER BY date_absence DESC LIMIT 20");
        $abs->execute([':eid' => $eleveId]);
        foreach ($abs as $a) $events[] = $a;

        $inc = $this->pdo->prepare("SELECT 'incident' AS type, date_incident AS date, description AS detail FROM incidents WHERE eleve_id = :eid ORDER BY date_incident DESC LIMIT 20");
        $inc->execute([':eid' => $eleveId]);
        foreach ($inc as $i) $events[] = $i;

        $notes = $this->pdo->prepare("SELECT 'note' AS type, date_note AS date, CONCAT(note,'/',note_sur) AS detail FROM notes WHERE id_eleve = :eid ORDER BY date_note DESC LIMIT 20");
        $notes->execute([':eid' => $eleveId]);
        foreach ($notes as $n) $events[] = $n;

        usort($events, fn($a, $b) => strtotime($b['date']) - strtotime($a['date']));
        return array_slice($events, 0, $limit);
    }

    // ─── ALERTES CROSS-MODULE ───

    public function getAlertesActives(int $etabId): array
    {
        $alertes = [];

        // Absences non justifiées > 3 jours
        $abs = $this->pdo->prepare("SELECT a.id_eleve, CONCAT(e.prenom,' ',e.nom) AS eleve, e.classe, COUNT(*) AS nb FROM absences a JOIN eleves e ON a.id_eleve = e.id WHERE a.justifiee = 0 AND a.date_absence >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND e.actif = 1 GROUP BY a.id_eleve HAVING nb >= 3 ORDER BY nb DESC");
        $abs->execute();
        foreach ($abs as $a) $alertes[] = ['type' => 'absences_repetees', 'eleve' => $a['eleve'], 'classe' => $a['classe'], 'detail' => $a['nb'] . ' absences non justifiées'];

        // Incidents graves récents
        $inc = $this->pdo->prepare("SELECT i.eleve_id, CONCAT(e.prenom,' ',e.nom) AS eleve, e.classe, i.description FROM incidents i JOIN eleves e ON i.eleve_id = e.id WHERE i.gravite = 'grave' AND i.date_incident >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND i.etablissement_id = :eid ORDER BY i.date_incident DESC LIMIT 10");
        $inc->execute([':eid' => $etabId]);
        foreach ($inc as $i) $alertes[] = ['type' => 'incident_grave', 'eleve' => $i['eleve'], 'classe' => $i['classe'], 'detail' => $i['description']];

        return $alertes;
    }
}
