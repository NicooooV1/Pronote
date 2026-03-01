<?php
/**
 * AnnonceService — Service métier pour le module Annonces / Sondages (M11).
 *
 * Gère les annonces (CRUD, ciblage, accusés de lecture) et les sondages (options, votes).
 */
class AnnonceService
{
    protected $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ─── Annonces ────────────────────────────────────────────────

    /**
     * Crée une annonce. Retourne l'ID.
     */
    public function createAnnonce(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO annonces (titre, contenu, type, auteur_id, auteur_type,
                cible_roles, cible_classes, cible_niveaux, publie, epingle,
                date_publication, date_expiration)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['titre'],
            $data['contenu'],
            $data['type'] ?? 'info',
            $data['auteur_id'],
            $data['auteur_type'],
            is_array($data['cible_roles'] ?? null) ? json_encode($data['cible_roles']) : ($data['cible_roles'] ?? null),
            is_array($data['cible_classes'] ?? null) ? json_encode($data['cible_classes']) : ($data['cible_classes'] ?? null),
            is_array($data['cible_niveaux'] ?? null) ? json_encode($data['cible_niveaux']) : ($data['cible_niveaux'] ?? null),
            $data['publie'] ?? 1,
            $data['epingle'] ?? 0,
            $data['date_publication'] ?? date('Y-m-d H:i:s'),
            $data['date_expiration'] ?? null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Récupère une annonce par ID.
     */
    public function getAnnonce(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM annonces WHERE id = ?");
        $stmt->execute([$id]);
        $a = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($a) {
            $a['cible_roles']   = json_decode($a['cible_roles'] ?? '[]', true) ?: [];
            $a['cible_classes'] = json_decode($a['cible_classes'] ?? '[]', true) ?: [];
            $a['cible_niveaux'] = json_decode($a['cible_niveaux'] ?? '[]', true) ?: [];
        }
        return $a ?: null;
    }

    /**
     * Met à jour une annonce.
     */
    public function updateAnnonce(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE annonces SET titre = ?, contenu = ?, type = ?,
                cible_roles = ?, cible_classes = ?, cible_niveaux = ?,
                publie = ?, epingle = ?, date_expiration = ?
             WHERE id = ?"
        );
        return $stmt->execute([
            $data['titre'],
            $data['contenu'],
            $data['type'] ?? 'info',
            is_array($data['cible_roles'] ?? null) ? json_encode($data['cible_roles']) : ($data['cible_roles'] ?? null),
            is_array($data['cible_classes'] ?? null) ? json_encode($data['cible_classes']) : ($data['cible_classes'] ?? null),
            is_array($data['cible_niveaux'] ?? null) ? json_encode($data['cible_niveaux']) : ($data['cible_niveaux'] ?? null),
            $data['publie'] ?? 1,
            $data['epingle'] ?? 0,
            $data['date_expiration'] ?? null,
            $id,
        ]);
    }

    /**
     * Supprime une annonce.
     */
    public function deleteAnnonce(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM annonces WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Liste les annonces visibles pour un utilisateur donné (rôle + classe).
     * Filtre : publiées, non expirées, ciblées.
     */
    public function getAnnoncesVisibles(string $role, ?string $classeNom = null, ?int $classeId = null): array
    {
        $sql = "SELECT a.*,
                       (SELECT COUNT(*) FROM annonces_lues al WHERE al.annonce_id = a.id) AS nb_lues
                FROM annonces a
                WHERE a.publie = 1
                  AND (a.date_expiration IS NULL OR a.date_expiration > NOW())
                  AND a.date_publication <= NOW()
                ORDER BY a.epingle DESC, a.date_publication DESC";
        $stmt = $this->pdo->query($sql);
        $all = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Filtrer côté PHP pour gérer le ciblage JSON
        return array_filter($all, function($a) use ($role, $classeNom, $classeId) {
            $roles = json_decode($a['cible_roles'] ?? '[]', true) ?: [];
            $classes = json_decode($a['cible_classes'] ?? '[]', true) ?: [];
            $niveaux = json_decode($a['cible_niveaux'] ?? '[]', true) ?: [];

            // Si aucun ciblage : visible par tous
            if (empty($roles) && empty($classes) && empty($niveaux)) return true;

            // Ciblage par rôle
            if (!empty($roles) && !in_array($role, $roles)) return false;

            // Ciblage par classe
            if (!empty($classes) && $classeId && !in_array($classeId, $classes)) return false;

            return true;
        });
    }

    /**
     * Liste toutes les annonces (pour admin).
     */
    public function getAllAnnonces(array $filters = []): array
    {
        $sql = "SELECT a.*,
                       (SELECT COUNT(*) FROM annonces_lues al WHERE al.annonce_id = a.id) AS nb_lues
                FROM annonces a WHERE 1=1";
        $params = [];

        if (!empty($filters['type'])) {
            $sql .= " AND a.type = ?";
            $params[] = $filters['type'];
        }
        if (isset($filters['publie'])) {
            $sql .= " AND a.publie = ?";
            $params[] = (int)$filters['publie'];
        }

        $sql .= " ORDER BY a.epingle DESC, a.date_publication DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $annonces = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($annonces as &$a) {
            $a['cible_roles']   = json_decode($a['cible_roles'] ?? '[]', true) ?: [];
            $a['cible_classes'] = json_decode($a['cible_classes'] ?? '[]', true) ?: [];
        }
        return $annonces;
    }

    // ─── Accusés de lecture ──────────────────────────────────────

    /**
     * Marque une annonce comme lue.
     */
    public function marquerLue(int $annonceId, int $userId, string $userType): bool
    {
        $stmt = $this->pdo->prepare(
            "INSERT IGNORE INTO annonces_lues (annonce_id, user_id, user_type) VALUES (?, ?, ?)"
        );
        return $stmt->execute([$annonceId, $userId, $userType]);
    }

    /**
     * Vérifie si l'utilisateur a lu l'annonce.
     */
    public function estLue(int $annonceId, int $userId, string $userType): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM annonces_lues WHERE annonce_id = ? AND user_id = ? AND user_type = ?"
        );
        $stmt->execute([$annonceId, $userId, $userType]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Nombre de non-lues pour un utilisateur.
     */
    public function getNbNonLues(int $userId, string $userType, string $role): int
    {
        $visibles = $this->getAnnoncesVisibles($role);
        $count = 0;
        foreach ($visibles as $a) {
            if (!$this->estLue($a['id'], $userId, $userType)) {
                $count++;
            }
        }
        return $count;
    }

    // ─── Sondages ────────────────────────────────────────────────

    /**
     * Crée un sondage lié à une annonce.
     */
    public function createSondage(int $annonceId, array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO sondages (annonce_id, question, type_reponse, anonyme, date_fin)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $annonceId,
            $data['question'],
            $data['type_reponse'] ?? 'choix_unique',
            $data['anonyme'] ?? 0,
            $data['date_fin'] ?? null,
        ]);
        $sondageId = (int)$this->pdo->lastInsertId();

        // Créer les options
        if (!empty($data['options']) && is_array($data['options'])) {
            $stmtOpt = $this->pdo->prepare(
                "INSERT INTO sondage_options (sondage_id, label, ordre) VALUES (?, ?, ?)"
            );
            foreach ($data['options'] as $i => $label) {
                if (trim($label)) {
                    $stmtOpt->execute([$sondageId, trim($label), $i]);
                }
            }
        }

        return $sondageId;
    }

    /**
     * Récupère le sondage associé à une annonce.
     */
    public function getSondage(int $annonceId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM sondages WHERE annonce_id = ? AND actif = 1 LIMIT 1");
        $stmt->execute([$annonceId]);
        $sondage = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$sondage) return null;

        // Charger les options
        $stmtOpt = $this->pdo->prepare(
            "SELECT so.*, (SELECT COUNT(*) FROM sondage_votes sv WHERE sv.option_id = so.id) AS nb_votes
             FROM sondage_options so WHERE so.sondage_id = ? ORDER BY so.ordre"
        );
        $stmtOpt->execute([$sondage['id']]);
        $sondage['options'] = $stmtOpt->fetchAll(PDO::FETCH_ASSOC);

        // Total votes
        $stmtTotal = $this->pdo->prepare(
            "SELECT COUNT(DISTINCT user_id, user_type) FROM sondage_votes WHERE sondage_id = ?"
        );
        $stmtTotal->execute([$sondage['id']]);
        $sondage['total_votants'] = (int)$stmtTotal->fetchColumn();

        return $sondage;
    }

    /**
     * Voter dans un sondage.
     */
    public function voter(int $sondageId, int $userId, string $userType, ?int $optionId = null, ?string $textLibre = null): bool
    {
        // Vérifier si déjà voté (pour choix_unique)
        $sondage = $this->pdo->prepare("SELECT type_reponse FROM sondages WHERE id = ?");
        $sondage->execute([$sondageId]);
        $typeReponse = $sondage->fetchColumn();

        if ($typeReponse === 'choix_unique') {
            // Supprimer ancien vote
            $del = $this->pdo->prepare(
                "DELETE FROM sondage_votes WHERE sondage_id = ? AND user_id = ? AND user_type = ?"
            );
            $del->execute([$sondageId, $userId, $userType]);
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO sondage_votes (sondage_id, option_id, user_id, user_type, texte_libre) VALUES (?, ?, ?, ?, ?)"
        );
        return $stmt->execute([$sondageId, $optionId, $userId, $userType, $textLibre]);
    }

    /**
     * A l'utilisateur voté ?
     */
    public function aVote(int $sondageId, int $userId, string $userType): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM sondage_votes WHERE sondage_id = ? AND user_id = ? AND user_type = ?"
        );
        $stmt->execute([$sondageId, $userId, $userType]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Récupère le vote de l'utilisateur.
     */
    public function getVoteUtilisateur(int $sondageId, int $userId, string $userType): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM sondage_votes WHERE sondage_id = ? AND user_id = ? AND user_type = ?"
        );
        $stmt->execute([$sondageId, $userId, $userType]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── Helpers ─────────────────────────────────────────────────

    public function getClasses(): array
    {
        return $this->pdo->query("SELECT * FROM classes WHERE actif = 1 ORDER BY niveau, nom")->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getTypes(): array
    {
        return [
            'info'      => 'Information',
            'urgent'    => 'Urgent',
            'evenement' => 'Événement',
            'sondage'   => 'Sondage',
        ];
    }

    public static function getTypeBadgeClass(string $type): string
    {
        return [
            'info'      => 'badge-info',
            'urgent'    => 'badge-urgent',
            'evenement' => 'badge-event',
            'sondage'   => 'badge-poll',
        ][$type] ?? 'badge-info';
    }
}
