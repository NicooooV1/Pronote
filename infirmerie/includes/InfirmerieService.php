<?php
/**
 * M31 – Santé / Infirmerie — Service
 */
class InfirmerieService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /* ───────── FICHES SANTÉ ───────── */

    public function getFiche(int $eleveId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM fiches_sante WHERE eleve_id = ?");
        $stmt->execute([$eleveId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function sauvegarderFiche(int $eleveId, array $data): void
    {
        $existing = $this->getFiche($eleveId);
        if ($existing) {
            $stmt = $this->pdo->prepare("
                UPDATE fiches_sante SET allergies=?, traitements=?, contacts_urgence=?, pai=?, groupe_sanguin=?, remarques=?, date_maj=NOW()
                WHERE eleve_id=?
            ");
            $stmt->execute([
                $data['allergies'], $data['traitements'], $data['contacts_urgence'],
                $data['pai'] ?? null, $data['groupe_sanguin'] ?? null, $data['remarques'] ?? null,
                $eleveId,
            ]);
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO fiches_sante (eleve_id, allergies, traitements, contacts_urgence, pai, groupe_sanguin, remarques, date_maj)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $eleveId, $data['allergies'], $data['traitements'], $data['contacts_urgence'],
                $data['pai'] ?? null, $data['groupe_sanguin'] ?? null, $data['remarques'] ?? null,
            ]);
        }
    }

    public function getFiches(string $recherche = null): array
    {
        $sql = "
            SELECT fs.*, e.prenom, e.nom AS eleve_nom, cl.nom AS classe_nom
            FROM fiches_sante fs
            JOIN eleves e ON fs.eleve_id = e.id
            LEFT JOIN classes cl ON e.classe_id = cl.id
        ";
        $params = [];
        if ($recherche) {
            $sql .= " WHERE e.nom LIKE ? OR e.prenom LIKE ?";
            $params = ["%$recherche%", "%$recherche%"];
        }
        $sql .= ' ORDER BY e.nom, e.prenom';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ───────── PASSAGES INFIRMERIE ───────── */

    public function creerPassage(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO passages_infirmerie (eleve_id, date_passage, motif, symptomes, soins, orientation, notifier_parents, remarques)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['eleve_id'], $data['date_passage'] ?? date('Y-m-d H:i:s'),
            $data['motif'], $data['symptomes'] ?? null, $data['soins'] ?? null,
            $data['orientation'] ?? 'retour_classe', $data['notifier_parents'] ?? 0,
            $data['remarques'] ?? null,
        ]);
        return $this->pdo->lastInsertId();
    }

    public function getPassage(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT p.*, e.prenom, e.nom AS eleve_nom, cl.nom AS classe_nom
            FROM passages_infirmerie p
            JOIN eleves e ON p.eleve_id = e.id
            LEFT JOIN classes cl ON e.classe_id = cl.id
            WHERE p.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getPassages(array $filtres = []): array
    {
        $sql = "
            SELECT p.*, e.prenom, e.nom AS eleve_nom, cl.nom AS classe_nom
            FROM passages_infirmerie p
            JOIN eleves e ON p.eleve_id = e.id
            LEFT JOIN classes cl ON e.classe_id = cl.id
            WHERE 1=1
        ";
        $params = [];
        if (!empty($filtres['eleve_id'])) {
            $sql .= ' AND p.eleve_id = ?';
            $params[] = $filtres['eleve_id'];
        }
        if (!empty($filtres['date_debut'])) {
            $sql .= ' AND p.date_passage >= ?';
            $params[] = $filtres['date_debut'];
        }
        if (!empty($filtres['date_fin'])) {
            $sql .= ' AND p.date_passage <= ?';
            $params[] = $filtres['date_fin'] . ' 23:59:59';
        }
        if (!empty($filtres['orientation'])) {
            $sql .= ' AND p.orientation = ?';
            $params[] = $filtres['orientation'];
        }
        $sql .= ' ORDER BY p.date_passage DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPassagesEleve(int $eleveId): array
    {
        return $this->getPassages(['eleve_id' => $eleveId]);
    }

    public function getEleves(string $recherche = null): array
    {
        $sql = "SELECT e.id, e.prenom, e.nom, cl.nom AS classe_nom FROM eleves e LEFT JOIN classes cl ON e.classe_id = cl.id";
        $params = [];
        if ($recherche) { $sql .= ' WHERE e.nom LIKE ? OR e.prenom LIKE ?'; $params = ["%$recherche%", "%$recherche%"]; }
        $sql .= ' ORDER BY e.nom, e.prenom';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getEnfantsParent(int $parentId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT e.id, e.prenom, e.nom, cl.nom AS classe_nom
            FROM eleves e
            JOIN parent_eleve pe ON e.id = pe.eleve_id
            LEFT JOIN classes cl ON e.classe_id = cl.id
            WHERE pe.parent_id = ?
        ");
        $stmt->execute([$parentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getStats(): array
    {
        $today = date('Y-m-d');
        $month = date('Y-m');
        return [
            'fiches' => $this->pdo->query("SELECT COUNT(*) FROM fiches_sante")->fetchColumn(),
            'passages_jour' => $this->pdo->prepare("SELECT COUNT(*) FROM passages_infirmerie WHERE DATE(date_passage) = ?")->execute([$today]) ? $this->pdo->prepare("SELECT COUNT(*) FROM passages_infirmerie WHERE DATE(date_passage) = ?")->execute([$today]) : 0,
            'passages_mois' => $this->pdo->query("SELECT COUNT(*) FROM passages_infirmerie WHERE date_passage LIKE '$month%'")->fetchColumn(),
            'pai_actifs' => $this->pdo->query("SELECT COUNT(*) FROM fiches_sante WHERE pai IS NOT NULL AND pai != ''")->fetchColumn(),
        ];
    }

    public function getStatsPassages(): array
    {
        // Recalculate properly
        $today = date('Y-m-d');
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM passages_infirmerie WHERE DATE(date_passage) = ?");
        $stmt->execute([$today]);
        $jour = $stmt->fetchColumn();

        $month = date('Y-m');
        $mois = $this->pdo->query("SELECT COUNT(*) FROM passages_infirmerie WHERE date_passage LIKE '$month%'")->fetchColumn();

        $envois = $this->pdo->query("SELECT COUNT(*) FROM passages_infirmerie WHERE orientation = 'renvoye_domicile'")->fetchColumn();
        $urgences = $this->pdo->query("SELECT COUNT(*) FROM passages_infirmerie WHERE orientation = 'urgences'")->fetchColumn();

        return ['jour' => $jour, 'mois' => $mois, 'renvoyes' => $envois, 'urgences' => $urgences];
    }

    public static function orientations(): array
    {
        return [
            'retour_classe' => 'Retour en classe',
            'repos_infirmerie' => 'Repos à l\'infirmerie',
            'renvoye_domicile' => 'Renvoyé à domicile',
            'urgences' => 'Urgences / SAMU',
            'medecin' => 'Médecin traitant',
        ];
    }

    public static function orientationBadge(string $o): string
    {
        $map = [
            'retour_classe' => 'success',
            'repos_infirmerie' => 'info',
            'renvoye_domicile' => 'warning',
            'urgences' => 'danger',
            'medecin' => 'secondary',
        ];
        $labels = self::orientations();
        return '<span class="badge badge-' . ($map[$o] ?? 'secondary') . '">' . ($labels[$o] ?? $o) . '</span>';
    }

    public static function groupesSanguins(): array
    {
        return ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
    }

    /* ───────── STATISTIQUES MENSUELLES ───────── */

    /**
     * Stats passages par mois (12 derniers mois)
     */
    public function getStatsMensuelles(): array
    {
        $result = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = date('Y-m', strtotime("-{$i} months"));
            $label = date('M Y', strtotime($month . '-01'));
            $count = (int)$this->pdo->query("SELECT COUNT(*) FROM passages_infirmerie WHERE date_passage LIKE '$month%'")->fetchColumn();
            $result[] = ['mois' => $label, 'passages' => $count];
        }
        return $result;
    }

    /**
     * Répartition par orientation
     */
    public function getRepartitionOrientations(): array
    {
        return $this->pdo->query("
            SELECT orientation, COUNT(*) AS total 
            FROM passages_infirmerie 
            WHERE date_passage >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY orientation ORDER BY total DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ───────── EXPORT ───────── */

    public function getPassagesForExport(array $filtres = []): array
    {
        $passages = $this->getPassages($filtres);
        $orientations = self::orientations();
        $rows = [];
        foreach ($passages as $p) {
            $rows[] = [
                $p['eleve_nom'] ?? '',
                $p['classe_nom'] ?? '',
                $p['date_passage'] ?? '',
                $p['motif'] ?? '',
                $p['symptomes'] ?? '',
                $p['soins'] ?? '',
                $orientations[$p['orientation'] ?? ''] ?? $p['orientation'] ?? '',
                $p['commentaire'] ?? '',
            ];
        }
        return $rows;
    }
}
