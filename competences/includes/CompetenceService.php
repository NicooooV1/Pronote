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
}
