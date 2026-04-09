<?php
/**
 * M16 – Documents administratifs — Service
 */

class DocumentService {
    private PDO $pdo;

    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    /**
     * Récupère les documents visibles pour un rôle donné
     */
    public function getDocuments(string $role, ?string $categorie = null, ?string $search = null): array {
        $sql = "SELECT d.*, 
                CASE d.auteur_type
                    WHEN 'administrateur' THEN (SELECT CONCAT(prenom,' ',nom) FROM administrateurs WHERE id=d.auteur_id)
                    WHEN 'professeur' THEN (SELECT CONCAT(prenom,' ',nom) FROM professeurs WHERE id=d.auteur_id)
                    WHEN 'vie_scolaire' THEN (SELECT CONCAT(prenom,' ',nom) FROM vie_scolaire WHERE id=d.auteur_id)
                    ELSE 'Système'
                END AS auteur_nom
                FROM documents d WHERE 1=1";
        $params = [];

        // Filtrer uniquement les documents visibles pour ce rôle
        $sql .= " AND (d.visibilite IS NULL OR d.visibilite = '[]' OR JSON_CONTAINS(d.visibilite, ?))";
        $params[] = json_encode($role);

        if ($categorie) {
            $sql .= " AND d.categorie = ?";
            $params[] = $categorie;
        }
        if ($search) {
            $sql .= " AND (d.titre LIKE ? OR d.description LIKE ?)";
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
        }
        $sql .= " ORDER BY d.date_creation DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère un document par ID
     */
    public function getDocument(int $id): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM documents WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Ajouter un document
     */
    public function ajouter(array $data, array $file): int {
        $uploadDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = uniqid('doc_') . '.' . $ext;
        move_uploaded_file($file['tmp_name'], $uploadDir . $filename);

        $visibilite = !empty($data['visibilite']) ? json_encode($data['visibilite']) : '[]';

        $stmt = $this->pdo->prepare("
            INSERT INTO documents (titre, description, categorie, fichier_nom, fichier_chemin, fichier_taille, fichier_type,
                                   visibilite, auteur_id, auteur_type, date_creation)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $data['titre'],
            $data['description'] ?? '',
            $data['categorie'] ?? 'autre',
            $file['name'],
            'uploads/' . $filename,
            $file['size'],
            $file['type'],
            $visibilite,
            $data['auteur_id'],
            $data['auteur_type']
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Supprimer un document
     */
    public function supprimer(int $id): bool {
        $doc = $this->getDocument($id);
        if (!$doc) return false;

        $filepath = __DIR__ . '/../' . $doc['fichier_chemin'];
        if (file_exists($filepath)) unlink($filepath);

        $stmt = $this->pdo->prepare("DELETE FROM documents WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Incrémenter le compteur de téléchargements
     */
    public function incrementerTelechargements(int $id): void {
        $this->pdo->prepare("UPDATE documents SET telechargements = telechargements + 1 WHERE id = ?")->execute([$id]);
    }

    /**
     * Catégories disponibles
     */
    public static function categories(): array {
        return [
            'administratif' => 'Administratif',
            'pedagogique'   => 'Pédagogique',
            'reglementaire' => 'Réglementaire',
            'formulaire'    => 'Formulaire',
            'communication' => 'Communication',
            'autre'         => 'Autre',
        ];
    }

    /**
     * Icône de fichier
     */
    public static function fileIcon(string $type): string {
        if (str_contains($type, 'pdf')) return 'fa-file-pdf text-danger';
        if (str_contains($type, 'word') || str_contains($type, 'document')) return 'fa-file-word text-primary';
        if (str_contains($type, 'sheet') || str_contains($type, 'excel')) return 'fa-file-excel text-success';
        if (str_contains($type, 'image')) return 'fa-file-image text-warning';
        if (str_contains($type, 'presentation') || str_contains($type, 'powerpoint')) return 'fa-file-powerpoint text-danger';
        if (str_contains($type, 'zip') || str_contains($type, 'archive')) return 'fa-file-archive text-secondary';
        return 'fa-file text-secondary';
    }

    /**
     * Taille formatée
     */
    public static function formatSize(int $bytes): string {
        if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' Mo';
        if ($bytes >= 1024) return round($bytes / 1024, 1) . ' Ko';
        return $bytes . ' o';
    }

    // ─── Versioning ─────────────────────────────────────────────

    /**
     * Upload a new version of a document. Keeps old version in history.
     */
    public function uploadNewVersion(int $docId, array $file, int $uploadedBy, string $uploadedByType): int
    {
        $doc = $this->getDocument($docId);
        if (!$doc) throw new \Exception("Document introuvable");

        // Save current version to history
        $currentVersion = (int)($doc['version'] ?? 1);
        $stmtHist = $this->pdo->prepare("
            INSERT INTO document_versions (document_id, version, fichier_chemin, fichier_nom, fichier_taille, fichier_type, uploaded_by, uploaded_by_type, uploaded_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmtHist->execute([
            $docId, $currentVersion,
            $doc['fichier_chemin'], $doc['fichier_nom'],
            $doc['fichier_taille'], $doc['fichier_type'],
            $uploadedBy, $uploadedByType
        ]);

        // Upload new file
        $uploadDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = uniqid('doc_v' . ($currentVersion + 1) . '_') . '.' . $ext;
        move_uploaded_file($file['tmp_name'], $uploadDir . $filename);

        // Update document with new file
        $newVersion = $currentVersion + 1;
        $stmt = $this->pdo->prepare("
            UPDATE documents SET fichier_nom = ?, fichier_chemin = ?, fichier_taille = ?,
                fichier_type = ?, version = ?, date_modification = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$file['name'], 'uploads/' . $filename, $file['size'], $file['type'], $newVersion, $docId]);

        return $newVersion;
    }

    /**
     * Get version history for a document.
     */
    public function getVersions(int $docId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT dv.*,
                CASE dv.uploaded_by_type
                    WHEN 'administrateur' THEN (SELECT CONCAT(prenom,' ',nom) FROM administrateurs WHERE id=dv.uploaded_by)
                    WHEN 'professeur' THEN (SELECT CONCAT(prenom,' ',nom) FROM professeurs WHERE id=dv.uploaded_by)
                    ELSE 'Système'
                END AS uploaded_by_nom
            FROM document_versions dv
            WHERE dv.document_id = ?
            ORDER BY dv.version DESC
        ");
        $stmt->execute([$docId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Restore a previous version of a document.
     */
    public function restoreVersion(int $docId, int $versionNumber, int $userId, string $userType): bool
    {
        $stmt = $this->pdo->prepare("SELECT * FROM document_versions WHERE document_id = ? AND version = ?");
        $stmt->execute([$docId, $versionNumber]);
        $version = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$version) return false;

        // Save current as new version entry first
        $this->uploadNewVersionFromExisting($docId, $userId, $userType);

        // Restore the old version's file info
        $this->pdo->prepare("
            UPDATE documents SET fichier_nom = ?, fichier_chemin = ?, fichier_taille = ?,
                fichier_type = ?, date_modification = NOW()
            WHERE id = ?
        ")->execute([$version['fichier_nom'], $version['fichier_chemin'], $version['fichier_taille'], $version['fichier_type'], $docId]);

        return true;
    }

    private function uploadNewVersionFromExisting(int $docId, int $userId, string $userType): void
    {
        $doc = $this->getDocument($docId);
        if (!$doc) return;
        $currentVersion = (int)($doc['version'] ?? 1);
        $this->pdo->prepare("
            INSERT INTO document_versions (document_id, version, fichier_chemin, fichier_nom, fichier_taille, fichier_type, uploaded_by, uploaded_by_type, uploaded_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ")->execute([$docId, $currentVersion, $doc['fichier_chemin'], $doc['fichier_nom'], $doc['fichier_taille'], $doc['fichier_type'], $userId, $userType]);
        $this->pdo->prepare("UPDATE documents SET version = ? WHERE id = ?")->execute([$currentVersion + 1, $docId]);
    }

    // ─── Sharing ─────────────────────────────────────────────────

    /**
     * Share a document with specific users or classes.
     */
    public function partager(int $docId, array $sharedWith): bool
    {
        $json = json_encode($sharedWith);
        $stmt = $this->pdo->prepare("UPDATE documents SET shared_with = ? WHERE id = ?");
        return $stmt->execute([$json, $docId]);
    }

    /**
     * Get documents shared with a specific user.
     */
    public function getDocumentsPartages(int $userId, string $userType): array
    {
        $stmt = $this->pdo->prepare("
            SELECT d.*,
                CASE d.auteur_type
                    WHEN 'administrateur' THEN (SELECT CONCAT(prenom,' ',nom) FROM administrateurs WHERE id=d.auteur_id)
                    WHEN 'professeur' THEN (SELECT CONCAT(prenom,' ',nom) FROM professeurs WHERE id=d.auteur_id)
                    ELSE 'Système'
                END AS auteur_nom
            FROM documents d
            WHERE JSON_CONTAINS(d.shared_with, ?)
               OR JSON_CONTAINS(d.shared_with, ?)
            ORDER BY d.date_creation DESC
        ");
        $userKey = json_encode(['type' => $userType, 'id' => $userId]);
        $roleKey = json_encode($userType);
        $stmt->execute([$userKey, $roleKey]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ─── VERSIONING ───

    public function creerVersion(int $documentId, string $fichierChemin, int $modifiePar): int
    {
        $version = $this->pdo->prepare("SELECT COALESCE(MAX(version),0)+1 FROM document_versions WHERE document_id = :did");
        $version->execute([':did' => $documentId]);
        $num = (int)$version->fetchColumn();

        $stmt = $this->pdo->prepare("INSERT INTO document_versions (document_id, version, fichier_chemin, modifie_par) VALUES (:did, :v, :fc, :mp)");
        $stmt->execute([':did' => $documentId, ':v' => $num, ':fc' => $fichierChemin, ':mp' => $modifiePar]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getVersions(int $documentId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM document_versions WHERE document_id = :did ORDER BY version DESC");
        $stmt->execute([':did' => $documentId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ─── DOSSIERS HIÉRARCHIQUES ───

    public function creerDossier(int $etabId, string $nom, ?int $parentId = null, int $ordre = 0): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO document_dossiers (etablissement_id, parent_id, nom, ordre) VALUES (:eid, :pid, :n, :o)");
        $stmt->execute([':eid' => $etabId, ':pid' => $parentId, ':n' => $nom, ':o' => $ordre]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getDossiers(int $etabId, ?int $parentId = null): array
    {
        $sql = "SELECT * FROM document_dossiers WHERE etablissement_id = :eid";
        $params = [':eid' => $etabId];
        if ($parentId === null) { $sql .= " AND parent_id IS NULL"; }
        else { $sql .= " AND parent_id = :pid"; $params[':pid'] = $parentId; }
        $sql .= " ORDER BY ordre, nom";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
