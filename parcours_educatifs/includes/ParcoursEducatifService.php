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

    /* ==================== PORTFOLIO ==================== */

    /**
     * Add a portfolio entry for a student's parcours.
     */
    public function ajouterPortfolio(int $eleveId, string $typeParcours, array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO parcours_portfolio (eleve_id, type_parcours, titre, contenu, fichiers, liens, annee_scolaire, validated_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, NULL)
        ");
        $stmt->execute([
            $eleveId, $typeParcours, $data['titre'],
            $data['contenu'] ?? null,
            !empty($data['fichiers']) ? json_encode($data['fichiers']) : null,
            !empty($data['liens']) ? json_encode($data['liens']) : null,
            $data['annee_scolaire'] ?? $this->anneeScolaire(),
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Get portfolio entries for a student.
     */
    public function getPortfolio(int $eleveId, ?string $typeParcours = null): array
    {
        $sql = "SELECT pp.*, CONCAT(p.prenom, ' ', p.nom) AS valideur_nom
                FROM parcours_portfolio pp
                LEFT JOIN professeurs p ON pp.validated_by = p.id
                WHERE pp.eleve_id = ?";
        $params = [$eleveId];
        if ($typeParcours) { $sql .= ' AND pp.type_parcours = ?'; $params[] = $typeParcours; }
        $sql .= ' ORDER BY pp.created_at DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['fichiers'] = $r['fichiers'] ? json_decode($r['fichiers'], true) : [];
            $r['liens'] = $r['liens'] ? json_decode($r['liens'], true) : [];
        }
        return $rows;
    }

    /**
     * Validate a portfolio entry.
     */
    public function validerPortfolio(int $portfolioId, int $profId): void
    {
        $this->pdo->prepare("UPDATE parcours_portfolio SET validated_by = ?, validated_at = NOW() WHERE id = ?")
                   ->execute([$profId, $portfolioId]);
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

    // ─── PORTFOLIO PDF ───

    /**
     * Génère les données du portfolio d'un élève pour PDF.
     */
    public function genererPortfolio(int $eleveId): array
    {
        $eleve = $this->pdo->prepare("SELECT id, nom, prenom, classe FROM eleves WHERE id = :id");
        $eleve->execute([':id' => $eleveId]);

        $parcours = $this->pdo->prepare("SELECT pe.*, m.titre AS modele_titre, m.type_parcours FROM parcours_educatifs pe JOIN parcours_modeles m ON pe.modele_id = m.id WHERE pe.eleve_id = :eid ORDER BY m.type_parcours, pe.date_inscription DESC");
        $parcours->execute([':eid' => $eleveId]);

        $photos = $this->pdo->prepare("SELECT * FROM parcours_photos WHERE parcours_id IN (SELECT id FROM parcours_educatifs WHERE eleve_id = :eid) ORDER BY created_at");
        $photos->execute([':eid' => $eleveId]);

        return [
            'eleve' => $eleve->fetch(\PDO::FETCH_ASSOC),
            'parcours' => $parcours->fetchAll(\PDO::FETCH_ASSOC),
            'photos' => $photos->fetchAll(\PDO::FETCH_ASSOC),
            'date_generation' => date('Y-m-d')
        ];
    }

    // ─── VALIDATION EN MASSE ───

    /**
     * Valide en masse un parcours pour un groupe d'élèves.
     */
    public function validerEnMasse(int $modeleId, array $eleveIds, int $validePar): int
    {
        $count = 0;
        $stmt = $this->pdo->prepare("UPDATE parcours_educatifs SET statut = 'valide', valide_par = :vp, date_validation = NOW() WHERE modele_id = :mid AND eleve_id = :eid AND statut != 'valide'");
        foreach ($eleveIds as $eleveId) {
            $stmt->execute([':vp' => $validePar, ':mid' => $modeleId, ':eid' => $eleveId]);
            $count += $stmt->rowCount();
        }
        return $count;
    }

    // ─── GALERIE PHOTOS ───

    /**
     * Ajoute une photo à un parcours éducatif.
     */
    public function ajouterPhoto(int $parcoursId, string $fichierPath, string $legende = ''): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO parcours_photos (parcours_id, fichier_path, legende) VALUES (:pid, :fp, :l)");
        $stmt->execute([':pid' => $parcoursId, ':fp' => $fichierPath, ':l' => $legende]);
        return (int)$this->pdo->lastInsertId();
    }

    // ─── PROGRESSION CROSS-ANNÉES ───

    /**
     * Retourne la progression d'un élève à travers les années scolaires.
     */
    public function getProgression(int $eleveId): array
    {
        $stmt = $this->pdo->prepare("SELECT pe.annee_scolaire, m.type_parcours, COUNT(*) AS nb_activites, SUM(CASE WHEN pe.statut = 'valide' THEN 1 ELSE 0 END) AS nb_valides FROM parcours_educatifs pe JOIN parcours_modeles m ON pe.modele_id = m.id WHERE pe.eleve_id = :eid GROUP BY pe.annee_scolaire, m.type_parcours ORDER BY pe.annee_scolaire, m.type_parcours");
        $stmt->execute([':eid' => $eleveId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
