<?php
/**
 * M23 – RGPD & Audit — Service
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
        $today = date('Y-m-d');
        $month = date('Y-m');
        return [
            'total' => $this->pdo->query("SELECT COUNT(*) FROM audit_log")->fetchColumn(),
            'today' => $this->pdo->prepare("SELECT COUNT(*) FROM audit_log WHERE DATE(created_at) = ?")->execute([$today]) ?
                       $this->pdo->prepare("SELECT COUNT(*) FROM audit_log WHERE DATE(created_at) = ?")->execute([$today]) : 0,
            'month' => $this->pdo->query("SELECT COUNT(*) FROM audit_log WHERE created_at LIKE '$month%'")->fetchColumn(),
        ];
    }

    public function getAuditStatsProper(): array
    {
        $today = date('Y-m-d');
        $month = date('Y-m');

        $stmtToday = $this->pdo->prepare("SELECT COUNT(*) FROM audit_log WHERE DATE(created_at) = ?");
        $stmtToday->execute([$today]);
        $todayCount = $stmtToday->fetchColumn();

        return [
            'total' => $this->pdo->query("SELECT COUNT(*) FROM audit_log")->fetchColumn(),
            'today' => $todayCount,
            'month' => $this->pdo->query("SELECT COUNT(*) FROM audit_log WHERE created_at LIKE '$month%'")->fetchColumn(),
        ];
    }

    /* ───────── CONSENTEMENTS ───────── */

    public function getConsentements(int $userId, string $userType): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM rgpd_consentements WHERE user_id = ? AND user_type = ? ORDER BY type_consentement");
        $stmt->execute([$userId, $userType]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Remplir les types manquants avec valeurs par défaut
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
        return $this->pdo->lastInsertId();
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
