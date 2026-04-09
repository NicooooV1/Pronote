<?php
/**
 * AbsenceRepository — Centralisation de toutes les requêtes SQL du module Absences.
 * Remplace les fonctions éparses de functions.php et les requêtes dupliquées
 * dans absences.php, retards.php, justificatifs.php, statistiques.php.
 */

class AbsenceRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /* ================================================================
     *  ABSENCES
     * ================================================================ */

    /**
     * Récupère les absences selon le rôle de l'utilisateur.
     * Centralise la logique dupliquée dans absences.php (~100 lignes).
     */
    public function getByRole(string $role, int $userId, array $filters = []): array
    {
        $dateDebut = $filters['date_debut'] ?? date('Y-m-d', strtotime('-30 days'));
        $dateFin   = $filters['date_fin']   ?? date('Y-m-d');
        $classe    = $filters['classe']     ?? '';
        $justifie  = $filters['justifie']   ?? '';

        switch ($role) {
            case 'admin':
            case 'vie_scolaire':
                return $this->getAbsencesAdmin($dateDebut, $dateFin, $classe, $justifie);

            case 'professeur':
                return $this->getAbsencesTeacher($userId, $dateDebut, $dateFin, $classe, $justifie);

            case 'eleve':
                return $this->getAbsencesEleve($userId, $dateDebut, $dateFin);

            case 'parent':
                return $this->getAbsencesParent($userId, $dateDebut, $dateFin, $justifie);

            default:
                return [];
        }
    }

    /**
     * Récupère les retards selon le rôle de l'utilisateur.
     */
    public function getRetardsByRole(string $role, int $userId, array $filters = []): array
    {
        $dateDebut = $filters['date_debut'] ?? date('Y-m-d', strtotime('-30 days'));
        $dateFin   = $filters['date_fin']   ?? date('Y-m-d');
        $classe    = $filters['classe']     ?? '';
        $justifie  = $filters['justifie']   ?? '';

        switch ($role) {
            case 'admin':
            case 'vie_scolaire':
                return $this->getRetardsAdmin($dateDebut, $dateFin, $classe, $justifie);

            case 'professeur':
                return $this->getRetardsTeacher($userId, $dateDebut, $dateFin, $classe, $justifie);

            case 'eleve':
                return $this->getRetardsEleve($userId, $dateDebut, $dateFin);

            case 'parent':
                return $this->getRetardsParent($userId, $dateDebut, $dateFin, $justifie);

            default:
                return [];
        }
    }

    /* ---------- Absences: requêtes internes ---------- */

    private function getAbsencesAdmin(string $dateDebut, string $dateFin, string $classe, string $justifie): array
    {
        $sql = "SELECT a.*, e.nom, e.prenom, e.classe 
                FROM absences a 
                JOIN eleves e ON a.id_eleve = e.id 
                WHERE " . $this->buildDateFilter('a', $dateDebut, $dateFin);
        $params = $this->buildDateParams($dateDebut, $dateFin);

        if (!empty($classe)) {
            $sql .= " AND e.classe = ?";
            $params[] = $classe;
        }
        $sql .= $this->buildJustifieFilter($justifie, $params);
        $sql .= " ORDER BY a.date_debut DESC";

        return $this->executeQuery($sql, $params);
    }

    private function getAbsencesTeacher(int $userId, string $dateDebut, string $dateFin, string $classe, string $justifie): array
    {
        $profClasses = $this->getClassesForTeacher($userId);
        if (empty($profClasses)) return [];

        if (!empty($classe) && in_array($classe, $profClasses)) {
            return $this->getAbsencesAdmin($dateDebut, $dateFin, $classe, $justifie);
        }

        $placeholders = implode(',', array_fill(0, count($profClasses), '?'));
        $sql = "SELECT a.*, e.nom, e.prenom, e.classe 
                FROM absences a 
                JOIN eleves e ON a.id_eleve = e.id 
                WHERE e.classe IN ($placeholders)
                AND " . $this->buildDateFilter('a', $dateDebut, $dateFin);
        $params = array_merge($profClasses, $this->buildDateParams($dateDebut, $dateFin));
        $sql .= $this->buildJustifieFilter($justifie, $params);
        $sql .= " ORDER BY e.classe, e.nom, e.prenom, a.date_debut DESC";

        return $this->executeQuery($sql, $params);
    }

    private function getAbsencesEleve(int $userId, string $dateDebut, string $dateFin): array
    {
        $sql = "SELECT a.*, e.nom, e.prenom, e.classe 
                FROM absences a 
                JOIN eleves e ON a.id_eleve = e.id 
                WHERE a.id_eleve = ?
                AND " . $this->buildDateFilter('a', $dateDebut, $dateFin);
        $params = array_merge([$userId], $this->buildDateParams($dateDebut, $dateFin));
        $sql .= " ORDER BY a.date_debut DESC";

        return $this->executeQuery($sql, $params);
    }

    private function getAbsencesParent(int $userId, string $dateDebut, string $dateFin, string $justifie): array
    {
        $enfants = $this->getChildrenForParent($userId);
        if (empty($enfants)) return [];

        $placeholders = implode(',', array_fill(0, count($enfants), '?'));
        $sql = "SELECT a.*, e.nom, e.prenom, e.classe 
                FROM absences a 
                JOIN eleves e ON a.id_eleve = e.id 
                WHERE a.id_eleve IN ($placeholders)
                AND " . $this->buildDateFilter('a', $dateDebut, $dateFin);
        $params = array_merge($enfants, $this->buildDateParams($dateDebut, $dateFin));
        $sql .= $this->buildJustifieFilter($justifie, $params);
        $sql .= " ORDER BY e.nom, e.prenom, a.date_debut DESC";

        return $this->executeQuery($sql, $params);
    }

    /* ---------- Retards: requêtes internes ---------- */

    private function getRetardsAdmin(string $dateDebut, string $dateFin, string $classe, string $justifie): array
    {
        $sql = "SELECT r.*, e.nom, e.prenom, e.classe 
                FROM retards r 
                JOIN eleves e ON r.id_eleve = e.id 
                WHERE " . $this->buildRetardDateFilter($dateDebut, $dateFin);
        $params = [$dateDebut, $dateFin, $dateDebut, $dateFin];

        if (!empty($classe)) {
            $sql .= " AND e.classe = ?";
            $params[] = $classe;
        }
        $sql .= $this->buildJustifieFilter($justifie, $params, 'r');
        $sql .= " ORDER BY r.date_retard DESC";

        return $this->executeQuery($sql, $params);
    }

    private function getRetardsTeacher(int $userId, string $dateDebut, string $dateFin, string $classe, string $justifie): array
    {
        $profClasses = $this->getClassesForTeacher($userId);
        if (empty($profClasses)) return [];

        if (!empty($classe) && in_array($classe, $profClasses)) {
            return $this->getRetardsAdmin($dateDebut, $dateFin, $classe, $justifie);
        }

        $placeholders = implode(',', array_fill(0, count($profClasses), '?'));
        $sql = "SELECT r.*, e.nom, e.prenom, e.classe 
                FROM retards r 
                JOIN eleves e ON r.id_eleve = e.id 
                WHERE e.classe IN ($placeholders)
                AND " . $this->buildRetardDateFilter($dateDebut, $dateFin);
        $params = array_merge($profClasses, [$dateDebut, $dateFin, $dateDebut, $dateFin]);
        $sql .= $this->buildJustifieFilter($justifie, $params, 'r');
        $sql .= " ORDER BY e.classe, e.nom, e.prenom, r.date_retard DESC";

        return $this->executeQuery($sql, $params);
    }

    private function getRetardsEleve(int $userId, string $dateDebut, string $dateFin): array
    {
        $sql = "SELECT r.*, e.nom, e.prenom, e.classe 
                FROM retards r 
                JOIN eleves e ON r.id_eleve = e.id 
                WHERE r.id_eleve = ?
                AND " . $this->buildRetardDateFilter($dateDebut, $dateFin);
        $params = array_merge([$userId], [$dateDebut, $dateFin, $dateDebut, $dateFin]);
        $sql .= " ORDER BY r.date_retard DESC";

        return $this->executeQuery($sql, $params);
    }

    private function getRetardsParent(int $userId, string $dateDebut, string $dateFin, string $justifie): array
    {
        $enfants = $this->getChildrenForParent($userId);
        if (empty($enfants)) return [];

        $placeholders = implode(',', array_fill(0, count($enfants), '?'));
        $sql = "SELECT r.*, e.nom, e.prenom, e.classe 
                FROM retards r 
                JOIN eleves e ON r.id_eleve = e.id 
                WHERE r.id_eleve IN ($placeholders)
                AND " . $this->buildRetardDateFilter($dateDebut, $dateFin);
        $params = array_merge($enfants, [$dateDebut, $dateFin, $dateDebut, $dateFin]);
        $sql .= $this->buildJustifieFilter($justifie, $params, 'r');
        $sql .= " ORDER BY e.nom, e.prenom, r.date_retard DESC";

        return $this->executeQuery($sql, $params);
    }

    /* ================================================================
     *  CRUD : Absences
     * ================================================================ */

    public function getById(int $id): ?array
    {
        $sql = "SELECT a.*, e.nom, e.prenom, e.classe 
                FROM absences a 
                JOIN eleves e ON a.id_eleve = e.id 
                WHERE a.id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function create(array $data): int|false
    {
        $required = ['id_eleve', 'date_debut', 'date_fin', 'type_absence', 'signale_par'];
        foreach ($required as $field) {
            if (empty($data[$field])) return false;
        }

        $sql = "INSERT INTO absences (id_eleve, date_debut, date_fin, type_absence, motif, justifie, commentaire, signale_par)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $success = $stmt->execute([
            $data['id_eleve'],
            $data['date_debut'],
            $data['date_fin'],
            $data['type_absence'],
            $data['motif'] ?? null,
            !empty($data['justifie']) ? 1 : 0,
            $data['commentaire'] ?? null,
            $data['signale_par']
        ]);
        $absenceId = $success ? (int) $this->pdo->lastInsertId() : false;

        // --- Notification auto-trigger ---
        if ($absenceId) {
            $this->notifyAbsence((int)$data['id_eleve'], $data, $absenceId);
        }

        return $absenceId;
    }

    /**
     * Envoie une notification à l'élève et à ses parents lors de la création d'une absence.
     */
    private function notifyAbsence(int $eleveId, array $data, int $absenceId): void
    {
        try {
            require_once __DIR__ . '/../../notifications/includes/NotificationService.php';
            $notifService = new \NotificationService($this->pdo);

            $dateDebut = date('d/m/Y', strtotime($data['date_debut']));
            $type = $data['type_absence'] ?? 'absence';
            $titre = "Nouvelle $type signalée";
            $contenu = "Une $type a été enregistrée à partir du $dateDebut.";
            $lien = '/absences/details_absence.php?id=' . $absenceId;

            // Notifier l'élève
            $notifService->creer($eleveId, 'eleve', 'absence', $titre, $contenu, $lien, 'importante', 'absence', $absenceId);

            // Notifier le(s) parent(s)
            $parents = $this->pdo->prepare("SELECT id_parent FROM eleve_parent WHERE id_eleve = ?");
            $parents->execute([$eleveId]);
            while ($pid = $parents->fetchColumn()) {
                $notifService->creer((int)$pid, 'parent', 'absence', $titre, $contenu, $lien, 'importante', 'absence', $absenceId);
            }

            // Optional: push via WebSocket
            $wsUrl = 'http://localhost:3001/notify/absence';
            $ctx = stream_context_create(['http' => [
                'method' => 'POST', 'header' => 'Content-Type: application/json',
                'content' => json_encode(['eleve_id' => $eleveId, 'absence_id' => $absenceId]),
                'timeout' => 2,
            ]]);
            @file_get_contents($wsUrl, false, $ctx);
        } catch (\Exception $e) {
            // Notification failures must not break the main flow
        }
    }

    public function update(int $id, array $data): bool
    {
        $sql = "UPDATE absences SET 
                    date_debut = ?, date_fin = ?, type_absence = ?, 
                    motif = ?, justifie = ?, commentaire = ? 
                WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $data['date_debut'],
            $data['date_fin'],
            $data['type_absence'],
            $data['motif'] ?? null,
            !empty($data['justifie']) ? 1 : 0,
            $data['commentaire'] ?? null,
            $id
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM absences WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /* ================================================================
     *  CRUD : Retards
     * ================================================================ */

    public function createRetard(array $data): int|false
    {
        $required = ['id_eleve', 'date_retard', 'duree', 'signale_par'];
        foreach ($required as $field) {
            if (empty($data[$field])) return false;
        }

        $sql = "INSERT INTO retards (id_eleve, date_retard, duree, motif, justifie, commentaire, signale_par)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $success = $stmt->execute([
            $data['id_eleve'],
            $data['date_retard'],
            $data['duree'],
            $data['motif'] ?? null,
            !empty($data['justifie']) ? 1 : 0,
            $data['commentaire'] ?? null,
            $data['signale_par']
        ]);
        return $success ? (int) $this->pdo->lastInsertId() : false;
    }

    /* ================================================================
     *  JUSTIFICATIFS
     * ================================================================ */

    public function getJustificatifs(array $filters = []): array
    {
        $dateDebut = $filters['date_debut'] ?? date('Y-m-d', strtotime('-30 days'));
        $dateFin   = $filters['date_fin']   ?? date('Y-m-d');
        $classe    = $filters['classe']     ?? '';
        $traite    = $filters['traite']     ?? '';

        $sql = "SELECT j.*, e.nom, e.prenom, e.classe 
                FROM justificatifs j 
                JOIN eleves e ON j.id_eleve = e.id 
                WHERE j.date_soumission BETWEEN ? AND ?";
        $params = [$dateDebut, $dateFin];

        if (!empty($classe)) {
            $sql .= " AND e.classe = ?";
            $params[] = $classe;
        }
        if ($traite !== '') {
            $sql .= " AND j.traite = ?";
            $params[] = ($traite === 'oui') ? 1 : 0;
        }
        $sql .= " ORDER BY j.date_soumission DESC";

        return $this->executeQuery($sql, $params);
    }

    public function getJustificatifById(int $id): ?array
    {
        $sql = "SELECT j.*, e.nom, e.prenom, e.classe 
                FROM justificatifs j 
                JOIN eleves e ON j.id_eleve = e.id 
                WHERE j.id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function createJustificatif(array $data): int|false
    {
        $required = ['id_eleve', 'date_soumission', 'date_debut_absence', 'date_fin_absence', 'type', 'motif'];
        foreach ($required as $field) {
            if (empty($data[$field])) return false;
        }

        $sql = "INSERT INTO justificatifs (id_eleve, date_soumission, date_debut_absence, date_fin_absence, type, fichier, motif, commentaire)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $success = $stmt->execute([
            $data['id_eleve'],
            $data['date_soumission'],
            $data['date_debut_absence'],
            $data['date_fin_absence'],
            $data['type'],
            $data['fichier'] ?? null,
            $data['motif'],
            $data['commentaire'] ?? null
        ]);
        return $success ? (int) $this->pdo->lastInsertId() : false;
    }

    public function traiterJustificatif(int $id, bool $approuve, string $commentaire, string $traitePar, ?int $idAbsence = null): bool
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                UPDATE justificatifs 
                SET traite = 1, approuve = ?, id_absence = ?, commentaire_admin = ?, 
                    date_traitement = NOW(), traite_par = ?
                WHERE id = ?
            ");
            $stmt->execute([$approuve ? 1 : 0, $idAbsence, $commentaire, $traitePar, $id]);

            if ($approuve && $idAbsence) {
                $stmt = $this->pdo->prepare("UPDATE absences SET justifie = 1 WHERE id = ?");
                $stmt->execute([$idAbsence]);
            }

            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    /**
     * Récupère les absences d'un élève sur la période d'un justificatif
     */
    public function getAbsencesForJustificatif(int $idEleve, string $dateDebut, string $dateFin): array
    {
        $sql = "SELECT a.*, e.nom, e.prenom, e.classe
                FROM absences a
                JOIN eleves e ON a.id_eleve = e.id
                WHERE a.id_eleve = ? 
                AND (
                    (a.date_debut BETWEEN ? AND ?) OR
                    (a.date_fin BETWEEN ? AND ?) OR
                    (a.date_debut <= ? AND a.date_fin >= ?)
                )
                ORDER BY a.date_debut DESC";
        return $this->executeQuery($sql, [
            $idEleve,
            $dateDebut, $dateFin,
            $dateDebut, $dateFin,
            $dateDebut, $dateFin
        ]);
    }

    /* ================================================================
     *  FICHIERS JUSTIFICATIFS
     * ================================================================ */

    public function getAttachments(int $justificatifId): array
    {
        $sql = "SELECT * FROM justificatif_fichiers WHERE id_justificatif = ? ORDER BY date_upload DESC";
        try {
            return $this->executeQuery($sql, [$justificatifId]);
        } catch (PDOException $e) {
            // Table n'existe peut-être pas encore
            return [];
        }
    }

    public function addAttachment(int $justificatifId, string $nomOriginal, string $nomServeur, string $type, int $taille): int|false
    {
        $sql = "INSERT INTO justificatif_fichiers (id_justificatif, nom_original, nom_serveur, type_mime, taille, date_upload)
                VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $this->pdo->prepare($sql);
        $success = $stmt->execute([$justificatifId, $nomOriginal, $nomServeur, $type, $taille]);
        return $success ? (int) $this->pdo->lastInsertId() : false;
    }

    public function getAttachmentById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM justificatif_fichiers WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /* ================================================================
     *  STATISTIQUES
     * ================================================================ */

    public function getStats(string $role, int $userId, array $filters = []): array
    {
        $dateDebut = $filters['date_debut'] ?? date('Y-m-d', strtotime('-1 year'));
        $dateFin   = $filters['date_fin']   ?? date('Y-m-d');
        $classe    = $filters['classe']     ?? '';

        $baseWhere = $this->buildDateFilter('a', $dateDebut, $dateFin);
        $baseParams = $this->buildDateParams($dateDebut, $dateFin);

        // Contrainte de classe
        $classeWhere = '';
        if (!empty($classe)) {
            $classeWhere = " AND e.classe = ?";
            $baseParams[] = $classe;
        }

        // Contrainte de rôle
        $roleWhere = '';
        if ($role === 'professeur') {
            $profClasses = $this->getClassesForTeacher($userId);
            if (empty($profClasses)) return $this->emptyStats();
            $ph = implode(',', array_fill(0, count($profClasses), '?'));
            $roleWhere = " AND e.classe IN ($ph)";
            $baseParams = array_merge($profClasses, $baseParams);
            // Reorder: roleWhere params go first, then dateParams + classeParams
            // Actually we need to restructure this carefully
        } elseif ($role === 'eleve') {
            $roleWhere = " AND a.id_eleve = ?";
            array_unshift($baseParams, $userId);
        } elseif ($role === 'parent') {
            $enfants = $this->getChildrenForParent($userId);
            if (empty($enfants)) return $this->emptyStats();
            $ph = implode(',', array_fill(0, count($enfants), '?'));
            $roleWhere = " AND a.id_eleve IN ($ph)";
            $baseParams = array_merge($enfants, $baseParams);
        }
        // admin/vie_scolaire: pas de contrainte supplémentaire

        // Rebuild params in correct order: role params first for WHERE prefix
        // Since SQL is: WHERE <dateFilter> AND <roleWhere> AND <classeWhere>
        // All params for dateFilter come first, then role, then classe
        // Let's rebuild properly:
        $params = [];
        $roleParams = [];
        $classeParams = [];
        $dateParams = $this->buildDateParams($dateDebut, $dateFin);

        if ($role === 'professeur') {
            $profClasses = $this->getClassesForTeacher($userId);
            if (empty($profClasses)) return $this->emptyStats();
            $ph = implode(',', array_fill(0, count($profClasses), '?'));
            $roleWhere = " AND e.classe IN ($ph)";
            $roleParams = $profClasses;
        } elseif ($role === 'eleve') {
            $roleWhere = " AND a.id_eleve = ?";
            $roleParams = [$userId];
        } elseif ($role === 'parent') {
            $enfants = $this->getChildrenForParent($userId);
            if (empty($enfants)) return $this->emptyStats();
            $ph = implode(',', array_fill(0, count($enfants), '?'));
            $roleWhere = " AND a.id_eleve IN ($ph)";
            $roleParams = $enfants;
        }

        if (!empty($classe)) {
            $classeWhere = " AND e.classe = ?";
            $classeParams = [$classe];
        }

        $params = array_merge($dateParams, $roleParams, $classeParams);

        // Stats globales
        $sql = "SELECT 
                    COUNT(*) as nb_absences,
                    SUM(CASE WHEN a.justifie = 1 THEN 1 ELSE 0 END) as nb_absences_justifiees,
                    COUNT(DISTINCT a.id_eleve) as nb_eleves_absents,
                    COALESCE(SUM(TIMESTAMPDIFF(MINUTE, a.date_debut, a.date_fin)), 0) as duree_totale_minutes
                FROM absences a
                JOIN eleves e ON a.id_eleve = e.id
                WHERE $baseWhere $roleWhere $classeWhere";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        // Stats par classe (admin/vie_scolaire/professeur)
        $classesStats = [];
        if (in_array($role, ['admin', 'vie_scolaire', 'professeur'])) {
            $sql = "SELECT e.classe,
                        COUNT(*) as nb_absences,
                        SUM(CASE WHEN a.justifie = 1 THEN 1 ELSE 0 END) as nb_absences_justifiees,
                        COUNT(DISTINCT a.id_eleve) as nb_eleves_absents,
                        COALESCE(SUM(TIMESTAMPDIFF(MINUTE, a.date_debut, a.date_fin)), 0) as duree_totale_minutes
                    FROM absences a
                    JOIN eleves e ON a.id_eleve = e.id
                    WHERE $baseWhere $roleWhere $classeWhere
                    GROUP BY e.classe
                    ORDER BY nb_absences DESC";
            $classesStats = $this->executeQuery($sql, $params);
        }

        // Stats par élève
        $elevesStats = [];
        if (in_array($role, ['admin', 'vie_scolaire', 'professeur'])) {
            $sql = "SELECT a.id_eleve, e.nom, e.prenom, e.classe,
                        COUNT(*) as nb_absences,
                        SUM(CASE WHEN a.justifie = 1 THEN 1 ELSE 0 END) as nb_absences_justifiees,
                        COALESCE(SUM(TIMESTAMPDIFF(MINUTE, a.date_debut, a.date_fin)), 0) as duree_totale_minutes
                    FROM absences a
                    JOIN eleves e ON a.id_eleve = e.id
                    WHERE $baseWhere $roleWhere $classeWhere
                    GROUP BY a.id_eleve, e.nom, e.prenom, e.classe
                    ORDER BY nb_absences DESC
                    LIMIT 20";
            $elevesStats = $this->executeQuery($sql, $params);
        }

        return [
            'stats'         => $stats,
            'classes_stats'  => $classesStats,
            'eleves_stats'   => $elevesStats
        ];
    }

    /* ================================================================
     *  WORKFLOW DE VALIDATION (signalée → validée / refusée)
     * ================================================================ */

    /**
     * Valide une absence (statut → validee).
     */
    public function validateAbsence(int $id, int $validatorId, string $comment = ''): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE absences SET statut = 'validee', validated_by = ?, validated_at = NOW(), validation_comment = ?
             WHERE id = ? AND (statut IS NULL OR statut IN ('signalee', 'en_attente'))"
        );
        $success = $stmt->execute([$validatorId, $comment, $id]);
        if ($success && $stmt->rowCount() > 0) {
            $this->logValidation($id, $validatorId, 'validee', $comment);
            return true;
        }
        return false;
    }

    /**
     * Refuse une absence (statut → refusee).
     */
    public function rejectAbsence(int $id, int $validatorId, string $comment = ''): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE absences SET statut = 'refusee', validated_by = ?, validated_at = NOW(), validation_comment = ?
             WHERE id = ? AND (statut IS NULL OR statut IN ('signalee', 'en_attente'))"
        );
        $success = $stmt->execute([$validatorId, $comment, $id]);
        if ($success && $stmt->rowCount() > 0) {
            $this->logValidation($id, $validatorId, 'refusee', $comment);
            return true;
        }
        return false;
    }

    /**
     * Remet une absence en attente.
     */
    public function resetAbsenceStatus(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE absences SET statut = 'en_attente', validated_by = NULL, validated_at = NULL, validation_comment = NULL
             WHERE id = ?"
        );
        return $stmt->execute([$id]) && $stmt->rowCount() > 0;
    }

    /**
     * Récupère les absences en attente de validation.
     */
    public function getPendingValidation(array $filters = []): array
    {
        $classe = $filters['classe'] ?? '';
        $sql = "SELECT a.*, e.nom, e.prenom, e.classe 
                FROM absences a 
                JOIN eleves e ON a.id_eleve = e.id 
                WHERE (a.statut IS NULL OR a.statut IN ('signalee', 'en_attente'))";
        $params = [];

        if (!empty($classe)) {
            $sql .= " AND e.classe = ?";
            $params[] = $classe;
        }
        $sql .= " ORDER BY a.date_debut DESC";
        return $this->executeQuery($sql, $params);
    }

    /**
     * Compte les absences par statut.
     */
    public function countByStatus(): array
    {
        $sql = "SELECT 
                    COALESCE(statut, 'signalee') AS statut, 
                    COUNT(*) AS nb 
                FROM absences 
                GROUP BY COALESCE(statut, 'signalee')";
        $rows = $this->executeQuery($sql);
        $result = ['signalee' => 0, 'en_attente' => 0, 'validee' => 0, 'refusee' => 0];
        foreach ($rows as $row) {
            $result[$row['statut']] = (int) $row['nb'];
        }
        return $result;
    }

    /**
     * Validation en lot.
     */
    public function bulkValidate(array $ids, int $validatorId, string $comment = ''): int
    {
        $count = 0;
        foreach ($ids as $id) {
            if ($this->validateAbsence((int) $id, $validatorId, $comment)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Refus en lot.
     */
    public function bulkReject(array $ids, int $validatorId, string $comment = ''): int
    {
        $count = 0;
        foreach ($ids as $id) {
            if ($this->rejectAbsence((int) $id, $validatorId, $comment)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Log d'une action de validation dans l'audit_log.
     */
    private function logValidation(int $absenceId, int $validatorId, string $action, string $comment): void
    {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO audit_log (action, user_type, user_id, details, ip_address, created_at)
                 VALUES (?, 'vie_scolaire', ?, ?, ?, NOW())"
            );
            $stmt->execute([
                "absence.$action",
                $validatorId,
                json_encode(['absence_id' => $absenceId, 'comment' => $comment]),
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
            ]);
        } catch (\PDOException $e) {
            // Audit failures must not break the main flow
        }
    }

    /* ================================================================
     *  UTILITAIRES
     * ================================================================ */

    public function getClassesForTeacher(int $userId): array
    {
        $stmt = $this->pdo->prepare("SELECT DISTINCT nom_classe FROM professeur_classes WHERE id_professeur = ?");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getChildrenForParent(int $userId): array
    {
        $stmt = $this->pdo->prepare("SELECT id_eleve FROM parents_eleves WHERE id_parent = ?");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getAllEleves(): array
    {
        return $this->executeQuery("SELECT id, nom, prenom, classe FROM eleves ORDER BY classe, nom, prenom");
    }

    /**
     * Vérifie si un utilisateur peut voir une absence spécifique
     */
    public function canUserAccessAbsence(int $absenceId, string $role, int $userId): bool
    {
        $absence = $this->getById($absenceId);
        if (!$absence) return false;

        switch ($role) {
            case 'admin':
            case 'vie_scolaire':
                return true;
            case 'professeur':
                $classes = $this->getClassesForTeacher($userId);
                return in_array($absence['classe'], $classes);
            case 'eleve':
                return (int) $absence['id_eleve'] === $userId;
            case 'parent':
                $enfants = $this->getChildrenForParent($userId);
                return in_array($absence['id_eleve'], $enfants);
            default:
                return false;
        }
    }

    /* ================================================================
     *  SAISIE GROUPEE (Phase 7)
     * ================================================================ */

    /**
     * Bulk record absences for multiple students at once.
     * @param array $eleveIds  List of student IDs
     * @param array $common    Common fields (date_debut, date_fin, motif, cours_id, etc.)
     * @param int   $createdBy User ID who created the absences
     * @return int Number of absences created
     */
    public function bulkCreateAbsences(array $eleveIds, array $common, int $createdBy): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO absences (id_eleve, date_debut, date_fin, motif, justifie, cours_id, type, statut, created_by)
            VALUES (?, ?, ?, ?, 0, ?, ?, 'signalee', ?)
        ");

        $count = 0;
        foreach ($eleveIds as $eleveId) {
            $stmt->execute([
                (int) $eleveId,
                $common['date_debut'],
                $common['date_fin'] ?? $common['date_debut'],
                $common['motif'] ?? '',
                $common['cours_id'] ?? null,
                $common['type'] ?? 'absence',
                $createdBy,
            ]);
            $count++;
        }
        return $count;
    }

    /* ================================================================
     *  PATTERN DETECTION (Phase 7)
     * ================================================================ */

    /**
     * Detect absence patterns for a student.
     * Returns patterns like "absent every Monday" or "absent frequently in period X".
     */
    public function detectPatterns(int $eleveId, int $lookbackDays = 90): array
    {
        $dateDebut = date('Y-m-d', strtotime("-{$lookbackDays} days"));
        $stmt = $this->pdo->prepare("
            SELECT a.date_debut, DAYOFWEEK(a.date_debut) AS dow, WEEK(a.date_debut) AS week_num
            FROM absences a
            WHERE a.id_eleve = ? AND a.date_debut >= ?
            ORDER BY a.date_debut
        ");
        $stmt->execute([$eleveId, $dateDebut]);
        $absences = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $patterns = [];

        if (count($absences) < 3) return $patterns;

        // Count by day of week
        $dayCount = array_fill(1, 7, 0);
        $dayNames = [1 => 'dimanche', 2 => 'lundi', 3 => 'mardi', 4 => 'mercredi', 5 => 'jeudi', 6 => 'vendredi', 7 => 'samedi'];

        foreach ($absences as $a) {
            $dayCount[(int) $a['dow']]++;
        }

        $totalAbsences = count($absences);
        $avgPerDay = $totalAbsences / 5; // 5 school days

        foreach ($dayCount as $dow => $count) {
            if ($dow === 1 || $dow === 7) continue; // Skip weekends
            if ($count >= 3 && $count > $avgPerDay * 2) {
                $patterns[] = [
                    'type'    => 'day_of_week',
                    'detail'  => 'Absences fréquentes le ' . $dayNames[$dow],
                    'count'   => $count,
                    'severity' => $count >= 5 ? 'high' : 'medium',
                ];
            }
        }

        // Frequency check: more than 20% of school days absent
        $schoolDays = $lookbackDays * 5 / 7;
        $absenceRate = $totalAbsences / max(1, $schoolDays);
        if ($absenceRate > 0.2) {
            $patterns[] = [
                'type'     => 'high_rate',
                'detail'   => 'Taux d\'absentéisme élevé: ' . round($absenceRate * 100, 1) . '%',
                'count'    => $totalAbsences,
                'severity' => $absenceRate > 0.3 ? 'high' : 'medium',
            ];
        }

        return $patterns;
    }

    /**
     * Save detected pattern alerts to the absence_patterns table.
     */
    public function savePatternAlert(int $eleveId, array $pattern): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO absence_patterns (eleve_id, pattern_type, details_json, detected_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([
                $eleveId,
                $pattern['type'],
                json_encode($pattern, JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\PDOException $e) {
            // Table may not exist yet
        }
    }

    /**
     * Get absenteeism rate per class for dashboard.
     */
    public function getAbsenteeismByClasse(string $dateDebut, string $dateFin): array
    {
        $sql = "
            SELECT e.classe,
                   COUNT(DISTINCT a.id) AS nb_absences,
                   COUNT(DISTINCT a.id_eleve) AS nb_eleves_absents,
                   (SELECT COUNT(*) FROM eleves e2 WHERE e2.classe = e.classe AND e2.actif = 1) AS total_eleves
            FROM absences a
            JOIN eleves e ON a.id_eleve = e.id
            WHERE a.date_debut BETWEEN ? AND ?
            GROUP BY e.classe
            ORDER BY nb_absences DESC
        ";
        return $this->executeQuery($sql, [$dateDebut, $dateFin]);
    }

    /* ---------- Helpers SQL ---------- */

    private function buildDateFilter(string $alias, string $dateDebut, string $dateFin): string
    {
        return "(
            ({$alias}.date_debut BETWEEN ? AND ?) OR
            ({$alias}.date_fin BETWEEN ? AND ?) OR
            ({$alias}.date_debut <= ? AND {$alias}.date_fin >= ?)
        )";
    }

    private function buildDateParams(string $dateDebut, string $dateFin): array
    {
        return [$dateDebut, $dateFin, $dateDebut, $dateFin, $dateDebut, $dateFin];
    }

    private function buildRetardDateFilter(string $dateDebut, string $dateFin): string
    {
        return "((r.date_retard BETWEEN ? AND ?) OR (DATE(r.date_retard) BETWEEN ? AND ?))";
    }

    private function buildJustifieFilter(string $justifie, array &$params, string $alias = 'a'): string
    {
        if ($justifie === 'oui') {
            $params[] = 1;
            return " AND {$alias}.justifie = ?";
        } elseif ($justifie === 'non') {
            $params[] = 0;
            return " AND {$alias}.justifie = ?";
        }
        return '';
    }

    private function executeQuery(string $sql, array $params = []): array
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("AbsenceRepository SQL Error: " . $e->getMessage());
            return [];
        }
    }

    private function emptyStats(): array
    {
        return [
            'stats' => [
                'nb_absences' => 0,
                'nb_absences_justifiees' => 0,
                'nb_eleves_absents' => 0,
                'duree_totale_minutes' => 0
            ],
            'classes_stats' => [],
            'eleves_stats' => []
        ];
    }

    // ─── DETECTION PATTERNS ───

    public function detectPatterns(int $eleveId): array
    {
        $parJour = $this->pdo->prepare("SELECT DAYNAME(date_absence) AS jour, COUNT(*) AS nb FROM absences WHERE id_eleve = :eid GROUP BY jour ORDER BY nb DESC");
        $parJour->execute([':eid' => $eleveId]);

        $parMatiere = $this->pdo->prepare("SELECT m.nom AS matiere, COUNT(*) AS nb FROM absences a JOIN emploi_du_temps edt ON a.date_absence = DATE(edt.date_debut) JOIN matieres m ON edt.id_matiere = m.id WHERE a.id_eleve = :eid GROUP BY m.id HAVING nb >= 2 ORDER BY nb DESC");
        $parMatiere->execute([':eid' => $eleveId]);

        return ['par_jour' => $parJour->fetchAll(\PDO::FETCH_ASSOC), 'par_matiere' => $parMatiere->fetchAll(\PDO::FETCH_ASSOC)];
    }

    public function getCumulatifHeures(int $eleveId, int $periodeId): float
    {
        $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(duree_heures),0) FROM absences WHERE id_eleve = :eid AND periode_id = :pid");
        $stmt->execute([':eid' => $eleveId, ':pid' => $periodeId]);
        return (float)$stmt->fetchColumn();
    }

    public function getHeatmapData(string $classe, string $from, string $to): array
    {
        $stmt = $this->pdo->prepare("SELECT a.date_absence AS date, COUNT(*) AS nb FROM absences a JOIN eleves e ON a.id_eleve = e.id WHERE e.classe = :c AND a.date_absence BETWEEN :f AND :t GROUP BY a.date_absence ORDER BY a.date_absence");
        $stmt->execute([':c' => $classe, ':f' => $from, ':t' => $to]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function comparerClasses(string $from, string $to): array
    {
        $stmt = $this->pdo->prepare("SELECT e.classe, COUNT(*) AS nb_absences, COUNT(DISTINCT a.id_eleve) AS nb_eleves_absents, SUM(CASE WHEN a.justifiee = 0 THEN 1 ELSE 0 END) AS non_justifiees FROM absences a JOIN eleves e ON a.id_eleve = e.id WHERE a.date_absence BETWEEN :f AND :t GROUP BY e.classe ORDER BY nb_absences DESC");
        $stmt->execute([':f' => $from, ':t' => $to]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
