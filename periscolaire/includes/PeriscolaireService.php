<?php
/**
 * M16 – Périscolaire / Cantine — Service
 */
class PeriscolaireService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /* ───── SERVICES ───── */

    public function getServices(?string $type = null): array
    {
        $sql = "SELECT sp.*, (SELECT COUNT(*) FROM inscriptions_periscolaire ip WHERE ip.service_id = sp.id AND ip.statut = 'active') AS nb_inscrits FROM services_periscolaires sp WHERE 1=1";
        $params = [];
        if ($type) { $sql .= ' AND sp.type = ?'; $params[] = $type; }
        $sql .= ' ORDER BY sp.nom';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getService(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM services_periscolaires WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function creerService(array $d): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO services_periscolaires (nom, type, description, tarif, places_max, horaires) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$d['nom'], $d['type'], $d['description'] ?? null, $d['tarif'] ?? 0, $d['places_max'] ?? null, $d['horaires'] ?? null]);
        return $this->pdo->lastInsertId();
    }

    /* ───── INSCRIPTIONS ───── */

    public function inscrire(int $serviceId, int $eleveId, string $jour): void
    {
        // Vérifier places
        $service = $this->getService($serviceId);
        if ($service && $service['places_max']) {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM inscriptions_periscolaire WHERE service_id = ? AND statut = 'active'");
            $stmt->execute([$serviceId]);
            if ($stmt->fetchColumn() >= $service['places_max']) {
                throw new RuntimeException('Plus de places disponibles.');
            }
        }
        $stmt = $this->pdo->prepare("INSERT INTO inscriptions_periscolaire (service_id, eleve_id, jour, date_debut, statut) VALUES (?,?,?,CURDATE(),?)");
        $stmt->execute([$serviceId, $eleveId, $jour, 'active']);
    }

    public function desinscrire(int $inscriptionId): void
    {
        $this->pdo->prepare("UPDATE inscriptions_periscolaire SET statut = 'annulee', date_fin = CURDATE() WHERE id = ?")->execute([$inscriptionId]);
    }

    public function getInscriptions(int $serviceId): array
    {
        $stmt = $this->pdo->prepare("SELECT ip.*, CONCAT(e.prenom, ' ', e.nom) AS eleve_nom, cl.nom AS classe_nom FROM inscriptions_periscolaire ip JOIN eleves e ON ip.eleve_id = e.id LEFT JOIN classes cl ON e.classe_id = cl.id WHERE ip.service_id = ? AND ip.statut = 'active' ORDER BY e.nom");
        $stmt->execute([$serviceId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getInscriptionsEleve(int $eleveId): array
    {
        $stmt = $this->pdo->prepare("SELECT ip.*, sp.nom AS service_nom, sp.type AS service_type FROM inscriptions_periscolaire ip JOIN services_periscolaires sp ON ip.service_id = sp.id WHERE ip.eleve_id = ? ORDER BY ip.created_at DESC");
        $stmt->execute([$eleveId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getInscriptionsParent(int $parentId): array
    {
        $stmt = $this->pdo->prepare("SELECT ip.*, sp.nom AS service_nom, sp.type AS service_type, CONCAT(e.prenom, ' ', e.nom) AS eleve_nom FROM inscriptions_periscolaire ip JOIN services_periscolaires sp ON ip.service_id = sp.id JOIN eleves e ON ip.eleve_id = e.id JOIN parent_eleve pe ON pe.eleve_id = e.id WHERE pe.parent_id = ? ORDER BY ip.created_at DESC");
        $stmt->execute([$parentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ───── PRÉSENCES ───── */

    public function enregistrerPresence(int $inscriptionId, string $date, bool $present): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO presences_periscolaire (inscription_id, date, present) VALUES (?,?,?) ON DUPLICATE KEY UPDATE present = VALUES(present)");
        $stmt->execute([$inscriptionId, $date, $present ? 1 : 0]);
    }

    public function getPresences(int $serviceId, string $date): array
    {
        $stmt = $this->pdo->prepare("
            SELECT ip.id AS inscription_id, CONCAT(e.prenom, ' ', e.nom) AS eleve_nom, pp.present
            FROM inscriptions_periscolaire ip
            JOIN eleves e ON ip.eleve_id = e.id
            LEFT JOIN presences_periscolaire pp ON pp.inscription_id = ip.id AND pp.date = ?
            WHERE ip.service_id = ? AND ip.statut = 'active'
            ORDER BY e.nom
        ");
        $stmt->execute([$date, $serviceId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ───── WAITLIST ───── */

    /**
     * Add student to waitlist when service is full.
     */
    public function ajouterListeAttente(int $serviceId, int $eleveId, string $jour): int
    {
        $pos = (int)$this->pdo->prepare("SELECT COALESCE(MAX(position), 0) + 1 FROM periscolaire_waitlist WHERE service_id = ? AND jour = ?")
                              ->execute([$serviceId, $jour]) ? $this->pdo->query("SELECT COALESCE(MAX(position), 0) + 1 FROM periscolaire_waitlist WHERE service_id = {$serviceId}")->fetchColumn() : 1;

        $stmt = $this->pdo->prepare("INSERT INTO periscolaire_waitlist (service_id, eleve_id, jour, position, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$serviceId, $eleveId, $jour, $pos]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Get waitlist for a service.
     */
    public function getListeAttente(int $serviceId, ?string $jour = null): array
    {
        $sql = "SELECT pw.*, CONCAT(e.prenom, ' ', e.nom) AS eleve_nom
                FROM periscolaire_waitlist pw
                JOIN eleves e ON pw.eleve_id = e.id
                WHERE pw.service_id = ?";
        $params = [$serviceId];
        if ($jour) { $sql .= ' AND pw.jour = ?'; $params[] = $jour; }
        $sql .= ' ORDER BY pw.position ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Promote next student from waitlist when a spot opens.
     */
    public function promoteFromWaitlist(int $serviceId, string $jour): bool
    {
        $next = $this->pdo->prepare("SELECT * FROM periscolaire_waitlist WHERE service_id = ? AND jour = ? ORDER BY position LIMIT 1");
        $next->execute([$serviceId, $jour]);
        $entry = $next->fetch(PDO::FETCH_ASSOC);
        if (!$entry) return false;

        try {
            $this->inscrire($serviceId, $entry['eleve_id'], $jour);
            $this->pdo->prepare("DELETE FROM periscolaire_waitlist WHERE id = ?")->execute([$entry['id']]);
            return true;
        } catch (\RuntimeException $e) {
            return false;
        }
    }

    /* ───── MENUS CANTINE ───── */

    public function getMenus(string $dateDebut = null, string $dateFin = null): array
    {
        $sql = "SELECT * FROM menus_cantine WHERE 1=1";
        $params = [];
        if ($dateDebut) { $sql .= ' AND date >= ?'; $params[] = $dateDebut; }
        if ($dateFin) { $sql .= ' AND date <= ?'; $params[] = $dateFin; }
        $sql .= ' ORDER BY date';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function creerMenu(array $d): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO menus_cantine (date, entree, plat, accompagnement, dessert, allergenes, regime) VALUES (?,?,?,?,?,?,?)");
        $stmt->execute([$d['date'], $d['entree'] ?? null, $d['plat'] ?? null, $d['accompagnement'] ?? null, $d['dessert'] ?? null, $d['allergenes'] ?? null, $d['regime'] ?? null]);
        return $this->pdo->lastInsertId();
    }

    /* ───── HELPERS ───── */

    public function getEleves(): array
    {
        return $this->pdo->query("SELECT e.id, e.prenom, e.nom, cl.nom AS classe_nom FROM eleves e LEFT JOIN classes cl ON e.classe_id = cl.id ORDER BY e.nom")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getEnfantsParent(int $parentId): array
    {
        $stmt = $this->pdo->prepare("SELECT e.id, e.prenom, e.nom FROM eleves e JOIN parent_eleve pe ON pe.eleve_id = e.id WHERE pe.parent_id = ?");
        $stmt->execute([$parentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function typesService(): array
    {
        return ['cantine' => 'Cantine', 'garderie' => 'Garderie', 'etude' => 'Étude surveillée', 'activite' => 'Activité'];
    }

    public static function jours(): array
    {
        return ['lundi' => 'Lundi', 'mardi' => 'Mardi', 'mercredi' => 'Mercredi', 'jeudi' => 'Jeudi', 'vendredi' => 'Vendredi'];
    }

    public static function iconeType(string $t): string
    {
        $m = ['cantine' => 'utensils', 'garderie' => 'child', 'etude' => 'book-reader', 'activite' => 'running'];
        return $m[$t] ?? 'concierge-bell';
    }

    /* ───── EXPORT ───── */

    public function getInscriptionsForExport(?int $serviceId = null): array
    {
        if ($serviceId) {
            $rows = $this->getInscriptions($serviceId);
        } else {
            $stmt = $this->pdo->query("
                SELECT ip.*, CONCAT(e.prenom, ' ', e.nom) AS eleve_nom, cl.nom AS classe_nom,
                       sp.nom AS service_nom, sp.type AS service_type
                FROM inscriptions_periscolaire ip
                JOIN eleves e ON ip.eleve_id = e.id
                LEFT JOIN classes cl ON e.classe_id = cl.id
                JOIN services_periscolaires sp ON ip.service_id = sp.id
                WHERE ip.statut = 'active'
                ORDER BY sp.nom, e.nom
            ");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        $types = self::typesService();
        return array_map(fn($r) => [
            $r['eleve_nom'],
            $r['classe_nom'] ?? '-',
            $r['service_nom'],
            $types[$r['service_type']] ?? $r['service_type'],
            ucfirst($r['jour'] ?? '-'),
            $r['date_debut'] ?? '-',
            ucfirst($r['statut']),
        ], $rows);
    }

    public function getServicesForExport(?string $type = null): array
    {
        $services = $this->getServices($type);
        $types = self::typesService();
        return array_map(fn($s) => [
            $s['nom'],
            $types[$s['type']] ?? $s['type'],
            $s['description'] ?? '-',
            $s['horaires'] ?? '-',
            number_format($s['tarif'] ?? 0, 2, ',', ' ') . ' €',
            $s['places_max'] ?? 'Illimité',
            $s['nb_inscrits'] ?? 0,
        ], $services);
    }

    // ─── CATALOGUE ILLUSTRÉ ───

    public function getCatalogue(?string $type = null): array
    {
        $services = $this->getServices($type);
        return array_map(fn($s) => [
            'id' => $s['id'],
            'nom' => $s['nom'],
            'type' => $s['type'],
            'type_label' => self::typesService()[$s['type']] ?? $s['type'],
            'icone' => self::iconeType($s['type']),
            'description' => $s['description'] ?? '',
            'horaires' => $s['horaires'] ?? '',
            'tarif' => $s['tarif'] ?? 0,
            'places_max' => $s['places_max'],
            'nb_inscrits' => $s['nb_inscrits'] ?? 0,
            'places_restantes' => ($s['places_max'] ?? 999) - ($s['nb_inscrits'] ?? 0),
            'image' => $s['image'] ?? null,
        ], $services);
    }

    // ─── FACTURATION AUTO ───

    public function genererFacturationMois(string $mois): array
    {
        $stmt = $this->pdo->prepare("
            SELECT ip.eleve_id, CONCAT(e.prenom, ' ', e.nom) AS eleve_nom,
                   sp.nom AS service_nom, sp.tarif,
                   COUNT(pp.id) AS nb_presences
            FROM inscriptions_periscolaire ip
            JOIN eleves e ON ip.eleve_id = e.id
            JOIN services_periscolaires sp ON ip.service_id = sp.id
            LEFT JOIN presences_periscolaire pp ON pp.inscription_id = ip.id
                  AND pp.present = 1
                  AND DATE_FORMAT(pp.date, '%Y-%m') = :m
            WHERE ip.statut = 'active'
            GROUP BY ip.eleve_id, sp.id
            HAVING nb_presences > 0
            ORDER BY e.nom
        ");
        $stmt->execute([':m' => $mois]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $factures = [];
        foreach ($rows as $r) {
            $montant = ((float)$r['tarif']) * (int)$r['nb_presences'];
            $factures[] = [
                'eleve_id' => $r['eleve_id'],
                'eleve_nom' => $r['eleve_nom'],
                'service' => $r['service_nom'],
                'tarif_unitaire' => (float)$r['tarif'],
                'nb_presences' => (int)$r['nb_presences'],
                'montant_total' => round($montant, 2),
            ];
        }
        return $factures;
    }

    // ─── RAPPORT MENSUEL ───

    public function getRapportMensuel(string $mois): array
    {
        $stmt = $this->pdo->prepare("
            SELECT sp.nom AS service, sp.type,
                   COUNT(DISTINCT ip.eleve_id) AS nb_inscrits,
                   COUNT(pp.id) AS nb_presences_total,
                   SUM(pp.present) AS nb_presents,
                   COUNT(pp.id) - COALESCE(SUM(pp.present), 0) AS nb_absents
            FROM services_periscolaires sp
            LEFT JOIN inscriptions_periscolaire ip ON ip.service_id = sp.id AND ip.statut = 'active'
            LEFT JOIN presences_periscolaire pp ON pp.inscription_id = ip.id AND DATE_FORMAT(pp.date, '%Y-%m') = :m
            GROUP BY sp.id
            ORDER BY sp.nom
        ");
        $stmt->execute([':m' => $mois]);
        $services = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $totalInscrits = array_sum(array_column($services, 'nb_inscrits'));
        $totalPresences = array_sum(array_column($services, 'nb_presents'));

        return [
            'mois' => $mois,
            'services' => $services,
            'total_inscrits' => $totalInscrits,
            'total_presences' => $totalPresences,
            'taux_presence_global' => $totalPresences > 0 && array_sum(array_column($services, 'nb_presences_total')) > 0
                ? round($totalPresences / array_sum(array_column($services, 'nb_presences_total')) * 100, 1) : 0,
        ];
    }
}
