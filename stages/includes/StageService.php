<?php
/**
 * M17 – Stages & Alternance — Service
 */
class StageService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getStages(array $filters = []): array
    {
        $sql = "SELECT s.*, CONCAT(e.prenom, ' ', e.nom) AS eleve_nom, cl.nom AS classe_nom,
                       CONCAT(p.prenom, ' ', p.nom) AS prof_nom
                FROM stages s
                JOIN eleves e ON s.eleve_id = e.id
                LEFT JOIN classes cl ON e.classe_id = cl.id
                LEFT JOIN professeurs p ON s.prof_referent_id = p.id
                WHERE 1=1";
        $params = [];
        if (!empty($filters['type'])) { $sql .= ' AND s.type = ?'; $params[] = $filters['type']; }
        if (!empty($filters['statut'])) { $sql .= ' AND s.statut = ?'; $params[] = $filters['statut']; }
        if (!empty($filters['eleve_id'])) { $sql .= ' AND s.eleve_id = ?'; $params[] = $filters['eleve_id']; }
        if (!empty($filters['prof_referent_id'])) { $sql .= ' AND s.prof_referent_id = ?'; $params[] = $filters['prof_referent_id']; }
        $sql .= ' ORDER BY s.date_debut DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getStage(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT s.*, CONCAT(e.prenom, ' ', e.nom) AS eleve_nom, cl.nom AS classe_nom,
                   CONCAT(p.prenom, ' ', p.nom) AS prof_nom
            FROM stages s
            JOIN eleves e ON s.eleve_id = e.id
            LEFT JOIN classes cl ON e.classe_id = cl.id
            LEFT JOIN professeurs p ON s.prof_referent_id = p.id
            WHERE s.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function creerStage(array $d): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO stages (eleve_id, type, entreprise_nom, entreprise_adresse, entreprise_tel, tuteur_nom, tuteur_email, prof_referent_id, date_debut, date_fin, statut, description) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $d['eleve_id'], $d['type'], $d['entreprise_nom'], $d['entreprise_adresse'] ?? null,
            $d['entreprise_tel'] ?? null, $d['tuteur_nom'] ?? null, $d['tuteur_email'] ?? null,
            $d['prof_referent_id'] ?: null, $d['date_debut'], $d['date_fin'],
            $d['statut'] ?? 'en_recherche', $d['description'] ?? null
        ]);
        return $this->pdo->lastInsertId();
    }

    public function modifierStage(int $id, array $d): void
    {
        $stmt = $this->pdo->prepare("UPDATE stages SET type=?, entreprise_nom=?, entreprise_adresse=?, entreprise_tel=?, tuteur_nom=?, tuteur_email=?, prof_referent_id=?, date_debut=?, date_fin=?, statut=?, description=?, evaluation_entreprise=?, evaluation_prof=?, rapport_path=? WHERE id=?");
        $stmt->execute([
            $d['type'], $d['entreprise_nom'], $d['entreprise_adresse'], $d['entreprise_tel'],
            $d['tuteur_nom'], $d['tuteur_email'], $d['prof_referent_id'] ?: null,
            $d['date_debut'], $d['date_fin'], $d['statut'], $d['description'],
            $d['evaluation_entreprise'] ?? null, $d['evaluation_prof'] ?? null,
            $d['rapport_path'] ?? null, $id
        ]);
    }

    public function getStagesEleve(int $eleveId): array
    {
        return $this->getStages(['eleve_id' => $eleveId]);
    }

    public function getStagesParent(int $parentId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT s.*, CONCAT(e.prenom, ' ', e.nom) AS eleve_nom, cl.nom AS classe_nom,
                   CONCAT(p.prenom, ' ', p.nom) AS prof_nom
            FROM stages s
            JOIN eleves e ON s.eleve_id = e.id
            LEFT JOIN classes cl ON e.classe_id = cl.id
            LEFT JOIN professeurs p ON s.prof_referent_id = p.id
            JOIN parent_eleve pe ON pe.eleve_id = e.id
            WHERE pe.parent_id = ?
            ORDER BY s.date_debut DESC
        ");
        $stmt->execute([$parentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ───── JOURNAL DE BORD ───── */

    /**
     * Add a journal entry for a stage.
     */
    public function ajouterJournal(int $stageId, int $semaine, string $contenu): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO stage_journal (stage_id, semaine, contenu, created_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE contenu = VALUES(contenu), created_at = NOW()
        ");
        $stmt->execute([$stageId, $semaine, $contenu]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Get all journal entries for a stage.
     */
    public function getJournal(int $stageId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM stage_journal WHERE stage_id = ? ORDER BY semaine");
        $stmt->execute([$stageId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /* ───── EXTERNAL EVALUATION ───── */

    /**
     * Generate a unique token for external tutor evaluation.
     */
    public function genererTokenEvaluation(int $stageId): string
    {
        $token = bin2hex(random_bytes(32));
        $this->pdo->prepare("
            INSERT INTO stage_evaluations (stage_id, token, created_at)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE token = VALUES(token), created_at = NOW()
        ")->execute([$stageId, $token]);
        return $token;
    }

    /**
     * Get evaluation by token (for external tutor access).
     */
    public function getEvaluationByToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT se.*, s.eleve_id, s.entreprise_nom, s.tuteur_nom,
                   CONCAT(e.prenom, ' ', e.nom) AS eleve_nom
            FROM stage_evaluations se
            JOIN stages s ON se.stage_id = s.id
            JOIN eleves e ON s.eleve_id = e.id
            WHERE se.token = ?
        ");
        $stmt->execute([$token]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Submit external evaluation (tutor fills in the form via token).
     */
    public function soumettrEvaluation(string $token, array $grille): bool
    {
        $json = json_encode($grille, JSON_UNESCAPED_UNICODE);
        $stmt = $this->pdo->prepare("UPDATE stage_evaluations SET grille_json = ?, submitted_at = NOW() WHERE token = ?");
        return $stmt->execute([$json, $token]);
    }

    /* ───── ENTREPRISES ───── */

    /**
     * Get or create an enterprise in the directory.
     */
    public function getEntreprises(?string $search = null): array
    {
        $sql = "SELECT * FROM entreprises WHERE 1=1";
        $params = [];
        if ($search) {
            $sql .= " AND (nom LIKE ? OR secteur LIKE ?)";
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
        }
        $sql .= " ORDER BY nom";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function creerEntreprise(array $d): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO entreprises (nom, adresse, contact_nom, contact_email, secteur)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$d['nom'], $d['adresse'] ?? null, $d['contact_nom'] ?? null, $d['contact_email'] ?? null, $d['secteur'] ?? null]);
        return (int)$this->pdo->lastInsertId();
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
        $total = $this->pdo->query("SELECT COUNT(*) FROM stages")->fetchColumn();
        $enCours = $this->pdo->query("SELECT COUNT(*) FROM stages WHERE statut = 'en_cours'")->fetchColumn();
        $recherche = $this->pdo->query("SELECT COUNT(*) FROM stages WHERE statut = 'en_recherche'")->fetchColumn();
        return ['total' => $total, 'en_cours' => $enCours, 'en_recherche' => $recherche];
    }

    public static function typesStage(): array
    {
        return ['stage' => 'Stage', 'alternance' => 'Alternance', 'immersion' => 'Immersion'];
    }

    public static function statutsStage(): array
    {
        return ['en_recherche' => 'En recherche', 'convention_envoyee' => 'Convention envoyée', 'en_cours' => 'En cours', 'termine' => 'Terminé', 'annule' => 'Annulé'];
    }

    public static function badgeStatut(string $s): string
    {
        $m = ['en_recherche' => 'warning', 'convention_envoyee' => 'info', 'en_cours' => 'success', 'termine' => 'secondary', 'annule' => 'danger'];
        return '<span class="badge badge-' . ($m[$s] ?? 'secondary') . '">' . ucfirst(str_replace('_', ' ', $s)) . '</span>';
    }

    /* ───── EXPORT ───── */

    public function getStagesForExport(array $filters = []): array
    {
        $stages = $this->getStages($filters);
        $types = self::typesStage();
        $statuts = self::statutsStage();
        return array_map(fn($s) => [
            $s['eleve_nom'],
            $s['classe_nom'] ?? '-',
            $types[$s['type']] ?? $s['type'],
            $s['entreprise_nom'] ?? '-',
            $s['tuteur_nom'] ?? '-',
            $s['prof_nom'] ?? '-',
            $s['date_debut'],
            $s['date_fin'],
            $statuts[$s['statut']] ?? $s['statut'],
        ], $stages);
    }

    // ─── CONVENTION PDF DATA ───

    public function genererConventionData(int $stageId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT s.*, CONCAT(e.prenom, ' ', e.nom) AS eleve_nom, e.date_naissance,
                   c.nom AS classe_nom, CONCAT(p.prenom, ' ', p.nom) AS prof_nom,
                   ent.nom AS entreprise_nom_full, ent.adresse AS entreprise_adresse,
                   ent.contact_nom AS tuteur_nom_full, ent.contact_email AS tuteur_email
            FROM stages s
            JOIN eleves e ON s.eleve_id = e.id
            LEFT JOIN classes c ON e.classe_id = c.id
            LEFT JOIN professeurs p ON s.prof_referent_id = p.id
            LEFT JOIN entreprises ent ON s.entreprise_id = ent.id
            WHERE s.id = :id
        ");
        $stmt->execute([':id' => $stageId]);
        $stage = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$stage) throw new \RuntimeException('Stage introuvable');

        return [
            'titre' => 'Convention de stage',
            'date_generation' => date('d/m/Y'),
            'eleve' => $stage['eleve_nom'],
            'date_naissance' => $stage['date_naissance'],
            'classe' => $stage['classe_nom'],
            'entreprise' => $stage['entreprise_nom_full'] ?? $stage['entreprise_nom'] ?? '',
            'adresse_entreprise' => $stage['entreprise_adresse'] ?? '',
            'tuteur' => $stage['tuteur_nom_full'] ?? $stage['tuteur_nom'] ?? '',
            'tuteur_email' => $stage['tuteur_email'] ?? '',
            'professeur_referent' => $stage['prof_nom'] ?? '',
            'date_debut' => $stage['date_debut'],
            'date_fin' => $stage['date_fin'],
            'type' => $stage['type'],
            'sujet' => $stage['sujet'] ?? '',
        ];
    }

    // ─── MARKETPLACE STAGES (OFFRES) ───

    public function getOffresStage(?string $search = null, ?string $secteur = null): array
    {
        $sql = "SELECT * FROM stages_offres WHERE actif = 1";
        $params = [];
        if ($search) { $sql .= " AND (titre LIKE :s OR description LIKE :s2 OR entreprise LIKE :s3)"; $params[':s'] = "%{$search}%"; $params[':s2'] = "%{$search}%"; $params[':s3'] = "%{$search}%"; }
        if ($secteur) { $sql .= " AND secteur = :sec"; $params[':sec'] = $secteur; }
        $sql .= " ORDER BY created_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function creerOffreStage(array $d): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO stages_offres (entreprise, titre, description, lieu, secteur, competences, date_debut, date_fin, places, actif, created_at)
            VALUES (:ent, :t, :desc, :lieu, :sec, :comp, :dd, :df, :pl, 1, NOW())
        ");
        $stmt->execute([
            ':ent' => $d['entreprise'], ':t' => $d['titre'], ':desc' => $d['description'] ?? '',
            ':lieu' => $d['lieu'] ?? null, ':sec' => $d['secteur'] ?? null,
            ':comp' => $d['competences'] ?? null, ':dd' => $d['date_debut'] ?? null,
            ':df' => $d['date_fin'] ?? null, ':pl' => $d['places'] ?? 1,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    // ─── PLANNING VISITES ───

    public function planifierVisite(int $stageId, int $professeurId, string $date, ?string $commentaire = null): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO stages_visites (stage_id, professeur_id, date_visite, commentaire, created_at) VALUES (:s, :p, :d, :c, NOW())");
        $stmt->execute([':s' => $stageId, ':p' => $professeurId, ':d' => $date, ':c' => $commentaire]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getVisites(int $stageId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT sv.*, CONCAT(p.prenom, ' ', p.nom) AS professeur_nom
            FROM stages_visites sv
            LEFT JOIN professeurs p ON sv.professeur_id = p.id
            WHERE sv.stage_id = :s ORDER BY sv.date_visite
        ");
        $stmt->execute([':s' => $stageId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getVisitesAPlanifier(): array
    {
        $stmt = $this->pdo->query("
            SELECT s.id, CONCAT(e.prenom, ' ', e.nom) AS eleve_nom, s.entreprise_nom,
                   s.date_debut, s.date_fin, CONCAT(p.prenom, ' ', p.nom) AS prof_nom,
                   (SELECT COUNT(*) FROM stages_visites sv WHERE sv.stage_id = s.id) AS nb_visites
            FROM stages s
            JOIN eleves e ON s.eleve_id = e.id
            LEFT JOIN professeurs p ON s.prof_referent_id = p.id
            WHERE s.statut = 'en_cours'
            ORDER BY nb_visites ASC, s.date_debut ASC
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
