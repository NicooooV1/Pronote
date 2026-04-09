<?php
/**
 * M40 – Gestion des salles & matériels — Service
 */
class SallesMaterielService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /* ───── RÉSERVATIONS SALLES ───── */

    public function getReservations(array $filters = []): array
    {
        $sql = "SELECT rs.*, s.nom AS salle_nom, s.capacite,
                       COALESCE(
                           (SELECT CONCAT(prenom, ' ', nom) FROM professeurs WHERE id = rs.reserveur_id),
                           (SELECT CONCAT(prenom, ' ', nom) FROM administrateurs WHERE id = rs.reserveur_id),
                           CONCAT('User #', rs.reserveur_id)
                       ) AS reserveur_nom
                FROM reservations_salles rs
                JOIN salles s ON rs.salle_id = s.id
                WHERE 1=1";
        $params = [];
        if (!empty($filters['date'])) { $sql .= ' AND rs.date_reservation = ?'; $params[] = $filters['date']; }
        if (!empty($filters['salle_id'])) { $sql .= ' AND rs.salle_id = ?'; $params[] = $filters['salle_id']; }
        if (!empty($filters['statut'])) { $sql .= ' AND rs.statut = ?'; $params[] = $filters['statut']; }
        $sql .= ' ORDER BY rs.date_reservation DESC, rs.heure_debut';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function creerReservation(array $d): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO reservations_salles (salle_id, reserveur_id, objet, date_reservation, heure_debut, heure_fin, statut, recurrence) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([$d['salle_id'], $d['reserveur_id'], $d['objet'], $d['date_reservation'], $d['heure_debut'], $d['heure_fin'], $d['statut'] ?? 'confirmee', $d['recurrence'] ?? null]);
        return $this->pdo->lastInsertId();
    }

    public function annulerReservation(int $id): void
    {
        $this->pdo->prepare("UPDATE reservations_salles SET statut = 'annulee' WHERE id = ?")->execute([$id]);
    }

    public function getSalles(): array
    {
        return $this->pdo->query("SELECT id, nom, capacite, batiment, etage FROM salles ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function verifierDisponibilite(int $salleId, string $date, string $heureDebut, string $heureFin): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM reservations_salles WHERE salle_id = ? AND date_reservation = ? AND statut != 'annulee' AND heure_debut < ? AND heure_fin > ?");
        $stmt->execute([$salleId, $date, $heureFin, $heureDebut]);
        return $stmt->fetchColumn() == 0;
    }

    /* ───── EQUIPMENT TRACKING ───── */

    /**
     * Get equipment list for a specific room.
     */
    public function getEquipementsSalle(int $salleId): array
    {
        $stmt = $this->pdo->prepare("SELECT equipements FROM salles WHERE id = ?");
        $stmt->execute([$salleId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || empty($row['equipements'])) return [];
        return json_decode($row['equipements'], true) ?: [];
    }

    /**
     * Update equipment list for a room.
     */
    public function setEquipementsSalle(int $salleId, array $equipements): void
    {
        $json = json_encode($equipements, JSON_UNESCAPED_UNICODE);
        $this->pdo->prepare("UPDATE salles SET equipements = ? WHERE id = ?")->execute([$json, $salleId]);
    }

    /**
     * Search rooms by equipment availability.
     */
    public function chercherSallesParEquipement(string $equipement): array
    {
        $stmt = $this->pdo->prepare("SELECT id, nom, capacite, batiment, etage, equipements FROM salles WHERE equipements LIKE ? ORDER BY nom");
        $stmt->execute(['%' . $equipement . '%']);
        $salles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($salles as &$s) {
            $s['equipements'] = $s['equipements'] ? json_decode($s['equipements'], true) : [];
        }
        return $salles;
    }

    public static function equipementsStandard(): array
    {
        return [
            'videoprojecteur' => 'Vidéoprojecteur', 'tbi' => 'Tableau interactif',
            'ordinateurs' => 'Ordinateurs', 'imprimante' => 'Imprimante',
            'ecran' => 'Écran TV', 'sono' => 'Système audio',
            'climatisation' => 'Climatisation', 'wifi' => 'WiFi',
        ];
    }

    /* ───── MATÉRIELS ───── */

    public function getMateriels(array $filters = []): array
    {
        $sql = "SELECT m.*, s.nom AS salle_nom FROM materiels m LEFT JOIN salles s ON m.salle_id = s.id WHERE 1=1";
        $params = [];
        if (!empty($filters['categorie'])) { $sql .= ' AND m.categorie = ?'; $params[] = $filters['categorie']; }
        if (!empty($filters['etat'])) { $sql .= ' AND m.etat = ?'; $params[] = $filters['etat']; }
        $sql .= ' ORDER BY m.nom';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMateriel(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT m.*, s.nom AS salle_nom FROM materiels m LEFT JOIN salles s ON m.salle_id = s.id WHERE m.id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function creerMateriel(array $d): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO materiels (nom, categorie, reference, etat, salle_id, quantite, valeur) VALUES (?,?,?,?,?,?,?)");
        $stmt->execute([$d['nom'], $d['categorie'], $d['reference'] ?? null, $d['etat'] ?? 'bon', $d['salle_id'] ?: null, $d['quantite'] ?? 1, $d['valeur'] ?? null]);
        return $this->pdo->lastInsertId();
    }

    public function modifierMateriel(int $id, array $d): void
    {
        $stmt = $this->pdo->prepare("UPDATE materiels SET nom=?, categorie=?, reference=?, etat=?, salle_id=?, quantite=?, valeur=? WHERE id=?");
        $stmt->execute([$d['nom'], $d['categorie'], $d['reference'], $d['etat'], $d['salle_id'] ?: null, $d['quantite'], $d['valeur'], $id]);
    }

    /* ───── PRÊTS ───── */

    public function getPrets(array $filters = []): array
    {
        $sql = "SELECT pm.*, mat.nom AS materiel_nom,
                       COALESCE(
                           (SELECT CONCAT(prenom, ' ', nom) FROM professeurs WHERE id = pm.emprunteur_id),
                           (SELECT CONCAT(prenom, ' ', nom) FROM eleves WHERE id = pm.emprunteur_id),
                           CONCAT('User #', pm.emprunteur_id)
                       ) AS emprunteur_nom
                FROM prets_materiels pm
                JOIN materiels mat ON pm.materiel_id = mat.id
                WHERE 1=1";
        $params = [];
        if (!empty($filters['statut'])) { $sql .= ' AND pm.statut = ?'; $params[] = $filters['statut']; }
        $sql .= ' ORDER BY pm.date_emprunt DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function creerPret(array $d): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO prets_materiels (materiel_id, emprunteur_id, date_emprunt, date_retour_prevue, statut) VALUES (?,?,?,?,?)");
        $stmt->execute([$d['materiel_id'], $d['emprunteur_id'], $d['date_emprunt'], $d['date_retour_prevue'], 'en_cours']);
        return $this->pdo->lastInsertId();
    }

    public function retournerPret(int $id): void
    {
        $this->pdo->prepare("UPDATE prets_materiels SET statut = 'retourne', date_retour_effective = NOW() WHERE id = ?")->execute([$id]);
    }

    /* ───── HELPERS ───── */

    public function getStatsMateriels(): array
    {
        $total = $this->pdo->query("SELECT COUNT(*) FROM materiels")->fetchColumn();
        $prets_en_cours = $this->pdo->query("SELECT COUNT(*) FROM prets_materiels WHERE statut = 'en_cours'")->fetchColumn();
        $hs = $this->pdo->query("SELECT COUNT(*) FROM materiels WHERE etat = 'hors_service'")->fetchColumn();
        return ['total' => $total, 'prets_en_cours' => $prets_en_cours, 'hors_service' => $hs];
    }

    public static function categoriesMateriels(): array
    {
        return ['informatique' => 'Informatique', 'audiovisuel' => 'Audiovisuel', 'sportif' => 'Sportif', 'scientifique' => 'Scientifique', 'mobilier' => 'Mobilier', 'autre' => 'Autre'];
    }

    public static function etatsMateriels(): array
    {
        return ['neuf' => 'Neuf', 'bon' => 'Bon état', 'usage' => 'Usagé', 'hors_service' => 'Hors service'];
    }

    public static function badgeEtat(string $e): string
    {
        $m = ['neuf' => 'success', 'bon' => 'info', 'usage' => 'warning', 'hors_service' => 'danger'];
        return '<span class="badge badge-' . ($m[$e] ?? 'secondary') . '">' . ucfirst(str_replace('_', ' ', $e)) . '</span>';
    }

    /* ───────── EXPORT ───────── */

    public function getReservationsForExport(array $filters = []): array
    {
        $reservations = $this->getReservations($filters);
        $rows = [];
        foreach ($reservations as $r) {
            $rows[] = [
                $r['salle_nom'] ?? '',
                $r['date_reservation'] ?? '',
                $r['heure_debut'] ?? '',
                $r['heure_fin'] ?? '',
                $r['motif'] ?? '',
                $r['demandeur_nom'] ?? '',
                $r['statut'] ?? '',
            ];
        }
        return $rows;
    }

    public function getMaterielsForExport(array $filters = []): array
    {
        $materiels = $this->getMateriels($filters);
        $cats = self::categoriesMateriels();
        $etats = self::etatsMateriels();
        $rows = [];
        foreach ($materiels as $m) {
            $rows[] = [
                $m['nom'] ?? '',
                $m['reference'] ?? '',
                $cats[$m['categorie'] ?? ''] ?? $m['categorie'] ?? '',
                $etats[$m['etat'] ?? ''] ?? $m['etat'] ?? '',
                $m['quantite'] ?? 0,
                $m['localisation'] ?? '',
            ];
        }
        return $rows;
    }

    /**
     * Planning d'occupation d'une salle pour une semaine
     */
    public function getPlanningOccupation(int $salleId, string $dateDebut, string $dateFin): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM reservations_salles 
            WHERE salle_id = ? AND date_reservation BETWEEN ? AND ? AND statut != 'annulee'
            ORDER BY date_reservation, heure_debut
        ");
        $stmt->execute([$salleId, $dateDebut, $dateFin]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Taux d'occupation des salles sur une période
     */
    public function getTauxOccupation(string $dateDebut, string $dateFin): array
    {
        $salles = $this->getSalles();
        $result = [];
        foreach ($salles as $s) {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as nb_reservations,
                       SUM(TIMESTAMPDIFF(MINUTE, heure_debut, heure_fin)) as minutes_occupees
                FROM reservations_salles
                WHERE salle_id = ? AND date_reservation BETWEEN ? AND ? AND statut != 'annulee'
            ");
            $stmt->execute([$s['id'], $dateDebut, $dateFin]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            $result[] = [
                'salle' => $s['nom'] ?? $s['numero'] ?? "Salle #{$s['id']}",
                'reservations' => (int)$data['nb_reservations'],
                'heures' => round(($data['minutes_occupees'] ?? 0) / 60, 1),
            ];
        }
        return $result;
    }

    // ─── PLAN INTERACTIF ÉTAGES ───

    public function getPlanEtage(?string $batiment = null, ?int $etage = null): array
    {
        $sql = "SELECT s.*,
                  (SELECT COUNT(*) FROM reservations_salles rs WHERE rs.salle_id = s.id AND rs.date_reservation = CURDATE() AND rs.statut != 'annulee') AS reservations_aujourdhui
                FROM salles s WHERE 1=1";
        $params = [];
        if ($batiment !== null) { $sql .= " AND s.batiment = :b"; $params[':b'] = $batiment; }
        if ($etage !== null) { $sql .= " AND s.etage = :e"; $params[':e'] = $etage; }
        $sql .= " ORDER BY s.batiment, s.etage, s.nom";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $salles = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($salles as &$s) {
            $s['equipements'] = $s['equipements'] ? json_decode($s['equipements'], true) : [];
            $s['occupee'] = $s['reservations_aujourdhui'] > 0;
        }
        return $salles;
    }

    // ─── CALENDRIER DISPONIBILITÉ ───

    public function getDisponibilites(int $salleId, string $date): array
    {
        $stmt = $this->pdo->prepare("
            SELECT heure_debut, heure_fin, motif, demandeur_nom, statut
            FROM reservations_salles
            WHERE salle_id = :s AND date_reservation = :d AND statut != 'annulee'
            ORDER BY heure_debut
        ");
        $stmt->execute([':s' => $salleId, ':d' => $date]);
        $reservees = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $creneaux = [];
        $heureDebut = '08:00';
        foreach ($reservees as $r) {
            if ($heureDebut < $r['heure_debut']) {
                $creneaux[] = ['debut' => $heureDebut, 'fin' => $r['heure_debut'], 'libre' => true];
            }
            $creneaux[] = ['debut' => $r['heure_debut'], 'fin' => $r['heure_fin'], 'libre' => false, 'motif' => $r['motif'], 'par' => $r['demandeur_nom']];
            $heureDebut = $r['heure_fin'];
        }
        if ($heureDebut < '18:00') {
            $creneaux[] = ['debut' => $heureDebut, 'fin' => '18:00', 'libre' => true];
        }
        return $creneaux;
    }

    // ─── SIGNALEMENT MAINTENANCE ───

    public function signalerMaintenance(int $salleId, string $description, int $signalePar, string $priorite = 'normale'): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO salles_maintenance (salle_id, description, signale_par, priorite, statut, created_at)
            VALUES (:s, :d, :sp, :p, 'signale', NOW())
        ");
        $stmt->execute([':s' => $salleId, ':d' => $description, ':sp' => $signalePar, ':p' => $priorite]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getMaintenances(?int $salleId = null, ?string $statut = null): array
    {
        $sql = "SELECT sm.*, s.nom AS salle_nom FROM salles_maintenance sm JOIN salles s ON sm.salle_id = s.id WHERE 1=1";
        $params = [];
        if ($salleId) { $sql .= " AND sm.salle_id = :s"; $params[':s'] = $salleId; }
        if ($statut) { $sql .= " AND sm.statut = :st"; $params[':st'] = $statut; }
        $sql .= " ORDER BY FIELD(sm.priorite, 'urgente', 'haute', 'normale', 'basse'), sm.created_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function traiterMaintenance(int $id, string $statut, ?string $commentaire = null): void
    {
        $this->pdo->prepare("UPDATE salles_maintenance SET statut = :s, commentaire_resolution = :c, resolved_at = NOW() WHERE id = :id")
            ->execute([':s' => $statut, ':c' => $commentaire, ':id' => $id]);
    }

    // ─── QR CODES SALLES ───

    public function getQrCodeData(int $salleId): array
    {
        $salle = $this->getSalle($salleId);
        if (!$salle) throw new \RuntimeException('Salle introuvable');
        return [
            'type' => 'salle',
            'id' => $salleId,
            'nom' => $salle['nom'] ?? '',
            'batiment' => $salle['batiment'] ?? '',
            'etage' => $salle['etage'] ?? '',
            'url' => "/salles/detail.php?id={$salleId}",
        ];
    }

    // ─── RÉSERVATIONS RÉCURRENTES ───

    public function creerReservationRecurrente(int $salleId, string $jour, string $heureDebut, string $heureFin, string $motif, int $demandeurId, string $dateDebut, string $dateFin): int
    {
        $count = 0;
        $current = new \DateTime($dateDebut);
        $end = new \DateTime($dateFin);
        $jourNum = ['lundi' => 1, 'mardi' => 2, 'mercredi' => 3, 'jeudi' => 4, 'vendredi' => 5, 'samedi' => 6, 'dimanche' => 7];
        $targetDay = $jourNum[strtolower($jour)] ?? 1;

        while ($current <= $end) {
            if ((int)$current->format('N') === $targetDay) {
                $dateStr = $current->format('Y-m-d');
                $exists = $this->pdo->prepare("SELECT id FROM reservations_salles WHERE salle_id = :s AND date_reservation = :d AND heure_debut < :hf AND heure_fin > :hd AND statut != 'annulee'");
                $exists->execute([':s' => $salleId, ':d' => $dateStr, ':hf' => $heureFin, ':hd' => $heureDebut]);
                if (!$exists->fetch()) {
                    $this->pdo->prepare("
                        INSERT INTO reservations_salles (salle_id, date_reservation, heure_debut, heure_fin, motif, demandeur_id, statut, recurrence_group)
                        VALUES (:s, :d, :hd, :hf, :m, :di, 'confirmee', :rg)
                    ")->execute([':s' => $salleId, ':d' => $dateStr, ':hd' => $heureDebut, ':hf' => $heureFin, ':m' => $motif, ':di' => $demandeurId, ':rg' => "rec_{$salleId}_{$dateDebut}"]);
                    $count++;
                }
            }
            $current->modify('+1 day');
        }
        return $count;
    }
}
