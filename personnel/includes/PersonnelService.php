<?php
/**
 * M39 – Gestion du personnel — Service
 */
class PersonnelService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /* ───── ABSENCES PERSONNEL ───── */

    public function getAbsences(array $filters = []): array
    {
        $sql = "SELECT pa.*, CONCAT(p.prenom, ' ', p.nom) AS personnel_nom
                FROM personnel_absences pa
                JOIN professeurs p ON pa.personnel_id = p.id
                WHERE 1=1";
        $params = [];
        if (!empty($filters['statut'])) { $sql .= ' AND pa.statut = ?'; $params[] = $filters['statut']; }
        if (!empty($filters['type'])) { $sql .= ' AND pa.type = ?'; $params[] = $filters['type']; }
        if (!empty($filters['personnel_id'])) { $sql .= ' AND pa.personnel_id = ?'; $params[] = $filters['personnel_id']; }
        $sql .= ' ORDER BY pa.date_debut DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAbsence(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT pa.*, CONCAT(p.prenom, ' ', p.nom) AS personnel_nom FROM personnel_absences pa JOIN professeurs p ON pa.personnel_id = p.id WHERE pa.id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function creerAbsence(array $d): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO personnel_absences (personnel_id, type, date_debut, date_fin, motif, statut) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$d['personnel_id'], $d['type'], $d['date_debut'], $d['date_fin'], $d['motif'] ?? null, $d['statut'] ?? 'en_attente']);
        return $this->pdo->lastInsertId();
    }

    public function modifierStatut(int $id, string $statut): void
    {
        $this->pdo->prepare("UPDATE personnel_absences SET statut = ? WHERE id = ?")->execute([$statut, $id]);
    }

    /* ───── REMPLACEMENTS ───── */

    public function getRemplacements(array $filters = []): array
    {
        $sql = "SELECT r.*,
                       CONCAT(pa.prenom, ' ', pa.nom) AS absent_nom,
                       CONCAT(pr.prenom, ' ', pr.nom) AS remplacant_nom,
                       m.nom AS matiere_nom, cl.nom AS classe_nom
                FROM remplacements r
                JOIN professeurs pa ON r.professeur_absent_id = pa.id
                LEFT JOIN professeurs pr ON r.professeur_remplacant_id = pr.id
                LEFT JOIN matieres m ON r.matiere_id = m.id
                LEFT JOIN classes cl ON r.classe_id = cl.id
                WHERE 1=1";
        $params = [];
        if (!empty($filters['statut'])) { $sql .= ' AND r.statut = ?'; $params[] = $filters['statut']; }
        $sql .= ' ORDER BY r.date_debut DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function creerRemplacement(array $d): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO remplacements (absence_id, professeur_absent_id, professeur_remplacant_id, matiere_id, classe_id, date_debut, date_fin, statut) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([$d['absence_id'] ?? null, $d['professeur_absent_id'], $d['professeur_remplacant_id'] ?? null, $d['matiere_id'] ?? null, $d['classe_id'] ?? null, $d['date_debut'], $d['date_fin'], $d['statut'] ?? 'propose']);
        return $this->pdo->lastInsertId();
    }

    public function attribuerRemplacant(int $id, int $remplacantId): void
    {
        $this->pdo->prepare("UPDATE remplacements SET professeur_remplacant_id = ?, statut = 'confirme' WHERE id = ?")->execute([$remplacantId, $id]);
    }

    /* ───── LEAVE MANAGEMENT (CONGÉS) ───── */

    /**
     * Submit a leave request.
     */
    public function demanderConge(array $d): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO personnel_conges (personnel_id, type, date_debut, date_fin, motif, statut, justificatif_path)
            VALUES (?, ?, ?, ?, ?, 'en_attente', ?)
        ");
        $stmt->execute([
            $d['personnel_id'], $d['type'], $d['date_debut'], $d['date_fin'],
            $d['motif'] ?? null, $d['justificatif_path'] ?? null
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Validate or refuse a leave request.
     */
    public function traiterConge(int $congeId, string $decision, int $validePar): void
    {
        $statut = ($decision === 'accepte') ? 'validee' : 'refusee';
        $this->pdo->prepare("UPDATE personnel_conges SET statut = ?, valide_par = ?, date_decision = NOW() WHERE id = ?")
                   ->execute([$statut, $validePar, $congeId]);

        // Auto-create absence if accepted
        if ($statut === 'validee') {
            $conge = $this->pdo->prepare("SELECT * FROM personnel_conges WHERE id = ?")->fetch(\PDO::FETCH_ASSOC);
            if ($conge) {
                $this->creerAbsence([
                    'personnel_id' => $conge['personnel_id'],
                    'type' => 'conge',
                    'date_debut' => $conge['date_debut'],
                    'date_fin' => $conge['date_fin'],
                    'motif' => $conge['motif'],
                    'statut' => 'validee',
                ]);
            }
        }
    }

    /**
     * Get leave requests.
     */
    public function getConges(array $filters = []): array
    {
        $sql = "SELECT pc.*, CONCAT(p.prenom, ' ', p.nom) AS personnel_nom
                FROM personnel_conges pc
                JOIN professeurs p ON pc.personnel_id = p.id
                WHERE 1=1";
        $params = [];
        if (!empty($filters['personnel_id'])) { $sql .= ' AND pc.personnel_id = ?'; $params[] = $filters['personnel_id']; }
        if (!empty($filters['statut'])) { $sql .= ' AND pc.statut = ?'; $params[] = $filters['statut']; }
        $sql .= ' ORDER BY pc.date_debut DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /* ───── CONFLICT DETECTION ───── */

    /**
     * Detect schedule conflicts: absent prof with no replacement on active schedule.
     */
    public function detecterConflits(): array
    {
        $conflicts = [];
        $absences = $this->getAbsences(['statut' => 'validee']);

        foreach ($absences as $abs) {
            // Check if there are scheduled courses during this absence
            $stmt = $this->pdo->prepare("
                SELECT edt.*, cl.nom AS classe_nom, m.nom AS matiere_nom, ch.heure_debut, ch.heure_fin, ch.jour
                FROM emploi_du_temps edt
                JOIN classes cl ON edt.classe_id = cl.id
                JOIN matieres m ON edt.matiere_id = m.id
                JOIN creneaux_horaires ch ON edt.creneau_id = ch.id
                WHERE edt.professeur_id = ? AND edt.actif = 1
            ");
            $stmt->execute([$abs['personnel_id']]);
            $cours = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Check if replacement exists
            $rempStmt = $this->pdo->prepare("
                SELECT id FROM remplacements
                WHERE professeur_absent_id = ? AND date_debut <= ? AND date_fin >= ? AND statut = 'confirme'
            ");

            foreach ($cours as $c) {
                $rempStmt->execute([$abs['personnel_id'], $abs['date_fin'], $abs['date_debut']]);
                if (!$rempStmt->fetch()) {
                    $conflicts[] = [
                        'absence_id' => $abs['id'],
                        'personnel_nom' => $abs['personnel_nom'],
                        'classe' => $c['classe_nom'],
                        'matiere' => $c['matiere_nom'],
                        'jour' => $c['jour'] ?? '',
                        'heure' => ($c['heure_debut'] ?? '') . ' - ' . ($c['heure_fin'] ?? ''),
                        'date_debut' => $abs['date_debut'],
                        'date_fin' => $abs['date_fin'],
                    ];
                }
            }
        }
        return $conflicts;
    }

    /**
     * Get personnel directory with full info.
     */
    public function getAnnuaire(?string $search = null): array
    {
        $sql = "SELECT p.id, p.prenom, p.nom, p.email, p.telephone, p.matiere,
                       (SELECT COUNT(*) FROM emploi_du_temps edt WHERE edt.professeur_id = p.id AND edt.actif = 1) AS nb_cours,
                       (SELECT GROUP_CONCAT(DISTINCT cl.nom) FROM emploi_du_temps edt2 JOIN classes cl ON edt2.classe_id = cl.id WHERE edt2.professeur_id = p.id AND edt2.actif = 1) AS classes
                FROM professeurs p WHERE p.actif = 1";
        $params = [];
        if ($search) {
            $sql .= " AND (p.nom LIKE ? OR p.prenom LIKE ? OR p.matiere LIKE ?)";
            $like = '%' . $search . '%';
            $params = [$like, $like, $like];
        }
        $sql .= " ORDER BY p.nom";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /* ───── HELPERS ───── */

    public function getPersonnel(): array
    {
        return $this->pdo->query("SELECT id, prenom, nom FROM professeurs ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMatieres(): array
    {
        return $this->pdo->query("SELECT id, nom FROM matieres ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getClasses(): array
    {
        return $this->pdo->query("SELECT id, nom FROM classes ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getStats(): array
    {
        $abs = $this->pdo->query("SELECT COUNT(*) FROM personnel_absences WHERE statut IN ('validee','en_attente')")->fetchColumn();
        $remp = $this->pdo->query("SELECT COUNT(*) FROM remplacements WHERE statut = 'propose'")->fetchColumn();
        $conf = $this->pdo->query("SELECT COUNT(*) FROM remplacements WHERE statut = 'confirme'")->fetchColumn();
        return ['absences_actives' => $abs, 'remplacements_en_attente' => $remp, 'remplacements_confirmes' => $conf];
    }

    public static function typesAbsence(): array
    {
        return ['maladie' => 'Maladie', 'conge' => 'Congé', 'formation' => 'Formation', 'personnel' => 'Personnel', 'autre' => 'Autre'];
    }

    public static function statutsAbsence(): array
    {
        return ['en_attente' => 'En attente', 'validee' => 'Validée', 'refusee' => 'Refusée'];
    }

    public static function statutsRemplacement(): array
    {
        return ['propose' => 'Proposé', 'confirme' => 'Confirmé', 'annule' => 'Annulé'];
    }

    public static function badgeStatut(string $s): string
    {
        $m = ['en_attente' => 'warning', 'validee' => 'success', 'refusee' => 'danger', 'propose' => 'info', 'confirme' => 'success', 'annule' => 'danger'];
        return '<span class="badge badge-' . ($m[$s] ?? 'secondary') . '">' . ucfirst(str_replace('_', ' ', $s)) . '</span>';
    }

    // ─── HEURES SUPPLÉMENTAIRES ───

    public function ajouterHeuresSup(int $personnelId, string $date, float $heures, string $motif): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO personnel_heures_sup (personnel_id, date_heures, heures, motif, created_at) VALUES (:p, :d, :h, :m, NOW())");
        $stmt->execute([':p' => $personnelId, ':d' => $date, ':h' => $heures, ':m' => $motif]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getHeuresSup(int $personnelId, ?string $mois = null): array
    {
        $sql = "SELECT * FROM personnel_heures_sup WHERE personnel_id = :p";
        $params = [':p' => $personnelId];
        if ($mois) { $sql .= " AND DATE_FORMAT(date_heures, '%Y-%m') = :m"; $params[':m'] = $mois; }
        $sql .= " ORDER BY date_heures DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getTotalHeuresSup(int $personnelId, string $mois): float
    {
        $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(heures), 0) FROM personnel_heures_sup WHERE personnel_id = :p AND DATE_FORMAT(date_heures, '%Y-%m') = :m");
        $stmt->execute([':p' => $personnelId, ':m' => $mois]);
        return round((float)$stmt->fetchColumn(), 2);
    }

    // ─── ÉVALUATION ANNUELLE ───

    public function creerEvaluation(int $personnelId, int $evaluateurId, int $annee, array $criteres, ?string $commentaire = null): int
    {
        $noteGlobale = count($criteres) > 0 ? round(array_sum($criteres) / count($criteres), 1) : 0;
        $stmt = $this->pdo->prepare("
            INSERT INTO personnel_evaluations (personnel_id, evaluateur_id, annee, criteres_json, note_globale, commentaire, date_evaluation)
            VALUES (:p, :e, :a, :c, :n, :co, NOW())
        ");
        $stmt->execute([':p' => $personnelId, ':e' => $evaluateurId, ':a' => $annee, ':c' => json_encode($criteres), ':n' => $noteGlobale, ':co' => $commentaire]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getEvaluations(int $personnelId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT pe.*, CONCAT(p.prenom, ' ', p.nom) AS evaluateur_nom
            FROM personnel_evaluations pe
            LEFT JOIN professeurs p ON pe.evaluateur_id = p.id
            WHERE pe.personnel_id = :p ORDER BY pe.annee DESC
        ");
        $stmt->execute([':p' => $personnelId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ─── SOLDE CONGÉS ───

    public function getSoldeConges(int $personnelId, ?int $annee = null): array
    {
        $annee = $annee ?? (int)date('Y');
        $stmt = $this->pdo->prepare("
            SELECT type, COUNT(*) AS nb_demandes,
                   SUM(DATEDIFF(date_fin, date_debut) + 1) AS jours_total
            FROM personnel_conges WHERE personnel_id = :p AND statut = 'validee' AND YEAR(date_debut) = :a
            GROUP BY type
        ");
        $stmt->execute([':p' => $personnelId, ':a' => $annee]);
        $pris = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $droits = ['conge' => 25, 'maladie' => 365, 'formation' => 12, 'personnel' => 5];
        $result = [];
        foreach ($droits as $type => $total) {
            $joursP = 0;
            foreach ($pris as $p) { if ($p['type'] === $type) $joursP = (int)$p['jours_total']; }
            $result[$type] = ['droit' => $total, 'pris' => $joursP, 'reste' => $total - $joursP];
        }
        return $result;
    }
}
