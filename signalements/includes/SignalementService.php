<?php
/**
 * M45 – Anti-harcèlement — Service de signalement
 */
class SignalementService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function creerSignalement(array $data): int
    {
        // Generate a unique tracking token for anonymous follow-up
        $trackingToken = bin2hex(random_bytes(32));

        $stmt = $this->pdo->prepare("
            INSERT INTO signalements (
                auteur_id, auteur_type, type, description, lieu, date_faits,
                personnes_impliquees, temoins, anonyme, urgence, confidentiel,
                statut, date_signalement, tracking_token
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 'nouveau', NOW(), ?)
        ");
        $stmt->execute([
            $data['anonyme'] ? null : $data['auteur_id'],
            $data['anonyme'] ? null : $data['auteur_type'],
            $data['type'],
            $data['description'],
            $data['lieu'] ?? null,
            $data['date_faits'] ?? null,
            $data['personnes_impliquees'] ?? null,
            $data['temoins'] ?? null,
            $data['anonyme'] ? 1 : 0,
            $data['urgence'] ?? 'normale',
            $trackingToken,
        ]);

        $id = (int) $this->pdo->lastInsertId();

        // Auto-notify direction for urgent reports
        if (in_array($data['urgence'] ?? '', ['haute', 'critique'])) {
            $this->notifyDirection($id, $data);
        }

        return $id;
    }

    /**
     * Get a signalement by its tracking token (for anonymous follow-up).
     */
    public function getByTrackingToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM signalements WHERE tracking_token = ?');
        $stmt->execute([$token]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Resolve a signalement with a resolution note.
     */
    public function resoudre(int $id, string $resolutionNote, int $resolvedBy): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE signalements
            SET statut = 'traite', resolved_at = NOW(), resolution_note = ?, traite_par = ?
            WHERE id = ?
        ");
        $stmt->execute([$resolutionNote, $resolvedBy, $id]);
    }

    /**
     * Auto-notify direction (CPE + admin) for urgent signalements.
     */
    private function notifyDirection(int $signalementId, array $data): void
    {
        try {
            $notifPath = __DIR__ . '/../../notifications/includes/NotificationService.php';
            if (!file_exists($notifPath)) return;
            require_once $notifPath;
            $notifService = new \NotificationService($this->pdo);

            $types = self::typesSignalement();
            $typeName = $types[$data['type']] ?? $data['type'];
            $titre = "Signalement urgent : {$typeName}";
            $message = "Un signalement de niveau {$data['urgence']} a été déposé. Intervention requise.";
            $lien = '/signalements/detail.php?id=' . $signalementId;

            // Notify all admins and CPE
            $admins = $this->pdo->query("SELECT id FROM administrateurs WHERE actif = 1")->fetchAll(\PDO::FETCH_COLUMN);
            foreach ($admins as $adminId) {
                $notifService->creer((int) $adminId, 'administrateur', 'signalement_urgent', $titre, $message, $lien, 'haute');
            }

            $cpe = $this->pdo->query("SELECT id FROM vie_scolaire WHERE actif = 1")->fetchAll(\PDO::FETCH_COLUMN);
            foreach ($cpe as $cpeId) {
                $notifService->creer((int) $cpeId, 'vie_scolaire', 'signalement_urgent', $titre, $message, $lien, 'haute');
            }
        } catch (\Exception $e) {
            // Notification failure must not block signalement creation
        }
    }

    public function getSignalement(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM signalements WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getTousSignalements(array $filters = []): array
    {
        $sql = 'SELECT * FROM signalements WHERE 1=1';
        $params = [];
        if (!empty($filters['statut'])) { $sql .= ' AND statut = ?'; $params[] = $filters['statut']; }
        if (!empty($filters['type'])) { $sql .= ' AND type = ?'; $params[] = $filters['type']; }
        if (!empty($filters['urgence'])) { $sql .= ' AND urgence = ?'; $params[] = $filters['urgence']; }
        $sql .= ' ORDER BY FIELD(urgence, "critique", "haute", "normale", "basse"), date_signalement DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMesSignalements(int $userId, string $userType): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM signalements WHERE auteur_id = ? AND auteur_type = ? ORDER BY date_signalement DESC');
        $stmt->execute([$userId, $userType]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function changerStatut(int $id, string $statut, int $traitePar = null): void
    {
        $sql = 'UPDATE signalements SET statut = ?, date_traitement = NOW()';
        $params = [$statut];
        if ($traitePar) {
            $sql .= ', traite_par = ?';
            $params[] = $traitePar;
        }
        $sql .= ' WHERE id = ?';
        $params[] = $id;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    public function ajouterNote(int $id, string $notes): void
    {
        $stmt = $this->pdo->prepare('UPDATE signalements SET notes_traitement = CONCAT(COALESCE(notes_traitement, ""), ?) WHERE id = ?');
        $stmt->execute(["\n[" . date('d/m/Y H:i') . "] " . $notes, $id]);
    }

    public function getStats(): array
    {
        $stmt = $this->pdo->query("
            SELECT
                COUNT(*) AS total,
                COUNT(CASE WHEN statut = 'nouveau' THEN 1 END) AS nouveaux,
                COUNT(CASE WHEN statut = 'en_cours' THEN 1 END) AS en_cours,
                COUNT(CASE WHEN urgence IN ('haute', 'critique') THEN 1 END) AS urgents,
                COUNT(CASE WHEN anonyme = 1 THEN 1 END) AS anonymes
            FROM signalements
        ");
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function typesSignalement(): array
    {
        return [
            'harcelement' => 'Harcèlement',
            'cyber_harcelement' => 'Cyberharcèlement',
            'violence' => 'Violence physique',
            'discrimination' => 'Discrimination',
            'intimidation' => 'Intimidation',
            'exclusion' => 'Exclusion sociale',
            'autre' => 'Autre',
        ];
    }

    public static function statutBadge(string $statut): string
    {
        $map = [
            'nouveau' => '<span class="badge badge-danger">Nouveau</span>',
            'en_cours' => '<span class="badge badge-warning">En cours</span>',
            'traite' => '<span class="badge badge-success">Traité</span>',
            'classe' => '<span class="badge badge-secondary">Classé</span>',
        ];
        return $map[$statut] ?? '<span class="badge">' . $statut . '</span>';
    }

    public static function urgenceBadge(string $urgence): string
    {
        $map = [
            'basse' => '<span class="badge badge-secondary">Basse</span>',
            'normale' => '<span class="badge badge-info">Normale</span>',
            'haute' => '<span class="badge badge-warning">Haute</span>',
            'critique' => '<span class="badge badge-danger">Critique</span>',
        ];
        return $map[$urgence] ?? '<span class="badge">' . $urgence . '</span>';
    }

    /* ───── EXPORT ───── */

    public function getSignalementsForExport(array $filters = []): array
    {
        $signalements = $this->getTousSignalements($filters);
        $types = self::typesSignalement();
        return array_map(fn($s) => [
            $s['id'],
            $s['date_signalement'],
            $types[$s['type']] ?? $s['type'],
            ucfirst($s['urgence']),
            ucfirst($s['statut']),
            $s['anonyme'] ? 'Oui' : 'Non',
            $s['lieu'] ?? '-',
            mb_substr($s['description'] ?? '', 0, 120),
            $s['date_traitement'] ?? '-',
        ], $signalements);
    }

    // ─── TIMELINE SUIVI ───

    public function ajouterSuivi(int $signalementId, string $action, int $auteurId): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO signalements_suivi (signalement_id, action, auteur_id, date_action) VALUES (:sid, :a, :aid, NOW())");
        $stmt->execute([':sid' => $signalementId, ':a' => $action, ':aid' => $auteurId]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getSuivi(int $signalementId): array
    {
        $stmt = $this->pdo->prepare("SELECT ss.*, CONCAT(p.prenom,' ',p.nom) AS auteur_nom FROM signalements_suivi ss LEFT JOIN professeurs p ON ss.auteur_id = p.id WHERE ss.signalement_id = :sid ORDER BY ss.date_action ASC");
        $stmt->execute([':sid' => $signalementId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ─── ASSIGNATION MULTI ───

    public function assigner(int $signalementId, int $assigneAId): void
    {
        $this->pdo->prepare("INSERT INTO signalements_assignations (signalement_id, assigne_a_id, statut) VALUES (:sid, :aid, 'en_cours') ON DUPLICATE KEY UPDATE statut = 'en_cours'")
            ->execute([':sid' => $signalementId, ':aid' => $assigneAId]);
    }

    // ─── DÉTECTION RÉCURRENCE ───

    public function detecterRecurrence(int $signalementId): array
    {
        $sig = $this->pdo->prepare("SELECT type, lieu FROM signalements WHERE id = :id");
        $sig->execute([':id' => $signalementId]);
        $s = $sig->fetch(\PDO::FETCH_ASSOC);

        $similaires = $this->pdo->prepare("SELECT id, type, lieu, description, date_creation FROM signalements WHERE id != :id AND (type = :t OR lieu = :l) AND date_creation >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) ORDER BY date_creation DESC LIMIT 10");
        $similaires->execute([':id' => $signalementId, ':t' => $s['type'], ':l' => $s['lieu']]);
        return $similaires->fetchAll(\PDO::FETCH_ASSOC);
    }
}
