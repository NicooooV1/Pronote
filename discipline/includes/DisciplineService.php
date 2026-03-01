<?php
/**
 * DisciplineService — Service métier pour le module Discipline / Sanctions (M06).
 *
 * Gère les incidents, sanctions, retenues et les statistiques disciplinaires.
 */
class DisciplineService
{
    protected $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ─── Incidents ───────────────────────────────────────────────

    public function createIncident(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO incidents (eleve_id, date_incident, lieu, type_incident, gravite,
                description, temoins, signale_par_id, signale_par_type, classe_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['eleve_id'], $data['date_incident'], $data['lieu'] ?? null,
            $data['type_incident'], $data['gravite'] ?? 'moyen',
            $data['description'], $data['temoins'] ?? null,
            $data['signale_par_id'], $data['signale_par_type'],
            $data['classe_id'] ?? null
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getIncident(int $id): ?array
    {
        $sql = "SELECT i.*,
                       e.nom AS eleve_nom, e.prenom AS eleve_prenom, e.classe AS eleve_classe,
                       cl.nom AS classe_nom
                FROM incidents i
                JOIN eleves e ON i.eleve_id = e.id
                LEFT JOIN classes cl ON i.classe_id = cl.id
                WHERE i.id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function updateIncident(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE incidents SET type_incident = ?, gravite = ?, description = ?,
                lieu = ?, temoins = ?, statut = ?
             WHERE id = ?"
        );
        return $stmt->execute([
            $data['type_incident'], $data['gravite'], $data['description'],
            $data['lieu'] ?? null, $data['temoins'] ?? null,
            $data['statut'] ?? 'signale', $id
        ]);
    }

    public function getIncidents(array $filters = []): array
    {
        $sql = "SELECT i.*,
                       e.nom AS eleve_nom, e.prenom AS eleve_prenom, e.classe AS eleve_classe
                FROM incidents i
                JOIN eleves e ON i.eleve_id = e.id
                WHERE 1=1";
        $params = [];

        if (!empty($filters['eleve_id'])) {
            $sql .= " AND i.eleve_id = ?";
            $params[] = $filters['eleve_id'];
        }
        if (!empty($filters['statut'])) {
            $sql .= " AND i.statut = ?";
            $params[] = $filters['statut'];
        }
        if (!empty($filters['gravite'])) {
            $sql .= " AND i.gravite = ?";
            $params[] = $filters['gravite'];
        }
        if (!empty($filters['date_debut'])) {
            $sql .= " AND i.date_incident >= ?";
            $params[] = $filters['date_debut'];
        }
        if (!empty($filters['date_fin'])) {
            $sql .= " AND i.date_incident <= ?";
            $params[] = $filters['date_fin'] . ' 23:59:59';
        }
        if (!empty($filters['classe'])) {
            $sql .= " AND e.classe = ?";
            $params[] = $filters['classe'];
        }

        $sql .= " ORDER BY i.date_incident DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── Sanctions ───────────────────────────────────────────────

    public function createSanction(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO sanctions (incident_id, eleve_id, type_sanction, motif, date_sanction,
                date_debut, date_fin, duree, lieu_retenue, convocation_parent,
                decide_par_id, decide_par_type, commentaire)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['incident_id'] ?? null, $data['eleve_id'],
            $data['type_sanction'], $data['motif'], $data['date_sanction'],
            $data['date_debut'] ?? null, $data['date_fin'] ?? null,
            $data['duree'] ?? null, $data['lieu_retenue'] ?? null,
            $data['convocation_parent'] ?? 0,
            $data['decide_par_id'], $data['decide_par_type'],
            $data['commentaire'] ?? null
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getSanction(int $id): ?array
    {
        $sql = "SELECT s.*,
                       e.nom AS eleve_nom, e.prenom AS eleve_prenom, e.classe AS eleve_classe
                FROM sanctions s
                JOIN eleves e ON s.eleve_id = e.id
                WHERE s.id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getSanctions(array $filters = []): array
    {
        $sql = "SELECT s.*,
                       e.nom AS eleve_nom, e.prenom AS eleve_prenom, e.classe AS eleve_classe,
                       i.type_incident
                FROM sanctions s
                JOIN eleves e ON s.eleve_id = e.id
                LEFT JOIN incidents i ON s.incident_id = i.id
                WHERE 1=1";
        $params = [];

        if (!empty($filters['eleve_id'])) {
            $sql .= " AND s.eleve_id = ?";
            $params[] = $filters['eleve_id'];
        }
        if (!empty($filters['type_sanction'])) {
            $sql .= " AND s.type_sanction = ?";
            $params[] = $filters['type_sanction'];
        }
        if (!empty($filters['classe'])) {
            $sql .= " AND e.classe = ?";
            $params[] = $filters['classe'];
        }

        $sql .= " ORDER BY s.date_sanction DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSanctionsEleve(int $eleveId): array
    {
        return $this->getSanctions(['eleve_id' => $eleveId]);
    }

    // ─── Fiche discipline élève ──────────────────────────────────

    public function getFicheEleve(int $eleveId): array
    {
        return [
            'incidents' => $this->getIncidents(['eleve_id' => $eleveId]),
            'sanctions' => $this->getSanctionsEleve($eleveId),
            'retenues'  => $this->getRetenuesEleve($eleveId),
        ];
    }

    // ─── Retenues ────────────────────────────────────────────────

    public function createRetenue(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO retenues (date_retenue, heure_debut, heure_fin, lieu,
                surveillant_id, surveillant_type, capacite_max, commentaire)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['date_retenue'], $data['heure_debut'], $data['heure_fin'],
            $data['lieu'] ?? null, $data['surveillant_id'] ?? null,
            $data['surveillant_type'] ?? null, $data['capacite_max'] ?? 30,
            $data['commentaire'] ?? null
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function affecterEleveRetenue(int $retenueId, int $eleveId, ?int $sanctionId = null): bool
    {
        $stmt = $this->pdo->prepare(
            "INSERT IGNORE INTO retenue_eleves (retenue_id, eleve_id, sanction_id)
             VALUES (?, ?, ?)"
        );
        return $stmt->execute([$retenueId, $eleveId, $sanctionId]);
    }

    public function getRetenues(array $filters = []): array
    {
        $sql = "SELECT r.*,
                       (SELECT COUNT(*) FROM retenue_eleves re WHERE re.retenue_id = r.id) AS nb_eleves
                FROM retenues r WHERE 1=1";
        $params = [];

        if (!empty($filters['date_debut'])) {
            $sql .= " AND r.date_retenue >= ?";
            $params[] = $filters['date_debut'];
        }
        if (!empty($filters['date_fin'])) {
            $sql .= " AND r.date_retenue <= ?";
            $params[] = $filters['date_fin'];
        }

        $sql .= " ORDER BY r.date_retenue DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRetenuesEleve(int $eleveId): array
    {
        $sql = "SELECT r.*, re.present, re.commentaire AS commentaire_eleve
                FROM retenue_eleves re
                JOIN retenues r ON re.retenue_id = r.id
                WHERE re.eleve_id = ?
                ORDER BY r.date_retenue DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$eleveId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── Statistiques ────────────────────────────────────────────

    public function getStats(?string $dateDebut = null, ?string $dateFin = null): array
    {
        $dateDebut = $dateDebut ?: date('Y-01-01');
        $dateFin   = $dateFin ?: date('Y-12-31');

        $stats = [];

        // Par type d'incident
        $stmt = $this->pdo->prepare(
            "SELECT type_incident, COUNT(*) AS nb
             FROM incidents WHERE date_incident BETWEEN ? AND ?
             GROUP BY type_incident ORDER BY nb DESC"
        );
        $stmt->execute([$dateDebut, $dateFin . ' 23:59:59']);
        $stats['par_type'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Par gravité
        $stmt = $this->pdo->prepare(
            "SELECT gravite, COUNT(*) AS nb
             FROM incidents WHERE date_incident BETWEEN ? AND ?
             GROUP BY gravite"
        );
        $stmt->execute([$dateDebut, $dateFin . ' 23:59:59']);
        $stats['par_gravite'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Par mois
        $stmt = $this->pdo->prepare(
            "SELECT DATE_FORMAT(date_incident, '%Y-%m') AS mois, COUNT(*) AS nb
             FROM incidents WHERE date_incident BETWEEN ? AND ?
             GROUP BY mois ORDER BY mois"
        );
        $stmt->execute([$dateDebut, $dateFin . ' 23:59:59']);
        $stats['par_mois'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Top élèves
        $stmt = $this->pdo->prepare(
            "SELECT e.nom, e.prenom, e.classe, COUNT(*) AS nb
             FROM incidents i JOIN eleves e ON i.eleve_id = e.id
             WHERE date_incident BETWEEN ? AND ?
             GROUP BY i.eleve_id ORDER BY nb DESC LIMIT 10"
        );
        $stmt->execute([$dateDebut, $dateFin . ' 23:59:59']);
        $stats['top_eleves'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Totaux
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM incidents WHERE date_incident BETWEEN ? AND ?");
        $stmt->execute([$dateDebut, $dateFin . ' 23:59:59']);
        $stats['total_incidents'] = (int)$stmt->fetchColumn();

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM sanctions WHERE date_sanction BETWEEN ? AND ?");
        $stmt->execute([$dateDebut, $dateFin]);
        $stats['total_sanctions'] = (int)$stmt->fetchColumn();

        return $stats;
    }

    // ─── Labels ──────────────────────────────────────────────────

    public static function getTypesIncident(): array
    {
        return [
            'violence'       => 'Violence',
            'insolence'      => 'Insolence',
            'fraude'         => 'Fraude / Triche',
            'retard_repete'  => 'Retards répétés',
            'degradation'    => 'Dégradation de matériel',
            'harcelement'    => 'Harcèlement',
            'non_respect'    => 'Non respect du règlement',
            'autre'          => 'Autre',
        ];
    }

    public static function getTypesSanction(): array
    {
        return [
            'avertissement'        => 'Avertissement',
            'blame'                => 'Blâme',
            'exclusion_cours'      => 'Exclusion de cours',
            'exclusion_temporaire' => 'Exclusion temporaire',
            'retenue'              => 'Retenue',
            'travail_interet'      => 'Travail d\'intérêt général',
            'autre'                => 'Autre',
        ];
    }

    public static function getGravites(): array
    {
        return [
            'mineur'     => 'Mineur',
            'moyen'      => 'Moyen',
            'grave'      => 'Grave',
            'tres_grave' => 'Très grave',
        ];
    }

    // ─── Helpers ─────────────────────────────────────────────────

    public function getClasses(): array
    {
        return $this->pdo->query("SELECT * FROM classes WHERE actif = 1 ORDER BY niveau, nom")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Recherche un élève par nom/prénom.
     */
    public function rechercherEleves(string $query): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, nom, prenom, classe FROM eleves
             WHERE actif = 1 AND (nom LIKE ? OR prenom LIKE ?)
             ORDER BY nom, prenom LIMIT 20"
        );
        $q = '%' . $query . '%';
        $stmt->execute([$q, $q]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
