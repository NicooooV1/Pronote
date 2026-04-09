<?php
/**
 * ProjetPedagogiqueService — Service métier pour le module Projets Pédagogiques (M41).
 */
class ProjetPedagogiqueService
{
    private PDO $pdo;

    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    /* ==================== PROJETS ==================== */

    public function getProjets(array $filters = []): array
    {
        $sql = "SELECT pp.*, CONCAT(p.prenom, ' ', p.nom) AS responsable_nom
                FROM projets_pedagogiques pp
                LEFT JOIN professeurs p ON pp.responsable_id = p.id
                WHERE 1=1";
        $params = [];
        if (!empty($filters['statut'])) { $sql .= " AND pp.statut = ?"; $params[] = $filters['statut']; }
        if (!empty($filters['type'])) { $sql .= " AND pp.type = ?"; $params[] = $filters['type']; }
        if (!empty($filters['responsable_id'])) { $sql .= " AND pp.responsable_id = ?"; $params[] = $filters['responsable_id']; }
        $sql .= " ORDER BY pp.date_debut DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProjet(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT pp.*, CONCAT(p.prenom, ' ', p.nom) AS responsable_nom
             FROM projets_pedagogiques pp
             LEFT JOIN professeurs p ON pp.responsable_id = p.id
             WHERE pp.id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function creerProjet(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO projets_pedagogiques (titre, description, objectifs, type, responsable_id, classes, matieres, date_debut, date_fin, budget, statut)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['titre'], $data['description'] ?? null, $data['objectifs'] ?? null,
            $data['type'] ?? 'projet_classe', $data['responsable_id'],
            $data['classes'] ?? null, $data['matieres'] ?? null,
            $data['date_debut'], $data['date_fin'] ?? null,
            $data['budget'] ?? null, $data['statut'] ?? 'brouillon',
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function modifierProjet(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE projets_pedagogiques SET titre = ?, description = ?, objectifs = ?, type = ?,
             classes = ?, matieres = ?, date_debut = ?, date_fin = ?, budget = ?, statut = ?, bilan = ?
             WHERE id = ?"
        );
        return $stmt->execute([
            $data['titre'], $data['description'] ?? null, $data['objectifs'] ?? null,
            $data['type'] ?? 'projet_classe',
            $data['classes'] ?? null, $data['matieres'] ?? null,
            $data['date_debut'], $data['date_fin'] ?? null,
            $data['budget'] ?? null, $data['statut'] ?? 'brouillon',
            $data['bilan'] ?? null, $id,
        ]);
    }

    public function changerStatut(int $id, string $statut): bool
    {
        $stmt = $this->pdo->prepare("UPDATE projets_pedagogiques SET statut = ? WHERE id = ?");
        return $stmt->execute([$statut, $id]);
    }

    /* ==================== PARTICIPANTS ==================== */

    public function getParticipants(int $projetId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT ppp.*, 
                    CASE WHEN ppp.user_type = 'professeur' THEN CONCAT(p.prenom, ' ', p.nom)
                         WHEN ppp.user_type = 'eleve' THEN CONCAT(e.prenom, ' ', e.nom)
                    END AS nom_complet
             FROM projets_pedagogiques_participants ppp
             LEFT JOIN professeurs p ON ppp.user_type = 'professeur' AND ppp.user_id = p.id
             LEFT JOIN eleves e ON ppp.user_type = 'eleve' AND ppp.user_id = e.id
             WHERE ppp.projet_id = ?
             ORDER BY ppp.user_type, nom_complet"
        );
        $stmt->execute([$projetId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function ajouterParticipant(int $projetId, int $userId, string $userType, ?string $role = null): bool
    {
        $stmt = $this->pdo->prepare(
            "INSERT IGNORE INTO projets_pedagogiques_participants (projet_id, user_id, user_type, role_projet)
             VALUES (?, ?, ?, ?)"
        );
        return $stmt->execute([$projetId, $userId, $userType, $role ?? 'participant']);
    }

    public function retirerParticipant(int $participantId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM projets_pedagogiques_participants WHERE id = ?");
        return $stmt->execute([$participantId]);
    }

    /* ==================== ÉTAPES ==================== */

    public function getEtapes(int $projetId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM projets_pedagogiques_etapes WHERE projet_id = ? ORDER BY ordre, date_echeance");
        $stmt->execute([$projetId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function ajouterEtape(int $projetId, array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO projets_pedagogiques_etapes (projet_id, titre, description, date_echeance, ordre) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$projetId, $data['titre'], $data['description'] ?? null, $data['date_echeance'] ?? null, $data['ordre'] ?? 0]);
        return (int) $this->pdo->lastInsertId();
    }

    public function changerStatutEtape(int $etapeId, string $statut): bool
    {
        $stmt = $this->pdo->prepare("UPDATE projets_pedagogiques_etapes SET statut = ? WHERE id = ?");
        return $stmt->execute([$statut, $etapeId]);
    }

    /* ==================== BUDGET TRACKING ==================== */

    /**
     * Record a budget expense for a project.
     */
    public function ajouterDepense(int $projetId, float $montant, string $description, ?string $justificatif = null): void
    {
        $this->pdo->prepare("
            INSERT INTO projets_depenses (projet_id, montant, description, justificatif_path, date_depense)
            VALUES (?, ?, ?, ?, NOW())
        ")->execute([$projetId, $montant, $description, $justificatif]);

        // Update cached budget_depense on projet
        $this->pdo->prepare("
            UPDATE projets_pedagogiques SET budget_depense = (
                SELECT COALESCE(SUM(montant), 0) FROM projets_depenses WHERE projet_id = ?
            ) WHERE id = ?
        ")->execute([$projetId, $projetId]);
    }

    /**
     * Get all expenses for a project.
     */
    public function getDepenses(int $projetId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM projets_depenses WHERE projet_id = ? ORDER BY date_depense DESC");
        $stmt->execute([$projetId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get budget summary for a project.
     */
    public function getBudgetResume(int $projetId): array
    {
        $projet = $this->getProjet($projetId);
        $depenses = $this->getDepenses($projetId);
        $totalDepense = array_sum(array_column($depenses, 'montant'));
        $budget = (float)($projet['budget'] ?? 0);

        return [
            'budget_prevu' => $budget,
            'total_depense' => $totalDepense,
            'reste' => $budget - $totalDepense,
            'pourcentage_utilise' => $budget > 0 ? round($totalDepense / $budget * 100, 1) : 0,
            'nb_depenses' => count($depenses),
        ];
    }

    /* ==================== KANBAN ==================== */

    /**
     * Get projects grouped by status for kanban display.
     */
    public function getKanban(array $filters = []): array
    {
        $projets = $this->getProjets($filters);
        $kanban = [
            'brouillon' => [], 'soumis' => [], 'valide' => [],
            'en_cours' => [], 'termine' => [], 'annule' => [],
        ];
        foreach ($projets as $p) {
            $statut = $p['statut'] ?? 'brouillon';
            if (isset($kanban[$statut])) {
                $kanban[$statut][] = $p;
            }
        }
        return $kanban;
    }

    /* ==================== HELPERS ==================== */

    public function getStats(): array
    {
        $stats = [];
        $stats['total'] = (int) $this->pdo->query("SELECT COUNT(*) FROM projets_pedagogiques")->fetchColumn();
        $stats['en_cours'] = (int) $this->pdo->query("SELECT COUNT(*) FROM projets_pedagogiques WHERE statut = 'en_cours'")->fetchColumn();
        $stats['termines'] = (int) $this->pdo->query("SELECT COUNT(*) FROM projets_pedagogiques WHERE statut = 'termine'")->fetchColumn();
        return $stats;
    }

    public static function typesLabels(): array
    {
        return ['EPI' => 'EPI', 'projet_classe' => 'Projet de classe', 'sortie' => 'Sortie scolaire', 'voyage' => 'Voyage', 'autre' => 'Autre'];
    }

    public static function statutLabels(): array
    {
        return ['brouillon' => 'Brouillon', 'soumis' => 'Soumis', 'valide' => 'Validé', 'en_cours' => 'En cours', 'termine' => 'Terminé', 'annule' => 'Annulé'];
    }

    public static function statutBadge(string $statut): string
    {
        $map = ['brouillon' => 'secondary', 'soumis' => 'info', 'valide' => 'primary', 'en_cours' => 'warning', 'termine' => 'success', 'annule' => 'danger'];
        $label = self::statutLabels()[$statut] ?? $statut;
        return '<span class="badge badge-' . ($map[$statut] ?? 'secondary') . '">' . $label . '</span>';
    }

    // ─── REÇUS DÉPENSES ───

    public function ajouterDepense(int $projetId, float $montant, string $description, string $categorie = 'materiel', string $justificatifPath = ''): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO projets_depenses (projet_id, montant, description, categorie, justificatif_path, date_depense) VALUES (:pid, :m, :d, :c, :jp, NOW())");
        $stmt->execute([':pid' => $projetId, ':m' => $montant, ':d' => $description, ':c' => $categorie, ':jp' => $justificatifPath]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getDepenses(int $projetId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM projets_depenses WHERE projet_id = :pid ORDER BY date_depense DESC");
        $stmt->execute([':pid' => $projetId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getBudgetResume(int $projetId): array
    {
        $projet = $this->pdo->prepare("SELECT budget FROM projets_pedagogiques WHERE id = :pid");
        $projet->execute([':pid' => $projetId]);
        $budget = (float)$projet->fetchColumn();

        $depense = $this->pdo->prepare("SELECT COALESCE(SUM(montant),0) FROM projets_depenses WHERE projet_id = :pid");
        $depense->execute([':pid' => $projetId]);
        $totalDepenses = (float)$depense->fetchColumn();

        return ['budget' => $budget, 'depenses' => $totalDepenses, 'restant' => $budget - $totalDepenses];
    }

    // ─── GANTT CHART DATA ───

    public function getGanttData(int $projetId): array
    {
        $stmt = $this->pdo->prepare("SELECT id, titre, description, date_debut, date_fin, statut FROM projets_etapes WHERE projet_id = :pid ORDER BY date_debut");
        $stmt->execute([':pid' => $projetId]);
        $etapes = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(fn($e) => [
            'id' => $e['id'],
            'name' => $e['titre'],
            'start' => $e['date_debut'],
            'end' => $e['date_fin'],
            'progress' => $e['statut'] === 'termine' ? 100 : ($e['statut'] === 'en_cours' ? 50 : 0),
            'status' => $e['statut']
        ], $etapes);
    }

    // ─── AUTORISATIONS PARENTALES ───

    public function demanderAutorisation(int $projetId, int $eleveId, int $parentId): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO projets_autorisations (projet_id, eleve_id, parent_id, statut) VALUES (:pid, :eid, :parid, 'en_attente')");
        $stmt->execute([':pid' => $projetId, ':eid' => $eleveId, ':parid' => $parentId]);
        return (int)$this->pdo->lastInsertId();
    }

    public function signerAutorisation(int $autorisationId, int $signatureId, string $decision = 'autorise'): void
    {
        $this->pdo->prepare("UPDATE projets_autorisations SET statut = :s, signature_id = :sid, date_reponse = NOW() WHERE id = :id")
            ->execute([':s' => $decision, ':sid' => $signatureId, ':id' => $autorisationId]);
    }

    public function getAutorisationsProjet(int $projetId): array
    {
        $stmt = $this->pdo->prepare("SELECT pa.*, CONCAT(e.prenom,' ',e.nom) AS eleve_nom FROM projets_autorisations pa JOIN eleves e ON pa.eleve_id = e.id WHERE pa.projet_id = :pid ORDER BY e.nom");
        $stmt->execute([':pid' => $projetId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ─── ÉVALUATION POST-PROJET ───

    public function evaluerProjet(int $projetId, int $evaluateurId, string $critere, int $note, string $commentaire = ''): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO projets_evaluations (projet_id, evaluateur_id, critere, note, commentaire) VALUES (:pid, :eid, :c, :n, :com)");
        $stmt->execute([':pid' => $projetId, ':eid' => $evaluateurId, ':c' => $critere, ':n' => $note, ':com' => $commentaire]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getEvaluationsProjet(int $projetId): array
    {
        $stmt = $this->pdo->prepare("SELECT pe.*, CONCAT(p.prenom,' ',p.nom) AS evaluateur_nom FROM projets_evaluations pe LEFT JOIN professeurs p ON pe.evaluateur_id = p.id WHERE pe.projet_id = :pid ORDER BY pe.critere");
        $stmt->execute([':pid' => $projetId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
