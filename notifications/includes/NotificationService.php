<?php
/**
 * M12 – Notifications — Service
 */
class NotificationService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ── Créer une notification ──
    public function creer(int $userId, string $userType, string $type, string $titre, ?string $contenu = null, ?string $lien = null, string $importance = 'normale', ?string $sourceType = null, ?int $sourceId = null): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO notifications_globales (user_id, user_type, type, titre, contenu, lien, icone, importance, source_type, source_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $icone = self::iconeParType($type);
        $stmt->execute([$userId, $userType, $type, $titre, $contenu, $lien, $icone, $importance, $sourceType, $sourceId]);
        return (int)$this->pdo->lastInsertId();
    }

    // ── Créer pour plusieurs destinataires ──
    public function creerPourGroupe(array $destinataires, string $type, string $titre, ?string $contenu = null, ?string $lien = null, string $importance = 'normale', ?string $sourceType = null, ?int $sourceId = null): int
    {
        $count = 0;
        foreach ($destinataires as $dest) {
            $this->creer($dest['id'], $dest['type'], $type, $titre, $contenu, $lien, $importance, $sourceType, $sourceId);
            $count++;
        }
        return $count;
    }

    // ── Notifications d'un utilisateur ──
    public function getNotifications(int $userId, string $userType, int $limit = 50, int $offset = 0, ?bool $lu = null): array
    {
        $sql = "SELECT * FROM notifications_globales WHERE user_id = ? AND user_type = ?";
        $params = [$userId, $userType];
        if ($lu !== null) {
            $sql .= " AND lu = ?";
            $params[] = $lu ? 1 : 0;
        }
        $sql .= " ORDER BY date_creation DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Nombre de non lues ──
    public function countNonLues(int $userId, string $userType): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM notifications_globales WHERE user_id = ? AND user_type = ? AND lu = 0");
        $stmt->execute([$userId, $userType]);
        return (int)$stmt->fetchColumn();
    }

    // ── Marquer une notification comme lue ──
    public function marquerLue(int $id, int $userId, string $userType): bool
    {
        $stmt = $this->pdo->prepare("UPDATE notifications_globales SET lu = 1, date_lecture = NOW() WHERE id = ? AND user_id = ? AND user_type = ?");
        return $stmt->execute([$id, $userId, $userType]);
    }

    // ── Marquer toutes comme lues ──
    public function marquerToutesLues(int $userId, string $userType): bool
    {
        $stmt = $this->pdo->prepare("UPDATE notifications_globales SET lu = 1, date_lecture = NOW() WHERE user_id = ? AND user_type = ? AND lu = 0");
        return $stmt->execute([$userId, $userType]);
    }

    // ── Supprimer une notification ──
    public function supprimer(int $id, int $userId, string $userType): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM notifications_globales WHERE id = ? AND user_id = ? AND user_type = ?");
        return $stmt->execute([$id, $userId, $userType]);
    }

    // ── Supprimer les anciennes ──
    public function nettoyerAnciennes(int $joursRetention = 90): int
    {
        $stmt = $this->pdo->prepare("DELETE FROM notifications_globales WHERE date_creation < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->execute([$joursRetention]);
        return $stmt->rowCount();
    }

    // ── Préférences ──
    public function getPreferences(int $userId, string $userType): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM notification_preferences WHERE user_id = ? AND user_type = ?");
        $stmt->execute([$userId, $userType]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $prefs = [];
        foreach ($rows as $row) {
            $prefs[$row['type_notification']] = $row;
        }
        // Remplir les defaults
        foreach (self::typesNotification() as $type => $label) {
            if (!isset($prefs[$type])) {
                $prefs[$type] = ['type_notification' => $type, 'canal_email' => 1, 'canal_web' => 1, 'canal_push' => 0, 'actif' => 1];
            }
        }
        return $prefs;
    }

    // ── Sauvegarder préférences ──
    public function sauvegarderPreferences(int $userId, string $userType, array $prefs): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO notification_preferences (user_id, user_type, type_notification, canal_email, canal_web, canal_push, actif)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE canal_email = VALUES(canal_email), canal_web = VALUES(canal_web), canal_push = VALUES(canal_push), actif = VALUES(actif)
        ");
        foreach ($prefs as $type => $pref) {
            $stmt->execute([
                $userId, $userType, $type,
                (int)($pref['canal_email'] ?? 1),
                (int)($pref['canal_web'] ?? 1),
                (int)($pref['canal_push'] ?? 0),
                (int)($pref['actif'] ?? 1)
            ]);
        }
    }

    // ── Statistiques ──
    public function getStats(int $userId, string $userType): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(lu = 0) as non_lues,
                SUM(importance = 'urgente' AND lu = 0) as urgentes
            FROM notifications_globales WHERE user_id = ? AND user_type = ?
        ");
        $stmt->execute([$userId, $userType]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'non_lues' => 0, 'urgentes' => 0];
    }

    // ── Helpers statiques ──
    public static function typesNotification(): array
    {
        return [
            'nouvelle_note'  => 'Nouvelle note',
            'absence'        => 'Absence signalée',
            'message'        => 'Nouveau message',
            'devoir'         => 'Nouveau devoir',
            'bulletin'       => 'Bulletin publié',
            'incident'       => 'Incident disciplinaire',
            'annonce'        => 'Nouvelle annonce',
            'reunion'        => 'Réunion / Convocation',
            'general'        => 'Information générale',
        ];
    }

    public static function iconeParType(string $type): string
    {
        $map = [
            'nouvelle_note'  => 'fa-chart-bar',
            'absence'        => 'fa-calendar-times',
            'message'        => 'fa-envelope',
            'devoir'         => 'fa-tasks',
            'bulletin'       => 'fa-file-alt',
            'incident'       => 'fa-exclamation-triangle',
            'annonce'        => 'fa-bullhorn',
            'reunion'        => 'fa-handshake',
            'general'        => 'fa-bell',
        ];
        return $map[$type] ?? 'fa-bell';
    }

    public static function importanceBadge(string $imp): string
    {
        $map = [
            'basse'   => '<span class="badge badge-secondary">Basse</span>',
            'normale' => '<span class="badge badge-info">Normale</span>',
            'haute'   => '<span class="badge badge-warning">Haute</span>',
            'urgente' => '<span class="badge badge-danger">Urgente</span>',
        ];
        return $map[$imp] ?? $map['normale'];
    }
}
