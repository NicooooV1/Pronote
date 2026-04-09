<?php
/**
 * M37 – Besoins particuliers — Service
 */
class BesoinService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /* ───── PLANS ───── */

    public function getPlans(array $filters = []): array
    {
        $sql = "SELECT pa.*, CONCAT(e.prenom, ' ', e.nom) AS eleve_nom, cl.nom AS classe_nom,
                       CONCAT(r.prenom, ' ', r.nom) AS responsable_nom
                FROM plans_accompagnement pa
                JOIN eleves e ON pa.eleve_id = e.id
                LEFT JOIN classes cl ON e.classe_id = cl.id
                LEFT JOIN professeurs r ON pa.responsable_id = r.id
                WHERE 1=1";
        $params = [];
        if (!empty($filters['type'])) { $sql .= ' AND pa.type = ?'; $params[] = $filters['type']; }
        if (!empty($filters['statut'])) { $sql .= ' AND pa.statut = ?'; $params[] = $filters['statut']; }
        if (!empty($filters['eleve_id'])) { $sql .= ' AND pa.eleve_id = ?'; $params[] = $filters['eleve_id']; }
        if (!empty($filters['responsable_id'])) { $sql .= ' AND pa.responsable_id = ?'; $params[] = $filters['responsable_id']; }
        $sql .= ' ORDER BY pa.created_at DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPlan(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT pa.*, CONCAT(e.prenom, ' ', e.nom) AS eleve_nom, cl.nom AS classe_nom,
                   CONCAT(r.prenom, ' ', r.nom) AS responsable_nom
            FROM plans_accompagnement pa
            JOIN eleves e ON pa.eleve_id = e.id
            LEFT JOIN classes cl ON e.classe_id = cl.id
            LEFT JOIN professeurs r ON pa.responsable_id = r.id
            WHERE pa.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function creerPlan(array $d): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO plans_accompagnement (eleve_id, type, amenagements, responsable_id, date_debut, date_fin, statut, document_path) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([$d['eleve_id'], $d['type'], $d['amenagements'] ?? null, $d['responsable_id'] ?: null, $d['date_debut'], $d['date_fin'] ?: null, 'actif', $d['document_path'] ?? null]);
        return $this->pdo->lastInsertId();
    }

    public function modifierPlan(int $id, array $d): void
    {
        $stmt = $this->pdo->prepare("UPDATE plans_accompagnement SET type=?, amenagements=?, responsable_id=?, date_debut=?, date_fin=?, statut=?, document_path=? WHERE id=?");
        $stmt->execute([$d['type'], $d['amenagements'], $d['responsable_id'] ?: null, $d['date_debut'], $d['date_fin'], $d['statut'], $d['document_path'] ?? null, $id]);
    }

    public function getPlansEleve(int $eleveId): array
    {
        return $this->getPlans(['eleve_id' => $eleveId]);
    }

    public function getPlansParent(int $parentId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT pa.*, CONCAT(e.prenom, ' ', e.nom) AS eleve_nom, cl.nom AS classe_nom,
                   CONCAT(r.prenom, ' ', r.nom) AS responsable_nom
            FROM plans_accompagnement pa
            JOIN eleves e ON pa.eleve_id = e.id
            LEFT JOIN classes cl ON e.classe_id = cl.id
            LEFT JOIN professeurs r ON pa.responsable_id = r.id
            JOIN parent_eleve pe ON pe.eleve_id = e.id
            WHERE pe.parent_id = ?
            ORDER BY pa.created_at DESC
        ");
        $stmt->execute([$parentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ───── SUIVIS ───── */

    public function getSuivis(int $planId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT ps.*,
                   COALESCE(
                       (SELECT CONCAT(prenom, ' ', nom) FROM professeurs WHERE id = ps.auteur_id),
                       (SELECT CONCAT(prenom, ' ', nom) FROM administrateurs WHERE id = ps.auteur_id),
                       CONCAT('User #', ps.auteur_id)
                   ) AS auteur_nom
            FROM plan_suivis ps
            WHERE ps.plan_id = ?
            ORDER BY ps.created_at DESC
        ");
        $stmt->execute([$planId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function ajouterSuivi(int $planId, int $auteurId, string $observations, string $progres): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO plan_suivis (plan_id, auteur_id, observations, progres) VALUES (?,?,?,?)");
        $stmt->execute([$planId, $auteurId, $observations, $progres]);
    }

    /* ───── EVALUATIONS PERIODIQUES ───── */

    /**
     * Add a periodic evaluation to a plan.
     */
    public function ajouterEvaluation(int $planId, array $data): void
    {
        $plan = $this->getPlan($planId);
        if (!$plan) return;

        $evaluations = !empty($plan['evaluations']) ? json_decode($plan['evaluations'], true) : [];
        $evaluations[] = [
            'date' => $data['date'] ?? date('Y-m-d'),
            'evaluateur_id' => $data['evaluateur_id'],
            'objectifs_atteints' => $data['objectifs_atteints'] ?? [],
            'commentaire' => $data['commentaire'] ?? '',
            'progres_global' => $data['progres_global'] ?? 'satisfaisant',
            'prochaine_evaluation' => $data['prochaine_evaluation'] ?? null,
        ];

        $this->pdo->prepare("UPDATE plans_accompagnement SET evaluations = ? WHERE id = ?")
                   ->execute([json_encode($evaluations, JSON_UNESCAPED_UNICODE), $planId]);
    }

    /**
     * Get evaluations for a plan.
     */
    public function getEvaluations(int $planId): array
    {
        $plan = $this->getPlan($planId);
        if (!$plan || empty($plan['evaluations'])) return [];
        return json_decode($plan['evaluations'], true) ?: [];
    }

    /**
     * Get plans needing periodic evaluation (last evaluation > 3 months ago or none).
     */
    public function getPlansAEvaluer(): array
    {
        $plans = $this->getPlans(['statut' => 'actif']);
        $result = [];
        $threshold = strtotime('-3 months');

        foreach ($plans as $p) {
            $evals = !empty($p['evaluations']) ? json_decode($p['evaluations'], true) : [];
            $lastEvalDate = null;
            if (!empty($evals)) {
                $lastEval = end($evals);
                $lastEvalDate = strtotime($lastEval['date'] ?? '');
            }

            if ($lastEvalDate === null || $lastEvalDate < $threshold) {
                $p['derniere_evaluation'] = $lastEvalDate ? date('Y-m-d', $lastEvalDate) : null;
                $p['nb_evaluations'] = count($evals);
                $result[] = $p;
            }
        }
        return $result;
    }

    /* ───── HELPERS ───── */

    public function getEleves(): array
    {
        return $this->pdo->query("SELECT e.id, e.prenom, e.nom, cl.nom AS classe_nom FROM eleves e LEFT JOIN classes cl ON e.classe_id = cl.id ORDER BY e.nom")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProfesseurs(): array
    {
        return $this->pdo->query("SELECT id, prenom, nom FROM professeurs ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getStats(): array
    {
        $stmt = $this->pdo->query("SELECT type, COUNT(*) AS c FROM plans_accompagnement WHERE statut='actif' GROUP BY type");
        $stats = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) { $stats[$r['type']] = $r['c']; }
        $total = $this->pdo->query("SELECT COUNT(*) FROM plans_accompagnement WHERE statut='actif'")->fetchColumn();
        return ['par_type' => $stats, 'total_actifs' => $total];
    }

    public static function typesPlan(): array
    {
        return ['PAP' => 'Plan d\'Accompagnement Personnalisé', 'PPS' => 'Projet Personnalisé de Scolarisation', 'PPRE' => 'Programme Personnalisé de Réussite Éducative', 'PAI' => 'Projet d\'Accueil Individualisé'];
    }

    public static function statutsPlan(): array
    {
        return ['actif' => 'Actif', 'termine' => 'Terminé', 'suspendu' => 'Suspendu'];
    }

    public static function niveauxProgres(): array
    {
        return ['insuffisant' => 'Insuffisant', 'en_difficulte' => 'En difficulté', 'satisfaisant' => 'Satisfaisant', 'tres_bien' => 'Très bien'];
    }

    public static function badgeType(string $type): string
    {
        $m = ['PAP' => 'info', 'PPS' => 'warning', 'PPRE' => 'success', 'PAI' => 'danger'];
        return '<span class="badge badge-' . ($m[$type] ?? 'secondary') . '">' . $type . '</span>';
    }

    public static function badgeProgres(string $p): string
    {
        $m = ['insuffisant' => 'danger', 'en_difficulte' => 'warning', 'satisfaisant' => 'info', 'tres_bien' => 'success'];
        return '<span class="badge badge-' . ($m[$p] ?? 'secondary') . '">' . ucfirst(str_replace('_', ' ', $p)) . '</span>';
    }

    // ─── NOTES MULTI-INTERVENANTS ───

    /**
     * Ajoute une observation d'un intervenant sur un plan d'accompagnement.
     */
    public function ajouterObservation(int $planId, int $auteurId, string $auteurType, string $texte): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO besoins_observations (plan_id, auteur_id, auteur_type, texte, date_observation) VALUES (:pid, :aid, :at, :t, NOW())");
        $stmt->execute([':pid' => $planId, ':aid' => $auteurId, ':at' => $auteurType, ':t' => $texte]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getObservations(int $planId): array
    {
        $stmt = $this->pdo->prepare("SELECT o.*, CASE WHEN o.auteur_type = 'professeur' THEN (SELECT CONCAT(pr.prenom,' ',pr.nom) FROM professeurs pr WHERE pr.id = o.auteur_id) ELSE CONCAT(o.auteur_type,'#',o.auteur_id) END AS auteur_nom FROM besoins_observations o WHERE o.plan_id = :pid ORDER BY o.date_observation DESC");
        $stmt->execute([':pid' => $planId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── VISUALISATION PROGRESSION ───

    /**
     * Données de progression d'un plan au fil du temps.
     */
    public function getProgressionChart(int $planId): array
    {
        $stmt = $this->pdo->prepare("SELECT date_evaluation, progres FROM plan_evaluations WHERE plan_id = :pid ORDER BY date_evaluation ASC");
        $stmt->execute([':pid' => $planId]);
        $evals = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $niveaux = ['insuffisant' => 1, 'en_difficulte' => 2, 'satisfaisant' => 3, 'tres_bien' => 4];
        $dates = [];
        $values = [];
        foreach ($evals as $e) {
            $dates[] = $e['date_evaluation'];
            $values[] = $niveaux[$e['progres']] ?? 0;
        }

        return ['dates' => $dates, 'values' => $values, 'labels' => self::niveauxProgres()];
    }

    // ─── TEMPLATES PLANS ───

    /**
     * Récupère les modèles de plan disponibles.
     */
    public function getModelesPlan(string $typePlan = ''): array
    {
        $sql = "SELECT * FROM besoins_modeles";
        $params = [];
        if ($typePlan) { $sql .= " WHERE type_plan = :tp"; $params[':tp'] = $typePlan; }
        $sql .= " ORDER BY type_plan, titre";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Crée un plan à partir d'un modèle.
     */
    public function creerDepuisModele(int $modeleId, int $eleveId, int $createurId): int
    {
        $modele = $this->pdo->prepare("SELECT * FROM besoins_modeles WHERE id = :id");
        $modele->execute([':id' => $modeleId]);
        $m = $modele->fetch(PDO::FETCH_ASSOC);
        if (!$m) throw new \RuntimeException('Modèle introuvable');

        $stmt = $this->pdo->prepare("INSERT INTO plans_accompagnement (eleve_id, type_plan, description, amenagements, objectifs, createur_id, statut) VALUES (:eid, :tp, :d, :a, :o, :cid, 'actif')");
        $stmt->execute([':eid' => $eleveId, ':tp' => $m['type_plan'], ':d' => $m['description'], ':a' => $m['amenagements_defaut'], ':o' => $m['objectifs_defaut'] ?? '', ':cid' => $createurId]);
        return (int)$this->pdo->lastInsertId();
    }

    // ─── ALERTES EXPIRATION ───

    /**
     * Retourne les plans qui expirent prochainement (pour cron).
     */
    public function alertExpiringSoon(int $daysBefore = 30): array
    {
        $stmt = $this->pdo->prepare("SELECT pa.*, CONCAT(e.prenom,' ',e.nom) AS eleve_nom, e.classe FROM plans_accompagnement pa JOIN eleves e ON pa.eleve_id = e.id WHERE pa.statut = 'actif' AND pa.date_fin BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :d DAY) ORDER BY pa.date_fin ASC");
        $stmt->execute([':d' => $daysBefore]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
