<?php
/**
 * DevoirService — Cahier de textes
 *
 * Centralise toute la logique métier du module :
 *   CRUD, validation, query builder, pagination, recherche,
 *   statistiques SQL, pièces jointes, statut élève (devoir fait).
 *
 * SEC-4  : whitelist ORDER BY
 * SEC-3  : validation DateTime
 * SEC-5  : maxlength titre/description
 * REF-5  : query builder
 * UX-1   : pagination + recherche
 * PERF-1 : stats SQL
 * PJ     : gestion pièces jointes
 * UX-3   : case « devoir fait »
 */
class DevoirService
{
    private PDO $pdo;

    /** Colonnes explicites, pas de SELECT * */
    private const COLS = 'id, titre, description, classe, nom_matiere, nom_professeur, date_ajout, date_rendu, date_creation';

    /** Whitelist ORDER BY (SEC-4) */
    private const ORDER_WHITELIST = [
        'date_rendu'    => 'date_rendu',
        'date_ajout'    => 'date_ajout',
        'nom_matiere'   => 'nom_matiere',
        'classe'        => 'classe',
        'titre'         => 'titre',
        'date_creation' => 'date_creation',
    ];

    private const ALLOWED_DIRS = ['asc', 'desc'];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /* ══════════ Query Builder (REF-5, UX-1) ══════════ */

    /**
     * Construit une requête filtrée, ordonnée et paginée.
     * @return array{sql:string, params:array, countSql:string, countParams:array}
     */
    public function buildQuery(
        string  $role,
        ?int    $userId,
        ?string $userFullName,
        array   $filters    = [],
        string  $orderField = 'date_rendu',
        string  $orderDir   = 'desc',
        int     $page       = 1,
        int     $perPage    = 20,
        ?string $search     = null
    ): array {
        $cols     = self::COLS;
        $sql      = "SELECT {$cols} FROM devoirs WHERE 1=1";
        $countSql = "SELECT COUNT(*) FROM devoirs WHERE 1=1";
        $params   = [];

        // ── Filtre par rôle ──
        if ($role === 'eleve') {
            $classe = $this->getEleveClasse($userId);
            if ($classe) {
                $sql      .= ' AND classe = :user_classe';
                $countSql .= ' AND classe = :user_classe';
                $params[':user_classe'] = $classe;
            }
        } elseif ($role === 'professeur') {
            $sql      .= ' AND nom_professeur = :prof_name';
            $countSql .= ' AND nom_professeur = :prof_name';
            $params[':prof_name'] = $userFullName;
        }

        // ── Filtres optionnels ──
        if (!empty($filters['classe'])) {
            $sql      .= ' AND classe = :f_classe';
            $countSql .= ' AND classe = :f_classe';
            $params[':f_classe'] = $filters['classe'];
        }
        if (!empty($filters['matiere'])) {
            $sql      .= ' AND nom_matiere = :f_matiere';
            $countSql .= ' AND nom_matiere = :f_matiere';
            $params[':f_matiere'] = $filters['matiere'];
        }
        if (!empty($filters['professeur'])) {
            $sql      .= ' AND nom_professeur = :f_prof';
            $countSql .= ' AND nom_professeur = :f_prof';
            $params[':f_prof'] = $filters['professeur'];
        }

        // ── Recherche (UX-1) ──
        if ($search !== null && $search !== '') {
            $sql      .= ' AND (titre LIKE :q1 OR description LIKE :q2)';
            $countSql .= ' AND (titre LIKE :q1 OR description LIKE :q2)';
            $params[':q1'] = '%' . $search . '%';
            $params[':q2'] = '%' . $search . '%';
        }

        // ── ORDER BY (SEC-4 whitelist) ──
        $order = $this->buildOrder($orderField, $orderDir);
        $sql  .= " ORDER BY {$order}";

        // ── Pagination (UX-1) ──
        $offset = max(0, ($page - 1) * $perPage);
        $sql   .= " LIMIT {$perPage} OFFSET {$offset}";

        return [
            'sql'         => $sql,
            'params'      => $params,
            'countSql'    => $countSql,
            'countParams' => $params,
        ];
    }

    /**
     * Exécute une requête construite par buildQuery.
     * @return array{0:array, 1:int} [devoirs, totalCount]
     */
    public function executeQuery(array $query): array
    {
        $stmt = $this->pdo->prepare($query['sql']);
        $stmt->execute($query['params']);
        $devoirs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $cnt = $this->pdo->prepare($query['countSql']);
        $cnt->execute($query['countParams']);
        $total = (int) $cnt->fetchColumn();

        return [$devoirs, $total];
    }

    /* ══════════ Lecture individuelle ══════════ */

    public function getDevoirById(int $id): ?array
    {
        $cols = self::COLS;
        $stmt = $this->pdo->prepare("SELECT {$cols} FROM devoirs WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /* ══════════ Écritures ══════════ */

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO devoirs
             (titre, description, classe, nom_matiere, nom_professeur, date_ajout, date_rendu)
             VALUES (:titre, :desc, :classe, :mat, :prof, :da, :dr)'
        );
        $stmt->execute([
            ':titre'  => trim($data['titre']),
            ':desc'   => trim($data['description'] ?? ''),
            ':classe' => $data['classe'],
            ':mat'    => $data['nom_matiere'],
            ':prof'   => $data['nom_professeur'],
            ':da'     => $data['date_ajout'],
            ':dr'     => $data['date_rendu'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE devoirs SET
                titre          = :titre,
                description    = :desc,
                classe         = :classe,
                nom_matiere    = :mat,
                nom_professeur = :prof,
                date_ajout     = :da,
                date_rendu     = :dr
             WHERE id = :id'
        );
        return $stmt->execute([
            ':titre'  => trim($data['titre']),
            ':desc'   => trim($data['description'] ?? ''),
            ':classe' => $data['classe'],
            ':mat'    => $data['nom_matiere'],
            ':prof'   => $data['nom_professeur'],
            ':da'     => $data['date_ajout'],
            ':dr'     => $data['date_rendu'],
            ':id'     => $id,
        ]);
    }

    public function delete(int $id): bool
    {
        $this->deleteAttachedFiles($id);
        $stmt = $this->pdo->prepare('DELETE FROM devoirs WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }

    /* ══════════ Validation (SEC-3, SEC-5) ══════════ */

    public function validate(array $data): array
    {
        $errors = [];

        if (empty($data['titre'])) {
            $errors[] = "Le titre est obligatoire.";
        } elseif (mb_strlen($data['titre']) > 255) {
            $errors[] = "Titre trop long (max 255 caractères).";
        }

        if (empty($data['description'])) {
            $errors[] = "La description est obligatoire.";
        } elseif (mb_strlen($data['description']) > 5000) {
            $errors[] = "Description trop longue (max 5000 caractères).";
        }

        if (empty($data['classe']))         $errors[] = "La classe est obligatoire.";
        if (empty($data['nom_matiere']))    $errors[] = "La matière est obligatoire.";
        if (empty($data['nom_professeur'])) $errors[] = "Le professeur est obligatoire.";

        $dateAjout = \DateTime::createFromFormat('Y-m-d', $data['date_ajout'] ?? '');
        $dateRendu = \DateTime::createFromFormat('Y-m-d', $data['date_rendu'] ?? '');

        if (!$dateAjout) $errors[] = "Format de date d'ajout invalide.";
        if (!$dateRendu) $errors[] = "Format de date de rendu invalide.";

        if ($dateAjout && $dateRendu && $dateRendu <= $dateAjout) {
            $errors[] = "La date de rendu doit être postérieure à la date d'ajout.";
        }

        return $errors;
    }

    /* ══════════ Permissions ══════════ */

    public function canUserEdit(array $devoir, string $fullname, string $role): bool
    {
        if (in_array($role, ['administrateur', 'vie_scolaire'], true)) return true;
        if ($role === 'professeur') return $devoir['nom_professeur'] === $fullname;
        return false;
    }

    /* ══════════ Status helpers ══════════ */

    public function computeStatus(string $dateRendu): array
    {
        $dr  = new \DateTime($dateRendu);
        $now = new \DateTime();
        $diff = $now->diff($dr);

        if ($dr < $now)       return ['class' => 'expired', 'label' => 'Expiré',         'icon' => 'fa-times-circle'];
        if ($diff->days <= 3) return ['class' => 'urgent',  'label' => 'Urgent',         'icon' => 'fa-exclamation-circle'];
        if ($diff->days <= 7) return ['class' => 'soon',    'label' => 'Cette semaine',  'icon' => 'fa-clock'];
        return                       ['class' => '',        'label' => 'À venir',        'icon' => 'fa-book'];
    }

    /** Badge « Nouveau » : devoir créé il y a moins de 48 h (UX-2) */
    public function isNew(array $devoir): bool
    {
        $created = new \DateTime($devoir['date_creation']);
        return (new \DateTime())->diff($created)->days < 2;
    }

    /* ══════════ Stats SQL (PERF-1) ══════════ */

    public function getStatsSql(string $role, ?int $userId, ?string $userFullName, array $filters = [], ?string $search = null): array
    {
        $sql = "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN date_rendu < CURDATE() THEN 1 ELSE 0 END) as expired,
                    SUM(CASE WHEN date_rendu BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY) THEN 1 ELSE 0 END) as urgent,
                    SUM(CASE WHEN date_rendu BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as soon
                FROM devoirs WHERE 1=1";
        $params = [];

        if ($role === 'eleve') {
            $classe = $this->getEleveClasse($userId);
            if ($classe) {
                $sql .= ' AND classe = :user_classe';
                $params[':user_classe'] = $classe;
            }
        } elseif ($role === 'professeur') {
            $sql .= ' AND nom_professeur = :prof_name';
            $params[':prof_name'] = $userFullName;
        }

        if (!empty($filters['classe']))  { $sql .= ' AND classe = :f_classe';      $params[':f_classe']  = $filters['classe']; }
        if (!empty($filters['matiere'])) { $sql .= ' AND nom_matiere = :f_matiere'; $params[':f_matiere'] = $filters['matiere']; }

        if ($search !== null && $search !== '') {
            $sql .= ' AND (titre LIKE :q1 OR description LIKE :q2)';
            $params[':q1'] = '%' . $search . '%';
            $params[':q2'] = '%' . $search . '%';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total'   => (int) ($row['total']   ?? 0),
            'expired' => (int) ($row['expired'] ?? 0),
            'urgent'  => (int) ($row['urgent']  ?? 0),
            'soon'    => (int) ($row['soon']    ?? 0),
        ];
    }

    /* ══════════ Filtres SQL ══════════ */

    public function getFilterOptions(string $role, ?int $userId, ?string $userFullName): array
    {
        $where  = '1=1';
        $params = [];

        if ($role === 'eleve') {
            $classe = $this->getEleveClasse($userId);
            if ($classe) {
                $where .= ' AND classe = :user_classe';
                $params[':user_classe'] = $classe;
            }
        } elseif ($role === 'professeur') {
            $where .= ' AND nom_professeur = :prof_name';
            $params[':prof_name'] = $userFullName;
        }

        $stmtC = $this->pdo->prepare("SELECT DISTINCT classe FROM devoirs WHERE {$where} ORDER BY classe");
        $stmtC->execute($params);

        $stmtM = $this->pdo->prepare("SELECT DISTINCT nom_matiere FROM devoirs WHERE {$where} ORDER BY nom_matiere");
        $stmtM->execute($params);

        $stmtP = $this->pdo->prepare("SELECT DISTINCT nom_professeur FROM devoirs WHERE {$where} ORDER BY nom_professeur");
        $stmtP->execute($params);

        return [
            'classes'     => $stmtC->fetchAll(PDO::FETCH_COLUMN),
            'matieres'    => $stmtM->fetchAll(PDO::FETCH_COLUMN),
            'professeurs' => $stmtP->fetchAll(PDO::FETCH_COLUMN),
        ];
    }

    /* ══════════ Pièces jointes (PJ) ══════════ */

    public function getFichiers(int $devoirId): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT id, nom_original, nom_stockage, type_mime, taille, date_upload
                 FROM devoirs_fichiers WHERE devoir_id = :did ORDER BY date_upload'
            );
            $stmt->execute([':did' => $devoirId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return []; // Table may not exist yet
        }
    }

    public function getFichierById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT df.*, d.classe, d.nom_professeur
             FROM devoirs_fichiers df
             JOIN devoirs d ON d.id = df.devoir_id
             WHERE df.id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function countFichiers(int $devoirId): int
    {
        try {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM devoirs_fichiers WHERE devoir_id = :did');
            $stmt->execute([':did' => $devoirId]);
            return (int) $stmt->fetchColumn();
        } catch (\PDOException $e) {
            return 0;
        }
    }

    public function addFichier(int $devoirId, string $nomOriginal, string $nomStockage, string $typeMime, int $taille): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO devoirs_fichiers (devoir_id, nom_original, nom_stockage, type_mime, taille)
             VALUES (:did, :nom, :stockage, :mime, :taille)'
        );
        $stmt->execute([
            ':did'      => $devoirId,
            ':nom'      => $nomOriginal,
            ':stockage' => $nomStockage,
            ':mime'     => $typeMime,
            ':taille'   => $taille,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function deleteFichier(int $fichierId): bool
    {
        $stmt = $this->pdo->prepare('SELECT nom_stockage FROM devoirs_fichiers WHERE id = :id');
        $stmt->execute([':id' => $fichierId]);
        $nom = $stmt->fetchColumn();
        if ($nom) {
            $uploader = new \API\Services\FileUploadService('devoirs');
            $uploader->delete($nom);
        }
        $stmt = $this->pdo->prepare('DELETE FROM devoirs_fichiers WHERE id = :id');
        return $stmt->execute([':id' => $fichierId]);
    }

    private function deleteAttachedFiles(int $devoirId): void
    {
        try {
            $fichiers = $this->getFichiers($devoirId);
            $uploader = new \API\Services\FileUploadService('devoirs');
            foreach ($fichiers as $f) {
                $uploader->delete($f['nom_stockage']);
            }
        } catch (\PDOException $e) {
            // Ignore if table doesn't exist
        }
    }

    /* ══════════ Devoir fait (UX-3) ══════════ */

    public function isDevoirFait(int $eleveId, int $devoirId): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT fait FROM devoirs_statuts_eleve WHERE eleve_id = :eid AND devoir_id = :did'
            );
            $stmt->execute([':eid' => $eleveId, ':did' => $devoirId]);
            return (bool) $stmt->fetchColumn();
        } catch (\PDOException $e) {
            return false;
        }
    }

    public function toggleDevoirFait(int $eleveId, int $devoirId): bool
    {
        $fait = $this->isDevoirFait($eleveId, $devoirId);
        if ($fait) {
            $stmt = $this->pdo->prepare(
                'UPDATE devoirs_statuts_eleve SET fait = 0, date_marque = NULL
                 WHERE eleve_id = :eid AND devoir_id = :did'
            );
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO devoirs_statuts_eleve (eleve_id, devoir_id, fait, date_marque)
                 VALUES (:eid, :did, 1, NOW())
                 ON DUPLICATE KEY UPDATE fait = 1, date_marque = NOW()'
            );
        }
        $stmt->execute([':eid' => $eleveId, ':did' => $devoirId]);
        return !$fait;
    }

    public function getDevoirsFaitsIds(int $eleveId): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT devoir_id FROM devoirs_statuts_eleve WHERE eleve_id = :eid AND fait = 1'
            );
            $stmt->execute([':eid' => $eleveId]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (\PDOException $e) {
            return [];
        }
    }

    /* ══════════ Helpers formulaire ══════════ */

    public function getProfesseurs(): array
    {
        return $this->pdo->query('SELECT id, nom, prenom, matiere FROM professeurs ORDER BY nom, prenom')
                         ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProfesseurInfo(int $profId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, nom, prenom, matiere FROM professeurs WHERE id = :id');
        $stmt->execute([':id' => $profId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getEtablissementData(): array
    {
        $file = __DIR__ . '/../../login/data/etablissement.json';
        if (file_exists($file)) {
            return json_decode(file_get_contents($file), true) ?: [];
        }
        return [];
    }

    /* ══════════ Helpers privés ══════════ */

    private function getEleveClasse(?int $eleveId): ?string
    {
        if (!$eleveId) return null;
        $stmt = $this->pdo->prepare('SELECT classe FROM eleves WHERE id = :eid LIMIT 1');
        $stmt->execute([':eid' => $eleveId]);
        return $stmt->fetchColumn() ?: null;
    }

    private function buildOrder(string $field, string $dir): string
    {
        $col = self::ORDER_WHITELIST[$field] ?? 'date_rendu';
        $dir = in_array(strtolower($dir), self::ALLOWED_DIRS, true) ? strtoupper($dir) : 'DESC';

        if (in_array($field, ['nom_matiere', 'classe'], true)) {
            return "{$col} {$dir}, date_rendu ASC";
        }
        return "{$col} {$dir}";
    }

    /* ══════════ Export devoirs ══════════ */

    /**
     * Export des devoirs pour ExportService.
     */
    public function getDevoirsForExport(string $role, ?int $userId, ?string $userFullName, array $filters = []): array
    {
        $query = $this->buildQuery($role, $userId, $userFullName, $filters, 'date_rendu', 'desc', 1, 9999);
        [$devoirs] = $this->executeQuery($query);

        $result = [];
        foreach ($devoirs as $d) {
            $status = $this->computeStatus($d['date_rendu']);
            $result[] = [
                'Matière'     => $d['nom_matiere'] ?? '',
                'Classe'      => $d['classe'] ?? '',
                'Titre'       => $d['titre'],
                'Description' => mb_substr(strip_tags($d['description'] ?? ''), 0, 200),
                'Date rendu'  => date('d/m/Y', strtotime($d['date_rendu'])),
                'Statut'      => $status['label'] ?? '',
                'Professeur'  => $d['professeur'] ?? '',
                'Type'        => $d['type_devoir'] ?? 'devoir',
            ];
        }
        return $result;
    }

    /* ══════════ Soumission de travaux (élèves) ══════════ */

    /**
     * Soumet un fichier de travail pour un devoir.
     */
    public function createSubmission(int $devoirId, int $eleveId, string $filePath, string $fileName): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO devoir_submissions (devoir_id, eleve_id, fichier_path, fichier_nom, soumis_at, statut)
             VALUES (?, ?, ?, ?, NOW(), 'soumis')
             ON DUPLICATE KEY UPDATE fichier_path = VALUES(fichier_path),
                fichier_nom = VALUES(fichier_nom), soumis_at = NOW(), statut = 'soumis'"
        );
        $stmt->execute([$devoirId, $eleveId, $filePath, $fileName]);

        // Marquer comme fait automatiquement
        $this->toggleDevoirFaitForce($eleveId, $devoirId, true);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Récupère la soumission d'un élève pour un devoir.
     */
    public function getSubmission(int $devoirId, int $eleveId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM devoir_submissions WHERE devoir_id = ? AND eleve_id = ?"
        );
        $stmt->execute([$devoirId, $eleveId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Liste toutes les soumissions pour un devoir (vue professeur).
     */
    public function getSubmissions(int $devoirId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT ds.*, CONCAT(e.prenom, ' ', e.nom) AS eleve_nom, e.classe
             FROM devoir_submissions ds
             JOIN eleves e ON ds.eleve_id = e.id
             WHERE ds.devoir_id = ?
             ORDER BY e.nom, e.prenom"
        );
        $stmt->execute([$devoirId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Note/évalue une soumission (professeur).
     */
    public function gradeSubmission(int $submissionId, ?float $note, ?string $commentaire): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE devoir_submissions SET note = ?, commentaire_prof = ?, statut = 'evalue', evalue_at = NOW()
             WHERE id = ?"
        );
        return $stmt->execute([$note, $commentaire, $submissionId]);
    }

    /**
     * Force le statut "fait" sans toggle.
     */
    private function toggleDevoirFaitForce(int $eleveId, int $devoirId, bool $fait): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO devoirs_statuts_eleve (eleve_id, devoir_id, fait, date_marque)
                 VALUES (:eid, :did, :fait, NOW())
                 ON DUPLICATE KEY UPDATE fait = :fait2, date_marque = NOW()'
            );
            $stmt->execute([':eid' => $eleveId, ':did' => $devoirId, ':fait' => $fait ? 1 : 0, ':fait2' => $fait ? 1 : 0]);
        } catch (\PDOException $e) {
            // Ignore
        }
    }
}
