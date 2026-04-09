<?php
/**
 * M14 – Réunions / RDV / Convocations — Service
 */
class ReunionService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ── Réunions ──

    public function getReunions(array $filters = []): array
    {
        $sql = "SELECT r.*, c.nom AS classe_nom FROM reunions r LEFT JOIN classes c ON r.classe_id = c.id WHERE 1=1";
        $params = [];
        if (!empty($filters['type'])) { $sql .= " AND r.type = ?"; $params[] = $filters['type']; }
        if (!empty($filters['statut'])) { $sql .= " AND r.statut = ?"; $params[] = $filters['statut']; }
        if (!empty($filters['classe_id'])) { $sql .= " AND r.classe_id = ?"; $params[] = $filters['classe_id']; }
        if (!empty($filters['date_debut'])) { $sql .= " AND r.date_debut >= ?"; $params[] = $filters['date_debut']; }
        $sql .= " ORDER BY r.date_debut DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getReunion(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT r.*, c.nom AS classe_nom FROM reunions r LEFT JOIN classes c ON r.classe_id = c.id WHERE r.id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function creerReunion(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO reunions (titre, description, type, date_debut, date_fin, lieu, classe_id, organisateur_id, organisateur_type)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['titre'], $data['description'] ?? null, $data['type'],
            $data['date_debut'], $data['date_fin'], $data['lieu'] ?? null,
            $data['classe_id'] ?: null, $data['organisateur_id'], $data['organisateur_type']
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function modifierReunion(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE reunions SET titre = ?, description = ?, type = ?, date_debut = ?, date_fin = ?, lieu = ?, classe_id = ?, statut = ?
            WHERE id = ?
        ");
        return $stmt->execute([
            $data['titre'], $data['description'] ?? null, $data['type'],
            $data['date_debut'], $data['date_fin'], $data['lieu'] ?? null,
            $data['classe_id'] ?: null, $data['statut'] ?? 'planifiee', $id
        ]);
    }

    public function supprimerReunion(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM reunions WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function sauvegarderPV(int $id, string $pv): bool
    {
        $stmt = $this->pdo->prepare("UPDATE reunions SET pv_contenu = ?, statut = 'terminee' WHERE id = ?");
        return $stmt->execute([$pv, $id]);
    }

    // ── Créneaux ──

    public function getCreneaux(int $reunionId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT rc.*, p.nom AS prof_nom, p.prenom AS prof_prenom, m.nom AS matiere_nom,
                   rr.id AS reservation_id, rr.parent_id, rr.eleve_id, rr.statut AS resa_statut,
                   par.nom AS parent_nom, par.prenom AS parent_prenom,
                   e.nom AS eleve_nom, e.prenom AS eleve_prenom
            FROM reunion_creneaux rc
            JOIN professeurs p ON rc.professeur_id = p.id
            LEFT JOIN matieres m ON p.matiere = m.nom
            LEFT JOIN reunion_reservations rr ON rr.creneau_id = rc.id AND rr.statut != 'annulee'
            LEFT JOIN parents par ON rr.parent_id = par.id
            LEFT JOIN eleves e ON rr.eleve_id = e.id
            WHERE rc.reunion_id = ?
            ORDER BY rc.heure_debut, p.nom
        ");
        $stmt->execute([$reunionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function ajouterCreneau(int $reunionId, int $profId, string $heureDebut, string $heureFin, int $duree = 15, ?string $salle = null): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO reunion_creneaux (reunion_id, professeur_id, heure_debut, heure_fin, duree_minutes, salle) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$reunionId, $profId, $heureDebut, $heureFin, $duree, $salle]);
        return (int)$this->pdo->lastInsertId();
    }

    public function genererCreneaux(int $reunionId, int $profId, string $heureDebut, string $heureFin, int $dureeMinutes = 15, ?string $salle = null): int
    {
        $count = 0;
        $debut = strtotime($heureDebut);
        $fin = strtotime($heureFin);
        while ($debut + ($dureeMinutes * 60) <= $fin) {
            $hd = date('H:i:s', $debut);
            $hf = date('H:i:s', $debut + ($dureeMinutes * 60));
            $this->ajouterCreneau($reunionId, $profId, $hd, $hf, $dureeMinutes, $salle);
            $debut += $dureeMinutes * 60;
            $count++;
        }
        return $count;
    }

    // ── Réservations ──

    public function reserver(int $creneauId, int $parentId, int $eleveId, ?string $commentaire = null): int
    {
        // Vérifier disponibilité
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM reunion_reservations WHERE creneau_id = ? AND statut != 'annulee'");
        $stmt->execute([$creneauId]);
        if ((int)$stmt->fetchColumn() > 0) {
            throw new \RuntimeException('Ce créneau est déjà réservé.');
        }
        $stmt = $this->pdo->prepare("INSERT INTO reunion_reservations (creneau_id, parent_id, eleve_id, commentaire) VALUES (?, ?, ?, ?)");
        $stmt->execute([$creneauId, $parentId, $eleveId, $commentaire]);
        return (int)$this->pdo->lastInsertId();
    }

    public function annulerReservation(int $id): bool
    {
        $stmt = $this->pdo->prepare("UPDATE reunion_reservations SET statut = 'annulee' WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function getReservationsParent(int $parentId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT rr.*, rc.heure_debut, rc.heure_fin, rc.salle,
                   r.titre AS reunion_titre, r.date_debut AS reunion_date,
                   p.nom AS prof_nom, p.prenom AS prof_prenom,
                   e.nom AS eleve_nom, e.prenom AS eleve_prenom
            FROM reunion_reservations rr
            JOIN reunion_creneaux rc ON rr.creneau_id = rc.id
            JOIN reunions r ON rc.reunion_id = r.id
            JOIN professeurs p ON rc.professeur_id = p.id
            JOIN eleves e ON rr.eleve_id = e.id
            WHERE rr.parent_id = ?
            ORDER BY r.date_debut DESC, rc.heure_debut
        ");
        $stmt->execute([$parentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Convocations ──

    public function creerConvocation(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO convocations (reunion_id, destinataire_id, destinataire_type, objet, contenu, date_convocation, heure, lieu, type, emetteur_id, emetteur_type)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['reunion_id'] ?? null, $data['destinataire_id'], $data['destinataire_type'],
            $data['objet'], $data['contenu'] ?? null, $data['date_convocation'],
            $data['heure'] ?? null, $data['lieu'] ?? null, $data['type'] ?? 'reunion',
            $data['emetteur_id'], $data['emetteur_type']
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getConvocations(int $userId, string $userType): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM convocations WHERE destinataire_id = ? AND destinataire_type = ? ORDER BY date_convocation DESC");
        $stmt->execute([$userId, $userType]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function marquerConvocationLue(int $id): bool
    {
        $stmt = $this->pdo->prepare("UPDATE convocations SET lue = 1, date_lecture = NOW() WHERE id = ?");
        return $stmt->execute([$id]);
    }

    // ── Helpers ──

    public function getClasses(): array
    {
        $stmt = $this->pdo->query("SELECT id, nom, niveau FROM classes WHERE actif = 1 ORDER BY nom");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProfesseurs(): array
    {
        $stmt = $this->pdo->query("SELECT id, nom, prenom, matiere FROM professeurs WHERE actif = 1 ORDER BY nom, prenom");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function typesReunion(): array
    {
        return [
            'parents_profs'  => 'Réunion parents-professeurs',
            'conseil_classe' => 'Conseil de classe',
            'reunion_equipe' => 'Réunion d\'équipe',
            'individuel'     => 'Entretien individuel',
            'autre'          => 'Autre',
        ];
    }

    // ── Auto-reminders ──

    /**
     * Get reunions needing reminders (in the next 24h, not yet reminded).
     */
    public function getReunionsNeedingReminders(): array
    {
        $now = date('Y-m-d H:i:s');
        $in24h = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $stmt = $this->pdo->prepare("
            SELECT r.*, c.nom AS classe_nom
            FROM reunions r
            LEFT JOIN classes c ON r.classe_id = c.id
            WHERE r.date_debut BETWEEN ? AND ?
              AND r.statut = 'planifiee'
              AND (r.reminder_sent IS NULL OR r.reminder_sent = 0)
        ");
        $stmt->execute([$now, $in24h]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Send reminders for upcoming reunions.
     * @return int number of reminders sent
     */
    public function sendReminders(): int
    {
        $reunions = $this->getReunionsNeedingReminders();
        $count = 0;

        foreach ($reunions as $r) {
            $creneaux = $this->getCreneaux($r['id']);
            $parentIds = [];
            foreach ($creneaux as $c) {
                if (!empty($c['parent_id'])) {
                    $parentIds[$c['parent_id']] = true;
                }
            }

            // Notify all parents with reservations
            try {
                $notifPath = __DIR__ . '/../../notifications/includes/NotificationService.php';
                if (file_exists($notifPath)) {
                    require_once $notifPath;
                    $notifService = new \NotificationService($this->pdo);
                    $titre = "Rappel : " . $r['titre'];
                    $message = "Votre réunion est prévue le " . date('d/m/Y à H:i', strtotime($r['date_debut']));
                    $lien = '/reunions/detail_reunion.php?id=' . $r['id'];

                    foreach (array_keys($parentIds) as $pid) {
                        $notifService->creer((int)$pid, 'parent', 'reunion', $titre, $message, $lien, 'haute');
                    }
                }
            } catch (\Exception $e) {}

            $this->pdo->prepare("UPDATE reunions SET reminder_sent = 1 WHERE id = ?")->execute([$r['id']]);
            $count++;
        }

        return $count;
    }

    /**
     * Mark a reunion's reminder as sent.
     */
    public function markReminderSent(int $reunionId): void
    {
        $this->pdo->prepare("UPDATE reunions SET reminder_sent = 1 WHERE id = ?")->execute([$reunionId]);
    }

    // ── Video conference ──

    /**
     * Set video conference link for a reunion.
     */
    public function setVideoLink(int $reunionId, string $link): bool
    {
        $stmt = $this->pdo->prepare("UPDATE reunions SET visio_url = ? WHERE id = ?");
        return $stmt->execute([$link, $reunionId]);
    }

    /**
     * Get available slots for a parent to book (unreserved creneaux).
     */
    public function getCreneauxDisponibles(int $reunionId, ?int $profId = null): array
    {
        $sql = "
            SELECT rc.*, CONCAT(p.prenom, ' ', p.nom) AS prof_nom, p.matiere AS prof_matiere
            FROM reunion_creneaux rc
            JOIN professeurs p ON rc.professeur_id = p.id
            LEFT JOIN reunion_reservations rr ON rr.creneau_id = rc.id AND rr.statut != 'annulee'
            WHERE rc.reunion_id = ? AND rr.id IS NULL
        ";
        $params = [$reunionId];
        if ($profId) {
            $sql .= " AND rc.professeur_id = ?";
            $params[] = $profId;
        }
        $sql .= " ORDER BY rc.heure_debut, p.nom";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ── Meeting notes (PV) ──

    /**
     * Save meeting notes for a specific creneau.
     */
    public function savePVCreneau(int $creneauId, string $contenu, int $profId): int
    {
        // Check if PV already exists
        $stmt = $this->pdo->prepare("SELECT id FROM reunion_pv WHERE creneau_id = ?");
        $stmt->execute([$creneauId]);
        $existing = $stmt->fetchColumn();

        if ($existing) {
            $this->pdo->prepare("UPDATE reunion_pv SET contenu = ?, updated_at = NOW() WHERE id = ?")
                       ->execute([$contenu, $existing]);
            return (int)$existing;
        }

        // Get reunion_id from creneau
        $stmt = $this->pdo->prepare("SELECT reunion_id FROM reunion_creneaux WHERE id = ?");
        $stmt->execute([$creneauId]);
        $reunionId = $stmt->fetchColumn();

        $stmt = $this->pdo->prepare("
            INSERT INTO reunion_pv (reunion_id, creneau_id, contenu, prof_id, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$reunionId, $creneauId, $contenu, $profId]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Get all PVs for a reunion.
     */
    public function getPVs(int $reunionId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT rpv.*, rc.heure_debut, rc.heure_fin,
                   CONCAT(p.prenom, ' ', p.nom) AS prof_nom
            FROM reunion_pv rpv
            JOIN reunion_creneaux rc ON rpv.creneau_id = rc.id
            JOIN professeurs p ON rpv.prof_id = p.id
            WHERE rpv.reunion_id = ?
            ORDER BY rc.heure_debut
        ");
        $stmt->execute([$reunionId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function statutBadge(string $statut): string
    {
        $map = [
            'planifiee' => '<span class="badge badge-info">Planifiée</span>',
            'en_cours'  => '<span class="badge badge-warning">En cours</span>',
            'terminee'  => '<span class="badge badge-success">Terminée</span>',
            'annulee'   => '<span class="badge badge-danger">Annulée</span>',
        ];
        return $map[$statut] ?? $statut;
    }

    // ─── VISIOCONFÉRENCE ───

    public function setVisioUrl(int $reunionId, string $url, string $provider = 'jitsi'): void
    {
        $this->pdo->prepare("UPDATE reunions SET visio_url = :u, visio_provider = :p WHERE id = :id")
            ->execute([':u' => $url, ':p' => $provider, ':id' => $reunionId]);
    }

    // ─── SUIVI PRÉSENCES ───

    public function enregistrerPresence(int $reunionId, int $userId, bool $present): void
    {
        $this->pdo->prepare("INSERT INTO reunion_presences (reunion_id, user_id, present) VALUES (:rid, :uid, :p) ON DUPLICATE KEY UPDATE present = VALUES(present)")
            ->execute([':rid' => $reunionId, ':uid' => $userId, ':p' => $present ? 1 : 0]);
    }

    public function getPresences(int $reunionId): array
    {
        $stmt = $this->pdo->prepare("SELECT rp.*, CONCAT(u.prenom,' ',u.nom) AS nom_complet FROM reunion_presences rp LEFT JOIN utilisateurs u ON rp.user_id = u.id WHERE rp.reunion_id = :rid ORDER BY u.nom");
        $stmt->execute([':rid' => $reunionId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ─── COMPTES RENDUS ───

    public function sauverCompteRendu(int $reunionId, string $contenu, int $redacteurId): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO reunion_comptes_rendus (reunion_id, contenu, redacteur_id) VALUES (:rid, :c, :redi) ON DUPLICATE KEY UPDATE contenu = VALUES(contenu), redacteur_id = VALUES(redacteur_id)");
        $stmt->execute([':rid' => $reunionId, ':c' => $contenu, ':redi' => $redacteurId]);
        return (int)$this->pdo->lastInsertId();
    }

    // ─── EXPORT ICS ───

    public function exportIcs(int $reunionId): string
    {
        $r = $this->pdo->prepare("SELECT * FROM reunions WHERE id = :id");
        $r->execute([':id' => $reunionId]);
        $reunion = $r->fetch(\PDO::FETCH_ASSOC);
        if (!$reunion) return '';

        $dtStart = date('Ymd\THis', strtotime($reunion['date_reunion']));
        $uid = md5('reunion' . $reunionId . 'fronote');
        $ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Fronote//Reunions//FR\r\n";
        $ics .= "BEGIN:VEVENT\r\nUID:{$uid}@fronote\r\nDTSTART:{$dtStart}\r\nSUMMARY:{$reunion['titre']}\r\nLOCATION:{$reunion['lieu']}\r\nDESCRIPTION:{$reunion['description']}\r\nEND:VEVENT\r\n";
        $ics .= "END:VCALENDAR\r\n";
        return $ics;
    }
}
