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

    /* ───── SHARING & DOWNLOADS ───── */

    /**
     * Share a resource with specific targets.
     */
    public function partagerRessource(int $ressourceId, array $sharedWith): void
    {
        $json = json_encode($sharedWith, JSON_UNESCAPED_UNICODE);
        $this->pdo->prepare("UPDATE ressources_pedagogiques SET shared_with = ? WHERE id = ?")
                   ->execute([$json, $ressourceId]);
    }

    /**
     * Increment download counter for a resource.
     */
    public function incrementerTelechargements(int $ressourceId): void
    {
        $this->pdo->prepare("UPDATE ressources_pedagogiques SET downloads = downloads + 1 WHERE id = ?")
                   ->execute([$ressourceId]);
    }

    /**
     * Get resources shared with a specific class or role.
     */
    public function getRessourcesPartagees(string $targetType, int $targetId): array
    {
        $rows = $this->getRessources(['publie' => 1]);
        return array_filter($rows, function ($r) use ($targetType, $targetId) {
            if (empty($r['shared_with'])) return true; // Public if no restriction
            $shared = json_decode($r['shared_with'], true);
            if (!is_array($shared)) return true;
            foreach ($shared as $s) {
                if (($s['type'] ?? '') === $targetType && ($s['id'] ?? 0) == $targetId) return true;
                if (($s['type'] ?? '') === 'tous') return true;
            }
            return false;
        });
    }

    /**
     * Get most downloaded resources.
     */
    public function getTopTelechargements(int $limit = 10): array
    {
        $stmt = $this->pdo->prepare("
            SELECT r.*, m.nom AS matiere_nom
            FROM ressources_pedagogiques r
            LEFT JOIN matieres m ON r.matiere_id = m.id
            WHERE r.publie = 1
            ORDER BY r.downloads DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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

    // ─── HISTORIQUE VERSIONS ───

    public function creerVersion(int $ressourceId, string $fichierPath, int $modifiePar): int
    {
        $version = $this->pdo->prepare("SELECT COALESCE(MAX(version),0)+1 FROM ressources_versions WHERE ressource_id = :rid");
        $version->execute([':rid' => $ressourceId]);
        $numVersion = (int)$version->fetchColumn();

        $stmt = $this->pdo->prepare("INSERT INTO ressources_versions (ressource_id, version, fichier_path, modifie_par) VALUES (:rid, :v, :fp, :mp)");
        $stmt->execute([':rid' => $ressourceId, ':v' => $numVersion, ':fp' => $fichierPath, ':mp' => $modifiePar]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getVersions(int $ressourceId): array
    {
        $stmt = $this->pdo->prepare("SELECT rv.*, CONCAT(p.prenom,' ',p.nom) AS modifie_par_nom FROM ressources_versions rv LEFT JOIN professeurs p ON rv.modifie_par = p.id WHERE rv.ressource_id = :rid ORDER BY rv.version DESC");
        $stmt->execute([':rid' => $ressourceId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ─── PARTAGE ENTRE PROFS ───

    public function partagerRessource(int $ressourceId, array $profIds): void
    {
        $this->pdo->prepare("UPDATE ressources SET partage = 1, partage_avec = :pa WHERE id = :id")
            ->execute([':pa' => json_encode($profIds), ':id' => $ressourceId]);
    }

    public function getRessourcesPartagees(int $profId): array
    {
        $stmt = $this->pdo->prepare("SELECT r.*, CONCAT(p.prenom,' ',p.nom) AS auteur_nom FROM ressources r LEFT JOIN professeurs p ON r.professeur_id = p.id WHERE r.partage = 1 AND (JSON_CONTAINS(r.partage_avec, CAST(:pid AS CHAR)) OR r.professeur_id = :pid2) ORDER BY r.date_creation DESC");
        $stmt->execute([':pid' => $profId, ':pid2' => $profId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ─── ANALYTICS USAGE ───

    public function getUsageStats(int $ressourceId): array
    {
        $vues = $this->pdo->prepare("SELECT COUNT(*) AS total_vues, COUNT(DISTINCT user_id) AS users_uniques FROM ressources_vues WHERE ressource_id = :rid");
        $vues->execute([':rid' => $ressourceId]);
        $stats = $vues->fetch(\PDO::FETCH_ASSOC);

        $parJour = $this->pdo->prepare("SELECT DATE(date_vue) AS jour, COUNT(*) AS nb FROM ressources_vues WHERE ressource_id = :rid AND date_vue >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY jour ORDER BY jour");
        $parJour->execute([':rid' => $ressourceId]);
        $stats['par_jour'] = $parJour->fetchAll(\PDO::FETCH_ASSOC);

        return $stats;
    }

    public function enregistrerVue(int $ressourceId, int $userId, string $userType): void
    {
        $this->pdo->prepare("INSERT INTO ressources_vues (ressource_id, user_id, user_type, date_vue) VALUES (:rid, :uid, :ut, NOW())")
            ->execute([':rid' => $ressourceId, ':uid' => $userId, ':ut' => $userType]);
    }

    // ─── TAGS + RECHERCHE ───

    public function setTags(int $ressourceId, array $tags): void
    {
        $this->pdo->prepare("UPDATE ressources SET tags = :t WHERE id = :id")
            ->execute([':t' => json_encode($tags), ':id' => $ressourceId]);
    }

    public function searchByTag(string $tag): array
    {
        $stmt = $this->pdo->prepare("SELECT r.*, CONCAT(p.prenom,' ',p.nom) AS auteur_nom FROM ressources r LEFT JOIN professeurs p ON r.professeur_id = p.id WHERE JSON_CONTAINS(r.tags, :tag) ORDER BY r.date_creation DESC");
        $stmt->execute([':tag' => json_encode($tag)]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
