<?php
/**
 * M29 – Bibliothèque/CDI — Service
 */
class BibliothequeService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /* ───────── LIVRES ───────── */

    public function getLivres(array $filters = []): array
    {
        $sql = 'SELECT l.*, (l.exemplaires_total - COALESCE((SELECT COUNT(*) FROM emprunts WHERE livre_id = l.id AND statut = "emprunte"), 0)) AS exemplaires_disponibles FROM livres l WHERE 1=1';
        $params = [];
        if (!empty($filters['recherche'])) {
            $sql .= ' AND (l.titre LIKE ? OR l.auteur LIKE ? OR l.isbn LIKE ?)';
            $r = '%' . $filters['recherche'] . '%';
            $params = array_merge($params, [$r, $r, $r]);
        }
        if (!empty($filters['categorie'])) {
            $sql .= ' AND l.categorie = ?';
            $params[] = $filters['categorie'];
        }
        $sql .= ' ORDER BY l.titre';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLivre(int $id): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT l.*, (l.exemplaires_total - COALESCE((SELECT COUNT(*) FROM emprunts WHERE livre_id = l.id AND statut = "emprunte"), 0)) AS exemplaires_disponibles
            FROM livres l WHERE l.id = ?
        ');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function ajouterLivre(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO livres (titre, auteur, isbn, editeur, annee_publication, categorie, description, exemplaires_total, emplacement, date_ajout)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $data['titre'], $data['auteur'] ?? null, $data['isbn'] ?? null,
            $data['editeur'] ?? null, $data['annee_publication'] ?? null,
            $data['categorie'] ?? 'general', $data['description'] ?? null,
            $data['exemplaires_total'] ?? 1, $data['emplacement'] ?? null,
        ]);
        return $this->pdo->lastInsertId();
    }

    public function modifierLivre(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE livres SET titre=?, auteur=?, isbn=?, editeur=?,
            annee_publication=?, categorie=?, description=?, exemplaires_total=?, emplacement=?
            WHERE id=?
        ");
        $stmt->execute([
            $data['titre'], $data['auteur'], $data['isbn'],
            $data['editeur'], $data['annee_publication'],
            $data['categorie'], $data['description'],
            $data['exemplaires_total'], $data['emplacement'], $id
        ]);
    }

    public function supprimerLivre(int $id): void
    {
        $this->pdo->prepare('DELETE FROM livres WHERE id = ?')->execute([$id]);
    }

    /* ───────── EMPRUNTS ───────── */

    public function emprunter(int $livreId, int $emprunteurId, string $emprunteurType): int
    {
        // Vérifier disponibilité
        $livre = $this->getLivre($livreId);
        if (!$livre || $livre['exemplaires_disponibles'] <= 0) {
            throw new RuntimeException('Aucun exemplaire disponible.');
        }

        $dateRetourPrevue = date('Y-m-d', strtotime('+21 days'));
        $stmt = $this->pdo->prepare("
            INSERT INTO emprunts (livre_id, emprunteur_id, emprunteur_type, date_emprunt, date_retour_prevue, statut)
            VALUES (?, ?, ?, NOW(), ?, 'emprunte')
        ");
        $stmt->execute([$livreId, $emprunteurId, $emprunteurType, $dateRetourPrevue]);
        return $this->pdo->lastInsertId();
    }

    public function retourner(int $empruntId): void
    {
        $stmt = $this->pdo->prepare("UPDATE emprunts SET statut = 'retourne', date_retour_effectif = NOW() WHERE id = ?");
        $stmt->execute([$empruntId]);
    }

    public function prolonger(int $empruntId, int $jours = 14): void
    {
        $stmt = $this->pdo->prepare("UPDATE emprunts SET date_retour_prevue = DATE_ADD(date_retour_prevue, INTERVAL ? DAY) WHERE id = ? AND statut = 'emprunte'");
        $stmt->execute([$jours, $empruntId]);
    }

    public function getEmpruntsActifs(int $userId = null, string $userType = null): array
    {
        $sql = "
            SELECT e.*, l.titre, l.auteur
            FROM emprunts e JOIN livres l ON e.livre_id = l.id
            WHERE e.statut = 'emprunte'
        ";
        $params = [];
        if ($userId) {
            $sql .= ' AND e.emprunteur_id = ? AND e.emprunteur_type = ?';
            $params = [$userId, $userType];
        }
        $sql .= ' ORDER BY e.date_retour_prevue';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getHistorique(int $userId, string $userType): array
    {
        $stmt = $this->pdo->prepare("
            SELECT e.*, l.titre, l.auteur
            FROM emprunts e JOIN livres l ON e.livre_id = l.id
            WHERE e.emprunteur_id = ? AND e.emprunteur_type = ?
            ORDER BY e.date_emprunt DESC
        ");
        $stmt->execute([$userId, $userType]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTousEmprunts(array $filters = []): array
    {
        $sql = "SELECT e.*, l.titre, l.auteur FROM emprunts e JOIN livres l ON e.livre_id = l.id WHERE 1=1";
        $params = [];
        if (!empty($filters['statut'])) { $sql .= ' AND e.statut = ?'; $params[] = $filters['statut']; }
        if (!empty($filters['retard'])) { $sql .= " AND e.statut = 'emprunte' AND e.date_retour_prevue < CURDATE()"; }
        $sql .= ' ORDER BY e.date_emprunt DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ───────── HELPERS ───────── */

    public function getStats(): array
    {
        $livres = $this->pdo->query("SELECT COUNT(*) AS total, SUM(exemplaires_total) AS exemplaires FROM livres")->fetch(PDO::FETCH_ASSOC);
        $emprunts = $this->pdo->query("
            SELECT COUNT(*) AS actifs,
                   COUNT(CASE WHEN date_retour_prevue < CURDATE() THEN 1 END) AS retards
            FROM emprunts WHERE statut = 'emprunte'
        ")->fetch(PDO::FETCH_ASSOC);
        return array_merge($livres, $emprunts);
    }

    /* ───────── RESERVATIONS ───────── */

    /**
     * Reserve a book that is currently borrowed.
     */
    public function reserverLivre(int $livreId, int $userId, string $userType): int
    {
        // Check the book exists and is fully borrowed
        $livre = $this->getLivre($livreId);
        if (!$livre) throw new \RuntimeException('Livre introuvable.');
        if ($livre['exemplaires_disponibles'] > 0) throw new \RuntimeException('Des exemplaires sont disponibles, pas besoin de réserver.');

        // Check not already reserved by this user
        $check = $this->pdo->prepare("SELECT id FROM livre_reservations WHERE livre_id = ? AND user_id = ? AND user_type = ? AND statut = 'en_attente'");
        $check->execute([$livreId, $userId, $userType]);
        if ($check->fetch()) throw new \RuntimeException('Vous avez déjà une réservation en attente.');

        // Get position in queue
        $posStmt = $this->pdo->prepare("SELECT MAX(position_queue) FROM livre_reservations WHERE livre_id = ? AND statut = 'en_attente'");
        $posStmt->execute([$livreId]);
        $pos = ((int)$posStmt->fetchColumn()) + 1;

        $stmt = $this->pdo->prepare("
            INSERT INTO livre_reservations (livre_id, user_id, user_type, position_queue, statut, created_at)
            VALUES (?, ?, ?, ?, 'en_attente', NOW())
        ");
        $stmt->execute([$livreId, $userId, $userType, $pos]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Get reservations for a book.
     */
    public function getReservationsLivre(int $livreId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT lr.*,
                COALESCE(
                    (SELECT CONCAT(el.prenom, ' ', el.nom) FROM eleves el WHERE el.id = lr.user_id AND lr.user_type = 'eleve'),
                    (SELECT CONCAT(p.prenom, ' ', p.nom) FROM professeurs p WHERE p.id = lr.user_id AND lr.user_type = 'professeur')
                ) AS user_nom
            FROM livre_reservations lr
            WHERE lr.livre_id = ? AND lr.statut = 'en_attente'
            ORDER BY lr.position_queue
        ");
        $stmt->execute([$livreId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Notify next in queue when a book is returned.
     */
    public function notifierProchainReservation(int $livreId): void
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM livre_reservations
            WHERE livre_id = ? AND statut = 'en_attente'
            ORDER BY position_queue LIMIT 1
        ");
        $stmt->execute([$livreId]);
        $next = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$next) return;

        $livre = $this->getLivre($livreId);

        try {
            require_once __DIR__ . '/../../notifications/includes/NotificationService.php';
            $notif = new \NotificationService($this->pdo);
            $notif->creer(
                $next['user_id'], $next['user_type'], 'bibliotheque',
                'Livre disponible',
                "Le livre « {$livre['titre']} » est maintenant disponible. Venez le chercher au CDI.",
                '/bibliotheque/detail.php?id=' . $livreId, 'haute'
            );
        } catch (\Exception $e) {}

        $this->pdo->prepare("UPDATE livre_reservations SET statut = 'notifie', notified_at = NOW() WHERE id = ?")
                   ->execute([$next['id']]);
    }

    /* ───────── RETARDS & RELANCES ───────── */

    /**
     * Liste des emprunts en retard
     */
    public function getEmpruntsEnRetard(): array
    {
        $stmt = $this->pdo->query("
            SELECT e.*, l.titre, l.isbn,
                   DATEDIFF(CURDATE(), e.date_retour_prevue) AS jours_retard,
                   COALESCE(
                       (SELECT CONCAT(el.prenom, ' ', el.nom) FROM eleves el WHERE el.id = e.emprunteur_id AND e.emprunteur_type = 'eleve'),
                       (SELECT CONCAT(p.prenom, ' ', p.nom) FROM professeurs p WHERE p.id = e.emprunteur_id AND e.emprunteur_type = 'professeur')
                   ) AS emprunteur_nom
            FROM emprunts e
            JOIN livres l ON e.livre_id = l.id
            WHERE e.statut = 'emprunte' AND e.date_retour_prevue < CURDATE()
            ORDER BY e.date_retour_prevue ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Envoie des rappels pour les emprunts en retard
     */
    public function envoyerRappelsRetard(): int
    {
        $retards = $this->getEmpruntsEnRetard();
        $count = 0;
        foreach ($retards as $r) {
            if (!empty($r['rappel_envoye'])) continue;
            try {
                if (class_exists('NotificationService')) {
                    $notifService = new NotificationService($this->pdo);
                    $notifService->creerNotification(
                        $r['emprunteur_id'], $r['emprunteur_type'],
                        "Emprunt en retard : {$r['titre']}",
                        "Votre emprunt de « {$r['titre']} » devait être retourné le " . date('d/m/Y', strtotime($r['date_retour_prevue'])) . ". Merci de le rapporter.",
                        'haute', '/bibliotheque/emprunts.php'
                    );
                }
                $stmt = $this->pdo->prepare("UPDATE emprunts SET rappel_envoye = 1 WHERE id = ?");
                $stmt->execute([$r['id']]);
                $count++;
            } catch (\Exception $e) {}
        }
        return $count;
    }

    /* ───────── EXPORT ───────── */

    public function getLivresForExport(array $filters = []): array
    {
        $livres = $this->getLivres($filters);
        $rows = [];
        $cats = self::categories();
        foreach ($livres as $l) {
            $rows[] = [
                $l['isbn'] ?? '',
                $l['titre'],
                $l['auteur'] ?? '',
                $cats[$l['categorie'] ?? ''] ?? $l['categorie'] ?? '',
                $l['editeur'] ?? '',
                $l['annee_publication'] ?? '',
                $l['exemplaires_total'] ?? 0,
                $l['exemplaires_disponibles'] ?? 0,
            ];
        }
        return $rows;
    }

    public function getEmpruntsForExport(array $filters = []): array
    {
        $emprunts = $this->getTousEmprunts($filters);
        $rows = [];
        foreach ($emprunts as $e) {
            $rows[] = [
                $e['livre_titre'] ?? $e['titre'] ?? '',
                $e['emprunteur_nom'] ?? '',
                $e['emprunteur_type'] ?? '',
                $e['date_emprunt'] ?? '',
                $e['date_retour_prevue'] ?? '',
                $e['date_retour_effective'] ?? '',
                $e['statut'] ?? '',
            ];
        }
        return $rows;
    }

    public static function categories(): array
    {
        return [
            'roman' => 'Roman', 'poesie' => 'Poésie', 'theatre' => 'Théâtre',
            'science' => 'Sciences', 'histoire' => 'Histoire', 'geographie' => 'Géographie',
            'philosophie' => 'Philosophie', 'art' => 'Art', 'bd' => 'BD / Manga',
            'dictionnaire' => 'Dictionnaire', 'encyclopedie' => 'Encyclopédie',
            'revue' => 'Revue / Magazine', 'general' => 'Général',
        ];
    }

    // ─── SCAN ISBN ───

    public function lookupIsbn(string $isbn): ?array
    {
        $isbn = preg_replace('/[^0-9X]/', '', strtoupper($isbn));
        $stmt = $this->pdo->prepare("SELECT * FROM livres WHERE isbn = ?");
        $stmt->execute([$isbn]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // ─── LISTES DE LECTURE ───

    public function creerListeLecture(string $titre, int $professeurId, ?string $classe = null, array $livreIds = []): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO bibliotheque_listes_lecture (titre, professeur_id, classe, livres_ids, created_at) VALUES (:t, :p, :c, :l, NOW())");
        $stmt->execute([':t' => $titre, ':p' => $professeurId, ':c' => $classe, ':l' => json_encode($livreIds)]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getListesLecture(?int $professeurId = null, ?string $classe = null): array
    {
        $sql = "SELECT ll.*, CONCAT(p.prenom, ' ', p.nom) AS professeur_nom FROM bibliotheque_listes_lecture ll LEFT JOIN professeurs p ON ll.professeur_id = p.id WHERE 1=1";
        $params = [];
        if ($professeurId) { $sql .= " AND ll.professeur_id = :p"; $params[':p'] = $professeurId; }
        if ($classe) { $sql .= " AND ll.classe = :c"; $params[':c'] = $classe; }
        $sql .= " ORDER BY ll.created_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLivresListe(int $listeId): array
    {
        $stmt = $this->pdo->prepare("SELECT livres_ids FROM bibliotheque_listes_lecture WHERE id = ?");
        $stmt->execute([$listeId]);
        $ids = json_decode($stmt->fetchColumn() ?: '[]', true);
        if (empty($ids)) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt2 = $this->pdo->prepare("SELECT * FROM livres WHERE id IN ($placeholders) ORDER BY titre");
        $stmt2->execute($ids);
        return $stmt2->fetchAll(PDO::FETCH_ASSOC);
    }

    public function ajouterLivreAListe(int $listeId, int $livreId): void
    {
        $stmt = $this->pdo->prepare("SELECT livres_ids FROM bibliotheque_listes_lecture WHERE id = ?");
        $stmt->execute([$listeId]);
        $ids = json_decode($stmt->fetchColumn() ?: '[]', true);
        if (!in_array($livreId, $ids)) {
            $ids[] = $livreId;
            $this->pdo->prepare("UPDATE bibliotheque_listes_lecture SET livres_ids = ? WHERE id = ?")->execute([json_encode($ids), $listeId]);
        }
    }

    // ─── HISTORIQUE LECTEUR ───

    public function getHistoriqueEleve(int $eleveId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT e.*, l.titre, l.auteur, l.isbn, l.categorie
            FROM emprunts e
            JOIN livres l ON e.livre_id = l.id
            WHERE e.emprunteur_id = :eid AND e.emprunteur_type = 'eleve'
            ORDER BY e.date_emprunt DESC
        ");
        $stmt->execute([':eid' => $eleveId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getStatsLecteur(int $eleveId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) AS total_emprunts,
                   COUNT(CASE WHEN statut = 'retourne' THEN 1 END) AS retournes,
                   COUNT(CASE WHEN statut = 'emprunte' THEN 1 END) AS en_cours,
                   COUNT(CASE WHEN statut = 'emprunte' AND date_retour_prevue < CURDATE() THEN 1 END) AS en_retard
            FROM emprunts WHERE emprunteur_id = ? AND emprunteur_type = 'eleve'
        ");
        $stmt->execute([$eleveId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
}
