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

    /* ───────── WAITLIST ───────── */

    /**
     * Get waitlisted inscriptions sorted by position.
     */
    public function getListeAttente(?int $classeId = null): array
    {
        $sql = "SELECT i.*, c.nom AS classe_nom FROM inscriptions i
                LEFT JOIN classes c ON i.classe_demandee = c.id
                WHERE i.statut = 'liste_attente'";
        $params = [];
        if ($classeId) {
            $sql .= " AND i.classe_demandee = ?";
            $params[] = $classeId;
        }
        $sql .= " ORDER BY i.waitlist_position ASC, i.date_soumission ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Set waitlist position.
     */
    public function setWaitlistPosition(int $inscriptionId, int $position): void
    {
        $this->pdo->prepare("UPDATE inscriptions SET waitlist_position = ? WHERE id = ?")
                   ->execute([$position, $inscriptionId]);
    }

    /**
     * Promote first in waitlist to accepted when a spot opens.
     */
    public function promoteFromWaitlist(int $classeId, int $traitePar): ?int
    {
        $liste = $this->getListeAttente($classeId);
        if (empty($liste)) return null;

        $first = $liste[0];
        $this->changerStatut($first['id'], 'acceptee', $traitePar, 'Promotion automatique depuis la liste d\'attente');
        return $first['id'];
    }

    /* ───────── MULTI-STEP FORM ───────── */

    /**
     * Save progress for a multi-step inscription form.
     */
    public function saveStep(int $inscriptionId, int $step, array $data): void
    {
        $this->pdo->prepare("UPDATE inscriptions SET step_current = ?, step_data = ? WHERE id = ?")
                   ->execute([$step, json_encode($data, JSON_UNESCAPED_UNICODE), $inscriptionId]);
    }

    /**
     * Get step data for resuming a multi-step form.
     */
    public function getStepData(int $inscriptionId): array
    {
        $stmt = $this->pdo->prepare("SELECT step_current, step_data FROM inscriptions WHERE id = ?");
        $stmt->execute([$inscriptionId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return [
            'step' => (int)($row['step_current'] ?? 1),
            'data' => json_decode($row['step_data'] ?? '{}', true) ?: [],
        ];
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

    // ─── PORTAIL PUBLIC ───

    public function getFormulairePublic(): array
    {
        return [
            'champs' => [
                ['key' => 'nom_eleve', 'label' => 'Nom', 'type' => 'text', 'required' => true],
                ['key' => 'prenom_eleve', 'label' => 'Prénom', 'type' => 'text', 'required' => true],
                ['key' => 'date_naissance', 'label' => 'Date de naissance', 'type' => 'date', 'required' => true],
                ['key' => 'sexe', 'label' => 'Sexe', 'type' => 'select', 'options' => ['M' => 'Masculin', 'F' => 'Féminin'], 'required' => true],
                ['key' => 'classe_demandee', 'label' => 'Classe demandée', 'type' => 'select', 'options_from' => 'classes'],
                ['key' => 'email_contact', 'label' => 'Email du responsable', 'type' => 'email', 'required' => true],
                ['key' => 'telephone', 'label' => 'Téléphone', 'type' => 'tel', 'required' => true],
                ['key' => 'adresse', 'label' => 'Adresse', 'type' => 'textarea'],
            ],
            'documents_requis' => self::typesDocument(),
            'classes' => $this->getClasses(),
        ];
    }

    // ─── CHECKLIST DOCUMENTS ───

    public function getChecklistCompletion(int $inscriptionId): array
    {
        $docs = $this->getDocuments($inscriptionId);
        $typesReq = self::typesDocument();
        $obligatoires = ['certificat_scolarite', 'carte_identite', 'justificatif_domicile', 'photo_identite'];
        $result = [];
        foreach ($typesReq as $key => $label) {
            $found = array_filter($docs, fn($d) => $d['type_document'] === $key);
            $foundDoc = !empty($found) ? reset($found) : null;
            $result[] = [
                'type' => $key,
                'label' => $label,
                'obligatoire' => in_array($key, $obligatoires),
                'fourni' => !empty($found),
                'valide' => $foundDoc && ($foundDoc['valide'] ?? false),
                'document_id' => $foundDoc['id'] ?? null,
            ];
        }
        return $result;
    }

    // ─── AFFECTATION CLASSE AUTO ───

    public function suggestClasse(int $inscriptionId): ?string
    {
        $stmt = $this->pdo->prepare("SELECT date_naissance, classe_demandee FROM inscriptions WHERE id = ?");
        $stmt->execute([$inscriptionId]);
        $insc = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$insc) return null;

        if ($insc['classe_demandee']) return $insc['classe_demandee'];

        $age = (int)date_diff(date_create($insc['date_naissance']), date_create())->y;
        $niveauMap = [
            6 => '6eme', 7 => '6eme', 8 => '6eme',
            9 => '5eme', 10 => '5eme',
            11 => '4eme', 12 => '4eme',
            13 => '3eme', 14 => '3eme',
            15 => '2nde', 16 => '1ere', 17 => 'terminale',
        ];
        $niveau = $niveauMap[$age] ?? null;
        if (!$niveau) return null;

        $stmt2 = $this->pdo->prepare("
            SELECT c.id, c.nom, c.capacite_max,
                   (SELECT COUNT(*) FROM eleves e WHERE e.classe_id = c.id) AS effectif
            FROM classes c WHERE c.niveau = ?
            HAVING effectif < COALESCE(c.capacite_max, 35)
            ORDER BY effectif ASC LIMIT 1
        ");
        $stmt2->execute([$niveau]);
        $classe = $stmt2->fetch(\PDO::FETCH_ASSOC);
        return $classe ? $classe['id'] : null;
    }

    // ─── LETTRE ADMISSION PDF ───

    public function genererLettreAdmissionData(int $inscriptionId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT i.*, c.nom AS classe_nom FROM inscriptions i
            LEFT JOIN classes c ON i.classe_demandee = c.id
            WHERE i.id = ?
        ");
        $stmt->execute([$inscriptionId]);
        $insc = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$insc || $insc['statut'] !== 'acceptee') {
            throw new \RuntimeException('Inscription non acceptée');
        }
        return [
            'titre' => 'Lettre d\'admission',
            'date' => date('d/m/Y'),
            'eleve' => $insc['prenom_eleve'] . ' ' . $insc['nom_eleve'],
            'date_naissance' => $insc['date_naissance'],
            'classe' => $insc['classe_nom'] ?? 'À déterminer',
            'email_contact' => $insc['email_contact'] ?? '',
            'inscription_id' => $inscriptionId,
        ];
    }

    // ─── CAMPAGNE REINSCRIPTION ───

    public function lancerCampagneReinscription(string $anneeCible): int
    {
        $stmt = $this->pdo->query("
            SELECT e.id, e.nom, e.prenom, e.date_naissance, c.nom AS classe_nom, c.id AS classe_id,
                   pe.parent_id
            FROM eleves e
            LEFT JOIN classes c ON e.classe_id = c.id
            LEFT JOIN parent_eleve pe ON pe.eleve_id = e.id
            WHERE e.actif = 1
        ");
        $eleves = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $count = 0;
        foreach ($eleves as $el) {
            $exists = $this->pdo->prepare("SELECT id FROM inscriptions WHERE nom_eleve = ? AND prenom_eleve = ? AND annee_scolaire = ?");
            $exists->execute([$el['nom'], $el['prenom'], $anneeCible]);
            if ($exists->fetch()) continue;

            $this->pdo->prepare("
                INSERT INTO inscriptions (nom_eleve, prenom_eleve, date_naissance, classe_demandee, statut, annee_scolaire, date_soumission, type_inscription)
                VALUES (?, ?, ?, ?, 'brouillon', ?, NOW(), 'reinscription')
            ")->execute([$el['nom'], $el['prenom'], $el['date_naissance'], $el['classe_id'], $anneeCible]);
            $count++;
        }
        return $count;
    }
}
