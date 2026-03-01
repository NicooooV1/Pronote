<?php
/**
 * M28 – Orientation — Service
 */
class OrientationService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /* ───────── FICHES ───────── */

    public function creerFiche(int $eleveId, array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO orientation_fiches (eleve_id, annee_scolaire, projet_professionnel, centres_interet, competences_cles, avis_pp, avis_conseil, statut, date_creation)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'brouillon', NOW())
        ");
        $stmt->execute([
            $eleveId,
            $data['annee_scolaire'],
            $data['projet_professionnel'] ?? null,
            $data['centres_interet'] ?? null,
            $data['competences_cles'] ?? null,
            $data['avis_pp'] ?? null,
            $data['avis_conseil'] ?? null,
        ]);
        return $this->pdo->lastInsertId();
    }

    public function modifierFiche(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE orientation_fiches
            SET projet_professionnel = ?, centres_interet = ?, competences_cles = ?,
                avis_pp = ?, avis_conseil = ?, statut = ?, date_modification = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $data['projet_professionnel'] ?? null,
            $data['centres_interet'] ?? null,
            $data['competences_cles'] ?? null,
            $data['avis_pp'] ?? null,
            $data['avis_conseil'] ?? null,
            $data['statut'] ?? 'brouillon',
            $id
        ]);
    }

    public function getFiche(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT f.*, e.prenom, e.nom AS eleve_nom, c.nom AS classe_nom
            FROM orientation_fiches f
            JOIN eleves e ON f.eleve_id = e.id
            LEFT JOIN classes c ON e.classe_id = c.id
            WHERE f.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getFicheEleve(int $eleveId, string $annee = null): ?array
    {
        $sql = "SELECT f.*, e.prenom, e.nom AS eleve_nom FROM orientation_fiches f JOIN eleves e ON f.eleve_id = e.id WHERE f.eleve_id = ?";
        $params = [$eleveId];
        if ($annee) { $sql .= ' AND f.annee_scolaire = ?'; $params[] = $annee; }
        $sql .= ' ORDER BY f.date_creation DESC LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getFiches(array $filters = []): array
    {
        $sql = "
            SELECT f.*, e.prenom, e.nom AS eleve_nom, c.nom AS classe_nom
            FROM orientation_fiches f
            JOIN eleves e ON f.eleve_id = e.id
            LEFT JOIN classes c ON e.classe_id = c.id
            WHERE 1=1
        ";
        $params = [];
        if (!empty($filters['classe_id'])) {
            $sql .= ' AND e.classe_id = ?';
            $params[] = $filters['classe_id'];
        }
        if (!empty($filters['statut'])) {
            $sql .= ' AND f.statut = ?';
            $params[] = $filters['statut'];
        }
        $sql .= ' ORDER BY e.nom, e.prenom';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ───────── VOEUX ───────── */

    public function getVoeux(int $ficheId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM orientation_voeux WHERE fiche_id = ? ORDER BY rang');
        $stmt->execute([$ficheId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function sauvegarderVoeux(int $ficheId, array $voeux): void
    {
        $this->pdo->prepare('DELETE FROM orientation_voeux WHERE fiche_id = ?')->execute([$ficheId]);

        $stmt = $this->pdo->prepare("
            INSERT INTO orientation_voeux (fiche_id, rang, formation, etablissement_vise, motivation, avis_pp, avis_conseil)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($voeux as $i => $v) {
            if (empty(trim($v['formation'] ?? ''))) continue;
            $stmt->execute([
                $ficheId,
                $i + 1,
                $v['formation'],
                $v['etablissement_vise'] ?? null,
                $v['motivation'] ?? null,
                $v['avis_pp'] ?? null,
                $v['avis_conseil'] ?? null,
            ]);
        }
    }

    /* ───────── HELPERS ───────── */

    public function getClasses(): array
    {
        $stmt = $this->pdo->query('SELECT id, nom FROM classes ORDER BY nom');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getElevesClasse(int $classeId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, prenom, nom FROM eleves WHERE classe_id = ? ORDER BY nom, prenom');
        $stmt->execute([$classeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Enfants du parent connecté
     */
    public function getEnfantsParent(int $parentId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT e.id, e.prenom, e.nom, c.nom AS classe_nom
            FROM parent_eleve pe
            JOIN eleves e ON pe.eleve_id = e.id
            LEFT JOIN classes c ON e.classe_id = c.id
            WHERE pe.parent_id = ?
        ");
        $stmt->execute([$parentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getStats(): array
    {
        $stmt = $this->pdo->query("
            SELECT
                COUNT(*) AS total,
                COUNT(CASE WHEN statut = 'brouillon' THEN 1 END) AS brouillons,
                COUNT(CASE WHEN statut = 'soumise' THEN 1 END) AS soumises,
                COUNT(CASE WHEN statut = 'validee' THEN 1 END) AS validees
            FROM orientation_fiches
        ");
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function statutBadge(string $statut): string
    {
        $map = [
            'brouillon' => '<span class="badge badge-secondary">Brouillon</span>',
            'soumise' => '<span class="badge badge-info">Soumise</span>',
            'validee' => '<span class="badge badge-success">Validée</span>',
            'refusee' => '<span class="badge badge-danger">Refusée</span>',
        ];
        return $map[$statut] ?? '<span class="badge">' . $statut . '</span>';
    }
}
