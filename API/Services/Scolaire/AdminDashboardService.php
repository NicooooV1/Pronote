<?php
declare(strict_types=1);

namespace API\Services\Scolaire;

use PDO;

class AdminDashboardService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Compteurs par profil utilisateur.
     */
    public function getUserCounts(): array
    {
        $counts = [];
        $tables = ['eleves', 'professeurs', 'parents', 'vie_scolaire', 'administrateurs'];
        foreach ($tables as $table) {
            try {
                $counts[$table] = (int) $this->pdo->query("SELECT COUNT(*) FROM {$table} WHERE actif = 1")->fetchColumn();
            } catch (\Throwable $e) {
                $counts[$table] = 0;
            }
        }
        return $counts;
    }

    /**
     * Alertes : comptes verrouillés, demandes en attente, justificatifs.
     */
    public function getAlerts(): array
    {
        $alerts = [];

        // Comptes verrouillés
        try {
            $stmt = $this->pdo->query("
                SELECT identifiant, locked_until, 'eleve' as type FROM eleves WHERE locked_until > NOW()
                UNION ALL SELECT identifiant, locked_until, 'professeur' FROM professeurs WHERE locked_until > NOW()
                UNION ALL SELECT identifiant, locked_until, 'parent' FROM parents WHERE locked_until > NOW()
                UNION ALL SELECT identifiant, locked_until, 'vie_scolaire' FROM vie_scolaire WHERE locked_until > NOW()
                UNION ALL SELECT identifiant, locked_until, 'administrateur' FROM administrateurs WHERE locked_until > NOW()
            ");
            $alerts['locked_accounts'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            $alerts['locked_accounts'] = [];
        }

        // Demandes de réinitialisation
        try {
            $alerts['reset_pending'] = (int) $this->pdo->query("SELECT COUNT(*) FROM demandes_reinitialisation WHERE status = 'pending'")->fetchColumn();
        } catch (\Throwable $e) {
            $alerts['reset_pending'] = 0;
        }

        // Justificatifs en attente
        try {
            $alerts['justificatifs_pending'] = (int) $this->pdo->query("SELECT COUNT(*) FROM justificatifs WHERE traite = 0")->fetchColumn();
        } catch (\Throwable $e) {
            $alerts['justificatifs_pending'] = 0;
        }

        return $alerts;
    }

    /**
     * KPIs : moyenne générale, taux absentéisme, remplissage notes, WebSocket, sessions.
     */
    public function getKPIs(): array
    {
        $kpi = [
            'taux_absenteisme' => null,
            'moyenne_generale' => null,
            'taux_remplissage_notes' => null,
            'ws_status' => 'disabled',
            'sessions_actives' => 0,
        ];

        // Taux d'absentéisme (30 derniers jours)
        try {
            $totalEleves = max((int) $this->pdo->query("SELECT COUNT(*) FROM eleves WHERE actif = 1")->fetchColumn(), 1);
            $absences30j = (int) $this->pdo->query("SELECT COUNT(DISTINCT id_eleve) FROM absences WHERE date_debut >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
            $kpi['taux_absenteisme'] = round(($absences30j / $totalEleves) * 100, 1);
        } catch (\Throwable $e) {}

        // Moyenne générale (trimestre courant)
        $trimestre = $this->getCurrentTrimestre();
        try {
            $stmt = $this->pdo->prepare("SELECT ROUND(AVG(note / note_sur * 20), 2) FROM notes WHERE trimestre = ?");
            $stmt->execute([$trimestre]);
            $kpi['moyenne_generale'] = $stmt->fetchColumn() ?: null;
        } catch (\Throwable $e) {}

        // Taux de remplissage notes
        try {
            $stmtProfs = $this->pdo->prepare("SELECT COUNT(DISTINCT id_professeur) FROM notes WHERE trimestre = ?");
            $stmtProfs->execute([$trimestre]);
            $profsAvecNotes = (int) $stmtProfs->fetchColumn();
            $profsTotal = max((int) $this->pdo->query("SELECT COUNT(*) FROM professeurs WHERE actif = 1")->fetchColumn(), 1);
            $kpi['taux_remplissage_notes'] = round(($profsAvecNotes / $profsTotal) * 100, 1);
        } catch (\Throwable $e) {}

        // WebSocket status
        try {
            $wsUrl = function_exists('env') ? env('WEBSOCKET_CLIENT_URL', '') : '';
            if ($wsUrl) {
                $ctx = stream_context_create(['http' => ['timeout' => 2, 'method' => 'GET']]);
                $wsHealth = @file_get_contents(rtrim($wsUrl, '/') . '/health', false, $ctx);
                $kpi['ws_status'] = $wsHealth !== false ? 'ok' : 'down';
                if ($wsHealth) {
                    $wsData = json_decode($wsHealth, true);
                    $kpi['ws_connections'] = $wsData['connections'] ?? 0;
                }
            }
        } catch (\Throwable $e) {
            $kpi['ws_status'] = 'error';
        }

        // Sessions actives
        try {
            $kpi['sessions_actives'] = (int) $this->pdo->query("SELECT COUNT(*) FROM session_security WHERE is_active = 1 AND expires_at > NOW()")->fetchColumn();
        } catch (\Throwable $e) {}

        return $kpi;
    }

    /**
     * Dernières connexions (tous profils confondus).
     */
    public function getRecentLogins(int $limit = 10): array
    {
        try {
            $stmt = $this->pdo->prepare("
                (SELECT identifiant, last_login, 'eleve' as type FROM eleves WHERE last_login IS NOT NULL ORDER BY last_login DESC LIMIT :lim)
                UNION ALL
                (SELECT identifiant, last_login, 'professeur' FROM professeurs WHERE last_login IS NOT NULL ORDER BY last_login DESC LIMIT :lim)
                UNION ALL
                (SELECT identifiant, last_login, 'parent' FROM parents WHERE last_login IS NOT NULL ORDER BY last_login DESC LIMIT :lim)
                UNION ALL
                (SELECT identifiant, last_login, 'vie_scolaire' FROM vie_scolaire WHERE last_login IS NOT NULL ORDER BY last_login DESC LIMIT :lim)
                ORDER BY last_login DESC LIMIT :total
            ");
            $stmt->bindValue(':lim', (int) ceil($limit / 2), PDO::PARAM_INT);
            $stmt->bindValue(':total', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Messages récents (24h ou custom).
     */
    public function getRecentMessages(int $limit = 5): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT m.id, m.body, m.sender_type, m.created_at, c.subject
                FROM messages m
                JOIN conversations c ON m.conversation_id = c.id
                WHERE m.is_deleted = 0
                ORDER BY m.created_at DESC
                LIMIT :lim
            ");
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Statistiques modules.
     */
    public function getModuleStats(): array
    {
        try {
            $total = (int) $this->pdo->query("SELECT COUNT(*) FROM modules_config")->fetchColumn();
            $enabled = (int) $this->pdo->query("SELECT COUNT(*) FROM modules_config WHERE enabled = 1")->fetchColumn();
            return ['total' => $total, 'enabled' => $enabled];
        } catch (\Throwable $e) {
            return ['total' => 0, 'enabled' => 0];
        }
    }

    /**
     * Compteurs divers (classes, messages 24h, absences aujourd'hui).
     */
    public function getExtraCounts(): array
    {
        $extra = [];
        try { $extra['classes'] = (int) $this->pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn(); } catch (\Throwable $e) { $extra['classes'] = 0; }
        try { $extra['absences_today'] = (int) $this->pdo->query("SELECT COUNT(*) FROM absences WHERE DATE(date_debut) = CURDATE()")->fetchColumn(); } catch (\Throwable $e) { $extra['absences_today'] = 0; }
        try { $extra['messages_24h'] = (int) $this->pdo->query("SELECT COUNT(*) FROM messages WHERE is_deleted = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn(); } catch (\Throwable $e) { $extra['messages_24h'] = 0; }
        return $extra;
    }

    private function getCurrentTrimestre(): int
    {
        $mois = (int) date('n');
        if ($mois >= 9 && $mois <= 12) return 1;
        if ($mois >= 1 && $mois <= 3) return 2;
        return 3;
    }
}
