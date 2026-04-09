<?php
/**
 * M28 – Orientation — Service
 */
class OrientationService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /* ───────── FICHES ───────── */

    public function creerFiche(int $eleveId, array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO orientation_fiches (eleve_id, annee_scolaire, projet_professionnel, centres_interet, competences_cles, avis_pp, avis_conseil, statut, date_creation)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'brouillon', NOW())
        ");
        $stmt->execute([
            $eleveId,
            $data['annee_scolaire'],
            $data['projet_professionnel'] ?? null,
            $data['centres_interet'] ?? null,
            $data['competences_cles'] ?? null,
            $data['avis_pp'] ?? null,
            $data['avis_conseil'] ?? null,
        ]);
        return $this->pdo->lastInsertId();
    }

    public function modifierFiche(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE orientation_fiches
            SET projet_professionnel = ?, centres_interet = ?, competences_cles = ?,
                avis_pp = ?, avis_conseil = ?, statut = ?, date_modification = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $data['projet_professionnel'] ?? null,
            $data['centres_interet'] ?? null,
            $data['competences_cles'] ?? null,
            $data['avis_pp'] ?? null,
            $data['avis_conseil'] ?? null,
            $data['statut'] ?? 'brouillon',
            $id
        ]);
    }

    public function getFiche(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT f.*, e.prenom, e.nom AS eleve_nom, c.nom AS classe_nom
            FROM orientation_fiches f
            JOIN eleves e ON f.eleve_id = e.id
            LEFT JOIN classes c ON e.classe_id = c.id
            WHERE f.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getFicheEleve(int $eleveId, string $annee = null): ?array
    {
        $sql = "SELECT f.*, e.prenom, e.nom AS eleve_nom FROM orientation_fiches f JOIN eleves e ON f.eleve_id = e.id WHERE f.eleve_id = ?";
        $params = [$eleveId];
        if ($annee) { $sql .= ' AND f.annee_scolaire = ?'; $params[] = $annee; }
        $sql .= ' ORDER BY f.date_creation DESC LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getFiches(array $filters = []): array
    {
        $sql = "
            SELECT f.*, e.prenom, e.nom AS eleve_nom, c.nom AS classe_nom
            FROM orientation_fiches f
            JOIN eleves e ON f.eleve_id = e.id
            LEFT JOIN classes c ON e.classe_id = c.id
            WHERE 1=1
        ";
        $params = [];
        if (!empty($filters['classe_id'])) {
            $sql .= ' AND e.classe_id = ?';
            $params[] = $filters['classe_id'];
        }
        if (!empty($filters['statut'])) {
            $sql .= ' AND f.statut = ?';
            $params[] = $filters['statut'];
        }
        $sql .= ' ORDER BY e.nom, e.prenom';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ───────── VOEUX ───────── */

    public function getVoeux(int $ficheId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM orientation_voeux WHERE fiche_id = ? ORDER BY rang');
        $stmt->execute([$ficheId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function sauvegarderVoeux(int $ficheId, array $voeux): void
    {
        $this->pdo->prepare('DELETE FROM orientation_voeux WHERE fiche_id = ?')->execute([$ficheId]);

        $stmt = $this->pdo->prepare("
            INSERT INTO orientation_voeux (fiche_id, rang, formation, etablissement_vise, motivation, avis_pp, avis_conseil)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($voeux as $i => $v) {
            if (empty(trim($v['formation'] ?? ''))) continue;
            $stmt->execute([
                $ficheId,
                $i + 1,
                $v['formation'],
                $v['etablissement_vise'] ?? null,
                $v['motivation'] ?? null,
                $v['avis_pp'] ?? null,
                $v['avis_conseil'] ?? null,
            ]);
        }
    }

    /* ───────── HELPERS ───────── */

    public function getClasses(): array
    {
        $stmt = $this->pdo->query('SELECT id, nom FROM classes ORDER BY nom');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getElevesClasse(int $classeId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, prenom, nom FROM eleves WHERE classe_id = ? ORDER BY nom, prenom');
        $stmt->execute([$classeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Enfants du parent connecté
     */
    public function getEnfantsParent(int $parentId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT e.id, e.prenom, e.nom, c.nom AS classe_nom
            FROM parent_eleve pe
            JOIN eleves e ON pe.eleve_id = e.id
            LEFT JOIN classes c ON e.classe_id = c.id
            WHERE pe.parent_id = ?
        ");
        $stmt->execute([$parentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getStats(): array
    {
        $stmt = $this->pdo->query("
            SELECT
                COUNT(*) AS total,
                COUNT(CASE WHEN statut = 'brouillon' THEN 1 END) AS brouillons,
                COUNT(CASE WHEN statut = 'soumise' THEN 1 END) AS soumises,
                COUNT(CASE WHEN statut = 'validee' THEN 1 END) AS validees
            FROM orientation_fiches
        ");
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function statutBadge(string $statut): string
    {
        $map = [
            'brouillon' => '<span class="badge badge-secondary">Brouillon</span>',
            'soumise' => '<span class="badge badge-info">Soumise</span>',
            'validee' => '<span class="badge badge-success">Validée</span>',
            'refusee' => '<span class="badge badge-danger">Refusée</span>',
        ];
        return $map[$statut] ?? '<span class="badge">' . $statut . '</span>';
    }

    /* ───────── COUNSELOR BOOKING ───────── */

    /**
     * Book a counselor appointment for a student.
     */
    public function prendreRdvConseiller(int $eleveId, string $date, string $heure, ?string $motif = null): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO orientation_rdv (eleve_id, date_rdv, heure_rdv, motif, statut)
            VALUES (?, ?, ?, ?, 'planifie')
        ");
        $stmt->execute([$eleveId, $date, $heure, $motif]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Get upcoming counselor appointments.
     */
    public function getRdvConseiller(array $filters = []): array
    {
        $sql = "SELECT r.*, CONCAT(e.prenom, ' ', e.nom) AS eleve_nom, c.nom AS classe_nom
                FROM orientation_rdv r
                JOIN eleves e ON r.eleve_id = e.id
                LEFT JOIN classes c ON e.classe_id = c.id
                WHERE 1=1";
        $params = [];
        if (!empty($filters['eleve_id'])) { $sql .= ' AND r.eleve_id = ?'; $params[] = $filters['eleve_id']; }
        if (!empty($filters['statut'])) { $sql .= ' AND r.statut = ?'; $params[] = $filters['statut']; }
        if (!empty($filters['date_debut'])) { $sql .= ' AND r.date_rdv >= ?'; $params[] = $filters['date_debut']; }
        $sql .= ' ORDER BY r.date_rdv, r.heure_rdv';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update counselor appointment status.
     */
    public function traiterRdv(int $rdvId, string $statut): void
    {
        $this->pdo->prepare("UPDATE orientation_rdv SET statut = ? WHERE id = ?")
                   ->execute([$statut, $rdvId]);
    }

    /* ───────── CAREER CATALOG ───────── */

    /**
     * Get career/formation catalog entries.
     */
    public function getFichesMetiers(?string $secteur = null, ?string $recherche = null): array
    {
        $sql = "SELECT * FROM orientation_fiches_metiers WHERE 1=1";
        $params = [];
        if ($secteur) { $sql .= ' AND secteur = ?'; $params[] = $secteur; }
        if ($recherche) {
            $sql .= ' AND (nom LIKE ? OR description LIKE ?)';
            $params[] = "%$recherche%";
            $params[] = "%$recherche%";
        }
        $sql .= ' ORDER BY nom';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create a career catalog entry.
     */
    public function creerFicheMetier(array $d): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO orientation_fiches_metiers (nom, secteur, description, formation_requise, debouches, salaire_moyen)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$d['nom'], $d['secteur'] ?? null, $d['description'] ?? null,
                        $d['formation_requise'] ?? null, $d['debouches'] ?? null, $d['salaire_moyen'] ?? null]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Get orientation history for a student across years.
     */
    public function getHistoriqueParcours(int $eleveId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT f.*, (SELECT COUNT(*) FROM orientation_voeux v WHERE v.fiche_id = f.id) AS nb_voeux
            FROM orientation_fiches f
            WHERE f.eleve_id = ?
            ORDER BY f.annee_scolaire DESC
        ");
        $stmt->execute([$eleveId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function secteursMetier(): array
    {
        return [
            'sante' => 'Santé', 'informatique' => 'Informatique', 'commerce' => 'Commerce',
            'industrie' => 'Industrie', 'education' => 'Éducation', 'arts' => 'Arts & Culture',
            'droit' => 'Droit', 'sciences' => 'Sciences', 'social' => 'Social', 'autre' => 'Autre',
        ];
    }

    /* ───────── EXPORT ───────── */

    public function getFichesForExport(array $filters = []): array
    {
        $fiches = $this->getFiches($filters);
        return array_map(fn($f) => [
            $f['eleve_nom'] ?? '-',
            $f['prenom'] ?? '-',
            $f['classe_nom'] ?? '-',
            $f['annee_scolaire'] ?? '-',
            $f['projet_professionnel'] ?? '-',
            ucfirst($f['statut']),
            $f['avis_pp'] ?? '-',
            $f['avis_conseil'] ?? '-',
        ], $fiches);
    }

    public function getVoeuxForExport(array $filters = []): array
    {
        $fiches = $this->getFiches($filters);
        $rows = [];
        foreach ($fiches as $f) {
            $voeux = $this->getVoeux($f['id']);
            foreach ($voeux as $v) {
                $rows[] = [
                    $f['eleve_nom'] ?? '-',
                    $f['prenom'] ?? '-',
                    $f['classe_nom'] ?? '-',
                    $v['rang'],
                    $v['formation'],
                    $v['etablissement_vise'] ?? '-',
                    $v['avis_pp'] ?? '-',
                    $v['avis_conseil'] ?? '-',
                ];
            }
        }
        return $rows;
    }

    // ─── DONNÉES PARCOURSUP ───

    public function enregistrerVoeuParcoursup(int $ficheId, string $voeu, string $formation, string $etablissement, int $rang = 0): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO orientation_parcoursup (fiche_id, voeu, formation, etablissement, rang, statut) VALUES (:fid, :v, :f, :e, :r, 'en_attente')");
        $stmt->execute([':fid' => $ficheId, ':v' => $voeu, ':f' => $formation, ':e' => $etablissement, ':r' => $rang]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getVoeuxParcoursup(int $ficheId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM orientation_parcoursup WHERE fiche_id = :fid ORDER BY rang");
        $stmt->execute([':fid' => $ficheId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function majStatutParcoursup(int $voeuId, string $statut, ?int $rang = null): void
    {
        $sql = "UPDATE orientation_parcoursup SET statut = :s";
        $params = [':s' => $statut, ':id' => $voeuId];
        if ($rang !== null) { $sql .= ", rang = :r"; $params[':r'] = $rang; }
        $sql .= " WHERE id = :id";
        $this->pdo->prepare($sql)->execute($params);
    }

    // ─── QUESTIONNAIRE INTÉRÊTS ───

    public function genererQuestionnaireInterets(int $eleveId): array
    {
        $domaines = [
            'sciences' => ['Mathématiques', 'Physique-Chimie', 'SVT', 'Informatique'],
            'lettres' => ['Français', 'Philosophie', 'Langues étrangères', 'Histoire-Géo'],
            'arts' => ['Arts plastiques', 'Musique', 'Théâtre', 'Cinéma'],
            'technique' => ['Technologie', 'Électronique', 'Mécanique', 'BTP'],
            'social' => ['Santé', 'Éducation', 'Social', 'Droit'],
            'commerce' => ['Commerce', 'Marketing', 'Gestion', 'Économie'],
        ];

        $questions = [];
        foreach ($domaines as $domaine => $exemples) {
            $questions[] = [
                'domaine' => $domaine,
                'question' => "Sur une échelle de 1 à 5, quel est votre intérêt pour le domaine : " . ucfirst($domaine) . " ?",
                'exemples' => $exemples,
                'type' => 'likert'
            ];
        }

        return ['eleve_id' => $eleveId, 'questions' => $questions];
    }

    // ─── SUIVI ALUMNI ───

    public function enregistrerAlumni(int $ancienEleveId, string $formationActuelle, string $etablissement, string $anneeSortie): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO orientation_alumni (ancien_eleve_id, formation_actuelle, etablissement, annee_sortie) VALUES (:aeid, :fa, :e, :as) ON DUPLICATE KEY UPDATE formation_actuelle=VALUES(formation_actuelle), etablissement=VALUES(etablissement)");
        $stmt->execute([':aeid' => $ancienEleveId, ':fa' => $formationActuelle, ':e' => $etablissement, ':as' => $anneeSortie]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getAlumni(string $anneeSortie = ''): array
    {
        $sql = "SELECT a.*, CONCAT(e.prenom,' ',e.nom) AS nom_complet FROM orientation_alumni a JOIN eleves e ON a.ancien_eleve_id = e.id";
        $params = [];
        if ($anneeSortie) { $sql .= " WHERE a.annee_sortie = :as"; $params[':as'] = $anneeSortie; }
        $sql .= " ORDER BY a.annee_sortie DESC, e.nom";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ─── PLANNING ENTRETIENS ───

    public function planifierEntretien(int $eleveId, int $ppId, string $dateEntretien, string $motif = ''): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO orientation_entretiens (eleve_id, pp_id, date_entretien, motif, statut) VALUES (:eid, :pid, :d, :m, 'planifie')");
        $stmt->execute([':eid' => $eleveId, ':pid' => $ppId, ':d' => $dateEntretien, ':m' => $motif]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getEntretiens(int $ppId): array
    {
        $stmt = $this->pdo->prepare("SELECT oe.*, CONCAT(e.prenom,' ',e.nom) AS eleve_nom, e.classe FROM orientation_entretiens oe JOIN eleves e ON oe.eleve_id = e.id WHERE oe.pp_id = :pid ORDER BY oe.date_entretien ASC");
        $stmt->execute([':pid' => $ppId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function completerEntretien(int $entretienId, string $compteRendu, string $recommandations = ''): void
    {
        $this->pdo->prepare("UPDATE orientation_entretiens SET statut = 'realise', compte_rendu = :cr, recommandations = :r WHERE id = :id")
            ->execute([':cr' => $compteRendu, ':r' => $recommandations, ':id' => $entretienId]);
    }
}
