<?php
/**
 * M26 – Inscriptions en ligne — Service
 */
class InscriptionService
{
    private PDO $pdo;

    /** Transitions de workflow valides */
    const WORKFLOW_TRANSITIONS = [
        'brouillon'       => ['soumise'],
        'soumise'         => ['en_revision', 'acceptee', 'refusee', 'liste_attente'],
        'en_revision'     => ['soumise', 'acceptee', 'refusee', 'liste_attente', 'documents_requis'],
        'documents_requis'=> ['en_revision', 'soumise'],
        'liste_attente'   => ['acceptee', 'refusee'],
        'acceptee'        => ['annulee'],
        'refusee'         => ['soumise'],    // Ré-examen possible
        'annulee'         => [],             // Terminal
    ];

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

    /**
     * Change le statut d'une inscription avec validation du workflow.
     */
    public function changerStatut(int $id, string $newStatut, int $traitePar = null, ?string $commentaire = null): void
    {
        // Récupérer le statut actuel
        $stmt = $this->pdo->prepare('SELECT statut FROM inscriptions WHERE id = ?');
        $stmt->execute([$id]);
        $currentStatut = $stmt->fetchColumn();

        if (!$currentStatut) {
            throw new \RuntimeException("Inscription introuvable");
        }

        // Valider la transition
        $allowed = self::WORKFLOW_TRANSITIONS[$currentStatut] ?? [];
        if (!in_array($newStatut, $allowed, true)) {
            throw new \RuntimeException("Transition invalide : {$currentStatut} → {$newStatut}");
        }

        $sql = 'UPDATE inscriptions SET statut = ?, date_traitement = NOW()';
        $params = [$newStatut];
        if ($traitePar) {
            $sql .= ', traite_par = ?';
            $params[] = $traitePar;
        }
        if ($commentaire !== null) {
            // Use workflow_statut + commentaire if columns exist
            $sql .= ', commentaire_traitement = ?';
            $params[] = $commentaire;
        }
        $sql .= ' WHERE id = ?';
        $params[] = $id;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        // Log audit
        $this->logAction($id, 'changement_statut', $traitePar, "{$currentStatut} → {$newStatut}" . ($commentaire ? " | {$commentaire}" : ''));
    }

    /**
     * Log une action sur une inscription.
     */
    private function logAction(int $inscriptionId, string $action, ?int $userId, string $details): void
    {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO audit_log (action, table_name, record_id, user_id, user_type, details, created_at)
                 VALUES (?, 'inscriptions', ?, ?, 'administrateur', ?, NOW())"
            );
            $stmt->execute([$action, $inscriptionId, $userId, $details]);
        } catch (\PDOException $e) {
            error_log("InscriptionService::logAction error: " . $e->getMessage());
        }
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
            'brouillon'        => '<span class="badge badge-secondary">Brouillon</span>',
            'soumise'          => '<span class="badge badge-info">Soumise</span>',
            'en_revision'      => '<span class="badge badge-warning">En révision</span>',
            'documents_requis' => '<span class="badge badge-warning">Documents requis</span>',
            'acceptee'         => '<span class="badge badge-success">Acceptée</span>',
            'refusee'          => '<span class="badge badge-danger">Refusée</span>',
            'liste_attente'    => '<span class="badge badge-secondary">Liste d\'attente</span>',
            'annulee'          => '<span class="badge badge-danger">Annulée</span>',
        ];
        return $map[$statut] ?? '<span class="badge">' . htmlspecialchars($statut) . '</span>';
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

    /**
     * Export des inscriptions pour ExportService.
     */
    public function getInscriptionsForExport(array $filters = []): array
    {
        $inscriptions = $this->getToutesInscriptions($filters);
        $result = [];
        foreach ($inscriptions as $i) {
            $result[] = [
                'ID'                => $i['id'],
                'Nom'               => $i['nom_eleve'],
                'Prénom'            => $i['prenom_eleve'],
                'Date naissance'    => $i['date_naissance'] ? date('d/m/Y', strtotime($i['date_naissance'])) : '',
                'Sexe'              => $i['sexe'] ?? '',
                'Classe demandée'   => $i['classe_nom'] ?? '',
                'Statut'            => $i['statut'],
                'Date soumission'   => $i['date_soumission'] ? date('d/m/Y H:i', strtotime($i['date_soumission'])) : '',
                'Email contact'     => $i['email_contact'] ?? '',
                'Téléphone'         => $i['telephone'] ?? '',
            ];
        }
        return $result;
    }

    /**
     * Retourne les transitions possibles pour un statut donné.
     */
    public static function getTransitionsPossibles(string $statut): array
    {
        return self::WORKFLOW_TRANSITIONS[$statut] ?? [];
    }

    /**
     * Labels pour les statuts.
     */
    public static function statutLabels(): array
    {
        return [
            'brouillon'        => 'Brouillon',
            'soumise'          => 'Soumise',
            'en_revision'      => 'En révision',
            'documents_requis' => 'Documents requis',
            'acceptee'         => 'Acceptée',
            'refusee'          => 'Refusée',
            'liste_attente'    => 'Liste d\'attente',
            'annulee'          => 'Annulée',
        ];
    }
}
