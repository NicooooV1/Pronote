<?php
/**
 * M44 – Diplômes & Relevés — Service
 */
class DiplomeService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /* ───── DIPLÔMES ───── */

    public function getDiplomes(array $filters = []): array
    {
        $sql = "SELECT d.*, CONCAT(e.prenom, ' ', e.nom) AS eleve_nom, c.nom AS classe_nom
                FROM diplomes d
                JOIN eleves e ON d.eleve_id = e.id
                LEFT JOIN classes c ON e.classe_id = c.id
                WHERE 1=1";
        $params = [];
        if (!empty($filters['type'])) { $sql .= ' AND d.type = ?'; $params[] = $filters['type']; }
        if (!empty($filters['eleve_id'])) { $sql .= ' AND d.eleve_id = ?'; $params[] = $filters['eleve_id']; }
        if (!empty($filters['mention'])) { $sql .= ' AND d.mention = ?'; $params[] = $filters['mention']; }
        if (!empty($filters['annee'])) { $sql .= ' AND YEAR(d.date_obtention) = ?'; $params[] = $filters['annee']; }
        $sql .= ' ORDER BY d.date_obtention DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDiplome(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT d.*, CONCAT(e.prenom, ' ', e.nom) AS eleve_nom, c.nom AS classe_nom
                FROM diplomes d JOIN eleves e ON d.eleve_id = e.id LEFT JOIN classes c ON e.classe_id = c.id WHERE d.id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function creerDiplome(array $d): int
    {
        $numero = strtoupper(substr($d['type'], 0, 3)) . '-' . date('Y') . '-' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
        $stmt = $this->pdo->prepare("INSERT INTO diplomes (eleve_id, intitule, type, mention, date_obtention, numero, fichier_path, description)
                VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([$d['eleve_id'], $d['intitule'], $d['type'], $d['mention'] ?? null,
                        $d['date_obtention'], $numero, $d['fichier_path'] ?? null, $d['description'] ?? null]);
        return $this->pdo->lastInsertId();
    }

    public function modifierDiplome(int $id, array $d): void
    {
        $stmt = $this->pdo->prepare("UPDATE diplomes SET intitule=?, type=?, mention=?, date_obtention=?, description=? WHERE id=?");
        $stmt->execute([$d['intitule'], $d['type'], $d['mention'] ?? null, $d['date_obtention'], $d['description'] ?? null, $id]);
    }

    public function supprimerDiplome(int $id): void
    {
        $this->pdo->prepare("DELETE FROM diplomes WHERE id = ?")->execute([$id]);
    }

    /* ───── ACCÈS PAR RÔLE ───── */

    public function getMesDiplomes(int $eleveId): array
    {
        return $this->getDiplomes(['eleve_id' => $eleveId]);
    }

    public function getDiplomesParent(int $parentId): array
    {
        $stmt = $this->pdo->prepare("SELECT d.*, CONCAT(e.prenom, ' ', e.nom) AS eleve_nom
            FROM diplomes d
            JOIN eleves e ON d.eleve_id = e.id
            JOIN parent_eleve pe ON e.id = pe.eleve_id
            WHERE pe.parent_id = ?
            ORDER BY d.date_obtention DESC");
        $stmt->execute([$parentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ───── HELPERS ───── */

    public function getEleves(): array
    {
        return $this->pdo->query("SELECT e.id, e.prenom, e.nom, c.nom AS classe_nom FROM eleves e LEFT JOIN classes c ON e.classe_id = c.id ORDER BY e.nom")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getStats(): array
    {
        $total = $this->pdo->query("SELECT COUNT(*) FROM diplomes")->fetchColumn();
        $annee = $this->pdo->query("SELECT COUNT(*) FROM diplomes WHERE YEAR(date_obtention) = YEAR(CURDATE())")->fetchColumn();
        return ['total' => $total, 'annee_courante' => $annee];
    }

    /* ───── STATIC ───── */

    public static function typesDiplome(): array
    {
        return [
            'brevet' => 'Brevet des collèges', 'bac' => 'Baccalauréat', 'cap' => 'CAP',
            'bep' => 'BEP', 'bts' => 'BTS', 'licence' => 'Licence',
            'attestation' => 'Attestation', 'certificat' => 'Certificat', 'autre' => 'Autre'
        ];
    }

    public static function mentions(): array
    {
        return [
            'sans' => 'Sans mention', 'ab' => 'Assez bien', 'bien' => 'Bien',
            'tb' => 'Très bien', 'felicitations' => 'Félicitations du jury'
        ];
    }

    public static function badgeMention(?string $m): string
    {
        if (!$m) return '';
        $c = ['sans' => 'secondary', 'ab' => 'info', 'bien' => 'primary', 'tb' => 'success', 'felicitations' => 'warning'];
        $labels = self::mentions();
        return '<span class="badge badge-' . ($c[$m] ?? 'secondary') . '">' . ($labels[$m] ?? $m) . '</span>';
    }
}
