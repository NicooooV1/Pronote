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

    /* ==================== ALLERGEN ALERTS ==================== */

    /**
     * Check menu allergens against student allergies.
     * Returns list of students with allergen conflicts.
     */
    public function checkAllergenConflicts(string $date): array
    {
        $menus = $this->getMenuDuJour($date);
        if (empty($menus)) return [];

        // Collect all allergens from menus
        $menuAllergens = [];
        foreach ($menus as $m) {
            $alls = json_decode($m['allergenes'] ?? '[]', true);
            if (is_array($alls)) {
                $menuAllergens = array_merge($menuAllergens, $alls);
            }
        }
        $menuAllergens = array_unique($menuAllergens);
        if (empty($menuAllergens)) return [];

        // Find students with reservations who have matching allergies
        $conflicts = [];
        $stmt = $this->pdo->prepare("
            SELECT cr.id AS reservation_id, e.id AS eleve_id,
                   CONCAT(e.prenom, ' ', e.nom) AS eleve_nom, e.classe,
                   ea.allergene, ea.severity
            FROM cantine_reservations cr
            JOIN eleves e ON cr.eleve_id = e.id
            JOIN eleve_allergies ea ON ea.eleve_id = e.id
            WHERE cr.date_repas = ? AND cr.statut != 'annule'
        ");
        $stmt->execute([$date]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($rows as $r) {
            if (in_array($r['allergene'], $menuAllergens)) {
                $conflicts[] = $r;
            }
        }
        return $conflicts;
    }

    /**
     * Get/set student allergies.
     */
    public function getAllergiesEleve(int $eleveId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM eleve_allergies WHERE eleve_id = ?");
        $stmt->execute([$eleveId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function setAllergieEleve(int $eleveId, string $allergene, string $severity = 'modere'): void
    {
        $this->pdo->prepare("
            INSERT INTO eleve_allergies (eleve_id, allergene, severity)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE severity = VALUES(severity)
        ")->execute([$eleveId, $allergene, $severity]);
    }

    /**
     * Frequentation forecast based on historical data.
     */
    public function getFrequentationPrevision(string $date): array
    {
        $dow = date('N', strtotime($date));
        // Average for same day of week over last 4 weeks
        $stmt = $this->pdo->prepare("
            SELECT AVG(cnt) AS prevision FROM (
                SELECT COUNT(*) AS cnt FROM cantine_reservations
                WHERE DAYOFWEEK(date_repas) = DAYOFWEEK(?) AND date_repas >= DATE_SUB(?, INTERVAL 28 DAY)
                  AND date_repas < ? AND statut != 'annule'
                GROUP BY date_repas
            ) sub
        ");
        $stmt->execute([$date, $date, $date]);
        $avg = $stmt->fetchColumn();

        // Current reservations
        $stmt2 = $this->pdo->prepare("SELECT COUNT(*) FROM cantine_reservations WHERE date_repas = ? AND statut != 'annule'");
        $stmt2->execute([$date]);
        $actual = (int)$stmt2->fetchColumn();

        return [
            'date' => $date,
            'prevision' => $avg ? round($avg) : null,
            'reservations_actuelles' => $actual,
        ];
    }

    public static function allergenesStandard(): array
    {
        return [
            'gluten' => 'Gluten', 'crustaces' => 'Crustacés', 'oeufs' => 'Œufs',
            'poisson' => 'Poisson', 'arachides' => 'Arachides', 'soja' => 'Soja',
            'lait' => 'Lait', 'fruits_coques' => 'Fruits à coque', 'celeri' => 'Céleri',
            'moutarde' => 'Moutarde', 'sesame' => 'Sésame', 'sulfites' => 'Sulfites',
            'lupin' => 'Lupin', 'mollusques' => 'Mollusques',
        ];
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

    // ─── INFO NUTRITIONNELLE ───

    public function setNutrition(int $menuId, array $nutrition): void
    {
        $json = json_encode($nutrition, JSON_UNESCAPED_UNICODE);
        $this->pdo->prepare("UPDATE menus_cantine SET nutrition = :n WHERE id = :id")->execute([':n' => $json, ':id' => $menuId]);
    }

    public function getNutrition(int $menuId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT nutrition FROM menus_cantine WHERE id = ?");
        $stmt->execute([$menuId]);
        $json = $stmt->fetchColumn();
        return $json ? json_decode($json, true) : null;
    }

    // ─── ENQUÊTE SATISFACTION ───

    public function evaluerMenu(int $menuId, int $eleveId, int $note, ?string $commentaire = null): void
    {
        $this->pdo->prepare("
            INSERT INTO cantine_evaluations (menu_id, eleve_id, note, commentaire, created_at)
            VALUES (:m, :e, :n, :c, NOW())
            ON DUPLICATE KEY UPDATE note = VALUES(note), commentaire = VALUES(commentaire)
        ")->execute([':m' => $menuId, ':e' => $eleveId, ':n' => $note, ':c' => $commentaire]);
    }

    public function getEvaluationsMenu(int $menuId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT ce.*, CONCAT(e.prenom, ' ', e.nom) AS eleve_nom
            FROM cantine_evaluations ce
            JOIN eleves e ON ce.eleve_id = e.id
            WHERE ce.menu_id = :m ORDER BY ce.created_at DESC
        ");
        $stmt->execute([':m' => $menuId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getNoteMoyenneMenu(int $menuId): ?float
    {
        $stmt = $this->pdo->prepare("SELECT AVG(note) FROM cantine_evaluations WHERE menu_id = ?");
        $stmt->execute([$menuId]);
        $avg = $stmt->fetchColumn();
        return $avg !== false ? round((float)$avg, 1) : null;
    }

    // ─── SUIVI GASPILLAGE ───

    public function enregistrerGaspillage(string $date, float $quantiteKg, string $type = 'preparation', ?string $commentaire = null): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO cantine_gaspillage (date_mesure, quantite_kg, type, commentaire, created_at) VALUES (:d, :q, :t, :c, NOW())");
        $stmt->execute([':d' => $date, ':q' => $quantiteKg, ':t' => $type, ':c' => $commentaire]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getGaspillage(string $dateDebut, string $dateFin): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM cantine_gaspillage WHERE date_mesure BETWEEN :d AND :f ORDER BY date_mesure DESC");
        $stmt->execute([':d' => $dateDebut, ':f' => $dateFin]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getStatsGaspillage(string $dateDebut, string $dateFin): array
    {
        $stmt = $this->pdo->prepare("
            SELECT SUM(quantite_kg) AS total_kg, AVG(quantite_kg) AS moyenne_kg,
                   COUNT(*) AS nb_mesures, type
            FROM cantine_gaspillage WHERE date_mesure BETWEEN :d AND :f
            GROUP BY type
        ");
        $stmt->execute([':d' => $dateDebut, ':f' => $dateFin]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ─── PRÉ-COMMANDE ───

    public function preCommanderMenu(int $reservationId, string $choixMenu): void
    {
        $this->pdo->prepare("UPDATE cantine_reservations SET choix_menu = :c WHERE id = :id")
            ->execute([':c' => $choixMenu, ':id' => $reservationId]);
    }

    public function getPreCommandesJour(string $date): array
    {
        $stmt = $this->pdo->prepare("
            SELECT choix_menu, COUNT(*) AS nb
            FROM cantine_reservations
            WHERE date_repas = :d AND statut != 'annule' AND choix_menu IS NOT NULL
            GROUP BY choix_menu
        ");
        $stmt->execute([':d' => $date]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
