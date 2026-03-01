<?php
/**
 * M26 – Inscriptions en ligne — Service
 */
class InscriptionService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /* ───────── INSCRIPTIONS ───────── */

    public function creerInscription(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO inscriptions (
                parent_id, nom_eleve, prenom_eleve, date_naissance, sexe,
                classe_demandee, adresse, telephone, email_contact,
                etablissement_precedent, observations, statut, date_soumission
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'soumise', NOW())
        ");
        $stmt->execute([
            $data['parent_id'], $data['nom_eleve'], $data['prenom_eleve'],
            $data['date_naissance'], $data['sexe'], $data['classe_demandee'],
            $data['adresse'], $data['telephone'], $data['email_contact'],
            $data['etablissement_precedent'] ?? null, $data['observations'] ?? null
        ]);
        return $this->pdo->lastInsertId();
    }

    public function getInscription(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT i.*, c.nom AS classe_nom
            FROM inscriptions i
            LEFT JOIN classes c ON i.classe_demandee = c.id
            WHERE i.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getInscriptionsParent(int $parentId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT i.*, c.nom AS classe_nom
            FROM inscriptions i
            LEFT JOIN classes c ON i.classe_demandee = c.id
            WHERE i.parent_id = ?
            ORDER BY i.date_soumission DESC
        ");
        $stmt->execute([$parentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getToutesInscriptions(array $filters = []): array
    {
        $sql = "
            SELECT i.*, c.nom AS classe_nom
            FROM inscriptions i
            LEFT JOIN classes c ON i.classe_demandee = c.id
            WHERE 1=1
        ";
        $params = [];
        if (!empty($filters['statut'])) {
            $sql .= ' AND i.statut = ?';
            $params[] = $filters['statut'];
        }
        if (!empty($filters['classe'])) {
            $sql .= ' AND i.classe_demandee = ?';
            $params[] = $filters['classe'];
        }
        $sql .= ' ORDER BY i.date_soumission DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function changerStatut(int $id, string $statut, int $traitePar = null): void
    {
        $sql = 'UPDATE inscriptions SET statut = ?, date_traitement = NOW()';
        $params = [$statut];
        if ($traitePar) {
            $sql .= ', traite_par = ?';
            $params[] = $traitePar;
        }
        $sql .= ' WHERE id = ?';
        $params[] = $id;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    /* ───────── DOCUMENTS ───────── */

    public function ajouterDocument(int $inscriptionId, string $typeDoc, array $fichier): int
    {
        $dir = __DIR__ . '/../uploads/inscriptions/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $ext = pathinfo($fichier['name'], PATHINFO_EXTENSION);
        $filename = 'doc_' . $inscriptionId . '_' . uniqid() . '.' . $ext;
        move_uploaded_file($fichier['tmp_name'], $dir . $filename);

        $stmt = $this->pdo->prepare("
            INSERT INTO inscription_documents (inscription_id, type_document, fichier_chemin, date_ajout)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$inscriptionId, $typeDoc, 'uploads/inscriptions/' . $filename]);
        return $this->pdo->lastInsertId();
    }

    public function getDocuments(int $inscriptionId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM inscription_documents WHERE inscription_id = ? ORDER BY date_ajout');
        $stmt->execute([$inscriptionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function validerDocument(int $docId, bool $valide): void
    {
        $stmt = $this->pdo->prepare('UPDATE inscription_documents SET valide = ? WHERE id = ?');
        $stmt->execute([$valide ? 1 : 0, $docId]);
    }

    /* ───────── HELPERS ───────── */

    public function getClasses(): array
    {
        $stmt = $this->pdo->query('SELECT id, nom FROM classes ORDER BY nom');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getStats(): array
    {
        $stmt = $this->pdo->query("
            SELECT
                COUNT(*) AS total,
                COUNT(CASE WHEN statut = 'soumise' THEN 1 END) AS soumises,
                COUNT(CASE WHEN statut = 'en_revision' THEN 1 END) AS en_revision,
                COUNT(CASE WHEN statut = 'acceptee' THEN 1 END) AS acceptees,
                COUNT(CASE WHEN statut = 'refusee' THEN 1 END) AS refusees
            FROM inscriptions
        ");
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function statutBadge(string $statut): string
    {
        $map = [
            'soumise' => '<span class="badge badge-info">Soumise</span>',
            'en_revision' => '<span class="badge badge-warning">En révision</span>',
            'acceptee' => '<span class="badge badge-success">Acceptée</span>',
            'refusee' => '<span class="badge badge-danger">Refusée</span>',
            'liste_attente' => '<span class="badge badge-secondary">Liste d\'attente</span>',
        ];
        return $map[$statut] ?? '<span class="badge">' . $statut . '</span>';
    }

    public static function typesDocument(): array
    {
        return [
            'certificat_scolarite' => 'Certificat de scolarité',
            'carte_identite' => 'Carte d\'identité',
            'livret_famille' => 'Livret de famille',
            'justificatif_domicile' => 'Justificatif de domicile',
            'photo_identite' => 'Photo d\'identité',
            'bulletins' => 'Bulletins scolaires',
            'certificat_medical' => 'Certificat médical',
            'autre' => 'Autre',
        ];
    }
}
