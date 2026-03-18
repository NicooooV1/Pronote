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
}
