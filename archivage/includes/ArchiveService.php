<?php
/**
 * M35 – Archivage annuel — Service
 */
class ArchiveService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Liste des archives
     */
    public function getArchives(string $annee = null, string $type = null): array
    {
        $sql = 'SELECT * FROM archives_annuelles WHERE 1=1';
        $params = [];
        if ($annee) { $sql .= ' AND annee_scolaire = ?'; $params[] = $annee; }
        if ($type) { $sql .= ' AND type = ?'; $params[] = $type; }
        $sql .= ' ORDER BY date_archive DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupérer une archive
     */
    public function getArchive(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM archives_annuelles WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Archiver les notes d'une année
     */
    public function archiverNotes(string $annee): int
    {
        $stmt = $this->pdo->prepare("
            SELECT n.*, m.nom AS matiere_nom, e.prenom, e.nom AS eleve_nom, c.nom AS classe_nom
            FROM notes n
            JOIN matieres m ON n.matiere_id = m.id
            JOIN eleves e ON n.eleve_id = e.id
            JOIN classes c ON e.classe_id = c.id
        ");
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->sauvegarderArchive($annee, 'notes', $data);
    }

    /**
     * Archiver les absences d'une année
     */
    public function archiverAbsences(string $annee): int
    {
        $stmt = $this->pdo->prepare("
            SELECT a.*, e.prenom, e.nom AS eleve_nom, c.nom AS classe_nom
            FROM absences a
            JOIN eleves e ON a.eleve_id = e.id
            JOIN classes c ON e.classe_id = c.id
        ");
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->sauvegarderArchive($annee, 'absences', $data);
    }

    /**
     * Archiver les bulletins d'une année
     */
    public function archiverBulletins(string $annee): int
    {
        $allowedTables = ['bulletins', 'bulletin_matieres'];
        $data = [];
        foreach ($allowedTables as $table) {
            $stmt = $this->pdo->query("SELECT * FROM `{$table}`");
            $data[$table] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        return $this->sauvegarderArchive($annee, 'bulletins', $data);
    }

    /**
     * Archiver les devoirs
     */
    public function archiverDevoirs(string $annee): int
    {
        $stmt = $this->pdo->query("SELECT * FROM devoirs");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $this->sauvegarderArchive($annee, 'devoirs', $data);
    }

    /**
     * Archiver les incidents
     */
    public function archiverIncidents(string $annee): int
    {
        $stmt = $this->pdo->query("SELECT * FROM incidents");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $this->sauvegarderArchive($annee, 'incidents', $data);
    }

    /**
     * Archiver tout pour une année
     */
    public function archiverTout(string $annee): array
    {
        $resultats = [];
        $types = ['notes', 'absences', 'bulletins', 'devoirs', 'incidents'];
        foreach ($types as $type) {
            $method = 'archiver' . ucfirst($type);
            $resultats[$type] = $this->$method($annee);
        }
        return $resultats;
    }

    /**
     * Sauvegarder une archive
     */
    private function sauvegarderArchive(string $annee, string $type, array $data): int
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $dir = __DIR__ . '/../exports/';
        if (!is_dir($dir)) { mkdir($dir, 0755, true); }

        $filename = "archive_{$annee}_{$type}_" . date('Ymd_His') . '.json';
        file_put_contents($dir . $filename, $json);

        $stmt = $this->pdo->prepare("
            INSERT INTO archives_annuelles (annee_scolaire, type, donnees, fichier_chemin, date_archive)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$annee, $type, $json, 'exports/' . $filename]);
        return $this->pdo->lastInsertId();
    }

    /**
     * Verrouiller/déverrouiller une archive
     */
    public function verrouillerArchive(int $id, bool $verrouiller): void
    {
        $stmt = $this->pdo->prepare('UPDATE archives_annuelles SET verrouille = ? WHERE id = ?');
        $stmt->execute([$verrouiller ? 1 : 0, $id]);
    }

    /**
     * Supprimer une archive (si non verrouillée)
     */
    public function supprimerArchive(int $id): bool
    {
        $archive = $this->getArchive($id);
        if (!$archive || $archive['verrouille']) return false;

        // Supprimer le fichier
        $path = __DIR__ . '/../' . $archive['fichier_chemin'];
        if (file_exists($path)) unlink($path);

        $stmt = $this->pdo->prepare('DELETE FROM archives_annuelles WHERE id = ? AND verrouille = 0');
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Télécharger une archive
     */
    public function getCheminFichier(int $id): ?string
    {
        $archive = $this->getArchive($id);
        if (!$archive) return null;
        $path = __DIR__ . '/../' . $archive['fichier_chemin'];
        return file_exists($path) ? $path : null;
    }

    /**
     * Stats
     */
    public function getStats(): array
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) AS total, COUNT(CASE WHEN verrouille = 1 THEN 1 END) AS verrouillee, COUNT(DISTINCT annee_scolaire) AS annees FROM archives_annuelles");
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Années disponibles
     */
    public function getAnneesDisponibles(): array
    {
        $stmt = $this->pdo->query('SELECT DISTINCT annee_scolaire FROM archives_annuelles ORDER BY annee_scolaire DESC');
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /* ───── STUDENT TRANSFER ───── */

    /**
     * Export a student's complete dossier for transfer to another school.
     */
    public function exporterDossierEleve(int $eleveId): array
    {
        $dossier = [];

        // Student info
        $stmt = $this->pdo->prepare("SELECT e.*, c.nom AS classe_nom FROM eleves e LEFT JOIN classes c ON e.classe_id = c.id WHERE e.id = ?");
        $stmt->execute([$eleveId]);
        $dossier['eleve'] = $stmt->fetch(PDO::FETCH_ASSOC);

        // Notes
        $stmt = $this->pdo->prepare("SELECT n.*, m.nom AS matiere_nom FROM notes n JOIN matieres m ON n.matiere_id = m.id WHERE n.eleve_id = ? ORDER BY n.date_evaluation DESC");
        $stmt->execute([$eleveId]);
        $dossier['notes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Absences
        $stmt = $this->pdo->prepare("SELECT * FROM absences WHERE eleve_id = ? ORDER BY date_absence DESC");
        $stmt->execute([$eleveId]);
        $dossier['absences'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Bulletins
        $stmt = $this->pdo->prepare("SELECT b.* FROM bulletins b WHERE b.eleve_id = ? ORDER BY b.periode_id");
        $stmt->execute([$eleveId]);
        $dossier['bulletins'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fiche santé
        $stmt = $this->pdo->prepare("SELECT * FROM fiches_sante WHERE eleve_id = ?");
        $stmt->execute([$eleveId]);
        $dossier['fiche_sante'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        // Save as file
        $json = json_encode($dossier, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $dir = __DIR__ . '/../exports/';
        if (!is_dir($dir)) { mkdir($dir, 0755, true); }
        $filename = "transfert_eleve_{$eleveId}_" . date('Ymd_His') . '.json';
        file_put_contents($dir . $filename, $json);

        return ['fichier' => $filename, 'chemin' => $dir . $filename, 'dossier' => $dossier];
    }

    public static function typesArchive(): array
    {
        return [
            'notes' => 'Notes',
            'absences' => 'Absences',
            'bulletins' => 'Bulletins',
            'devoirs' => 'Devoirs',
            'incidents' => 'Incidents',
        ];
    }

    // ─── ARCHIVAGE PLANIFIÉ ───

    public function planifierArchivage(string $annee, string $dateExecution, ?int $creerPar = null): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO archives_planifiees (annee_scolaire, date_execution, cree_par, statut, created_at) VALUES (:a, :d, :c, 'planifie', NOW())");
        $stmt->execute([':a' => $annee, ':d' => $dateExecution, ':c' => $creerPar]);
        return (int)$this->pdo->lastInsertId();
    }

    public function executerArchivagesPlanifies(): int
    {
        $stmt = $this->pdo->query("SELECT * FROM archives_planifiees WHERE statut = 'planifie' AND date_execution <= NOW()");
        $count = 0;
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $plan) {
            $this->archiverTout($plan['annee_scolaire']);
            $this->pdo->prepare("UPDATE archives_planifiees SET statut = 'execute', date_execution_effective = NOW() WHERE id = ?")->execute([$plan['id']]);
            $count++;
        }
        return $count;
    }

    // ─── COMPARAISON INTER-ANNUELLES ───

    public function comparerAnnees(string $annee1, string $annee2, string $type): array
    {
        $a1 = $this->getArchives($annee1, $type);
        $a2 = $this->getArchives($annee2, $type);
        $data1 = !empty($a1) ? json_decode($a1[0]['donnees'] ?? '[]', true) : [];
        $data2 = !empty($a2) ? json_decode($a2[0]['donnees'] ?? '[]', true) : [];
        return [
            'annee_1' => ['annee' => $annee1, 'count' => is_array($data1) ? count($data1) : 0],
            'annee_2' => ['annee' => $annee2, 'count' => is_array($data2) ? count($data2) : 0],
            'type' => $type,
        ];
    }

    // ─── INTÉGRITÉ ARCHIVES ───

    public function verifierIntegrite(int $archiveId): array
    {
        $archive = $this->getArchive($archiveId);
        if (!$archive) return ['valid' => false, 'error' => 'Archive introuvable'];

        $fichierExiste = false;
        if (!empty($archive['fichier_chemin'])) {
            $path = __DIR__ . '/../' . $archive['fichier_chemin'];
            $fichierExiste = file_exists($path);
        }

        $donneesValides = !empty($archive['donnees']) && json_decode($archive['donnees'], true) !== null;

        return [
            'id' => $archiveId,
            'valid' => $fichierExiste && $donneesValides,
            'fichier_existe' => $fichierExiste,
            'donnees_valides' => $donneesValides,
            'verrouille' => (bool)$archive['verrouille'],
            'taille' => $fichierExiste ? filesize($path) : 0,
        ];
    }

    public function verifierToutesIntegrites(): array
    {
        $archives = $this->getArchives();
        $results = [];
        foreach ($archives as $a) {
            $results[] = $this->verifierIntegrite($a['id']);
        }
        return $results;
    }
}
