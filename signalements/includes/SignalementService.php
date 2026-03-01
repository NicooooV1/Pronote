<?php
/**
 * M45 – Anti-harcèlement — Service de signalement
 */
class SignalementService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function creerSignalement(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO signalements (
                auteur_id, auteur_type, type, description, lieu, date_faits,
                personnes_impliquees, temoins, anonyme, urgence, confidentiel,
                statut, date_signalement
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 'nouveau', NOW())
        ");
        $stmt->execute([
            $data['anonyme'] ? null : $data['auteur_id'],
            $data['anonyme'] ? null : $data['auteur_type'],
            $data['type'],
            $data['description'],
            $data['lieu'] ?? null,
            $data['date_faits'] ?? null,
            $data['personnes_impliquees'] ?? null,
            $data['temoins'] ?? null,
            $data['anonyme'] ? 1 : 0,
            $data['urgence'] ?? 'normale',
        ]);
        return $this->pdo->lastInsertId();
    }

    public function getSignalement(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM signalements WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getTousSignalements(array $filters = []): array
    {
        $sql = 'SELECT * FROM signalements WHERE 1=1';
        $params = [];
        if (!empty($filters['statut'])) { $sql .= ' AND statut = ?'; $params[] = $filters['statut']; }
        if (!empty($filters['type'])) { $sql .= ' AND type = ?'; $params[] = $filters['type']; }
        if (!empty($filters['urgence'])) { $sql .= ' AND urgence = ?'; $params[] = $filters['urgence']; }
        $sql .= ' ORDER BY FIELD(urgence, "critique", "haute", "normale", "basse"), date_signalement DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMesSignalements(int $userId, string $userType): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM signalements WHERE auteur_id = ? AND auteur_type = ? ORDER BY date_signalement DESC');
        $stmt->execute([$userId, $userType]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function changerStatut(int $id, string $statut, int $traitePar = null): void
    {
        $sql = 'UPDATE signalements SET statut = ?, date_traitement = NOW()';
        $params = [$statut];
        if ($traitePar) {
            $sql .= ', traite_par = ?';
            $params[] = $traitePar;
        }
        $sql .= ' WHERE id = ?';
        $params[] = $id;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    public function ajouterNote(int $id, string $notes): void
    {
        $stmt = $this->pdo->prepare('UPDATE signalements SET notes_traitement = CONCAT(COALESCE(notes_traitement, ""), ?) WHERE id = ?');
        $stmt->execute(["\n[" . date('d/m/Y H:i') . "] " . $notes, $id]);
    }

    public function getStats(): array
    {
        $stmt = $this->pdo->query("
            SELECT
                COUNT(*) AS total,
                COUNT(CASE WHEN statut = 'nouveau' THEN 1 END) AS nouveaux,
                COUNT(CASE WHEN statut = 'en_cours' THEN 1 END) AS en_cours,
                COUNT(CASE WHEN urgence IN ('haute', 'critique') THEN 1 END) AS urgents,
                COUNT(CASE WHEN anonyme = 1 THEN 1 END) AS anonymes
            FROM signalements
        ");
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function typesSignalement(): array
    {
        return [
            'harcelement' => 'Harcèlement',
            'cyber_harcelement' => 'Cyberharcèlement',
            'violence' => 'Violence physique',
            'discrimination' => 'Discrimination',
            'intimidation' => 'Intimidation',
            'exclusion' => 'Exclusion sociale',
            'autre' => 'Autre',
        ];
    }

    public static function statutBadge(string $statut): string
    {
        $map = [
            'nouveau' => '<span class="badge badge-danger">Nouveau</span>',
            'en_cours' => '<span class="badge badge-warning">En cours</span>',
            'traite' => '<span class="badge badge-success">Traité</span>',
            'classe' => '<span class="badge badge-secondary">Classé</span>',
        ];
        return $map[$statut] ?? '<span class="badge">' . $statut . '</span>';
    }

    public static function urgenceBadge(string $urgence): string
    {
        $map = [
            'basse' => '<span class="badge badge-secondary">Basse</span>',
            'normale' => '<span class="badge badge-info">Normale</span>',
            'haute' => '<span class="badge badge-warning">Haute</span>',
            'critique' => '<span class="badge badge-danger">Critique</span>',
        ];
        return $map[$urgence] ?? '<span class="badge">' . $urgence . '</span>';
    }
}
