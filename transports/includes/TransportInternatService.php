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
}
