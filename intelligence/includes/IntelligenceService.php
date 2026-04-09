<?php
declare(strict_types=1);

namespace Intelligence;

use PDO;

/**
 * IntelligenceService — Analyse Prédictive & IA.
 *
 * Score de risque décrochage (absences 30%, notes 35%, discipline 20%, engagement 15%),
 * dashboard RAG, détection patterns, recommandations, alertes auto.
 */
class IntelligenceService
{
    private PDO $pdo;

    private array $poidsDefaut = [
        'absences' => 0.30,
        'notes' => 0.35,
        'discipline' => 0.20,
        'engagement' => 0.15
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ─── Calcul scores ────────────────────────────────────────────

    public function calculerScoreEleve(int $eleveId, int $etabId): array
    {
        $poids = $this->getPoidsConfig($etabId);

        $scoreAbsences = $this->calculerScoreAbsences($eleveId);
        $scoreNotes = $this->calculerScoreNotes($eleveId);
        $scoreDiscipline = $this->calculerScoreDiscipline($eleveId);
        $scoreEngagement = $this->calculerScoreEngagement($eleveId);

        $scoreRisque = round(
            $scoreAbsences * $poids['absences'] +
            $scoreNotes * $poids['notes'] +
            $scoreDiscipline * $poids['discipline'] +
            $scoreEngagement * $poids['engagement'],
            2
        );

        $seuils = $this->getSeuilsConfig($etabId);
        if ($scoreRisque >= $seuils['rouge']) $niveau = 'rouge';
        elseif ($scoreRisque >= $seuils['orange']) $niveau = 'orange';
        elseif ($scoreRisque >= $seuils['jaune']) $niveau = 'jaune';
        else $niveau = 'vert';

        $facteurs = $this->identifierFacteurs($scoreAbsences, $scoreNotes, $scoreDiscipline, $scoreEngagement);
        $recommandations = $this->genererRecommandations($niveau, $facteurs);

        // Persist
        $this->pdo->prepare("INSERT INTO intelligence_scores (etablissement_id, eleve_id, score_risque, score_absences, score_notes, score_discipline, score_engagement, niveau_alerte, facteurs_json, recommandations, date_calcul) VALUES (:eid, :elid, :sr, :sa, :sn, :sd, :se, :na, :f, :r, NOW()) ON DUPLICATE KEY UPDATE score_risque=VALUES(score_risque), score_absences=VALUES(score_absences), score_notes=VALUES(score_notes), score_discipline=VALUES(score_discipline), score_engagement=VALUES(score_engagement), niveau_alerte=VALUES(niveau_alerte), facteurs_json=VALUES(facteurs_json), recommandations=VALUES(recommandations), date_calcul=NOW()")
            ->execute([':eid' => $etabId, ':elid' => $eleveId, ':sr' => $scoreRisque, ':sa' => $scoreAbsences, ':sn' => $scoreNotes, ':sd' => $scoreDiscipline, ':se' => $scoreEngagement, ':na' => $niveau, ':f' => json_encode($facteurs), ':r' => json_encode($recommandations)]);

        return [
            'eleve_id' => $eleveId,
            'score_risque' => $scoreRisque,
            'scores' => compact('scoreAbsences', 'scoreNotes', 'scoreDiscipline', 'scoreEngagement'),
            'niveau_alerte' => $niveau,
            'facteurs' => $facteurs,
            'recommandations' => $recommandations
        ];
    }

    private function calculerScoreAbsences(int $eleveId): float
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) AS total, SUM(CASE WHEN justifiee = 0 THEN 1 ELSE 0 END) AS nj FROM absences WHERE id_eleve = :eid AND date_absence >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)");
        $stmt->execute([':eid' => $eleveId]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        $total = (int)$r['total'];
        $nj = (int)$r['nj'];

        // Score 0-100 : 0 absences = 0, 20+ non justifiées = 100
        return min(100, ($nj * 5) + ($total * 1.5));
    }

    private function calculerScoreNotes(int $eleveId): float
    {
        $stmt = $this->pdo->prepare("SELECT ROUND(AVG(note / note_sur * 20),2) AS moyenne FROM notes WHERE id_eleve = :eid AND date_evaluation >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)");
        $stmt->execute([':eid' => $eleveId]);
        $moyenne = (float)($stmt->fetchColumn() ?: 10);

        // Score inversé : 20/20 = 0 risque, 0/20 = 100 risque
        return max(0, min(100, (20 - $moyenne) * 5));
    }

    private function calculerScoreDiscipline(int $eleveId): float
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) AS incidents, SUM(CASE WHEN gravite = 'grave' THEN 3 WHEN gravite = 'moyen' THEN 2 ELSE 1 END) AS poids_total FROM incidents WHERE eleve_id = :eid AND date_incident >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)");
        $stmt->execute([':eid' => $eleveId]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        $poids = (int)($r['poids_total'] ?? 0);

        // Score 0-100 : chaque point de poids = 10, plafonné à 100
        return min(100, $poids * 10);
    }

    private function calculerScoreEngagement(int $eleveId): float
    {
        // Mesure inverse : moins d'engagement = plus de risque
        $devoirs = $this->pdo->prepare("SELECT COUNT(*) AS total, SUM(CASE WHEN dr.id IS NOT NULL THEN 1 ELSE 0 END) AS rendus FROM devoirs d LEFT JOIN devoirs_rendus dr ON dr.devoir_id = d.id AND dr.eleve_id = :eid WHERE d.classe = (SELECT classe FROM eleves WHERE id = :eid2) AND d.date_limite >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)");
        $devoirs->execute([':eid' => $eleveId, ':eid2' => $eleveId]);
        $r = $devoirs->fetch(PDO::FETCH_ASSOC);

        $total = (int)$r['total'];
        $rendus = (int)$r['rendus'];

        if ($total === 0) return 30; // Score neutre si pas de devoirs

        $tauxRendu = $rendus / $total;
        return max(0, min(100, (1 - $tauxRendu) * 100));
    }

    private function identifierFacteurs(float $abs, float $notes, float $disc, float $eng): array
    {
        $facteurs = [];
        if ($abs >= 50) $facteurs[] = ['type' => 'absences', 'score' => $abs, 'message' => 'Taux d\'absences élevé'];
        if ($notes >= 50) $facteurs[] = ['type' => 'notes', 'score' => $notes, 'message' => 'Résultats scolaires en difficulté'];
        if ($disc >= 50) $facteurs[] = ['type' => 'discipline', 'score' => $disc, 'message' => 'Incidents disciplinaires fréquents'];
        if ($eng >= 50) $facteurs[] = ['type' => 'engagement', 'score' => $eng, 'message' => 'Faible engagement (devoirs non rendus)'];
        usort($facteurs, fn($a, $b) => $b['score'] <=> $a['score']);
        return $facteurs;
    }

    private function genererRecommandations(string $niveau, array $facteurs): array
    {
        $recs = [];
        if ($niveau === 'rouge' || $niveau === 'orange') {
            $recs[] = 'Convoquer un entretien avec l\'élève et la famille';
        }
        foreach ($facteurs as $f) {
            switch ($f['type']) {
                case 'absences': $recs[] = 'Mettre en place un suivi d\'assiduité renforcé'; break;
                case 'notes': $recs[] = 'Proposer un tutorat ou du soutien scolaire'; break;
                case 'discipline': $recs[] = 'Envisager un contrat de comportement'; break;
                case 'engagement': $recs[] = 'Contacter le professeur principal pour un point'; break;
            }
        }
        if ($niveau === 'rouge') {
            $recs[] = 'Signaler à l\'équipe de direction pour suivi GPDS';
        }
        return array_unique($recs);
    }

    // ─── Calcul en masse ──────────────────────────────────────────

    public function recalculerTous(int $etabId): int
    {
        $eleves = $this->pdo->prepare("SELECT id FROM eleves WHERE actif = 1");
        $eleves->execute();
        $count = 0;
        foreach ($eleves as $e) {
            $this->calculerScoreEleve($e['id'], $etabId);
            $count++;
        }
        return $count;
    }

    // ─── Dashboard ────────────────────────────────────────────────

    public function getDashboardRisque(int $etabId): array
    {
        $stmt = $this->pdo->prepare("SELECT s.niveau_alerte, COUNT(*) AS nb FROM intelligence_scores s JOIN eleves e ON s.eleve_id = e.id WHERE s.etablissement_id = :eid AND e.actif = 1 GROUP BY s.niveau_alerte");
        $stmt->execute([':eid' => $etabId]);
        $distribution = ['vert' => 0, 'jaune' => 0, 'orange' => 0, 'rouge' => 0];
        foreach ($stmt as $row) {
            $distribution[$row['niveau_alerte']] = (int)$row['nb'];
        }

        $topRisque = $this->pdo->prepare("SELECT s.eleve_id, CONCAT(e.prenom,' ',e.nom) AS eleve, e.classe, s.score_risque, s.niveau_alerte, s.facteurs_json FROM intelligence_scores s JOIN eleves e ON s.eleve_id = e.id WHERE s.etablissement_id = :eid AND e.actif = 1 AND s.niveau_alerte IN ('rouge','orange') ORDER BY s.score_risque DESC LIMIT 20");
        $topRisque->execute([':eid' => $etabId]);

        return [
            'distribution' => $distribution,
            'total_eleves' => array_sum($distribution),
            'eleves_a_risque' => $topRisque->fetchAll(PDO::FETCH_ASSOC)
        ];
    }

    // ─── Alertes ──────────────────────────────────────────────────

    public function genererAlertes(int $etabId): int
    {
        $elevesRouge = $this->pdo->prepare("SELECT s.eleve_id, s.score_risque, s.facteurs_json FROM intelligence_scores s JOIN eleves e ON s.eleve_id = e.id WHERE s.etablissement_id = :eid AND s.niveau_alerte = 'rouge' AND e.actif = 1 AND s.eleve_id NOT IN (SELECT eleve_id FROM intelligence_alertes WHERE etablissement_id = :eid2 AND statut = 'active' AND type_alerte = 'risque_eleve')");
        $elevesRouge->execute([':eid' => $etabId, ':eid2' => $etabId]);
        $count = 0;

        foreach ($elevesRouge as $e) {
            $this->pdo->prepare("INSERT INTO intelligence_alertes (etablissement_id, eleve_id, type_alerte, niveau, message, details_json, statut) VALUES (:eid, :elid, 'risque_eleve', 'rouge', :msg, :det, 'active')")
                ->execute([':eid' => $etabId, ':elid' => $e['eleve_id'], ':msg' => 'Élève en risque élevé de décrochage (score: ' . $e['score_risque'] . ')', ':det' => $e['facteurs_json']]);
            $count++;
        }

        return $count;
    }

    public function getAlertes(int $etabId, string $statut = 'active'): array
    {
        $stmt = $this->pdo->prepare("SELECT a.*, CONCAT(e.prenom,' ',e.nom) AS eleve_nom, e.classe FROM intelligence_alertes a JOIN eleves e ON a.eleve_id = e.id WHERE a.etablissement_id = :eid AND a.statut = :s ORDER BY a.created_at DESC");
        $stmt->execute([':eid' => $etabId, ':s' => $statut]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function traiterAlerte(int $alerteId, string $action, int $traitePar): void
    {
        $this->pdo->prepare("UPDATE intelligence_alertes SET statut = 'traitee', action_prise = :a, traite_par = :tp, date_traitement = NOW() WHERE id = :id")
            ->execute([':a' => $action, ':tp' => $traitePar, ':id' => $alerteId]);
    }

    // ─── Patterns ─────────────────────────────────────────────────

    public function detecterPatterns(int $eleveId): array
    {
        $patterns = [];

        // Pattern : chute de notes
        $evolution = $this->pdo->prepare("SELECT ROUND(AVG(note / note_sur * 20),2) AS moy, DATE_FORMAT(date_evaluation, '%Y-%m') AS mois FROM notes WHERE id_eleve = :eid AND date_evaluation >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY mois ORDER BY mois");
        $evolution->execute([':eid' => $eleveId]);
        $mois = $evolution->fetchAll(PDO::FETCH_ASSOC);

        if (count($mois) >= 3) {
            $derniers = array_slice($mois, -3);
            if ($derniers[0]['moy'] > $derniers[1]['moy'] && $derniers[1]['moy'] > $derniers[2]['moy']) {
                $patterns[] = ['type' => 'chute_notes', 'message' => 'Baisse continue des résultats sur 3 mois', 'data' => $derniers];
            }
        }

        // Pattern : absences croissantes
        $absEvol = $this->pdo->prepare("SELECT COUNT(*) AS nb, DATE_FORMAT(date_absence, '%Y-%m') AS mois FROM absences WHERE id_eleve = :eid AND date_absence >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY mois ORDER BY mois");
        $absEvol->execute([':eid' => $eleveId]);
        $absMois = $absEvol->fetchAll(PDO::FETCH_ASSOC);

        if (count($absMois) >= 3) {
            $derniers = array_slice($absMois, -3);
            if ($derniers[0]['nb'] < $derniers[1]['nb'] && $derniers[1]['nb'] < $derniers[2]['nb']) {
                $patterns[] = ['type' => 'absences_croissantes', 'message' => 'Augmentation continue des absences sur 3 mois', 'data' => $derniers];
            }
        }

        // Pattern : absences ciblées (même matière)
        $absMat = $this->pdo->prepare("SELECT m.nom AS matiere, COUNT(*) AS nb FROM absences a JOIN emploi_du_temps edt ON a.date_absence = DATE(edt.date_debut) JOIN matieres m ON edt.id_matiere = m.id WHERE a.id_eleve = :eid AND a.date_absence >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) GROUP BY m.id HAVING nb >= 3 ORDER BY nb DESC");
        $absMat->execute([':eid' => $eleveId]);
        $ciblees = $absMat->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($ciblees)) {
            $patterns[] = ['type' => 'absences_ciblees', 'message' => 'Absences concentrées sur certaines matières', 'data' => $ciblees];
        }

        return $patterns;
    }

    // ─── Évolution ────────────────────────────────────────────────

    public function getEvolutionScore(int $eleveId, int $nbMois = 6): array
    {
        $stmt = $this->pdo->prepare("SELECT score_risque, niveau_alerte, date_calcul FROM intelligence_scores WHERE eleve_id = :eid AND date_calcul >= DATE_SUB(CURDATE(), INTERVAL :m MONTH) ORDER BY date_calcul");
        $stmt->execute([':eid' => $eleveId, ':m' => $nbMois]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── Configuration ────────────────────────────────────────────

    private function getPoidsConfig(int $etabId): array
    {
        $stmt = $this->pdo->prepare("SELECT poids_absences, poids_notes, poids_discipline, poids_engagement FROM intelligence_config WHERE etablissement_id = :eid");
        $stmt->execute([':eid' => $etabId]);
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$config) return $this->poidsDefaut;
        return [
            'absences' => (float)$config['poids_absences'],
            'notes' => (float)$config['poids_notes'],
            'discipline' => (float)$config['poids_discipline'],
            'engagement' => (float)$config['poids_engagement']
        ];
    }

    private function getSeuilsConfig(int $etabId): array
    {
        $stmt = $this->pdo->prepare("SELECT seuil_rouge, seuil_orange, seuil_jaune FROM intelligence_config WHERE etablissement_id = :eid");
        $stmt->execute([':eid' => $etabId]);
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        return [
            'rouge' => (int)($config['seuil_rouge'] ?? 70),
            'orange' => (int)($config['seuil_orange'] ?? 50),
            'jaune' => (int)($config['seuil_jaune'] ?? 30)
        ];
    }
}
