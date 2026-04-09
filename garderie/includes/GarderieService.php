<?php
/**
 * GarderieService — Service métier pour le module Garderie (M20).
 */
class GarderieService
{
    private PDO $pdo;

    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    /* ==================== CRÉNEAUX ==================== */

    public function getCreneaux(bool $actifsOnly = true): array
    {
        $sql = "SELECT * FROM garderie_creneaux" . ($actifsOnly ? " WHERE actif = 1" : "") . " ORDER BY type, heure_debut";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCreneau(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM garderie_creneaux WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function creerCreneau(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO garderie_creneaux (nom, type, heure_debut, heure_fin, places_max, tarif) VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$data['nom'], $data['type'], $data['heure_debut'], $data['heure_fin'],
            $data['places_max'] ?? null, $data['tarif'] ?? null]);
        return (int) $this->pdo->lastInsertId();
    }

    /* ==================== INSCRIPTIONS ==================== */

    public function inscrire(int $creneauId, int $eleveId, string $jour, string $dateDebut, ?string $par = null): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO garderie_inscriptions (creneau_id, eleve_id, jour, date_debut, inscrit_par)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE statut = 'actif', date_debut = VALUES(date_debut)"
        );
        $stmt->execute([$creneauId, $eleveId, $jour, $dateDebut, $par]);
        return (int) $this->pdo->lastInsertId();
    }

    public function desinscrire(int $inscriptionId): bool
    {
        $stmt = $this->pdo->prepare("UPDATE garderie_inscriptions SET statut = 'annule' WHERE id = ?");
        return $stmt->execute([$inscriptionId]);
    }

    public function getInscriptions(?int $creneauId = null): array
    {
        $sql = "SELECT gi.*, e.nom, e.prenom, e.classe, gc.nom AS creneau_nom, gc.type AS creneau_type
                FROM garderie_inscriptions gi
                JOIN eleves e ON gi.eleve_id = e.id
                JOIN garderie_creneaux gc ON gi.creneau_id = gc.id
                WHERE gi.statut = 'actif'";
        $params = [];
        if ($creneauId) { $sql .= " AND gi.creneau_id = ?"; $params[] = $creneauId; }
        $sql .= " ORDER BY gc.type, gi.jour, e.nom";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getInscriptionsEleve(int $eleveId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT gi.*, gc.nom AS creneau_nom, gc.type AS creneau_type, gc.heure_debut, gc.heure_fin
             FROM garderie_inscriptions gi
             JOIN garderie_creneaux gc ON gi.creneau_id = gc.id
             WHERE gi.eleve_id = ? AND gi.statut = 'actif'
             ORDER BY FIELD(gi.jour, 'lundi','mardi','mercredi','jeudi','vendredi'), gc.heure_debut"
        );
        $stmt->execute([$eleveId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ==================== PRÉSENCES ==================== */

    public function pointerPresence(int $inscriptionId, string $date, bool $present = true, ?string $remarques = null): bool
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO garderie_presences (inscription_id, date_presence, present, remarques, heure_arrivee)
             VALUES (?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE present = VALUES(present), remarques = VALUES(remarques)"
        );
        return $stmt->execute([$inscriptionId, $date, $present ? 1 : 0, $remarques]);
    }

    public function getPresencesJour(string $date, ?int $creneauId = null): array
    {
        $sql = "SELECT gp.*, gi.eleve_id, gi.jour, gi.creneau_id, e.nom, e.prenom, e.classe,
                       gc.nom AS creneau_nom, gc.type AS creneau_type
                FROM garderie_inscriptions gi
                JOIN eleves e ON gi.eleve_id = e.id
                JOIN garderie_creneaux gc ON gi.creneau_id = gc.id
                LEFT JOIN garderie_presences gp ON gi.id = gp.inscription_id AND gp.date_presence = ?
                WHERE gi.statut = 'actif' AND gi.jour = LOWER(DATE_FORMAT(?, '%W'))";
        $params = [$date, $date];
        if ($creneauId) { $sql .= " AND gi.creneau_id = ?"; $params[] = $creneauId; }
        $sql .= " ORDER BY gc.type, e.nom";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ==================== POINTAGE ARRIVÉE/DÉPART ==================== */

    /**
     * Record arrival time for a student.
     */
    public function pointerArrivee(int $inscriptionId, string $date): void
    {
        $this->pdo->prepare("
            UPDATE garderie_inscriptions SET pointage_arrivee = NOW() WHERE id = ?
        ")->execute([$inscriptionId]);

        $this->pointerPresence($inscriptionId, $date, true);
    }

    /**
     * Record departure time for a student.
     */
    public function pointerDepart(int $inscriptionId): void
    {
        $this->pdo->prepare("
            UPDATE garderie_inscriptions SET pointage_depart = NOW() WHERE id = ?
        ")->execute([$inscriptionId]);
    }

    /**
     * Calculate billable hours for a student in a given month.
     */
    public function calculerHeuresMois(int $eleveId, string $mois): float
    {
        $stmt = $this->pdo->prepare("
            SELECT SUM(TIMESTAMPDIFF(MINUTE, gi.pointage_arrivee, COALESCE(gi.pointage_depart, gi.pointage_arrivee))) / 60 AS heures
            FROM garderie_inscriptions gi
            WHERE gi.eleve_id = ? AND DATE_FORMAT(gi.pointage_arrivee, '%Y-%m') = ?
              AND gi.pointage_arrivee IS NOT NULL
        ");
        $stmt->execute([$eleveId, $mois]);
        return round((float)$stmt->fetchColumn(), 2);
    }

    /* ==================== STATS ==================== */

    public function getStats(): array
    {
        $stats = [];
        $stats['total_creneaux'] = (int) $this->pdo->query("SELECT COUNT(*) FROM garderie_creneaux WHERE actif = 1")->fetchColumn();
        $stats['total_inscrits'] = (int) $this->pdo->query("SELECT COUNT(*) FROM garderie_inscriptions WHERE statut = 'actif'")->fetchColumn();
        $stats['nb_eleves'] = (int) $this->pdo->query("SELECT COUNT(DISTINCT eleve_id) FROM garderie_inscriptions WHERE statut = 'actif'")->fetchColumn();
        return $stats;
    }

    /* ==================== EXPORT ==================== */

    public function getInscriptionsForExport(?int $creneauId = null): array
    {
        $inscriptions = $this->getInscriptions($creneauId);
        return array_map(fn($i) => [
            $i['nom'] . ' ' . $i['prenom'],
            $i['classe'] ?? '-',
            $i['creneau_nom'],
            ucfirst($i['creneau_type']),
            ucfirst($i['jour']),
            $i['date_debut'] ?? '-',
            ucfirst($i['statut']),
        ], $inscriptions);
    }

    public function getPresencesForExport(string $date, ?int $creneauId = null): array
    {
        $presences = $this->getPresencesJour($date, $creneauId);
        return array_map(fn($p) => [
            $p['nom'] . ' ' . $p['prenom'],
            $p['classe'] ?? '-',
            $p['creneau_nom'],
            $date,
            isset($p['present']) ? ($p['present'] ? 'Présent' : 'Absent') : 'Non pointé',
            $p['heure_arrivee'] ?? '-',
            $p['remarques'] ?? '',
        ], $presences);
    }

    // ─── PRÉSENTS EN TEMPS RÉEL ───

    public function getPresentsActuellement(?int $creneauId = null): array
    {
        $sql = "SELECT gi.id, gi.eleve_id, CONCAT(e.prenom, ' ', e.nom) AS eleve_nom, e.classe,
                       gc.nom AS creneau_nom, gi.pointage_arrivee, gi.pointage_depart
                FROM garderie_inscriptions gi
                JOIN eleves e ON gi.eleve_id = e.id
                JOIN garderie_creneaux gc ON gi.creneau_id = gc.id
                WHERE gi.statut = 'actif'
                  AND DATE(gi.pointage_arrivee) = CURDATE()
                  AND gi.pointage_depart IS NULL";
        $params = [];
        if ($creneauId) { $sql .= " AND gi.creneau_id = :c"; $params[':c'] = $creneauId; }
        $sql .= " ORDER BY gi.pointage_arrivee DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getNbPresentsActuellement(): int
    {
        return count($this->getPresentsActuellement());
    }

    // ─── PLANNING ACTIVITÉS ───

    public function creerActivite(array $d): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO garderie_activites (creneau_id, titre, description, date_activite, animateur)
            VALUES (:c, :t, :d, :da, :a)
        ");
        $stmt->execute([':c' => $d['creneau_id'], ':t' => $d['titre'], ':d' => $d['description'] ?? null,
                        ':da' => $d['date_activite'], ':a' => $d['animateur'] ?? null]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getActivites(?int $creneauId = null, ?string $dateDebut = null, ?string $dateFin = null): array
    {
        $sql = "SELECT ga.*, gc.nom AS creneau_nom FROM garderie_activites ga LEFT JOIN garderie_creneaux gc ON ga.creneau_id = gc.id WHERE 1=1";
        $params = [];
        if ($creneauId) { $sql .= " AND ga.creneau_id = :c"; $params[':c'] = $creneauId; }
        if ($dateDebut) { $sql .= " AND ga.date_activite >= :dd"; $params[':dd'] = $dateDebut; }
        if ($dateFin) { $sql .= " AND ga.date_activite <= :df"; $params[':df'] = $dateFin; }
        $sql .= " ORDER BY ga.date_activite DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ─── NOTIFICATION ARRIVÉE PARENT ───

    public function notifierArriveeParent(int $eleveId): void
    {
        $stmt = $this->pdo->prepare("SELECT pe.parent_id FROM parent_eleve pe WHERE pe.eleve_id = :e");
        $stmt->execute([':e' => $eleveId]);
        $parentIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $eleve = $this->pdo->prepare("SELECT prenom, nom FROM eleves WHERE id = ?");
        $eleve->execute([$eleveId]);
        $el = $eleve->fetch(\PDO::FETCH_ASSOC);
        $nom = $el ? $el['prenom'] . ' ' . $el['nom'] : 'Votre enfant';

        try {
            require_once __DIR__ . '/../../notifications/includes/NotificationService.php';
            $notif = new \NotificationService($this->pdo);
            foreach ($parentIds as $pid) {
                $notif->creer((int)$pid, 'parent', 'garderie', 'Départ garderie',
                    "{$nom} a quitté la garderie à " . date('H:i') . '.',
                    '/garderie/', 'normale');
            }
        } catch (\Exception $e) {}
    }

    // ─── BILAN MENSUEL ───

    public function getBilanMensuel(int $eleveId, string $mois): array
    {
        $stmt = $this->pdo->prepare("
            SELECT gp.date_presence, gp.present, gp.remarques, gc.nom AS creneau_nom, gc.type
            FROM garderie_presences gp
            JOIN garderie_inscriptions gi ON gp.inscription_id = gi.id
            JOIN garderie_creneaux gc ON gi.creneau_id = gc.id
            WHERE gi.eleve_id = :e AND DATE_FORMAT(gp.date_presence, '%Y-%m') = :m
            ORDER BY gp.date_presence
        ");
        $stmt->execute([':e' => $eleveId, ':m' => $mois]);
        $presences = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $heures = $this->calculerHeuresMois($eleveId, $mois);
        $nbPresent = count(array_filter($presences, fn($p) => $p['present']));
        $nbAbsent = count(array_filter($presences, fn($p) => !$p['present']));

        return [
            'mois' => $mois,
            'presences' => $presences,
            'total_jours_present' => $nbPresent,
            'total_jours_absent' => $nbAbsent,
            'heures_totales' => $heures,
        ];
    }
}
