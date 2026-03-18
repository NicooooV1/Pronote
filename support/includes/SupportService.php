<?php
/**
 * M34 – Support & Aide — Service
 */
class SupportService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ── Tickets ──

    public function creerTicket(int $userId, string $userType, string $sujet, string $description, string $categorie = 'technique', string $priorite = 'normale'): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO tickets_support (user_id, user_type, sujet, description, categorie, priorite) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $userType, $sujet, $description, $categorie, $priorite]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getTicketsUser(int $userId, string $userType): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM tickets_support WHERE user_id = ? AND user_type = ? ORDER BY date_creation DESC");
        $stmt->execute([$userId, $userType]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTousTickets(array $filters = []): array
    {
        $sql = "SELECT t.*, COALESCE(
            (SELECT CONCAT(e.prenom, ' ', e.nom) FROM eleves e WHERE e.id = t.user_id AND t.user_type = 'eleve'),
            (SELECT CONCAT(p.prenom, ' ', p.nom) FROM parents p WHERE p.id = t.user_id AND t.user_type = 'parent'),
            (SELECT CONCAT(pr.prenom, ' ', pr.nom) FROM professeurs pr WHERE pr.id = t.user_id AND t.user_type = 'professeur'),
            (SELECT CONCAT(v.prenom, ' ', v.nom) FROM vie_scolaire v WHERE v.id = t.user_id AND t.user_type = 'vie_scolaire'),
            (SELECT CONCAT(a.prenom, ' ', a.nom) FROM administrateurs a WHERE a.id = t.user_id AND t.user_type = 'administrateur')
        ) AS nom_utilisateur FROM tickets_support t WHERE 1=1";
        $params = [];
        if (!empty($filters['statut'])) { $sql .= " AND t.statut = ?"; $params[] = $filters['statut']; }
        if (!empty($filters['categorie'])) { $sql .= " AND t.categorie = ?"; $params[] = $filters['categorie']; }
        if (!empty($filters['priorite'])) { $sql .= " AND t.priorite = ?"; $params[] = $filters['priorite']; }
        $sql .= " ORDER BY FIELD(t.priorite, 'urgente', 'haute', 'normale', 'basse'), t.date_creation DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTicket(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM tickets_support WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function repondre(int $id, string $reponse, int $adminId): bool
    {
        $stmt = $this->pdo->prepare("UPDATE tickets_support SET reponse = ?, traite_par = ?, date_reponse = NOW(), statut = 'resolu' WHERE id = ?");
        return $stmt->execute([$reponse, $adminId, $id]);
    }

    public function changerStatut(int $id, string $statut): bool
    {
        $stmt = $this->pdo->prepare("UPDATE tickets_support SET statut = ? WHERE id = ?");
        return $stmt->execute([$statut, $id]);
    }

    public function getStatsTickets(): array
    {
        $stmt = $this->pdo->query("
            SELECT 
                COUNT(*) as total,
                SUM(statut = 'ouvert') as ouverts,
                SUM(statut = 'en_cours') as en_cours,
                SUM(statut = 'resolu') as resolus,
                SUM(priorite = 'urgente' AND statut IN ('ouvert','en_cours')) as urgents
            FROM tickets_support
        ");
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    // ── FAQ ──

    public function getFaqArticles(?string $categorie = null, ?string $recherche = null): array
    {
        $sql = "SELECT * FROM faq_articles WHERE actif = 1";
        $params = [];
        if ($categorie) { $sql .= " AND categorie = ?"; $params[] = $categorie; }
        if ($recherche) { $sql .= " AND (question LIKE ? OR reponse LIKE ?)"; $params[] = "%$recherche%"; $params[] = "%$recherche%"; }
        $sql .= " ORDER BY ordre, vues DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getFaqArticle(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM faq_articles WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function incrementerVues(int $id): void
    {
        $this->pdo->prepare("UPDATE faq_articles SET vues = vues + 1 WHERE id = ?")->execute([$id]);
    }

    public function voterUtile(int $id, bool $utile): void
    {
        $col = $utile ? 'utile_oui' : 'utile_non';
        $this->pdo->prepare("UPDATE faq_articles SET $col = $col + 1 WHERE id = ?")->execute([$id]);
    }

    public function creerFaq(string $question, string $reponse, string $categorie, int $ordre = 0, ?int $auteurId = null): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO faq_articles (question, reponse, categorie, ordre, auteur_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$question, $reponse, $categorie, $ordre, $auteurId]);
        return (int)$this->pdo->lastInsertId();
    }

    public function modifierFaq(int $id, string $question, string $reponse, string $categorie, int $ordre = 0): bool
    {
        $stmt = $this->pdo->prepare("UPDATE faq_articles SET question = ?, reponse = ?, categorie = ?, ordre = ? WHERE id = ?");
        return $stmt->execute([$question, $reponse, $categorie, $ordre, $id]);
    }

    public function supprimerFaq(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM faq_articles WHERE id = ?");
        return $stmt->execute([$id]);
    }

    // ── Helpers statiques ──

    public static function categoriesTicket(): array
    {
        return [
            'technique'     => 'Problème technique',
            'pedagogique'   => 'Question pédagogique',
            'administratif' => 'Question administrative',
            'compte'        => 'Mon compte',
            'autre'         => 'Autre',
        ];
    }

    public static function categoriesFaq(): array
    {
        return [
            'general'        => 'Général',
            'notes'          => 'Notes & Bulletins',
            'absences'       => 'Absences & Retards',
            'messagerie'     => 'Messagerie',
            'emploi_du_temps'=> 'Emploi du temps',
            'devoirs'        => 'Devoirs',
            'bulletins'      => 'Bulletins',
            'compte'         => 'Mon compte',
        ];
    }

    public static function statutBadge(string $statut): string
    {
        $map = [
            'ouvert'   => '<span class="badge badge-info">Ouvert</span>',
            'en_cours' => '<span class="badge badge-warning">En cours</span>',
            'resolu'   => '<span class="badge badge-success">Résolu</span>',
            'ferme'    => '<span class="badge badge-secondary">Fermé</span>',
        ];
        return $map[$statut] ?? $statut;
    }

    public static function prioriteBadge(string $p): string
    {
        $map = [
            'basse'   => '<span class="badge badge-secondary">Basse</span>',
            'normale' => '<span class="badge badge-info">Normale</span>',
            'haute'   => '<span class="badge badge-warning">Haute</span>',
            'urgente' => '<span class="badge badge-danger">Urgente</span>',
        ];
        return $map[$p] ?? $p;
    }

    /* ───── EXPORT ───── */

    public function getTicketsForExport(array $filters = []): array
    {
        $tickets = $this->getTousTickets($filters);
        $cats = self::categoriesTicket();
        return array_map(fn($t) => [
            $t['id'],
            $t['date_creation'],
            $t['nom_utilisateur'] ?? '-',
            $t['user_type'] ?? '-',
            $t['sujet'],
            $cats[$t['categorie']] ?? $t['categorie'],
            ucfirst($t['priorite']),
            ucfirst($t['statut']),
            $t['date_reponse'] ?? '-',
        ], $tickets);
    }

    public function getFaqForExport(?string $categorie = null): array
    {
        $articles = $this->getFaqArticles($categorie);
        $cats = self::categoriesFaq();
        return array_map(fn($a) => [
            $a['id'],
            $cats[$a['categorie']] ?? $a['categorie'],
            $a['question'],
            mb_substr($a['reponse'], 0, 200),
            $a['vues'] ?? 0,
            ($a['utile_oui'] ?? 0) . '/' . ($a['utile_non'] ?? 0),
            $a['ordre'] ?? 0,
        ], $articles);
    }
}
