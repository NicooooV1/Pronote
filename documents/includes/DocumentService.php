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
}
