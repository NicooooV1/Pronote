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
}
