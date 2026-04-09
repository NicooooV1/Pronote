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
     * Si publiée immédiatement, déclenche les notifications.
     */
    public function createAnnonce(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO annonces (titre, contenu, type, auteur_id, auteur_type,
                cible_roles, cible_classes, cible_niveaux, cible_matieres, publie, epingle,
                date_publication, date_expiration)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
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
            is_array($data['cible_matieres'] ?? null) ? json_encode($data['cible_matieres']) : ($data['cible_matieres'] ?? null),
            $data['publie'] ?? 1,
            $data['epingle'] ?? 0,
            $data['date_publication'] ?? date('Y-m-d H:i:s'),
            $data['date_expiration'] ?? null,
        ]);
        $annonceId = (int)$this->pdo->lastInsertId();

        // Envoyer les notifications si publication immédiate
        $publie = $data['publie'] ?? 1;
        $datePub = $data['date_publication'] ?? date('Y-m-d H:i:s');
        if ($publie && strtotime($datePub) <= time()) {
            $this->notifyRecipients($annonceId, $data['titre'], $data['type'] ?? 'info');
        }

        return $annonceId;
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
            $a['cible_roles']    = json_decode($a['cible_roles'] ?? '[]', true) ?: [];
            $a['cible_classes']  = json_decode($a['cible_classes'] ?? '[]', true) ?: [];
            $a['cible_niveaux']  = json_decode($a['cible_niveaux'] ?? '[]', true) ?: [];
            $a['cible_matieres'] = json_decode($a['cible_matieres'] ?? '[]', true) ?: [];
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
                cible_roles = ?, cible_classes = ?, cible_niveaux = ?, cible_matieres = ?,
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
            is_array($data['cible_matieres'] ?? null) ? json_encode($data['cible_matieres']) : ($data['cible_matieres'] ?? null),
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

    // ─── Pièces jointes ─────────────────────────────────────────

    /**
     * Ajoute une pièce jointe à une annonce.
     */
    public function addAttachment(int $annonceId, array $fileData): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO annonce_attachments (annonce_id, nom_fichier, nom_original, taille, mime_type, uploaded_by, uploaded_by_type)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $annonceId,
            $fileData['nom_fichier'],
            $fileData['nom_original'],
            $fileData['taille'] ?? 0,
            $fileData['mime_type'] ?? 'application/octet-stream',
            $fileData['uploaded_by'] ?? null,
            $fileData['uploaded_by_type'] ?? null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Récupère les pièces jointes d'une annonce.
     */
    public function getAttachments(int $annonceId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM annonce_attachments WHERE annonce_id = ? ORDER BY created_at");
        $stmt->execute([$annonceId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Supprime une pièce jointe.
     */
    public function deleteAttachment(int $attachmentId): bool
    {
        // Get file path before deleting
        $stmt = $this->pdo->prepare("SELECT nom_fichier FROM annonce_attachments WHERE id = ?");
        $stmt->execute([$attachmentId]);
        $filename = $stmt->fetchColumn();

        $deleted = $this->pdo->prepare("DELETE FROM annonce_attachments WHERE id = ?");
        $deleted->execute([$attachmentId]);

        if ($deleted->rowCount() > 0 && $filename) {
            $path = __DIR__ . '/../../uploads/annonces/' . $filename;
            if (file_exists($path)) {
                @unlink($path);
            }
        }
        return $deleted->rowCount() > 0;
    }

    /**
     * Handle file uploads for an announcement.
     * Returns array of successfully uploaded file info.
     */
    public function handleFileUploads(int $annonceId, array $files, int $uploadedBy, string $uploadedByType, int $maxFiles = 5): array
    {
        $uploadDir = __DIR__ . '/../../uploads/annonces/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $allowedMimes = [
            'application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain', 'text/csv',
        ];
        $maxSize = 10 * 1024 * 1024; // 10 MB

        $results = [];
        $count = 0;

        // Normalize $_FILES array structure
        $fileList = [];
        if (isset($files['name']) && is_array($files['name'])) {
            for ($i = 0; $i < count($files['name']); $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $fileList[] = [
                        'name'     => $files['name'][$i],
                        'type'     => $files['type'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'size'     => $files['size'][$i],
                    ];
                }
            }
        }

        foreach ($fileList as $file) {
            if ($count >= $maxFiles) break;

            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file['tmp_name']);

            if (!in_array($mimeType, $allowedMimes, true)) continue;
            if ($file['size'] > $maxSize) continue;

            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $safeName = bin2hex(random_bytes(16)) . ($ext ? '.' . strtolower($ext) : '');
            $destPath = $uploadDir . $safeName;

            if (move_uploaded_file($file['tmp_name'], $destPath)) {
                $attachId = $this->addAttachment($annonceId, [
                    'nom_fichier'     => $safeName,
                    'nom_original'    => $file['name'],
                    'taille'          => $file['size'],
                    'mime_type'       => $mimeType,
                    'uploaded_by'     => $uploadedBy,
                    'uploaded_by_type' => $uploadedByType,
                ]);
                $results[] = ['id' => $attachId, 'nom_original' => $file['name'], 'taille' => $file['size']];
                $count++;
            }
        }

        return $results;
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

    // ─── Notifications & Publication programmée ──────────────────

    /**
     * Publie les annonces programmées dont la date de publication est passée.
     * À appeler depuis un cron ou dans un hook de page.
     * @return int nombre d'annonces publiées
     */
    public function publishScheduled(): int
    {
        $stmt = $this->pdo->prepare("
            SELECT id, titre, type FROM annonces
            WHERE publie = 0 AND date_publication <= NOW()
              AND (date_expiration IS NULL OR date_expiration > NOW())
              AND notified = 0
        ");
        $stmt->execute();
        $pending = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $count = 0;
        foreach ($pending as $a) {
            $this->pdo->prepare("UPDATE annonces SET publie = 1, notified = 1 WHERE id = ?")
                      ->execute([$a['id']]);
            $this->notifyRecipients($a['id'], $a['titre'], $a['type']);
            $count++;
        }
        return $count;
    }

    /**
     * Envoie les notifications aux destinataires ciblés de l'annonce.
     */
    protected function notifyRecipients(int $annonceId, string $titre, string $type): void
    {
        if (!function_exists('app')) return;

        try {
            $notifService = app()->make('API\Services\NotificationService');
        } catch (\Throwable $e) {
            error_log("AnnonceService::notifyRecipients — NotificationService unavailable: " . $e->getMessage());
            return;
        }

        $annonce = $this->getAnnonce($annonceId);
        if (!$annonce) return;

        $cibleRoles   = $annonce['cible_roles'] ?? [];
        $cibleClasses = $annonce['cible_classes'] ?? [];

        // Déterminer les types de tables à notifier
        $roleTableMap = [
            'eleve'          => 'eleves',
            'parent'         => 'parents',
            'professeur'     => 'professeurs',
            'vie_scolaire'   => 'vie_scolaire',
            'administrateur' => 'administrateurs',
        ];

        $rolesToNotify = !empty($cibleRoles) ? $cibleRoles : array_keys($roleTableMap);

        $priorite = ($type === 'urgent') ? 'haute' : 'normale';
        $message  = "Nouvelle annonce : " . $titre;

        foreach ($rolesToNotify as $role) {
            if (!isset($roleTableMap[$role])) continue;
            $table = $roleTableMap[$role];

            try {
                $query = "SELECT id FROM `{$table}` WHERE actif = 1";
                $params = [];

                // Filtrer par classe si ciblage
                if (!empty($cibleClasses) && in_array($role, ['eleve', 'parent'])) {
                    // Pour les élèves : filtrer par classe_id
                    if ($role === 'eleve') {
                        $placeholders = implode(',', array_fill(0, count($cibleClasses), '?'));
                        $query = "SELECT id FROM eleves WHERE actif = 1 AND classe IN (
                            SELECT nom FROM classes WHERE id IN ({$placeholders})
                        )";
                        $params = $cibleClasses;
                    }
                }

                $stmt = $this->pdo->prepare($query);
                $stmt->execute($params);
                $userIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);

                foreach ($userIds as $uid) {
                    try {
                        $notifService->create([
                            'user_id'   => $uid,
                            'user_type' => $role,
                            'type'      => 'annonce',
                            'titre'     => 'Nouvelle annonce',
                            'message'   => $message,
                            'lien'      => "annonces/detail_annonce.php?id={$annonceId}",
                            'priorite'  => $priorite,
                        ]);
                    } catch (\Throwable $e) {
                        // Skip individual notification failures
                        continue;
                    }
                }
            } catch (\Throwable $e) {
                error_log("AnnonceService::notifyRecipients — Error for role {$role}: " . $e->getMessage());
                continue;
            }
        }

        // Marquer comme notifié
        $this->pdo->prepare("UPDATE annonces SET notified = 1 WHERE id = ?")->execute([$annonceId]);
    }

    /**
     * Export des annonces pour ExportService.
     */
    public function getAnnoncesForExport(array $filters = []): array
    {
        $annonces = $this->getAllAnnonces($filters);
        $result = [];
        foreach ($annonces as $a) {
            $result[] = [
                'ID'               => $a['id'],
                'Titre'            => $a['titre'],
                'Type'             => self::getTypes()[$a['type']] ?? $a['type'],
                'Publie'           => $a['publie'] ? 'Oui' : 'Non',
                'Epingle'          => $a['epingle'] ? 'Oui' : 'Non',
                'Date publication' => $a['date_publication'] ?? '',
                'Date expiration'  => $a['date_expiration'] ?? '-',
                'Nb lectures'      => $a['nb_lues'] ?? 0,
                'Rôles ciblés'     => is_array($a['cible_roles']) ? implode(', ', $a['cible_roles']) : '-',
            ];
        }
        return $result;
    }

    // ─── ACQUITTEMENT OBLIGATOIRE ───

    public function acquitter(int $annonceId, int $userId): void
    {
        $this->pdo->prepare("INSERT INTO annonces_acquittements (annonce_id, user_id, acquitte, date_acquittement) VALUES (:aid, :uid, 1, NOW()) ON DUPLICATE KEY UPDATE acquitte = 1, date_acquittement = NOW()")
            ->execute([':aid' => $annonceId, ':uid' => $userId]);
    }

    public function getAcquittements(int $annonceId): array
    {
        $stmt = $this->pdo->prepare("SELECT aa.*, CONCAT(u.prenom,' ',u.nom) AS user_nom FROM annonces_acquittements aa LEFT JOIN utilisateurs u ON aa.user_id = u.id WHERE aa.annonce_id = :aid ORDER BY aa.date_acquittement");
        $stmt->execute([':aid' => $annonceId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ─── ANALYTICS ───

    public function getAnalytics(int $annonceId): array
    {
        $vues = $this->pdo->prepare("SELECT COUNT(*) FROM annonce_lectures WHERE annonce_id = :aid");
        $vues->execute([':aid' => $annonceId]);

        $acquittes = $this->pdo->prepare("SELECT COUNT(*) FROM annonces_acquittements WHERE annonce_id = :aid AND acquitte = 1");
        $acquittes->execute([':aid' => $annonceId]);

        return [
            'nb_vues' => (int)$vues->fetchColumn(),
            'nb_acquittements' => (int)$acquittes->fetchColumn()
        ];
    }
}
