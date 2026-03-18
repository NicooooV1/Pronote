<?php
/**
 * CantineService — Service métier pour le module Cantine (M18).
 */
class CantineService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /* ==================== MENUS ==================== */

    public function getMenus(string $dateDebut, string $dateFin): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM menus_cantine WHERE date_menu BETWEEN ? AND ? ORDER BY date_menu, regime_special"
        );
        $stmt->execute([$dateDebut, $dateFin]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMenuDuJour(?string $date = null): array
    {
        $date = $date ?: date('Y-m-d');
        $stmt = $this->pdo->prepare("SELECT * FROM menus_cantine WHERE date_menu = ?");
        $stmt->execute([$date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function sauvegarderMenu(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO menus_cantine (date_menu, entree, plat_principal, accompagnement, dessert, allergenes, regime_special)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                entree = VALUES(entree), plat_principal = VALUES(plat_principal),
                accompagnement = VALUES(accompagnement), dessert = VALUES(dessert),
                allergenes = VALUES(allergenes)"
        );
        $stmt->execute([
            $data['date_menu'],
            $data['entree'] ?? null,
            $data['plat_principal'] ?? null,
            $data['accompagnement'] ?? null,
            $data['dessert'] ?? null,
            $data['allergenes'] ?? null,
            $data['regime_special'] ?? 'normal',
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function supprimerMenu(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM menus_cantine WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /* ==================== RÉSERVATIONS ==================== */

    public function reserver(int $eleveId, string $date, string $type = 'dejeuner', ?string $regime = null, ?string $par = null): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO cantine_reservations (eleve_id, date_repas, type_repas, regime, reserve_par)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE statut = 'reserve', regime = VALUES(regime)"
        );
        $stmt->execute([$eleveId, $date, $type, $regime, $par]);
        return (int) $this->pdo->lastInsertId();
    }

    public function annulerReservation(int $id): bool
    {
        $stmt = $this->pdo->prepare("UPDATE cantine_reservations SET statut = 'annule' WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function getReservationsJour(string $date): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT cr.*, e.nom, e.prenom, e.classe
             FROM cantine_reservations cr
             JOIN eleves e ON cr.eleve_id = e.id
             WHERE cr.date_repas = ? AND cr.statut != 'annule'
             ORDER BY e.classe, e.nom"
        );
        $stmt->execute([$date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getReservationsEleve(int $eleveId, string $dateDebut, string $dateFin): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM cantine_reservations
             WHERE eleve_id = ? AND date_repas BETWEEN ? AND ?
             ORDER BY date_repas"
        );
        $stmt->execute([$eleveId, $dateDebut, $dateFin]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ==================== POINTAGE ==================== */

    public function pointer(int $reservationId, int $pointePar): bool
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                "INSERT IGNORE INTO cantine_pointage (reservation_id, pointe_par) VALUES (?, ?)"
            );
            $stmt->execute([$reservationId, $pointePar]);

            $stmt = $this->pdo->prepare(
                "UPDATE cantine_reservations SET statut = 'consomme' WHERE id = ?"
            );
            $stmt->execute([$reservationId]);

            $this->pdo->commit();
            return true;
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    public function getPointageJour(string $date): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT cr.*, cp.heure_passage, e.nom, e.prenom, e.classe
             FROM cantine_reservations cr
             LEFT JOIN cantine_pointage cp ON cr.id = cp.reservation_id
             JOIN eleves e ON cr.eleve_id = e.id
             WHERE cr.date_repas = ? AND cr.statut != 'annule'
             ORDER BY e.classe, e.nom"
        );
        $stmt->execute([$date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ==================== TARIFS ==================== */

    public function getTarifs(?string $annee = null): array
    {
        $annee = $annee ?: $this->getAnneeScolaire();
        $stmt = $this->pdo->prepare("SELECT * FROM cantine_tarifs WHERE annee_scolaire = ? ORDER BY tranche");
        $stmt->execute([$annee]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function sauvegarderTarif(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO cantine_tarifs (tranche, tarif_repas, type_repas, annee_scolaire)
             VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['tranche'], $data['tarif_repas'],
            $data['type_repas'] ?? 'dejeuner', $data['annee_scolaire'] ?? $this->getAnneeScolaire(),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /* ==================== STATISTIQUES ==================== */

    public function getStats(string $dateDebut, string $dateFin): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) AS total_reservations,
                    SUM(CASE WHEN statut = 'consomme' THEN 1 ELSE 0 END) AS consommes,
                    SUM(CASE WHEN statut = 'annule' THEN 1 ELSE 0 END) AS annules,
                    COUNT(DISTINCT eleve_id) AS nb_eleves
             FROM cantine_reservations
             WHERE date_repas BETWEEN ? AND ?"
        );
        $stmt->execute([$dateDebut, $dateFin]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function getStatsParRegime(string $date): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(regime, 'normal') AS regime, COUNT(*) AS nb
             FROM cantine_reservations
             WHERE date_repas = ? AND statut != 'annule'
             GROUP BY regime"
        );
        $stmt->execute([$date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ==================== HELPERS ==================== */

    private function getAnneeScolaire(): string
    {
        $m = (int) date('n');
        $y = (int) date('Y');
        return $m >= 9 ? "$y-" . ($y + 1) : ($y - 1) . "-$y";
    }

    /* ==================== EXPORT ==================== */

    /**
     * Export réservations pour période donnée
     */
    public function getReservationsForExport(string $dateDebut, string $dateFin): array
    {
        $stmt = $this->pdo->prepare("
            SELECT r.date_repas, r.type_repas, r.regime, r.statut,
                   CONCAT(e.prenom, ' ', e.nom) AS eleve,
                   cl.nom AS classe
            FROM cantine_reservations r
            JOIN eleves e ON r.eleve_id = e.id
            LEFT JOIN classes cl ON e.classe_id = cl.id
            WHERE r.date_repas BETWEEN ? AND ?
            ORDER BY r.date_repas, e.nom
        ");
        $stmt->execute([$dateDebut, $dateFin]);
        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $rows[] = [
                $r['date_repas'],
                $r['eleve'],
                $r['classe'] ?? '',
                $r['type_repas'],
                $r['regime'] ?? 'normal',
                $r['statut'],
            ];
        }
        return $rows;
    }

    /**
     * Export menus de la semaine
     */
    public function getMenusForExport(string $dateDebut, string $dateFin): array
    {
        $menus = $this->getMenus($dateDebut, $dateFin);
        $rows = [];
        foreach ($menus as $m) {
            $rows[] = [
                $m['date_menu'] ?? '',
                $m['type_repas'] ?? 'dejeuner',
                $m['entree'] ?? '',
                $m['plat'] ?? '',
                $m['accompagnement'] ?? '',
                $m['dessert'] ?? '',
            ];
        }
        return $rows;
    }

    /**
     * Statistiques par classe pour une journée
     */
    public function getStatsParClasse(string $date): array
    {
        $stmt = $this->pdo->prepare("
            SELECT cl.nom AS classe, COUNT(*) AS reservations,
                   SUM(CASE WHEN r.statut = 'consomme' THEN 1 ELSE 0 END) AS consommes
            FROM cantine_reservations r
            JOIN eleves e ON r.eleve_id = e.id
            LEFT JOIN classes cl ON e.classe_id = cl.id
            WHERE r.date_repas = ? AND r.statut != 'annule'
            GROUP BY cl.id, cl.nom ORDER BY cl.nom
        ");
        $stmt->execute([$date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
