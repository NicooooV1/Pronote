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

    /* ───────── CALENDAR / SÉANCES ───────── */

    /**
     * Get upcoming sessions for a club.
     */
    public function getSeances(int $clubId, ?string $dateDebut = null, ?string $dateFin = null): array
    {
        $sql = "SELECT * FROM club_seances WHERE club_id = ?";
        $params = [$clubId];
        if ($dateDebut) { $sql .= ' AND date_seance >= ?'; $params[] = $dateDebut; }
        if ($dateFin) { $sql .= ' AND date_seance <= ?'; $params[] = $dateFin; }
        $sql .= ' ORDER BY date_seance, heure_debut';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create a club session.
     */
    public function creerSeance(int $clubId, array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO club_seances (club_id, date_seance, heure_debut, heure_fin, lieu, description)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$clubId, $data['date_seance'], $data['heure_debut'] ?? null,
                        $data['heure_fin'] ?? null, $data['lieu'] ?? null, $data['description'] ?? null]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Get all upcoming sessions across all clubs for a student.
     */
    public function getSeancesEleve(int $eleveId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT cs.*, c.nom AS club_nom
            FROM club_seances cs
            JOIN clubs c ON cs.club_id = c.id
            JOIN club_inscriptions ci ON ci.club_id = c.id AND ci.eleve_id = ? AND ci.statut = 'accepte'
            WHERE cs.date_seance >= CURDATE()
            ORDER BY cs.date_seance, cs.heure_debut
        ");
        $stmt->execute([$eleveId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getStats(): array
    {
        $clubs = (int)$this->pdo->query("SELECT COUNT(*) FROM clubs WHERE actif = 1")->fetchColumn();
        $inscrits = (int)$this->pdo->query("SELECT COUNT(*) FROM club_inscriptions WHERE statut = 'accepte'")->fetchColumn();
        $attente = (int)$this->pdo->query("SELECT COUNT(*) FROM club_inscriptions WHERE statut = 'en_attente'")->fetchColumn();
        return ['clubs_actifs' => $clubs, 'total_inscrits' => $inscrits, 'demandes_en_attente' => $attente];
    }

    /* ───────── EXPORT ───────── */

    public function getClubsForExport(?string $categorie = null): array
    {
        $clubs = $this->getClubs($categorie);
        return array_map(fn($c) => [
            $c['nom'],
            self::categories()[$c['categorie']] ?? $c['categorie'],
            $c['responsable_nom'] ?? '-',
            $c['horaires'] ?? '-',
            $c['lieu'] ?? '-',
            $c['places_max'] ?? 'Illimité',
            $c['nb_inscrits'],
            $c['date_debut'] ?? '-',
            $c['date_fin'] ?? '-',
        ], $clubs);
    }

    public function getMembresForExport(int $clubId): array
    {
        $membres = $this->getMembres($clubId);
        $club = $this->getClub($clubId);
        return array_map(fn($m) => [
            $club['nom'] ?? '',
            $m['prenom'],
            $m['eleve_nom'],
            $m['classe_nom'] ?? '-',
            $m['date_inscription'] ?? '-',
            ucfirst($m['statut']),
        ], $membres);
    }

    // ─── PRÉSENCE SÉANCES ───

    public function enregistrerPresenceSeance(int $seanceId, int $eleveId, bool $present): void
    {
        $this->pdo->prepare("
            INSERT INTO club_seances_presences (seance_id, eleve_id, present)
            VALUES (:s, :e, :p)
            ON DUPLICATE KEY UPDATE present = VALUES(present)
        ")->execute([':s' => $seanceId, ':e' => $eleveId, ':p' => $present ? 1 : 0]);
    }

    public function getPresencesSeance(int $seanceId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT csp.*, CONCAT(e.prenom, ' ', e.nom) AS eleve_nom, cl.nom AS classe_nom
            FROM club_seances_presences csp
            JOIN eleves e ON csp.eleve_id = e.id
            LEFT JOIN classes cl ON e.classe_id = cl.id
            WHERE csp.seance_id = :s ORDER BY e.nom
        ");
        $stmt->execute([':s' => $seanceId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getTauxPresenceClub(int $clubId): float
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) AS total, SUM(csp.present) AS presents
            FROM club_seances_presences csp
            JOIN club_seances cs ON csp.seance_id = cs.id
            WHERE cs.club_id = :c
        ");
        $stmt->execute([':c' => $clubId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return ($row['total'] > 0) ? round(($row['presents'] / $row['total']) * 100, 1) : 0;
    }

    // ─── BUDGET CLUB ───

    public function ajouterDepense(int $clubId, string $libelle, float $montant, string $type = 'depense', ?string $date = null): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO club_budget (club_id, libelle, montant, type, date_operation)
            VALUES (:c, :l, :m, :t, :d)
        ");
        $stmt->execute([':c' => $clubId, ':l' => $libelle, ':m' => $montant, ':t' => $type, ':d' => $date ?? date('Y-m-d')]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getBudget(int $clubId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM club_budget WHERE club_id = :c ORDER BY date_operation DESC");
        $stmt->execute([':c' => $clubId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getSoldeBudget(int $clubId): float
    {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(CASE WHEN type = 'recette' THEN montant ELSE -montant END), 0) AS solde
            FROM club_budget WHERE club_id = :c
        ");
        $stmt->execute([':c' => $clubId]);
        return round((float)$stmt->fetchColumn(), 2);
    }

    // ─── GALERIE PHOTOS ───

    public function ajouterPhoto(int $clubId, string $chemin, ?string $legende = null, ?int $seanceId = null): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO club_photos (club_id, chemin, legende, seance_id, uploaded_at) VALUES (:c, :ch, :l, :s, NOW())");
        $stmt->execute([':c' => $clubId, ':ch' => $chemin, ':l' => $legende, ':s' => $seanceId]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getPhotos(int $clubId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM club_photos WHERE club_id = :c ORDER BY uploaded_at DESC LIMIT :l");
        $stmt->bindValue(':c', $clubId, \PDO::PARAM_INT);
        $stmt->bindValue(':l', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ─── LISTE D'ATTENTE ───

    public function ajouterListeAttente(int $clubId, int $eleveId): int
    {
        $pos = (int)$this->pdo->prepare("SELECT COALESCE(MAX(position), 0) + 1 FROM club_waitlist WHERE club_id = ?")->execute([$clubId])
            ? (int)$this->pdo->query("SELECT COALESCE(MAX(position), 0) + 1 FROM club_waitlist WHERE club_id = {$clubId}")->fetchColumn() : 1;
        $stmt = $this->pdo->prepare("INSERT INTO club_waitlist (club_id, eleve_id, position, created_at) VALUES (:c, :e, :p, NOW())");
        $stmt->execute([':c' => $clubId, ':e' => $eleveId, ':p' => $pos]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getListeAttente(int $clubId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT cw.*, CONCAT(e.prenom, ' ', e.nom) AS eleve_nom
            FROM club_waitlist cw JOIN eleves e ON cw.eleve_id = e.id
            WHERE cw.club_id = :c ORDER BY cw.position
        ");
        $stmt->execute([':c' => $clubId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function promoteFromWaitlist(int $clubId): bool
    {
        $next = $this->pdo->prepare("SELECT * FROM club_waitlist WHERE club_id = :c ORDER BY position LIMIT 1");
        $next->execute([':c' => $clubId]);
        $entry = $next->fetch(\PDO::FETCH_ASSOC);
        if (!$entry) return false;
        try {
            $this->inscrire($clubId, $entry['eleve_id']);
            $this->traiterDemande($this->pdo->lastInsertId(), 'accepte');
            $this->pdo->prepare("DELETE FROM club_waitlist WHERE id = ?")->execute([$entry['id']]);
            return true;
        } catch (\RuntimeException $e) { return false; }
    }
}
