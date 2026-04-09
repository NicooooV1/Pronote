<?php
/**
 * M31 – Santé / Infirmerie — Service
 */
class InfirmerieService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /* ───────── FICHES SANTÉ ───────── */

    public function getFiche(int $eleveId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM fiches_sante WHERE eleve_id = ?");
        $stmt->execute([$eleveId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function sauvegarderFiche(int $eleveId, array $data): void
    {
        $existing = $this->getFiche($eleveId);
        if ($existing) {
            $stmt = $this->pdo->prepare("
                UPDATE fiches_sante SET allergies=?, traitements=?, contacts_urgence=?, pai=?, groupe_sanguin=?, remarques=?, date_maj=NOW()
                WHERE eleve_id=?
            ");
            $stmt->execute([
                $data['allergies'], $data['traitements'], $data['contacts_urgence'],
                $data['pai'] ?? null, $data['groupe_sanguin'] ?? null, $data['remarques'] ?? null,
                $eleveId,
            ]);
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO fiches_sante (eleve_id, allergies, traitements, contacts_urgence, pai, groupe_sanguin, remarques, date_maj)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $eleveId, $data['allergies'], $data['traitements'], $data['contacts_urgence'],
                $data['pai'] ?? null, $data['groupe_sanguin'] ?? null, $data['remarques'] ?? null,
            ]);
        }
    }

    public function getFiches(string $recherche = null): array
    {
        $sql = "
            SELECT fs.*, e.prenom, e.nom AS eleve_nom, cl.nom AS classe_nom
            FROM fiches_sante fs
            JOIN eleves e ON fs.eleve_id = e.id
            LEFT JOIN classes cl ON e.classe_id = cl.id
        ";
        $params = [];
        if ($recherche) {
            $sql .= " WHERE e.nom LIKE ? OR e.prenom LIKE ?";
            $params = ["%$recherche%", "%$recherche%"];
        }
        $sql .= ' ORDER BY e.nom, e.prenom';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ───────── PASSAGES INFIRMERIE ───────── */

    public function creerPassage(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO passages_infirmerie (eleve_id, date_passage, motif, symptomes, soins, orientation, notifier_parents, remarques)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['eleve_id'], $data['date_passage'] ?? date('Y-m-d H:i:s'),
            $data['motif'], $data['symptomes'] ?? null, $data['soins'] ?? null,
            $data['orientation'] ?? 'retour_classe', $data['notifier_parents'] ?? 0,
            $data['remarques'] ?? null,
        ]);
        return $this->pdo->lastInsertId();
    }

    public function getPassage(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT p.*, e.prenom, e.nom AS eleve_nom, cl.nom AS classe_nom
            FROM passages_infirmerie p
            JOIN eleves e ON p.eleve_id = e.id
            LEFT JOIN classes cl ON e.classe_id = cl.id
            WHERE p.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getPassages(array $filtres = []): array
    {
        $sql = "
            SELECT p.*, e.prenom, e.nom AS eleve_nom, cl.nom AS classe_nom
            FROM passages_infirmerie p
            JOIN eleves e ON p.eleve_id = e.id
            LEFT JOIN classes cl ON e.classe_id = cl.id
            WHERE 1=1
        ";
        $params = [];
        if (!empty($filtres['eleve_id'])) {
            $sql .= ' AND p.eleve_id = ?';
            $params[] = $filtres['eleve_id'];
        }
        if (!empty($filtres['date_debut'])) {
            $sql .= ' AND p.date_passage >= ?';
            $params[] = $filtres['date_debut'];
        }
        if (!empty($filtres['date_fin'])) {
            $sql .= ' AND p.date_passage <= ?';
            $params[] = $filtres['date_fin'] . ' 23:59:59';
        }
        if (!empty($filtres['orientation'])) {
            $sql .= ' AND p.orientation = ?';
            $params[] = $filtres['orientation'];
        }
        $sql .= ' ORDER BY p.date_passage DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPassagesEleve(int $eleveId): array
    {
        return $this->getPassages(['eleve_id' => $eleveId]);
    }

    public function getEleves(string $recherche = null): array
    {
        $sql = "SELECT e.id, e.prenom, e.nom, cl.nom AS classe_nom FROM eleves e LEFT JOIN classes cl ON e.classe_id = cl.id";
        $params = [];
        if ($recherche) { $sql .= ' WHERE e.nom LIKE ? OR e.prenom LIKE ?'; $params = ["%$recherche%", "%$recherche%"]; }
        $sql .= ' ORDER BY e.nom, e.prenom';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getEnfantsParent(int $parentId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT e.id, e.prenom, e.nom, cl.nom AS classe_nom
            FROM eleves e
            JOIN parent_eleve pe ON e.id = pe.eleve_id
            LEFT JOIN classes cl ON e.classe_id = cl.id
            WHERE pe.parent_id = ?
        ");
        $stmt->execute([$parentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getStats(): array
    {
        $today = date('Y-m-d');
        $month = date('Y-m') . '%';

        $stmtJour = $this->pdo->prepare("SELECT COUNT(*) FROM passages_infirmerie WHERE DATE(date_passage) = ?");
        $stmtJour->execute([$today]);

        $stmtMois = $this->pdo->prepare("SELECT COUNT(*) FROM passages_infirmerie WHERE date_passage LIKE ?");
        $stmtMois->execute([$month]);

        return [
            'fiches' => (int)$this->pdo->query("SELECT COUNT(*) FROM fiches_sante")->fetchColumn(),
            'passages_jour' => (int)$stmtJour->fetchColumn(),
            'passages_mois' => (int)$stmtMois->fetchColumn(),
            'pai_actifs' => (int)$this->pdo->query("SELECT COUNT(*) FROM fiches_sante WHERE pai IS NOT NULL AND pai != ''")->fetchColumn(),
        ];
    }

    public function getStatsPassages(): array
    {
        // Recalculate properly
        $today = date('Y-m-d');
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM passages_infirmerie WHERE DATE(date_passage) = ?");
        $stmt->execute([$today]);
        $jour = $stmt->fetchColumn();

        $month = date('Y-m') . '%';
        $stmtMois = $this->pdo->prepare("SELECT COUNT(*) FROM passages_infirmerie WHERE date_passage LIKE ?");
        $stmtMois->execute([$month]);
        $mois = $stmtMois->fetchColumn();

        $envois = $this->pdo->query("SELECT COUNT(*) FROM passages_infirmerie WHERE orientation = 'renvoye_domicile'")->fetchColumn();
        $urgences = $this->pdo->query("SELECT COUNT(*) FROM passages_infirmerie WHERE orientation = 'urgences'")->fetchColumn();

        return ['jour' => $jour, 'mois' => $mois, 'renvoyes' => $envois, 'urgences' => $urgences];
    }

    public static function orientations(): array
    {
        return [
            'retour_classe' => 'Retour en classe',
            'repos_infirmerie' => 'Repos à l\'infirmerie',
            'renvoye_domicile' => 'Renvoyé à domicile',
            'urgences' => 'Urgences / SAMU',
            'medecin' => 'Médecin traitant',
        ];
    }

    public static function orientationBadge(string $o): string
    {
        $map = [
            'retour_classe' => 'success',
            'repos_infirmerie' => 'info',
            'renvoye_domicile' => 'warning',
            'urgences' => 'danger',
            'medecin' => 'secondary',
        ];
        $labels = self::orientations();
        return '<span class="badge badge-' . ($map[$o] ?? 'secondary') . '">' . ($labels[$o] ?? $o) . '</span>';
    }

    public static function groupesSanguins(): array
    {
        return ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
    }

    /* ───────── VACCINATIONS ───────── */

    /**
     * Get vaccination records for a student.
     */
    public function getVaccinations(int $eleveId): array
    {
        $fiche = $this->getFiche($eleveId);
        if (!$fiche) return [];
        return json_decode($fiche['vaccinations'] ?? '[]', true) ?: [];
    }

    /**
     * Save vaccination records for a student.
     */
    public function saveVaccinations(int $eleveId, array $vaccinations): void
    {
        $json = json_encode($vaccinations, JSON_UNESCAPED_UNICODE);
        $fiche = $this->getFiche($eleveId);
        if ($fiche) {
            $this->pdo->prepare("UPDATE fiches_sante SET vaccinations = ?, date_maj = NOW() WHERE eleve_id = ?")
                       ->execute([$json, $eleveId]);
        } else {
            $this->pdo->prepare("INSERT INTO fiches_sante (eleve_id, vaccinations, date_maj) VALUES (?, ?, NOW())")
                       ->execute([$eleveId, $json]);
        }
    }

    /**
     * Get students with missing or expired vaccinations.
     */
    public function getVaccinationsManquantes(): array
    {
        $obligatoires = self::vaccinsObligatoires();
        $fiches = $this->getFiches();
        $manquants = [];

        foreach ($fiches as $f) {
            $vaccins = json_decode($f['vaccinations'] ?? '[]', true) ?: [];
            $vaccinsNoms = array_column($vaccins, 'nom');
            $missing = [];
            foreach ($obligatoires as $v) {
                if (!in_array($v, $vaccinsNoms)) {
                    $missing[] = $v;
                }
            }
            if (!empty($missing)) {
                $manquants[] = [
                    'eleve_id' => $f['eleve_id'],
                    'eleve_nom' => ($f['eleve_nom'] ?? '') . ' ' . ($f['prenom'] ?? ''),
                    'classe' => $f['classe_nom'] ?? '',
                    'vaccins_manquants' => $missing,
                ];
            }
        }
        return $manquants;
    }

    /* ───────── EMERGENCY PROTOCOLS ───────── */

    /**
     * Get emergency protocols.
     */
    public function getProtocoles(): array
    {
        return $this->pdo->query("SELECT * FROM protocoles_urgence ORDER BY nom")->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get protocol by pathology keyword.
     */
    public function getProtocoleByPathologie(string $keyword): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM protocoles_urgence WHERE pathologie LIKE ? OR nom LIKE ? LIMIT 1");
        $like = '%' . $keyword . '%';
        $stmt->execute([$like, $like]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get top motifs for passages (for stats dashboard).
     */
    public function getTopMotifs(int $limit = 10): array
    {
        $stmt = $this->pdo->prepare("
            SELECT motif, COUNT(*) AS total
            FROM passages_infirmerie
            WHERE date_passage >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY motif ORDER BY total DESC LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get frequent visitors (students with many passages).
     */
    public function getVisiteursFrequents(int $limit = 10): array
    {
        $stmt = $this->pdo->prepare("
            SELECT e.id, CONCAT(e.prenom, ' ', e.nom) AS eleve_nom, cl.nom AS classe_nom,
                   COUNT(*) AS nb_passages
            FROM passages_infirmerie p
            JOIN eleves e ON p.eleve_id = e.id
            LEFT JOIN classes cl ON e.classe_id = cl.id
            WHERE p.date_passage >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
            GROUP BY e.id HAVING nb_passages >= 3
            ORDER BY nb_passages DESC LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function vaccinsObligatoires(): array
    {
        return ['DTP', 'Coqueluche', 'Haemophilus B', 'Hépatite B', 'Pneumocoque', 'Méningocoque C', 'ROR'];
    }

    /* ───────── STATISTIQUES MENSUELLES ───────── */

    /**
     * Stats passages par mois (12 derniers mois)
     */
    public function getStatsMensuelles(): array
    {
        $result = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = date('Y-m', strtotime("-{$i} months"));
            $label = date('M Y', strtotime($month . '-01'));
            $stmtM = $this->pdo->prepare("SELECT COUNT(*) FROM passages_infirmerie WHERE date_passage LIKE ?");
            $stmtM->execute([$month . '%']);
            $count = (int)$stmtM->fetchColumn();
            $result[] = ['mois' => $label, 'passages' => $count];
        }
        return $result;
    }

    /**
     * Répartition par orientation
     */
    public function getRepartitionOrientations(): array
    {
        return $this->pdo->query("
            SELECT orientation, COUNT(*) AS total 
            FROM passages_infirmerie 
            WHERE date_passage >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY orientation ORDER BY total DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ───────── EXPORT ───────── */

    public function getPassagesForExport(array $filtres = []): array
    {
        $passages = $this->getPassages($filtres);
        $orientations = self::orientations();
        $rows = [];
        foreach ($passages as $p) {
            $rows[] = [
                $p['eleve_nom'] ?? '',
                $p['classe_nom'] ?? '',
                $p['date_passage'] ?? '',
                $p['motif'] ?? '',
                $p['symptomes'] ?? '',
                $p['soins'] ?? '',
                $orientations[$p['orientation'] ?? ''] ?? $p['orientation'] ?? '',
                $p['commentaire'] ?? '',
            ];
        }
        return $rows;
    }

    // ─── SUIVI MÉDICAMENTS ───

    public function ajouterTraitement(int $eleveId, string $medicament, string $posologie, string $dateDebut, ?string $dateFin = null, bool $paiActif = false): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO infirmerie_traitements (eleve_id, medicament, posologie, date_debut, date_fin, pai, created_at)
            VALUES (:e, :m, :p, :dd, :df, :pai, NOW())
        ");
        $stmt->execute([':e' => $eleveId, ':m' => $medicament, ':p' => $posologie, ':dd' => $dateDebut, ':df' => $dateFin, ':pai' => $paiActif ? 1 : 0]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getTraitements(int $eleveId, bool $actifsOnly = false): array
    {
        $sql = "SELECT * FROM infirmerie_traitements WHERE eleve_id = :e";
        if ($actifsOnly) { $sql .= " AND (date_fin IS NULL OR date_fin >= CURDATE())"; }
        $sql .= " ORDER BY date_debut DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':e' => $eleveId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function enregistrerPriseTraitement(int $traitementId, string $date, ?string $commentaire = null): void
    {
        $this->pdo->prepare("
            INSERT INTO infirmerie_prises (traitement_id, date_prise, heure_prise, commentaire)
            VALUES (:t, :d, NOW(), :c)
        ")->execute([':t' => $traitementId, ':d' => $date, ':c' => $commentaire]);
    }

    // ─── DÉTECTION ÉPIDÉMIE ───

    public function detecterEpidemie(int $joursAnalyse = 7, int $seuilAlerte = 5): array
    {
        $stmt = $this->pdo->prepare("
            SELECT motif, COUNT(*) AS nb_cas, COUNT(DISTINCT eleve_id) AS nb_eleves,
                   MIN(date_passage) AS premier_cas, MAX(date_passage) AS dernier_cas
            FROM passages_infirmerie
            WHERE date_passage >= DATE_SUB(CURDATE(), INTERVAL :j DAY)
              AND motif IS NOT NULL AND motif != ''
            GROUP BY motif
            HAVING nb_cas >= :s
            ORDER BY nb_cas DESC
        ");
        $stmt->execute([':j' => $joursAnalyse, ':s' => $seuilAlerte]);
        $alertes = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($alertes as &$a) {
            $stmtClasses = $this->pdo->prepare("
                SELECT DISTINCT cl.nom AS classe
                FROM passages_infirmerie p
                JOIN eleves e ON p.eleve_id = e.id
                LEFT JOIN classes cl ON e.classe_id = cl.id
                WHERE p.motif = :m AND p.date_passage >= DATE_SUB(CURDATE(), INTERVAL :j DAY)
            ");
            $stmtClasses->execute([':m' => $a['motif'], ':j' => $joursAnalyse]);
            $a['classes_touchees'] = $stmtClasses->fetchAll(\PDO::FETCH_COLUMN);
        }
        return $alertes;
    }

    // ─── AFFICHAGE PAI ───

    public function getElevesPai(?string $classe = null): array
    {
        $sql = "SELECT fs.eleve_id, CONCAT(e.prenom, ' ', e.nom) AS eleve_nom, cl.nom AS classe_nom,
                       fs.pai, fs.allergies, fs.pathologies
                FROM fiches_sante fs
                JOIN eleves e ON fs.eleve_id = e.id
                LEFT JOIN classes cl ON e.classe_id = cl.id
                WHERE fs.pai IS NOT NULL AND fs.pai != ''";
        $params = [];
        if ($classe) { $sql .= " AND cl.nom = :c"; $params[':c'] = $classe; }
        $sql .= " ORDER BY e.nom";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getPaiResume(int $eleveId): ?array
    {
        $fiche = $this->getFiche($eleveId);
        if (!$fiche || empty($fiche['pai'])) return null;
        $traitements = $this->getTraitements($eleveId, true);
        $traitementsPai = array_filter($traitements, fn($t) => $t['pai']);
        return [
            'eleve_id' => $eleveId,
            'pai' => $fiche['pai'],
            'allergies' => $fiche['allergies'] ?? '',
            'pathologies' => $fiche['pathologies'] ?? '',
            'traitements_actifs' => array_values($traitementsPai),
            'protocoles_associes' => $fiche['pathologies'] ? $this->getProtocoleByPathologie($fiche['pathologies']) : null,
        ];
    }

    // ─── STATS MENSUELLES WIDGET ───

    public function getStatsMensuellesWidget(): array
    {
        $moisCourant = date('Y-m');
        $moisPrec = date('Y-m', strtotime('-1 month'));

        $stmtCur = $this->pdo->prepare("SELECT COUNT(*) FROM passages_infirmerie WHERE date_passage LIKE ?");
        $stmtCur->execute([$moisCourant . '%']);
        $current = (int)$stmtCur->fetchColumn();

        $stmtPrev = $this->pdo->prepare("SELECT COUNT(*) FROM passages_infirmerie WHERE date_passage LIKE ?");
        $stmtPrev->execute([$moisPrec . '%']);
        $previous = (int)$stmtPrev->fetchColumn();

        $evolution = $previous > 0 ? round(($current - $previous) / $previous * 100, 1) : 0;

        $topMotifs = $this->getTopMotifs(3);

        return [
            'passages_mois' => $current,
            'passages_mois_precedent' => $previous,
            'evolution_pct' => $evolution,
            'top_motifs' => $topMotifs,
            'pai_actifs' => (int)$this->pdo->query("SELECT COUNT(*) FROM fiches_sante WHERE pai IS NOT NULL AND pai != ''")->fetchColumn(),
            'epidemies_detectees' => count($this->detecterEpidemie()),
        ];
    }
}
