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
}
