<?php
/**
 * ParcoursEducatifService — Service métier pour le module Parcours Éducatifs (M42).
 */
class ParcoursEducatifService
{
    private PDO $pdo;

    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    /* ==================== PARCOURS ==================== */

    public function getParcours(array $filters = []): array
    {
        $sql = "SELECT pe.*, CONCAT(e.prenom, ' ', e.nom) AS eleve_nom
                FROM parcours_educatifs pe
                LEFT JOIN eleves e ON pe.eleve_id = e.id
                WHERE 1=1";
        $params = [];
        if (!empty($filters['eleve_id']))       { $sql .= " AND pe.eleve_id = ?";       $params[] = $filters['eleve_id']; }
        if (!empty($filters['type_parcours']))   { $sql .= " AND pe.type_parcours = ?";  $params[] = $filters['type_parcours']; }
        if (!empty($filters['annee_scolaire']))  { $sql .= " AND pe.annee_scolaire = ?"; $params[] = $filters['annee_scolaire']; }
        if (isset($filters['validation']) && $filters['validation'] !== '') { $sql .= " AND pe.validation = ?"; $params[] = (int)$filters['validation']; }
        $sql .= " ORDER BY pe.date_activite DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getParcoursEleve(int $eleveId, ?string $type = null): array
    {
        $f = ['eleve_id' => $eleveId];
        if ($type) $f['type_parcours'] = $type;
        return $this->getParcours($f);
    }

    public function getEntry(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT pe.*, CONCAT(e.prenom, ' ', e.nom) AS eleve_nom
             FROM parcours_educatifs pe
             LEFT JOIN eleves e ON pe.eleve_id = e.id
             WHERE pe.id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function ajouter(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO parcours_educatifs (eleve_id, type_parcours, titre, description, date_activite, competences_visees, validation, annee_scolaire)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['eleve_id'], $data['type_parcours'], $data['titre'],
            $data['description'] ?? null, $data['date_activite'] ?? date('Y-m-d'),
            $data['competences_visees'] ?? null, $data['validation'] ?? 0,
            $data['annee_scolaire'] ?? $this->anneeScolaire(),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function modifier(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE parcours_educatifs SET titre = ?, description = ?, type_parcours = ?,
             date_activite = ?, competences_visees = ?, validation = ?, annee_scolaire = ?
             WHERE id = ?"
        );
        return $stmt->execute([
            $data['titre'], $data['description'] ?? null, $data['type_parcours'],
            $data['date_activite'] ?? date('Y-m-d'), $data['competences_visees'] ?? null,
            $data['validation'] ?? 0, $data['annee_scolaire'] ?? $this->anneeScolaire(), $id,
        ]);
    }

    public function valider(int $id, bool $valide = true): bool
    {
        $stmt = $this->pdo->prepare("UPDATE parcours_educatifs SET validation = ? WHERE id = ?");
        return $stmt->execute([$valide ? 1 : 0, $id]);
    }

    public function supprimer(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM parcours_educatifs WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /* ==================== MODÈLES ==================== */

    public function getModeles(?string $type = null): array
    {
        $sql = "SELECT * FROM parcours_educatifs_modeles";
        $params = [];
        if ($type) { $sql .= " WHERE type_parcours = ?"; $params[] = $type; }
        $sql .= " ORDER BY type_parcours, titre";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ==================== STATS ==================== */

    public function getStatsByType(?int $eleveId = null): array
    {
        $sql = "SELECT type_parcours, COUNT(*) AS total, SUM(validation) AS valides
                FROM parcours_educatifs WHERE 1=1";
        $params = [];
        if ($eleveId) { $sql .= " AND eleve_id = ?"; $params[] = $eleveId; }
        $sql .= " GROUP BY type_parcours";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ==================== HELPERS ==================== */

    public static function typesLabels(): array
    {
        return ['avenir' => 'Parcours Avenir', 'sante' => 'Parcours Santé', 'citoyen' => 'Parcours Citoyen', 'PEAC' => 'PEAC'];
    }

    public static function typeColor(string $type): string
    {
        return ['avenir' => '#6366f1', 'sante' => '#10b981', 'citoyen' => '#f59e0b', 'PEAC' => '#ec4899'][$type] ?? '#6b7280';
    }

    private function anneeScolaire(): string
    {
        $m = (int) date('m');
        $y = (int) date('Y');
        return $m >= 9 ? "$y/" . ($y + 1) : ($y - 1) . "/$y";
    }
}
