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
}
