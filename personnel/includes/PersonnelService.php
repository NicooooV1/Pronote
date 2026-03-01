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
}
