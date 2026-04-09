<?php
/**
 * M32 – Transports & Internat — Service
 */
class TransportInternatService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /* ───── LIGNES TRANSPORT ───── */

    public function getLignes(?string $type = null): array
    {
        $sql = "SELECT lt.*, (SELECT COUNT(*) FROM inscriptions_transport it WHERE it.ligne_id = lt.id) AS nb_inscrits FROM lignes_transport lt WHERE 1=1";
        $params = [];
        if ($type) { $sql .= ' AND lt.type = ?'; $params[] = $type; }
        $sql .= ' ORDER BY lt.nom';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLigne(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM lignes_transport WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function creerLigne(array $d): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO lignes_transport (nom, type, itineraire, horaires, capacite) VALUES (?,?,?,?,?)");
        $stmt->execute([$d['nom'], $d['type'], $d['itineraire'] ?? null, $d['horaires'] ?? null, $d['capacite'] ?? null]);
        return $this->pdo->lastInsertId();
    }

    /* ───── INSCRIPTIONS TRANSPORT ───── */

    public function inscrireTransport(int $ligneId, int $eleveId, ?string $arret): void
    {
        $stmt = $this->pdo->prepare("INSERT IGNORE INTO inscriptions_transport (ligne_id, eleve_id, arret) VALUES (?,?,?)");
        $stmt->execute([$ligneId, $eleveId, $arret]);
    }

    public function getInscritsLigne(int $ligneId): array
    {
        $stmt = $this->pdo->prepare("SELECT it.*, CONCAT(e.prenom, ' ', e.nom) AS eleve_nom, cl.nom AS classe_nom FROM inscriptions_transport it JOIN eleves e ON it.eleve_id = e.id LEFT JOIN classes cl ON e.classe_id = cl.id WHERE it.ligne_id = ? ORDER BY e.nom");
        $stmt->execute([$ligneId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getInscriptionsEleve(int $eleveId): array
    {
        $stmt = $this->pdo->prepare("SELECT it.*, lt.nom AS ligne_nom, lt.type FROM inscriptions_transport it JOIN lignes_transport lt ON it.ligne_id = lt.id WHERE it.eleve_id = ?");
        $stmt->execute([$eleveId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ───── INTERNAT ───── */

    public function getChambres(?string $batiment = null): array
    {
        $sql = "SELECT ic.*, (SELECT COUNT(*) FROM internat_affectations ia WHERE ia.chambre_id = ic.id AND ia.annee_scolaire = ?) AS nb_occupants FROM internat_chambres ic WHERE 1=1";
        $params = [date('Y') . '-' . (date('Y') + 1)];
        if ($batiment) { $sql .= ' AND ic.batiment = ?'; $params[] = $batiment; }
        $sql .= ' ORDER BY ic.batiment, ic.etage, ic.numero';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getChambre(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM internat_chambres WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function creerChambre(array $d): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO internat_chambres (numero, batiment, etage, capacite, type) VALUES (?,?,?,?,?)");
        $stmt->execute([$d['numero'], $d['batiment'] ?? null, $d['etage'] ?? null, $d['capacite'] ?? 1, $d['type'] ?? 'simple']);
        return $this->pdo->lastInsertId();
    }

    public function getOccupants(int $chambreId): array
    {
        $stmt = $this->pdo->prepare("SELECT ia.*, CONCAT(e.prenom, ' ', e.nom) AS eleve_nom, cl.nom AS classe_nom FROM internat_affectations ia JOIN eleves e ON ia.eleve_id = e.id LEFT JOIN classes cl ON e.classe_id = cl.id WHERE ia.chambre_id = ? AND ia.annee_scolaire = ? ORDER BY e.nom");
        $stmt->execute([$chambreId, date('Y') . '-' . (date('Y') + 1)]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function affecterChambre(int $chambreId, int $eleveId): void
    {
        $chambre = $this->getChambre($chambreId);
        $occupants = $this->getOccupants($chambreId);
        if ($chambre && count($occupants) >= $chambre['capacite']) {
            throw new RuntimeException('Chambre pleine.');
        }
        $stmt = $this->pdo->prepare("INSERT INTO internat_affectations (chambre_id, eleve_id, annee_scolaire) VALUES (?,?,?)");
        $stmt->execute([$chambreId, $eleveId, date('Y') . '-' . (date('Y') + 1)]);
    }

    /* ───── TRANSPORT DELAYS ───── */

    /**
     * Signal a bus delay and notify affected parents.
     */
    public function signalerRetard(int $ligneId, string $motif, int $minutesRetard): void
    {
        $this->pdo->prepare("UPDATE lignes_transport SET retard_signale_at = NOW(), retard_motif = ? WHERE id = ?")
                   ->execute([$motif, $ligneId]);

        // Notify parents of students on this line
        $inscrits = $this->getInscritsLigne($ligneId);
        $ligne = $this->getLigne($ligneId);

        try {
            require_once __DIR__ . '/../../notifications/includes/NotificationService.php';
            $notif = new \NotificationService($this->pdo);
            $titre = "Retard transport : {$ligne['nom']}";
            $message = "La ligne {$ligne['nom']} a un retard estimé de {$minutesRetard} min. Motif : {$motif}";

            foreach ($inscrits as $i) {
                // Get parent of student
                $parents = $this->pdo->prepare("SELECT parent_id FROM parent_eleve WHERE eleve_id = ?");
                $parents->execute([$i['eleve_id']]);
                while ($pid = $parents->fetchColumn()) {
                    $notif->creer((int)$pid, 'parent', 'transport', $titre, $message, '/transports/lignes.php', 'haute');
                }
            }
        } catch (\Exception $e) {}
    }

    /* ───── INTERNAT ATTENDANCE ───── */

    /**
     * Record evening/morning attendance for internat.
     */
    public function pointerPresenceInternat(int $affectationId, string $type): void
    {
        $col = ($type === 'matin') ? 'presence_matin' : 'presence_soir';
        $this->pdo->prepare("UPDATE internat_affectations SET {$col} = NOW() WHERE id = ?")
                   ->execute([$affectationId]);
    }

    /**
     * Get internat attendance for a date.
     */
    public function getPresenceInternat(string $date): array
    {
        $stmt = $this->pdo->prepare("
            SELECT ia.*, ic.numero AS chambre_numero, ic.batiment,
                   CONCAT(e.prenom, ' ', e.nom) AS eleve_nom, cl.nom AS classe_nom
            FROM internat_affectations ia
            JOIN internat_chambres ic ON ia.chambre_id = ic.id
            JOIN eleves e ON ia.eleve_id = e.id
            LEFT JOIN classes cl ON e.classe_id = cl.id
            WHERE ia.annee_scolaire = ?
            ORDER BY ic.batiment, ic.numero, e.nom
        ");
        $stmt->execute([date('Y') . '-' . (date('Y') + 1)]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /* ───── HELPERS ───── */

    public function getEleves(): array
    {
        return $this->pdo->query("SELECT e.id, e.prenom, e.nom, cl.nom AS classe_nom FROM eleves e LEFT JOIN classes cl ON e.classe_id = cl.id ORDER BY e.nom")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getBatiments(): array
    {
        return $this->pdo->query("SELECT DISTINCT batiment FROM internat_chambres WHERE batiment IS NOT NULL ORDER BY batiment")->fetchAll(PDO::FETCH_COLUMN);
    }

    public static function typesTransport(): array
    {
        return ['bus' => 'Bus', 'navette' => 'Navette', 'train' => 'Train', 'autre' => 'Autre'];
    }

    public static function typesChambre(): array
    {
        return ['simple' => 'Simple', 'double' => 'Double', 'triple' => 'Triple', 'dortoir' => 'Dortoir'];
    }

    /* ───── EXPORT ───── */

    public function getLignesForExport(?string $type = null): array
    {
        $lignes = $this->getLignes($type);
        $types = self::typesTransport();
        return array_map(fn($l) => [
            $l['nom'],
            $types[$l['type']] ?? $l['type'],
            $l['itineraire'] ?? '-',
            $l['horaires'] ?? '-',
            $l['capacite'] ?? '-',
            $l['nb_inscrits'] ?? 0,
        ], $lignes);
    }

    public function getInscritsForExport(int $ligneId): array
    {
        $inscrits = $this->getInscritsLigne($ligneId);
        $ligne = $this->getLigne($ligneId);
        return array_map(fn($i) => [
            $ligne['nom'] ?? '',
            $i['eleve_nom'],
            $i['classe_nom'] ?? '-',
            $i['arret'] ?? '-',
        ], $inscrits);
    }

    // ─── CARTE ARRÊTS GPS ───

    public function getArrets(int $ligneId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM transport_arrets WHERE ligne_id = :l ORDER BY ordre");
        $stmt->execute([':l' => $ligneId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function ajouterArret(int $ligneId, string $nom, ?string $adresse = null, ?float $latitude = null, ?float $longitude = null, int $ordre = 0): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO transport_arrets (ligne_id, nom, adresse, latitude, longitude, ordre) VALUES (:l, :n, :a, :lat, :lng, :o)");
        $stmt->execute([':l' => $ligneId, ':n' => $nom, ':a' => $adresse, ':lat' => $latitude, ':lng' => $longitude, ':o' => $ordre]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getArretsGeoJson(int $ligneId): array
    {
        $arrets = $this->getArrets($ligneId);
        $features = [];
        foreach ($arrets as $a) {
            if ($a['latitude'] && $a['longitude']) {
                $features[] = [
                    'type' => 'Feature',
                    'geometry' => ['type' => 'Point', 'coordinates' => [(float)$a['longitude'], (float)$a['latitude']]],
                    'properties' => ['id' => $a['id'], 'nom' => $a['nom'], 'adresse' => $a['adresse'] ?? '', 'ordre' => $a['ordre']],
                ];
            }
        }
        return ['type' => 'FeatureCollection', 'features' => $features];
    }

    // ─── PRÉSENCE BUS ───

    public function enregistrerPresenceBus(int $inscriptionId, string $date, bool $present): void
    {
        $this->pdo->prepare("
            INSERT INTO transport_presences (inscription_id, date_trajet, present) VALUES (:i, :d, :p)
            ON DUPLICATE KEY UPDATE present = VALUES(present)
        ")->execute([':i' => $inscriptionId, ':d' => $date, ':p' => $present ? 1 : 0]);
    }

    public function getPresencesBus(int $ligneId, string $date): array
    {
        $stmt = $this->pdo->prepare("
            SELECT it.id AS inscription_id, CONCAT(e.prenom, ' ', e.nom) AS eleve_nom,
                   tp.present, tp.date_trajet
            FROM inscriptions_transport it
            JOIN eleves e ON it.eleve_id = e.id
            LEFT JOIN transport_presences tp ON tp.inscription_id = it.id AND tp.date_trajet = :d
            WHERE it.ligne_id = :l
            ORDER BY e.nom
        ");
        $stmt->execute([':l' => $ligneId, ':d' => $date]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ─── AUTORISATIONS RÉCUPÉRATION ───

    public function ajouterAutorisationRecuperation(int $eleveId, string $personne, string $lien, string $telephone, ?string $photoPath = null): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO transport_autorisations_sortie (eleve_id, personne, lien, telephone, photo, created_at)
            VALUES (:e, :p, :l, :t, :ph, NOW())
        ");
        $stmt->execute([':e' => $eleveId, ':p' => $personne, ':l' => $lien, ':t' => $telephone, ':ph' => $photoPath]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getAutorisationsRecuperation(int $eleveId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM transport_autorisations_sortie WHERE eleve_id = :e ORDER BY personne");
        $stmt->execute([':e' => $eleveId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
