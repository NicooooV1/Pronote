<?php
/**
 * M30 – Clubs & Associations — Service
 */
class ClubService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getClubs(string $categorie = null): array
    {
        $sql = "
            SELECT c.*,
                   CONCAT(p.prenom, ' ', p.nom) AS responsable_nom,
                   (SELECT COUNT(*) FROM club_inscriptions ci WHERE ci.club_id = c.id AND ci.statut = 'accepte') AS nb_inscrits
            FROM clubs c
            LEFT JOIN professeurs p ON c.responsable_id = p.id
            WHERE c.actif = 1
        ";
        $params = [];
        if ($categorie) { $sql .= ' AND c.categorie = ?'; $params[] = $categorie; }
        $sql .= ' ORDER BY c.nom';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getClub(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT c.*, CONCAT(p.prenom, ' ', p.nom) AS responsable_nom,
                   (SELECT COUNT(*) FROM club_inscriptions ci WHERE ci.club_id = c.id AND ci.statut = 'accepte') AS nb_inscrits
            FROM clubs c
            LEFT JOIN professeurs p ON c.responsable_id = p.id
            WHERE c.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function creerClub(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO clubs (nom, description, categorie, responsable_id, horaires, lieu, places_max, date_debut, date_fin, actif)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute([
            $data['nom'], $data['description'] ?? null, $data['categorie'] ?? 'autre',
            $data['responsable_id'] ?? null, $data['horaires'] ?? null, $data['lieu'] ?? null,
            $data['places_max'] ?? null, $data['date_debut'] ?? null, $data['date_fin'] ?? null,
        ]);
        return $this->pdo->lastInsertId();
    }

    public function modifierClub(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE clubs SET nom=?, description=?, categorie=?, responsable_id=?,
            horaires=?, lieu=?, places_max=?, date_debut=?, date_fin=?, actif=?
            WHERE id=?
        ");
        $stmt->execute([
            $data['nom'], $data['description'], $data['categorie'],
            $data['responsable_id'], $data['horaires'], $data['lieu'],
            $data['places_max'], $data['date_debut'], $data['date_fin'],
            $data['actif'] ?? 1, $id
        ]);
    }

    public function supprimerClub(int $id): void
    {
        $this->pdo->prepare('UPDATE clubs SET actif = 0 WHERE id = ?')->execute([$id]);
    }

    /* ───────── INSCRIPTIONS ───────── */

    public function inscrire(int $clubId, int $eleveId): int
    {
        // Vérifier places
        $club = $this->getClub($clubId);
        if ($club['places_max'] && $club['nb_inscrits'] >= $club['places_max']) {
            throw new RuntimeException('Plus de places disponibles.');
        }
        // Vérifier doublon
        $stmt = $this->pdo->prepare("SELECT id FROM club_inscriptions WHERE club_id = ? AND eleve_id = ? AND statut != 'refuse'");
        $stmt->execute([$clubId, $eleveId]);
        if ($stmt->fetch()) throw new RuntimeException('Déjà inscrit à ce club.');

        $stmt = $this->pdo->prepare("INSERT INTO club_inscriptions (club_id, eleve_id, date_inscription, statut) VALUES (?, ?, NOW(), 'en_attente')");
        $stmt->execute([$clubId, $eleveId]);
        return $this->pdo->lastInsertId();
    }

    public function getMembres(int $clubId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT ci.*, e.prenom, e.nom AS eleve_nom, c.nom AS classe_nom
            FROM club_inscriptions ci
            JOIN eleves e ON ci.eleve_id = e.id
            LEFT JOIN classes cl ON e.classe_id = cl.id
            LEFT JOIN classes c ON e.classe_id = c.id
            WHERE ci.club_id = ? AND ci.statut = 'accepte'
            ORDER BY e.nom
        ");
        $stmt->execute([$clubId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDemandes(int $clubId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT ci.*, e.prenom, e.nom AS eleve_nom
            FROM club_inscriptions ci
            JOIN eleves e ON ci.eleve_id = e.id
            WHERE ci.club_id = ? AND ci.statut = 'en_attente'
            ORDER BY ci.date_inscription
        ");
        $stmt->execute([$clubId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function traiterDemande(int $inscriptionId, string $statut): void
    {
        $stmt = $this->pdo->prepare('UPDATE club_inscriptions SET statut = ? WHERE id = ?');
        $stmt->execute([$statut, $inscriptionId]);
    }

    public function desinscrire(int $inscriptionId): void
    {
        $this->pdo->prepare('DELETE FROM club_inscriptions WHERE id = ?')->execute([$inscriptionId]);
    }

    public function getInscriptionsEleve(int $eleveId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT ci.*, c.nom AS club_nom, c.horaires, c.lieu
            FROM club_inscriptions ci
            JOIN clubs c ON ci.club_id = c.id
            WHERE ci.eleve_id = ? AND c.actif = 1
            ORDER BY c.nom
        ");
        $stmt->execute([$eleveId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProfesseurs(): array
    {
        $stmt = $this->pdo->query("SELECT id, prenom, nom FROM professeurs ORDER BY nom");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function categories(): array
    {
        return [
            'sport' => 'Sport', 'culture' => 'Culture', 'science' => 'Sciences',
            'art' => 'Arts', 'musique' => 'Musique', 'theatre' => 'Théâtre',
            'lecture' => 'Lecture', 'informatique' => 'Informatique',
            'environnement' => 'Environnement', 'solidarite' => 'Solidarité', 'autre' => 'Autre',
        ];
    }

    public static function iconeCategorie(string $cat): string
    {
        $map = [
            'sport' => 'running', 'culture' => 'globe', 'science' => 'flask',
            'art' => 'palette', 'musique' => 'music', 'theatre' => 'theater-masks',
            'lecture' => 'book-reader', 'informatique' => 'laptop-code',
            'environnement' => 'leaf', 'solidarite' => 'hands-helping',
        ];
        return $map[$cat] ?? 'users';
    }
}
