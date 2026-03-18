<?php
/**
 * M23 – RGPD & Audit — Service
 * Enhanced: data export, anonymization, retention policies
 */
class AuditRgpdService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /* ───────── JOURNAL D'AUDIT ───────── */

    public function getAuditLogs(array $filtres = []): array
    {
        $sql = "SELECT * FROM audit_log WHERE 1=1";
        $params = [];
        if (!empty($filtres['user_id'])) { $sql .= ' AND user_id = ?'; $params[] = $filtres['user_id']; }
        if (!empty($filtres['action'])) { $sql .= ' AND action LIKE ?'; $params[] = '%' . $filtres['action'] . '%'; }
        if (!empty($filtres['date_debut'])) { $sql .= ' AND created_at >= ?'; $params[] = $filtres['date_debut']; }
        if (!empty($filtres['date_fin'])) { $sql .= ' AND created_at <= ?'; $params[] = $filtres['date_fin'] . ' 23:59:59'; }
        $sql .= ' ORDER BY created_at DESC LIMIT 500';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAuditStats(): array
    {
        return $this->getAuditStatsProper();
    }

    public function getAuditStatsProper(): array
    {
        $today = date('Y-m-d');
        $month = date('Y-m');

        $stmtToday = $this->pdo->prepare("SELECT COUNT(*) FROM audit_log WHERE DATE(created_at) = ?");
        $stmtToday->execute([$today]);
        $todayCount = (int)$stmtToday->fetchColumn();

        return [
            'total' => (int)$this->pdo->query("SELECT COUNT(*) FROM audit_log")->fetchColumn(),
            'today' => $todayCount,
            'month' => (int)$this->pdo->query("SELECT COUNT(*) FROM audit_log WHERE created_at LIKE '$month%'")->fetchColumn(),
        ];
    }

    /**
     * Export audit logs CSV
     */
    public function exportAuditCSV(array $filtres = []): array
    {
        $logs = $this->getAuditLogs($filtres);
        $rows = [];
        foreach ($logs as $l) {
            $rows[] = [
                'date' => $l['created_at'] ?? $l['date'] ?? '',
                'user' => ($l['user_type'] ?? '') . ' #' . ($l['user_id'] ?? ''),
                'action' => $l['action'] ?? '',
                'details' => $l['details'] ?? $l['description'] ?? '',
                'ip' => $l['ip_address'] ?? $l['ip'] ?? '',
            ];
        }
        return $rows;
    }

    /* ───────── CONSENTEMENTS ───────── */

    public function getConsentements(int $userId, string $userType): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM rgpd_consentements WHERE user_id = ? AND user_type = ? ORDER BY type_consentement");
        $stmt->execute([$userId, $userType]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $types = self::typesConsentement();
        $result = [];
        $existing = array_column($rows, null, 'type_consentement');
        foreach ($types as $key => $label) {
            $result[$key] = $existing[$key] ?? [
                'type_consentement' => $key,
                'consenti' => 0,
                'date_consentement' => null,
            ];
        }
        return $result;
    }

    public function sauvegarderConsentement(int $userId, string $userType, string $type, bool $consenti, ?string $ip = null): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO rgpd_consentements (user_id, user_type, type_consentement, consenti, date_consentement, ip_address)
            VALUES (?, ?, ?, ?, NOW(), ?)
            ON DUPLICATE KEY UPDATE consenti = VALUES(consenti),
                date_consentement = IF(VALUES(consenti) = 1, NOW(), date_consentement),
                date_retrait = IF(VALUES(consenti) = 0, NOW(), NULL),
                ip_address = VALUES(ip_address)
        ");
        $stmt->execute([$userId, $userType, $type, $consenti ? 1 : 0, $ip]);
    }

    public function sauvegarderConsentements(int $userId, string $userType, array $consentements, ?string $ip = null): void
    {
        $types = self::typesConsentement();
        foreach ($types as $key => $label) {
            $consenti = isset($consentements[$key]);
            $this->sauvegarderConsentement($userId, $userType, $key, $consenti, $ip);
        }
    }

    /* ───────── DEMANDES RGPD ───────── */

    public function creerDemande(int $userId, string $userType, string $type, ?string $description): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO rgpd_demandes (user_id, user_type, type_demande, description)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $userType, $type, $description]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getDemandes(array $filtres = []): array
    {
        $sql = "SELECT d.*, 
                COALESCE(
                    (SELECT CONCAT(prenom, ' ', nom) FROM administrateurs WHERE id = d.user_id AND d.user_type = 'administrateur'),
                    (SELECT CONCAT(prenom, ' ', nom) FROM professeurs WHERE id = d.user_id AND d.user_type = 'professeur'),
                    (SELECT CONCAT(prenom, ' ', nom) FROM eleves WHERE id = d.user_id AND d.user_type = 'eleve'),
                    (SELECT CONCAT(prenom, ' ', nom) FROM parents WHERE id = d.user_id AND d.user_type = 'parent'),
                    (SELECT CONCAT(prenom, ' ', nom) FROM vie_scolaire WHERE id = d.user_id AND d.user_type = 'vie_scolaire')
                ) AS demandeur_nom
                FROM rgpd_demandes d WHERE 1=1";
        $params = [];
        if (!empty($filtres['statut'])) { $sql .= ' AND d.statut = ?'; $params[] = $filtres['statut']; }
        if (!empty($filtres['type_demande'])) { $sql .= ' AND d.type_demande = ?'; $params[] = $filtres['type_demande']; }
        $sql .= ' ORDER BY d.date_demande DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDemande(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM rgpd_demandes WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function traiterDemande(int $id, string $statut, ?string $reponse, int $traiteParId): void
    {
        $stmt = $this->pdo->prepare("UPDATE rgpd_demandes SET statut = ?, reponse = ?, traite_par = ?, date_traitement = NOW() WHERE id = ?");
        $stmt->execute([$statut, $reponse, $traiteParId, $id]);
    }

    public function getMesDemandesUser(int $userId, string $userType): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM rgpd_demandes WHERE user_id = ? AND user_type = ? ORDER BY date_demande DESC");
        $stmt->execute([$userId, $userType]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ───────── EXPORT DONNÉES UTILISATEUR (Droit d'accès) ───────── */

    /**
     * Collecte TOUTES les données d'un utilisateur pour export (Art. 15 RGPD)
     */
    public function exporterDonneesUtilisateur(int $userId, string $userType): array
    {
        $pdo = $this->pdo;
        $data = ['meta' => [
            'export_date' => date('Y-m-d H:i:s'),
            'user_id' => $userId,
            'user_type' => $userType,
            'rgpd_article' => 'Article 15 - Droit d\'accès',
        ]];

        // 1. Profil utilisateur
        $tableMap = [
            'eleve' => 'eleves', 'professeur' => 'professeurs',
            'parent' => 'parents', 'administrateur' => 'administrateurs',
            'vie_scolaire' => 'vie_scolaire',
        ];
        $table = $tableMap[$userType] ?? null;
        if ($table) {
            $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE id = ?");
            $stmt->execute([$userId]);
            $profil = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($profil) {
                // Masquer hash mot de passe
                unset($profil['mot_de_passe'], $profil['password'], $profil['password_hash']);
                $data['profil'] = $profil;
            }
        }

        // 2. Consentements
        $data['consentements'] = $this->getConsentements($userId, $userType);

        // 3. Demandes RGPD
        $data['demandes_rgpd'] = $this->getMesDemandesUser($userId, $userType);

        // 4. Données spécifiques par type
        if ($userType === 'eleve') {
            // Notes
            try {
                $stmt = $pdo->prepare("SELECT n.*, m.nom AS matiere FROM notes n LEFT JOIN matieres m ON n.matiere_id = m.id WHERE n.eleve_id = ? ORDER BY n.date_note DESC");
                $stmt->execute([$userId]);
                $data['notes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (\Exception $e) { $data['notes'] = []; }

            // Absences
            try {
                $stmt = $pdo->prepare("SELECT * FROM absences WHERE eleve_id = ? ORDER BY date_absence DESC");
                $stmt->execute([$userId]);
                $data['absences'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (\Exception $e) { $data['absences'] = []; }

            // Retards
            try {
                $stmt = $pdo->prepare("SELECT * FROM retards WHERE eleve_id = ? ORDER BY date_retard DESC");
                $stmt->execute([$userId]);
                $data['retards'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (\Exception $e) { $data['retards'] = []; }

            // Incidents
            try {
                $stmt = $pdo->prepare("SELECT * FROM incidents WHERE eleve_id = ? ORDER BY date_incident DESC");
                $stmt->execute([$userId]);
                $data['incidents'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (\Exception $e) { $data['incidents'] = []; }

            // Bulletins
            try {
                $stmt = $pdo->prepare("SELECT * FROM bulletins WHERE eleve_id = ? ORDER BY id DESC");
                $stmt->execute([$userId]);
                $data['bulletins'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (\Exception $e) { $data['bulletins'] = []; }

            // Inscriptions
            try {
                $stmt = $pdo->prepare("SELECT * FROM inscriptions WHERE eleve_id = ? ORDER BY id DESC");
                $stmt->execute([$userId]);
                $data['inscriptions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (\Exception $e) { $data['inscriptions'] = []; }
        }

        if ($userType === 'parent') {
            // Factures
            try {
                $stmt = $pdo->prepare("SELECT * FROM factures WHERE parent_id = ? ORDER BY date_creation DESC");
                $stmt->execute([$userId]);
                $data['factures'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (\Exception $e) { $data['factures'] = []; }
        }

        // 5. Messages (tous types)
        try {
            $stmt = $pdo->prepare("
                SELECT m.id, m.body, m.created_at, c.subject 
                FROM messages m 
                JOIN conversations c ON m.conversation_id = c.id 
                WHERE m.sender_id = ? AND m.sender_type = ? AND m.is_deleted = 0
                ORDER BY m.created_at DESC
            ");
            $stmt->execute([$userId, $userType]);
            $data['messages_envoyes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) { $data['messages_envoyes'] = []; }

        // 6. Sessions / connexions
        try {
            $stmt = $pdo->prepare("SELECT * FROM session_security WHERE user_id = ? AND user_type = ? ORDER BY created_at DESC LIMIT 50");
            $stmt->execute([$userId, $userType]);
            $data['sessions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) { $data['sessions'] = []; }

        // 7. Audit logs relatifs
        try {
            $stmt = $pdo->prepare("SELECT action, details, ip_address, created_at FROM audit_log WHERE user_id = ? AND user_type = ? ORDER BY created_at DESC LIMIT 200");
            $stmt->execute([$userId, $userType]);
            $data['audit_trail'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) { $data['audit_trail'] = []; }

        return $data;
    }

    /* ───────── ANONYMISATION (Droit à l'oubli) ───────── */

    /**
     * Anonymise un utilisateur (Art. 17 RGPD)
     * Remplace les données personnelles par des valeurs anonymes
     * Conserve les données statistiques (notes, absences) sans identification
     */
    public function anonymiserUtilisateur(int $userId, string $userType, int $adminId): array
    {
        $pdo = $this->pdo;
        $anonymId = 'ANON_' . strtoupper(bin2hex(random_bytes(4)));
        $actions = [];

        $tableMap = [
            'eleve' => 'eleves', 'professeur' => 'professeurs',
            'parent' => 'parents', 'administrateur' => 'administrateurs',
            'vie_scolaire' => 'vie_scolaire',
        ];
        $table = $tableMap[$userType] ?? null;
        if (!$table) return ['error' => 'Type utilisateur inconnu'];

        try {
            $pdo->beginTransaction();

            // 1. Anonymiser le profil
            $stmt = $pdo->prepare("
                UPDATE {$table} SET 
                    nom = ?, prenom = 'Anonyme', 
                    email = ?, identifiant = ?,
                    telephone = NULL, adresse = NULL,
                    mot_de_passe = '', actif = 0,
                    photo = NULL
                WHERE id = ?
            ");
            $stmt->execute([$anonymId, $anonymId . '@anonymise.rgpd', $anonymId, $userId]);
            $actions[] = "Profil anonymisé dans {$table}";

            // 2. Supprimer consentements
            $stmt = $pdo->prepare("DELETE FROM rgpd_consentements WHERE user_id = ? AND user_type = ?");
            $stmt->execute([$userId, $userType]);
            $actions[] = "Consentements supprimés";

            // 3. Supprimer messages
            try {
                $stmt = $pdo->prepare("UPDATE messages SET body = '[Message supprimé - RGPD]', is_deleted = 1 WHERE sender_id = ? AND sender_type = ?");
                $stmt->execute([$userId, $userType]);
                $actions[] = "Messages anonymisés";
            } catch (\Exception $e) {}

            // 4. Supprimer sessions
            try {
                $stmt = $pdo->prepare("DELETE FROM session_security WHERE user_id = ? AND user_type = ?");
                $stmt->execute([$userId, $userType]);
                $actions[] = "Sessions supprimées";
            } catch (\Exception $e) {}

            // 5. Log l'action
            $this->logAudit($adminId, 'administrateur', 'rgpd_anonymisation', 
                "Anonymisation de {$userType} #{$userId} → {$anonymId}");

            $pdo->commit();
            $actions[] = "Anonymisation complète";
        } catch (\Exception $e) {
            $pdo->rollBack();
            return ['error' => $e->getMessage()];
        }

        return ['success' => true, 'anonym_id' => $anonymId, 'actions' => $actions];
    }

    /* ───────── RÉTENTION DES DONNÉES ───────── */

    /**
     * Politiques de rétention par défaut (en jours)
     */
    public static function retentionDefaults(): array
    {
        return [
            'audit_log'        => ['label' => 'Logs d\'audit', 'duree' => 365, 'obligatoire' => true],
            'session_security' => ['label' => 'Sessions de connexion', 'duree' => 90, 'obligatoire' => false],
            'messages'         => ['label' => 'Messages supprimés', 'duree' => 180, 'obligatoire' => false],
            'notifications'    => ['label' => 'Notifications lues', 'duree' => 90, 'obligatoire' => false],
            'rate_limits'      => ['label' => 'Logs rate limiting', 'duree' => 30, 'obligatoire' => false],
        ];
    }

    /**
     * Récupère les politiques de rétention (DB ou défaut)
     */
    public function getRetentionPolicies(): array
    {
        $defaults = self::retentionDefaults();
        try {
            $rows = $this->pdo->query("SELECT * FROM rgpd_retention_policies")->fetchAll(PDO::FETCH_ASSOC);
            $existing = array_column($rows, null, 'table_name');
            foreach ($defaults as $key => &$def) {
                if (isset($existing[$key])) {
                    $def['duree'] = (int)$existing[$key]['retention_days'];
                    $def['actif'] = (bool)$existing[$key]['actif'];
                    $def['derniere_purge'] = $existing[$key]['derniere_purge'];
                } else {
                    $def['actif'] = true;
                    $def['derniere_purge'] = null;
                }
            }
        } catch (\Exception $e) {
            // Table doesn't exist yet, use defaults
            foreach ($defaults as &$def) {
                $def['actif'] = true;
                $def['derniere_purge'] = null;
            }
        }
        return $defaults;
    }

    /**
     * Sauvegarde une politique de rétention
     */
    public function sauvegarderRetentionPolicy(string $tableName, int $dureeJours, bool $actif): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO rgpd_retention_policies (table_name, retention_days, actif)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE retention_days = VALUES(retention_days), actif = VALUES(actif)
        ");
        $stmt->execute([$tableName, $dureeJours, $actif ? 1 : 0]);
    }

    /**
     * Exécute la purge selon les politiques de rétention
     */
    public function executerPurge(): array
    {
        $policies = $this->getRetentionPolicies();
        $results = [];
        $dateColumnMap = [
            'audit_log' => 'created_at',
            'session_security' => 'created_at',
            'messages' => 'created_at',
            'notifications' => 'created_at',
            'rate_limits' => 'timestamp',
        ];
        $conditionMap = [
            'messages' => 'is_deleted = 1 AND',
            'notifications' => 'lu = 1 AND',
        ];

        foreach ($policies as $table => $policy) {
            if (!$policy['actif']) continue;
            $col = $dateColumnMap[$table] ?? 'created_at';
            $extra = $conditionMap[$table] ?? '';
            $cutoff = date('Y-m-d H:i:s', strtotime("-{$policy['duree']} days"));

            try {
                $sql = "DELETE FROM {$table} WHERE {$extra} {$col} < ?";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$cutoff]);
                $count = $stmt->rowCount();
                $results[$table] = ['purged' => $count, 'cutoff' => $cutoff];

                // Update derniere_purge
                try {
                    $stmt = $this->pdo->prepare("UPDATE rgpd_retention_policies SET derniere_purge = NOW() WHERE table_name = ?");
                    $stmt->execute([$table]);
                } catch (\Exception $e) {}
            } catch (\Exception $e) {
                $results[$table] = ['error' => $e->getMessage()];
            }
        }

        $this->logAudit(0, 'system', 'rgpd_purge', json_encode($results));
        return $results;
    }

    /* ───────── HELPER ───────── */

    private function logAudit(int $userId, string $userType, string $action, string $details): void
    {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO audit_log (user_id, user_type, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$userId, $userType, $action, $details, $_SERVER['REMOTE_ADDR'] ?? '']);
        } catch (\Exception $e) {}
    }

    /* ───────── STATIC ───────── */

    public static function typesConsentement(): array
    {
        return [
            'sms' => 'Notifications SMS',
            'email_marketing' => 'Emails d\'information',
            'photo' => 'Droit à l\'image (photos)',
            'donnees_medicales' => 'Données médicales',
            'partage_notes' => 'Partage des notes inter-modules',
            'geolocalisation' => 'Géolocalisation (transports)',
        ];
    }

    public static function typesDemande(): array
    {
        return [
            'acces' => 'Accès aux données',
            'rectification' => 'Rectification',
            'suppression' => 'Suppression (droit à l\'oubli)',
            'portabilite' => 'Portabilité',
            'opposition' => 'Opposition au traitement',
        ];
    }

    public static function statutBadge(string $s): string
    {
        $map = ['en_attente' => 'warning', 'en_cours' => 'info', 'traitee' => 'success', 'refusee' => 'danger'];
        $labels = ['en_attente' => 'En attente', 'en_cours' => 'En cours', 'traitee' => 'Traitée', 'refusee' => 'Refusée'];
        return '<span class="badge badge-' . ($map[$s] ?? 'secondary') . '">' . ($labels[$s] ?? $s) . '</span>';
    }
}
