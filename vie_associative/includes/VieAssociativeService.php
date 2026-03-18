<?php
/**
 * VieAssociativeService — Service métier pour le module Vie Associative (M43).
 */
class VieAssociativeService
{
    private PDO $pdo;

    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    /* ==================== ASSOCIATIONS ==================== */

    public function getAssociations(?string $type = null): array
    {
        $sql = "SELECT a.*, CONCAT(e.prenom, ' ', e.nom) AS president_nom,
                       CONCAT(p.prenom, ' ', p.nom) AS referent_nom,
                       (SELECT COUNT(*) FROM association_membres am WHERE am.association_id = a.id AND am.statut = 'actif') AS nb_membres
                FROM associations a
                LEFT JOIN eleves e ON a.president_eleve_id = e.id
                LEFT JOIN professeurs p ON a.referent_adulte_id = p.id
                WHERE 1=1";
        $params = [];
        if ($type) { $sql .= " AND a.type = ?"; $params[] = $type; }
        $sql .= " ORDER BY a.nom";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAssociation(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT a.*, CONCAT(e.prenom, ' ', e.nom) AS president_nom,
                    CONCAT(p.prenom, ' ', p.nom) AS referent_nom
             FROM associations a
             LEFT JOIN eleves e ON a.president_eleve_id = e.id
             LEFT JOIN professeurs p ON a.referent_adulte_id = p.id
             WHERE a.id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function creerAssociation(array $d): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO associations (nom, type, description, president_eleve_id, referent_adulte_id, budget_annuel, statut)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$d['nom'], $d['type'] ?? 'association', $d['description'] ?? null,
            $d['president_eleve_id'] ?: null, $d['referent_adulte_id'] ?: null,
            $d['budget_annuel'] ?? null, $d['statut'] ?? 'active']);
        return (int) $this->pdo->lastInsertId();
    }

    public function modifierAssociation(int $id, array $d): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE associations SET nom = ?, type = ?, description = ?, president_eleve_id = ?,
             referent_adulte_id = ?, budget_annuel = ?, statut = ? WHERE id = ?"
        );
        return $stmt->execute([$d['nom'], $d['type'] ?? 'association', $d['description'] ?? null,
            $d['president_eleve_id'] ?: null, $d['referent_adulte_id'] ?: null,
            $d['budget_annuel'] ?? null, $d['statut'] ?? 'active', $id]);
    }

    /* ==================== MEMBRES ==================== */

    public function getMembres(int $assoId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT am.*, CONCAT(e.prenom, ' ', e.nom) AS nom_complet, e.classe
             FROM association_membres am
             LEFT JOIN eleves e ON am.eleve_id = e.id
             WHERE am.association_id = ?
             ORDER BY am.role DESC, nom_complet"
        );
        $stmt->execute([$assoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function inscrireMembre(int $assoId, int $eleveId, string $role = 'membre'): bool
    {
        $stmt = $this->pdo->prepare(
            "INSERT IGNORE INTO association_membres (association_id, eleve_id, role, statut) VALUES (?, ?, ?, 'actif')"
        );
        return $stmt->execute([$assoId, $eleveId, $role]);
    }

    public function retirerMembre(int $membreId): bool
    {
        $stmt = $this->pdo->prepare("UPDATE association_membres SET statut = 'inactif' WHERE id = ?");
        return $stmt->execute([$membreId]);
    }

    /* ==================== ACTIVITÉS ==================== */

    public function getActivites(int $assoId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM association_activites WHERE association_id = ? ORDER BY date_activite DESC");
        $stmt->execute([$assoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function ajouterActivite(int $assoId, array $d): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO association_activites (association_id, titre, description, date_activite, lieu, budget_prevu) VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$assoId, $d['titre'], $d['description'] ?? null, $d['date_activite'], $d['lieu'] ?? null, $d['budget_prevu'] ?? null]);
        return (int) $this->pdo->lastInsertId();
    }

    /* ==================== TRÉSORERIE ==================== */

    public function getTresorerie(int $assoId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM association_tresorerie WHERE association_id = ? ORDER BY date_operation DESC");
        $stmt->execute([$assoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function ajouterOperation(int $assoId, array $d): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO association_tresorerie (association_id, type_operation, montant, description, date_operation, piece_justificative)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$assoId, $d['type_operation'], $d['montant'], $d['description'] ?? null,
            $d['date_operation'] ?? date('Y-m-d'), $d['piece_justificative'] ?? null]);
        return (int) $this->pdo->lastInsertId();
    }

    public function getSolde(int $assoId): float
    {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(CASE WHEN type_operation = 'recette' THEN montant ELSE -montant END), 0)
             FROM association_tresorerie WHERE association_id = ?"
        );
        $stmt->execute([$assoId]);
        return (float) $stmt->fetchColumn();
    }

    /* ==================== HELPERS ==================== */

    public static function typesLabels(): array
    {
        return ['MDL' => 'Maison des Lycéens', 'FSE' => 'Foyer Socio-Éducatif', 'association' => 'Association', 'autre' => 'Autre'];
    }

    public static function typeColor(string $t): string
    {
        return ['MDL' => '#6366f1', 'FSE' => '#10b981', 'association' => '#f59e0b', 'autre' => '#6b7280'][$t] ?? '#6b7280';
    }
}
