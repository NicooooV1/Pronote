<?php
/**
 * M36 – Contenus pédagogiques — Service
 */
class RessourceService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /* ───── RESSOURCES ───── */

    public function getRessources(array $filters = []): array
    {
        $sql = "SELECT r.*, m.nom AS matiere_nom,
                       COALESCE(CONCAT(pr.prenom, ' ', pr.nom), CONCAT(u.prenom, ' ', u.nom)) AS auteur_nom
                FROM ressources_pedagogiques r
                LEFT JOIN matieres m ON r.matiere_id = m.id
                LEFT JOIN professeurs pr ON r.auteur_id = pr.id
                LEFT JOIN utilisateurs u ON r.auteur_id = u.id
                WHERE 1=1";
        $params = [];
        if (!empty($filters['type'])) {
            $sql .= ' AND r.type = ?'; $params[] = $filters['type'];
        }
        if (!empty($filters['matiere_id'])) {
            $sql .= ' AND r.matiere_id = ?'; $params[] = $filters['matiere_id'];
        }
        if (!empty($filters['niveau'])) {
            $sql .= ' AND r.niveau = ?'; $params[] = $filters['niveau'];
        }
        if (!empty($filters['auteur_id'])) {
            $sql .= ' AND r.auteur_id = ?'; $params[] = $filters['auteur_id'];
        }
        if (isset($filters['publie'])) {
            $sql .= ' AND r.publie = ?'; $params[] = $filters['publie'];
        }
        if (!empty($filters['search'])) {
            $sql .= ' AND (r.titre LIKE ? OR r.tags LIKE ?)';
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
        }
        $sql .= ' ORDER BY r.created_at DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRessource(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT r.*, m.nom AS matiere_nom,
                COALESCE(CONCAT(pr.prenom, ' ', pr.nom), CONCAT(u.prenom, ' ', u.nom)) AS auteur_nom
                FROM ressources_pedagogiques r
                LEFT JOIN matieres m ON r.matiere_id = m.id
                LEFT JOIN professeurs pr ON r.auteur_id = pr.id
                LEFT JOIN utilisateurs u ON r.auteur_id = u.id
                WHERE r.id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function creerRessource(array $d): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO ressources_pedagogiques
            (titre, type, matiere_id, auteur_id, contenu, niveau, tags, publie)
            VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $d['titre'], $d['type'], $d['matiere_id'] ?: null,
            $d['auteur_id'], $d['contenu'] ?? null,
            $d['niveau'] ?? null, $d['tags'] ?? null,
            $d['publie'] ?? 1
        ]);
        return $this->pdo->lastInsertId();
    }

    public function modifierRessource(int $id, array $d): void
    {
        $stmt = $this->pdo->prepare("UPDATE ressources_pedagogiques SET titre=?, type=?, matiere_id=?, contenu=?, niveau=?, tags=?, publie=? WHERE id=?");
        $stmt->execute([$d['titre'], $d['type'], $d['matiere_id'] ?: null, $d['contenu'] ?? null, $d['niveau'] ?? null, $d['tags'] ?? null, $d['publie'] ?? 1, $id]);
    }

    public function supprimerRessource(int $id): void
    {
        $this->pdo->prepare("DELETE FROM ressources_pedagogiques WHERE id = ?")->execute([$id]);
    }

    /* ───── HELPERS ───── */

    public function getMatieres(): array
    {
        return $this->pdo->query("SELECT id, nom FROM matieres ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getStats(): array
    {
        $total = $this->pdo->query("SELECT COUNT(*) FROM ressources_pedagogiques")->fetchColumn();
        $publiees = $this->pdo->query("SELECT COUNT(*) FROM ressources_pedagogiques WHERE publie = 1")->fetchColumn();
        return ['total' => $total, 'publiees' => $publiees];
    }

    /* ───── STATIC ───── */

    public static function types(): array
    {
        return [
            'cours' => 'Cours', 'exercice' => 'Exercice', 'video' => 'Vidéo',
            'document' => 'Document', 'lien' => 'Lien', 'qcm' => 'QCM'
        ];
    }

    public static function niveaux(): array
    {
        return ['6eme' => '6ème', '5eme' => '5ème', '4eme' => '4ème', '3eme' => '3ème',
                '2nde' => '2nde', '1ere' => '1ère', 'terminale' => 'Terminale',
                'bts1' => 'BTS 1', 'bts2' => 'BTS 2'];
    }

    public static function difficultes(): array
    {
        return ['facile' => 'Facile', 'moyen' => 'Moyen', 'difficile' => 'Difficile'];
    }

    public static function iconeType(string $type): string
    {
        $m = ['cours' => 'book', 'exercice' => 'pencil-alt', 'video' => 'video', 'document' => 'file-alt', 'lien' => 'link', 'qcm' => 'list-ol'];
        return $m[$type] ?? 'file';
    }
}
