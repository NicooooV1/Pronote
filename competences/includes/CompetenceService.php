<?php
/**
 * M38 – Compétences & Évaluations — Service
 */

class CompetenceService {
    private PDO $pdo;

    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    /* ==================== RÉFÉRENTIEL ==================== */

    /**
     * Récupère l'arborescence des compétences
     */
    public function getArbreCompetences(): array {
        $all = $this->pdo->query("SELECT * FROM competences ORDER BY domaine, ordre, code")->fetchAll(PDO::FETCH_ASSOC);
        $tree = [];
        $byId = [];
        foreach ($all as $c) {
            $c['children'] = [];
            $byId[$c['id']] = $c;
        }
        foreach ($byId as &$c) {
            if ($c['parent_id'] && isset($byId[$c['parent_id']])) {
                $byId[$c['parent_id']]['children'][] = &$c;
            } else {
                $tree[] = &$c;
            }
        }
        return $tree;
    }

    /**
     * Récupère les compétences racines (domaines)
     */
    public function getDomaines(): array {
        return $this->pdo->query("SELECT * FROM competences WHERE parent_id IS NULL ORDER BY ordre, code")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les sous-compétences d'un domaine
     */
    public function getSousCompetences(int $parentId): array {
        $stmt = $this->pdo->prepare("SELECT * FROM competences WHERE parent_id = ? ORDER BY ordre, code");
        $stmt->execute([$parentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère toutes les compétences (flat)
     */
    public function getCompetencesFlat(): array {
        return $this->pdo->query("SELECT * FROM competences ORDER BY domaine, ordre")->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ==================== ÉVALUATIONS ==================== */

    /**
     * Évaluer un élève sur une compétence
     */
    public function evaluer(array $data): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO competence_evaluations (eleve_id, competence_id, professeur_id, matiere_id, niveau_acquis, commentaire, date_evaluation, periode_id)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)
            ON DUPLICATE KEY UPDATE
                niveau_acquis = VALUES(niveau_acquis),
                commentaire = VALUES(commentaire),
                date_evaluation = NOW()
        ");
        $stmt->execute([
            $data['eleve_id'],
            $data['competence_id'],
            $data['professeur_id'],
            $data['matiere_id'] ?? null,
            $data['niveau_acquis'],
            $data['commentaire'] ?? '',
            $data['periode_id'] ?? null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Évaluation en lot : tous les élèves d'une classe sur une compétence
     */
    public function evaluerLot(int $competenceId, int $profId, ?int $matiereId, ?int $periodeId, array $evaluations): int {
        $count = 0;
        foreach ($evaluations as $eleveId => $niveau) {
            if (empty($niveau)) continue;
            $this->evaluer([
                'eleve_id' => $eleveId,
                'competence_id' => $competenceId,
                'professeur_id' => $profId,
                'matiere_id' => $matiereId,
                'niveau_acquis' => $niveau,
                'periode_id' => $periodeId,
            ]);
            $count++;
        }
        return $count;
    }

    /**
     * Récupère les évaluations d'un élève
     */
    public function getEvaluationsEleve(int $eleveId, ?int $periodeId = null): array {
        $sql = "
            SELECT ce.*, c.code, c.nom AS competence_nom, c.domaine,
                   p.nom AS prof_nom, p.prenom AS prof_prenom,
                   m.nom AS matiere_nom
            FROM competence_evaluations ce
            JOIN competences c ON ce.competence_id = c.id
            LEFT JOIN professeurs p ON ce.professeur_id = p.id
            LEFT JOIN matieres m ON ce.matiere_id = m.id
            WHERE ce.eleve_id = ?
        ";
        $params = [$eleveId];
        if ($periodeId) {
            $sql .= " AND ce.periode_id = ?";
            $params[] = $periodeId;
        }
        $sql .= " ORDER BY c.domaine, c.ordre";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les évaluations d'une classe pour une compétence
     */
    public function getEvaluationsClasse(int $classeId, int $competenceId, ?int $periodeId = null): array {
        $sql = "
            SELECT ce.*, e.nom AS eleve_nom, e.prenom AS eleve_prenom
            FROM competence_evaluations ce
            JOIN eleves e ON ce.eleve_id = e.id
            WHERE e.classe_id = ? AND ce.competence_id = ?
        ";
        $params = [$classeId, $competenceId];
        if ($periodeId) {
            $sql .= " AND ce.periode_id = ?";
            $params[] = $periodeId;
        }
        $sql .= " ORDER BY e.nom, e.prenom";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Bilan compétences d'un élève : résumé par domaine
     */
    public function getBilanEleve(int $eleveId, ?int $periodeId = null): array {
        $evaluations = $this->getEvaluationsEleve($eleveId, $periodeId);
        $bilan = [];
        $niveaux = self::niveauxValues();

        foreach ($evaluations as $eval) {
            $domaine = $eval['domaine'] ?: 'Autre';
            if (!isset($bilan[$domaine])) {
                $bilan[$domaine] = ['domaine' => $domaine, 'evaluations' => [], 'total' => 0, 'count' => 0];
            }
            $bilan[$domaine]['evaluations'][] = $eval;
            if (isset($niveaux[$eval['niveau_acquis']])) {
                $bilan[$domaine]['total'] += $niveaux[$eval['niveau_acquis']];
                $bilan[$domaine]['count']++;
            }
        }

        // Calculer le niveau moyen par domaine
        foreach ($bilan as &$d) {
            if ($d['count'] > 0) {
                $avg = $d['total'] / $d['count'];
                $d['niveau_moyen'] = self::niveauFromValue($avg);
            } else {
                $d['niveau_moyen'] = 'non_evalue';
            }
        }
        return $bilan;
    }

    /**
     * Statistiques d'une classe par compétence
     */
    public function getStatsClasse(int $classeId, ?int $periodeId = null): array {
        $sql = "
            SELECT c.id AS competence_id, c.code, c.nom AS competence_nom, c.domaine,
                   ce.niveau_acquis, COUNT(*) AS nb
            FROM competence_evaluations ce
            JOIN competences c ON ce.competence_id = c.id
            JOIN eleves e ON ce.eleve_id = e.id
            WHERE e.classe_id = ?
        ";
        $params = [$classeId];
        if ($periodeId) {
            $sql .= " AND ce.periode_id = ?";
            $params[] = $periodeId;
        }
        $sql .= " GROUP BY c.id, ce.niveau_acquis ORDER BY c.domaine, c.ordre";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stats = [];
        foreach ($rows as $r) {
            $cid = $r['competence_id'];
            if (!isset($stats[$cid])) {
                $stats[$cid] = [
                    'code' => $r['code'], 'nom' => $r['competence_nom'], 'domaine' => $r['domaine'],
                    'distribution' => ['non_evalue' => 0, 'non_acquis' => 0, 'en_cours' => 0, 'acquis' => 0, 'depasse' => 0],
                    'total' => 0,
                ];
            }
            $stats[$cid]['distribution'][$r['niveau_acquis']] = (int)$r['nb'];
            $stats[$cid]['total'] += (int)$r['nb'];
        }
        return $stats;
    }

    /**
     * Récupère les classes
     */
    public function getClasses(): array {
        return $this->pdo->query("SELECT * FROM classes ORDER BY niveau, nom")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les élèves d'une classe
     */
    public function getElevesClasse(int $classeId): array {
        $stmt = $this->pdo->prepare("SELECT * FROM eleves WHERE classe_id = ? ORDER BY nom, prenom");
        $stmt->execute([$classeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les périodes
     */
    public function getPeriodes(): array {
        return $this->pdo->query("SELECT * FROM periodes ORDER BY date_debut")->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ==================== HELPERS ==================== */

    public static function niveauxValues(): array {
        return ['non_acquis' => 1, 'en_cours' => 2, 'acquis' => 3, 'depasse' => 4];
    }

    public static function niveauFromValue(float $avg): string {
        if ($avg >= 3.5) return 'depasse';
        if ($avg >= 2.5) return 'acquis';
        if ($avg >= 1.5) return 'en_cours';
        return 'non_acquis';
    }

    public static function niveauxLabels(): array {
        return [
            'non_evalue' => 'Non évalué',
            'non_acquis' => 'Non acquis',
            'en_cours'   => 'En cours d\'acquisition',
            'acquis'     => 'Acquis',
            'depasse'    => 'Dépassé',
        ];
    }

    public static function niveauBadge(string $niveau): string {
        $map = [
            'non_evalue' => 'secondary',
            'non_acquis' => 'danger',
            'en_cours'   => 'warning',
            'acquis'     => 'success',
            'depasse'    => 'info',
        ];
        $label = self::niveauxLabels()[$niveau] ?? $niveau;
        $class = $map[$niveau] ?? 'secondary';
        return "<span class=\"badge badge-{$class}\">{$label}</span>";
    }

    public static function niveauDot(string $niveau): string {
        $colors = ['non_evalue' => '#94a3b8', 'non_acquis' => '#ef4444', 'en_cours' => '#f59e0b', 'acquis' => '#10b981', 'depasse' => '#3b82f6'];
        $color = $colors[$niveau] ?? '#94a3b8';
        return "<span class=\"comp-dot\" style=\"background:{$color}\" title=\"" . htmlspecialchars(self::niveauxLabels()[$niveau] ?? '') . "\"></span>";
    }

    /* ==================== RADAR CHART DATA ==================== */

    /**
     * Get radar chart data for a student: one axis per domain, value = average level (1-4).
     */
    public function getRadarData(int $eleveId, ?int $periodeId = null): array
    {
        $bilan = $this->getBilanEleve($eleveId, $periodeId);
        $niveaux = self::niveauxValues();

        $labels = [];
        $values = [];
        foreach ($bilan as $domaine => $data) {
            $labels[] = $domaine;
            $values[] = $data['count'] > 0 ? round($data['total'] / $data['count'], 2) : 0;
        }

        return ['labels' => $labels, 'values' => $values, 'max' => 4];
    }

    /**
     * Get radar chart data for a class (average per domain across all students).
     */
    public function getRadarClasseData(int $classeId, ?int $periodeId = null): array
    {
        $sql = "
            SELECT c.domaine, ce.niveau_acquis
            FROM competence_evaluations ce
            JOIN competences c ON ce.competence_id = c.id
            JOIN eleves e ON ce.eleve_id = e.id
            WHERE e.classe_id = ?
        ";
        $params = [$classeId];
        if ($periodeId) {
            $sql .= " AND ce.periode_id = ?";
            $params[] = $periodeId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $niveaux = self::niveauxValues();
        $domaines = [];
        foreach ($rows as $r) {
            $d = $r['domaine'] ?: 'Autre';
            if (!isset($domaines[$d])) $domaines[$d] = ['total' => 0, 'count' => 0];
            if (isset($niveaux[$r['niveau_acquis']])) {
                $domaines[$d]['total'] += $niveaux[$r['niveau_acquis']];
                $domaines[$d]['count']++;
            }
        }

        $labels = [];
        $values = [];
        foreach ($domaines as $d => $data) {
            $labels[] = $d;
            $values[] = $data['count'] > 0 ? round($data['total'] / $data['count'], 2) : 0;
        }

        return ['labels' => $labels, 'values' => $values, 'max' => 4];
    }

    /* ==================== REFERENTIEL CRUD ==================== */

    /**
     * Create a new competence in the referentiel.
     */
    public function createCompetence(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO competences (code, nom, description, domaine, parent_id, ordre, niveau_attendu)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['code'],
            $data['nom'],
            $data['description'] ?? '',
            $data['domaine'] ?? '',
            $data['parent_id'] ?: null,
            $data['ordre'] ?? 0,
            $data['niveau_attendu'] ?? 'acquis',
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Update a competence.
     */
    public function updateCompetence(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE competences SET code = ?, nom = ?, description = ?, domaine = ?, parent_id = ?, ordre = ?, niveau_attendu = ?
            WHERE id = ?
        ");
        return $stmt->execute([
            $data['code'],
            $data['nom'],
            $data['description'] ?? '',
            $data['domaine'] ?? '',
            $data['parent_id'] ?: null,
            $data['ordre'] ?? 0,
            $data['niveau_attendu'] ?? 'acquis',
            $id,
        ]);
    }

    /**
     * Delete a competence (and its children).
     */
    public function deleteCompetence(int $id): bool
    {
        $this->pdo->beginTransaction();
        try {
            // Delete child evaluations
            $this->pdo->prepare("DELETE FROM competence_evaluations WHERE competence_id IN (SELECT id FROM competences WHERE parent_id = ?)")->execute([$id]);
            // Delete own evaluations
            $this->pdo->prepare("DELETE FROM competence_evaluations WHERE competence_id = ?")->execute([$id]);
            // Delete children
            $this->pdo->prepare("DELETE FROM competences WHERE parent_id = ?")->execute([$id]);
            // Delete self
            $this->pdo->prepare("DELETE FROM competences WHERE id = ?")->execute([$id]);
            $this->pdo->commit();
            return true;
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    /**
     * Get a competence by ID.
     */
    public function getCompetenceById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM competences WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /* ==================== EXPORT ==================== */

    public function getEvaluationsForExport(int $classeId, ?int $periodeId = null): array
    {
        $sql = "
            SELECT e.nom AS eleve_nom, e.prenom AS eleve_prenom, c.code, c.nom AS competence_nom,
                   c.domaine, ce.niveau_acquis, ce.date_evaluation,
                   CONCAT(p.prenom, ' ', p.nom) AS prof_nom, m.nom AS matiere_nom
            FROM competence_evaluations ce
            JOIN eleves e ON ce.eleve_id = e.id
            JOIN competences c ON ce.competence_id = c.id
            LEFT JOIN professeurs p ON ce.professeur_id = p.id
            LEFT JOIN matieres m ON ce.matiere_id = m.id
            WHERE e.classe_id = ?
        ";
        $params = [$classeId];
        if ($periodeId) { $sql .= ' AND ce.periode_id = ?'; $params[] = $periodeId; }
        $sql .= ' ORDER BY e.nom, c.domaine, c.ordre';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $labels = self::niveauxLabels();
        return array_map(fn($r) => [
            $r['eleve_nom'],
            $r['eleve_prenom'],
            $r['domaine'] ?? '-',
            $r['code'],
            $r['competence_nom'],
            $labels[$r['niveau_acquis']] ?? $r['niveau_acquis'],
            $r['matiere_nom'] ?? '-',
            $r['prof_nom'] ?? '-',
            $r['date_evaluation'],
        ], $rows);
    }

    public function getBilanForExport(int $classeId, ?int $periodeId = null): array
    {
        $eleves = $this->getElevesClasse($classeId);
        $labels = self::niveauxLabels();
        $rows = [];
        foreach ($eleves as $e) {
            $bilan = $this->getBilanEleve($e['id'], $periodeId);
            foreach ($bilan as $d) {
                $rows[] = [
                    $e['nom'],
                    $e['prenom'],
                    $d['domaine'],
                    $labels[$d['niveau_moyen']] ?? $d['niveau_moyen'],
                    $d['count'],
                ];
            }
        }
        return $rows;
    }

    /* ==================== ÉVALUATION EN MASSE ==================== */

    /**
     * Évaluer en masse : tous les élèves d'une classe sur une compétence.
     */
    public function evaluerEnMasse(int $competenceId, int $classeId, array $evals, int $profId, ?int $matiereId = null, ?int $periodeId = null): int
    {
        $count = 0;
        foreach ($evals as $eleveId => $niveau) {
            if (empty($niveau)) continue;
            $this->evaluer([
                'eleve_id' => (int)$eleveId,
                'competence_id' => $competenceId,
                'professeur_id' => $profId,
                'matiere_id' => $matiereId,
                'niveau_acquis' => $niveau,
                'periode_id' => $periodeId,
            ]);
            $count++;
        }
        return $count;
    }

    /* ==================== CROSS-REFERENCE NOTES ==================== */

    /**
     * Suggère des niveaux de compétence basés sur les notes de l'élève.
     * Mapping : < 8/20 => non_acquis, 8-12 => en_cours, 12-16 => acquis, > 16 => depasse
     */
    public function suggestFromNotes(int $eleveId): array
    {
        $stmt = $this->pdo->prepare("SELECT m.id AS matiere_id, m.nom AS matiere, ROUND(AVG(n.note / n.note_sur * 20),2) AS moyenne FROM notes n JOIN matieres m ON n.id_matiere = m.id WHERE n.id_eleve = :eid GROUP BY m.id ORDER BY m.nom");
        $stmt->execute([':eid' => $eleveId]);
        $moyennes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $suggestions = [];
        foreach ($moyennes as $m) {
            $moy = (float)$m['moyenne'];
            if ($moy >= 16) $niveau = 'depasse';
            elseif ($moy >= 12) $niveau = 'acquis';
            elseif ($moy >= 8) $niveau = 'en_cours';
            else $niveau = 'non_acquis';

            // Trouver les compétences liées à cette matière
            $comps = $this->pdo->prepare("SELECT DISTINCT ce.competence_id, c.code, c.nom FROM competence_evaluations ce JOIN competences c ON ce.competence_id = c.id WHERE ce.matiere_id = :mid LIMIT 20");
            $comps->execute([':mid' => $m['matiere_id']]);

            foreach ($comps as $comp) {
                $suggestions[] = [
                    'competence_id' => $comp['competence_id'],
                    'competence' => $comp['code'] . ' - ' . $comp['nom'],
                    'matiere' => $m['matiere'],
                    'moyenne' => $moy,
                    'niveau_suggere' => $niveau
                ];
            }
        }

        return $suggestions;
    }

    /* ==================== EXPORT LSU ==================== */

    /**
     * Export au format LSU (Livret Scolaire Unique) — données structurées.
     */
    public function exportLSU(int $classeId, int $periodeId): array
    {
        $eleves = $this->getElevesClasse($classeId);
        $lsu = [];

        foreach ($eleves as $e) {
            $bilan = $this->getBilanEleve($e['id'], $periodeId);
            $eleveData = [
                'eleve' => ['nom' => $e['nom'], 'prenom' => $e['prenom'], 'id' => $e['id']],
                'domaines' => []
            ];

            foreach ($bilan as $domaine => $data) {
                $eleveData['domaines'][] = [
                    'intitule' => $domaine,
                    'niveau' => $data['niveau_moyen'],
                    'nb_evaluations' => $data['count'],
                    'elements' => array_map(fn($ev) => [
                        'competence' => $ev['competence_nom'],
                        'code' => $ev['code'],
                        'niveau' => $ev['niveau_acquis']
                    ], $data['evaluations'])
                ];
            }

            $lsu[] = $eleveData;
        }

        return ['classe_id' => $classeId, 'periode_id' => $periodeId, 'export_date' => date('Y-m-d'), 'eleves' => $lsu];
    }

    /* ==================== TEMPLATES PAR CYCLE ==================== */

    /**
     * Importe un référentiel pré-construit pour un cycle (3 ou 4).
     */
    public function importerReferentielCycle(int $cycle): int
    {
        $referentiels = [
            3 => [
                ['code' => 'D1.1', 'nom' => 'Comprendre, s\'exprimer en utilisant la langue française à l\'oral et à l\'écrit', 'domaine' => 'D1 - Les langages'],
                ['code' => 'D1.2', 'nom' => 'Comprendre, s\'exprimer en utilisant une langue étrangère', 'domaine' => 'D1 - Les langages'],
                ['code' => 'D1.3', 'nom' => 'Comprendre, s\'exprimer en utilisant les langages mathématiques, scientifiques et informatiques', 'domaine' => 'D1 - Les langages'],
                ['code' => 'D1.4', 'nom' => 'Comprendre, s\'exprimer en utilisant les langages des arts et du corps', 'domaine' => 'D1 - Les langages'],
                ['code' => 'D2', 'nom' => 'Les méthodes et outils pour apprendre', 'domaine' => 'D2 - Méthodes et outils'],
                ['code' => 'D3', 'nom' => 'La formation de la personne et du citoyen', 'domaine' => 'D3 - Formation personne/citoyen'],
                ['code' => 'D4', 'nom' => 'Les systèmes naturels et les systèmes techniques', 'domaine' => 'D4 - Systèmes naturels/techniques'],
                ['code' => 'D5', 'nom' => 'Les représentations du monde et l\'activité humaine', 'domaine' => 'D5 - Représentations du monde'],
            ],
            4 => [
                ['code' => 'D1.1', 'nom' => 'Langue française à l\'oral et à l\'écrit', 'domaine' => 'D1 - Les langages'],
                ['code' => 'D1.2', 'nom' => 'Langues étrangères et régionales', 'domaine' => 'D1 - Les langages'],
                ['code' => 'D1.3', 'nom' => 'Langages mathématiques, scientifiques et informatiques', 'domaine' => 'D1 - Les langages'],
                ['code' => 'D1.4', 'nom' => 'Langages des arts et du corps', 'domaine' => 'D1 - Les langages'],
                ['code' => 'D2', 'nom' => 'Organisation du travail personnel', 'domaine' => 'D2 - Méthodes et outils'],
                ['code' => 'D3', 'nom' => 'Expression de la sensibilité et des opinions, respect des autres', 'domaine' => 'D3 - Formation personne/citoyen'],
                ['code' => 'D4', 'nom' => 'Démarches scientifiques, conception, création, réalisation', 'domaine' => 'D4 - Systèmes naturels/techniques'],
                ['code' => 'D5', 'nom' => 'Raisonnement, imagination, engagement, esprit critique', 'domaine' => 'D5 - Représentations du monde'],
            ]
        ];

        $items = $referentiels[$cycle] ?? [];
        $count = 0;
        foreach ($items as $idx => $item) {
            $this->createCompetence([
                'code' => $item['code'],
                'nom' => $item['nom'],
                'domaine' => $item['domaine'],
                'description' => "Cycle {$cycle} - Socle commun",
                'parent_id' => null,
                'ordre' => $idx + 1,
                'niveau_attendu' => 'acquis'
            ]);
            $count++;
        }
        return $count;
    }
}
