<?php
/**
 * NoteService — Service métier pour le module Notes.
 *
 * Centralise toutes les requêtes SQL liées aux notes pour que les pages
 * restent de simples contrôleurs légers.
 */
class NoteService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ─── Notes par rôle ──────────────────────────────────────────────

    /**
     * Récupère les notes d'un élève pour un trimestre donné.
     */
    public function getNotesEleve(int $eleveId, int $trimestre): array
    {
        $stmt = $this->pdo->prepare("
            SELECT n.*, m.nom AS matiere_nom, m.couleur AS matiere_couleur, m.code AS matiere_code,
                   CONCAT(p.prenom, ' ', p.nom) AS professeur_nom
            FROM notes n
            LEFT JOIN matieres m ON n.id_matiere = m.id
            LEFT JOIN professeurs p ON n.id_professeur = p.id
            WHERE n.id_eleve = ? AND n.trimestre = ?
            ORDER BY n.date_note DESC
        ");
        $stmt->execute([$eleveId, $trimestre]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les notes attribuées par un professeur pour un trimestre.
     */
    public function getNotesProfesseur(int $profId, int $trimestre): array
    {
        $stmt = $this->pdo->prepare("
            SELECT n.*, m.nom AS matiere_nom, m.couleur AS matiere_couleur,
                   CONCAT(e.prenom, ' ', e.nom) AS eleve_nom, e.classe
            FROM notes n
            LEFT JOIN matieres m ON n.id_matiere = m.id
            LEFT JOIN eleves e ON n.id_eleve = e.id
            WHERE n.id_professeur = ? AND n.trimestre = ?
            ORDER BY n.date_note DESC
        ");
        $stmt->execute([$profId, $trimestre]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère toutes les notes d'un trimestre (admin / vie scolaire) avec filtres SQL et pagination.
     *
     * @param int    $trimestre  Numéro du trimestre (1-3)
     * @param string $classe     Filtre par classe (vide = toutes)
     * @param int    $matiereId  Filtre par matière (0 = toutes)
     * @param int    $limit      Nombre de résultats par page
     * @param int    $offset     Décalage pour la pagination
     * @return array ['notes' => [...], 'total' => int]
     */
    public function getAllNotes(int $trimestre, int $limit = 50, int $offset = 0, string $classe = '', int $matiereId = 0): array
    {
        $where = "n.trimestre = ?";
        $params = [$trimestre];

        if ($classe !== '') {
            $where .= " AND e.classe = ?";
            $params[] = $classe;
        }
        if ($matiereId > 0) {
            $where .= " AND n.id_matiere = ?";
            $params[] = $matiereId;
        }

        // Count total
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM notes n LEFT JOIN eleves e ON n.id_eleve = e.id WHERE {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // Fetch page
        $stmt = $this->pdo->prepare("
            SELECT n.*, m.nom AS matiere_nom, m.couleur AS matiere_couleur,
                   CONCAT(e.prenom, ' ', e.nom) AS eleve_nom, e.classe,
                   CONCAT(p.prenom, ' ', p.nom) AS professeur_nom
            FROM notes n
            LEFT JOIN matieres m ON n.id_matiere = m.id
            LEFT JOIN eleves e ON n.id_eleve = e.id
            LEFT JOIN professeurs p ON n.id_professeur = p.id
            WHERE {$where}
            ORDER BY n.date_note DESC
            LIMIT " . (int) $limit . " OFFSET " . (int) $offset . "
        ");
        $stmt->execute($params);

        return [
            'notes' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
        ];
    }

    // ─── Moyennes ────────────────────────────────────────────────────

    /**
     * Calcule les moyennes par matière pour un élève.
     */
    public function getMoyennesParMatiere(int $eleveId, int $trimestre): array
    {
        $stmt = $this->pdo->prepare("
            SELECT n.id_matiere, m.nom AS matiere_nom, m.couleur, m.code,
                   ROUND(SUM(n.note / n.note_sur * 20 * n.coefficient) / SUM(n.coefficient), 2) AS moyenne,
                   COUNT(n.id) AS nb_notes
            FROM notes n
            LEFT JOIN matieres m ON n.id_matiere = m.id
            WHERE n.id_eleve = ? AND n.trimestre = ?
            GROUP BY n.id_matiere, m.nom, m.couleur, m.code
            ORDER BY m.nom
        ");
        $stmt->execute([$eleveId, $trimestre]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Calcule la moyenne générale d'un élève pour un trimestre.
     */
    public function getMoyenneGenerale(int $eleveId, int $trimestre): ?float
    {
        $moyennes = $this->getMoyennesParMatiere($eleveId, $trimestre);
        if (empty($moyennes)) {
            return null;
        }
        $total = 0;
        foreach ($moyennes as $m) {
            $total += $m['moyenne'];
        }
        return round($total / count($moyennes), 2);
    }

    // ─── CRUD ────────────────────────────────────────────────────────

    /**
     * Récupère une note par ID avec jointures.
     * @param int      $id      ID de la note
     * @param int|null $profId  Si renseigné, restreint au professeur
     */
    public function getNoteById(int $id, ?int $profId = null): ?array
    {
        $sql = "SELECT n.*, e.nom AS nom_eleve, e.prenom AS prenom_eleve,
                       m.nom AS nom_matiere, p.nom AS nom_professeur, p.prenom AS prenom_professeur
                FROM notes n
                LEFT JOIN eleves e ON n.id_eleve = e.id
                LEFT JOIN matieres m ON n.id_matiere = m.id
                LEFT JOIN professeurs p ON n.id_professeur = p.id
                WHERE n.id = ?";
        $params = [$id];

        if ($profId !== null) {
            $sql .= " AND n.id_professeur = ?";
            $params[] = $profId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Insère un lot de notes en une transaction.
     *
     * @param array $notesData  Tableau [['id_eleve'=>…, 'note'=>…, …], …]
     * @param array $common     Données communes (id_matiere, id_professeur, trimestre, date_note, etc.)
     * @return int Nombre de notes insérées
     */
    public function bulkInsert(array $notesData, array $common): int
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO notes (id_eleve, id_matiere, id_professeur, note, note_sur,
                                   coefficient, type_evaluation, commentaire, trimestre, date_note, date_creation)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $count = 0;
            $insertedEleveIds = [];
            foreach ($notesData as $data) {
                if (!isset($data['note']) || $data['note'] === '') {
                    continue;
                }
                $stmt->execute([
                    $data['id_eleve'],
                    $common['id_matiere'],
                    $common['id_professeur'],
                    $data['note'],
                    $common['note_sur'] ?? 20,
                    $common['coefficient'] ?? 1,
                    $common['type_evaluation'] ?? 'Contrôle',
                    $data['commentaire'] ?? null,
                    $common['trimestre'],
                    $common['date_note'] ?? date('Y-m-d'),
                ]);
                $insertedEleveIds[] = (int) $data['id_eleve'];
                $count++;
            }

            $this->pdo->commit();

            // --- Notification auto-trigger ---
            if ($count > 0) {
                $this->notifyNewNotes($insertedEleveIds, $common);
            }

            return $count;
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Envoie une notification à chaque élève et à ses parents après saisie de notes.
     */
    private function notifyNewNotes(array $eleveIds, array $common): void
    {
        try {
            require_once __DIR__ . '/../../notifications/includes/NotificationService.php';
            $notifService = new \NotificationService($this->pdo);

            // Récupérer le nom de la matière
            $matNom = 'une matière';
            if (!empty($common['id_matiere'])) {
                $st = $this->pdo->prepare("SELECT nom FROM matieres WHERE id = ?");
                $st->execute([$common['id_matiere']]);
                $matNom = $st->fetchColumn() ?: $matNom;
            }

            $titre = "Nouvelle note en $matNom";
            $type  = $common['type_evaluation'] ?? 'Contrôle';
            $lien  = '/notes/notes.php';

            foreach ($eleveIds as $eid) {
                // Notifier l'élève
                $notifService->creer($eid, 'eleve', 'nouvelle_note', $titre,
                    "$type — consultez vos notes.", $lien, 'normale', 'note', $common['id_matiere'] ?? null);

                // Notifier le(s) parent(s)
                $parents = $this->pdo->prepare("SELECT id_parent FROM eleve_parent WHERE id_eleve = ?");
                $parents->execute([$eid]);
                while ($pid = $parents->fetchColumn()) {
                    $notifService->creer((int)$pid, 'parent', 'nouvelle_note', $titre,
                        "$type — nouvelle note disponible.", $lien, 'normale', 'note', $common['id_matiere'] ?? null);
                }
            }

            // Optional: push via WebSocket
            $this->pushWebSocket('grade', ['eleve_ids' => $eleveIds, 'matiere' => $matNom]);
        } catch (\Exception $e) {
            // Notification failures must not break the main flow
        }
    }

    /**
     * Fire-and-forget HTTP POST to the WebSocket server for real-time push.
     */
    private function pushWebSocket(string $channel, array $payload): void
    {
        $wsUrl = 'http://localhost:3001/notify/' . $channel;
        $ctx = stream_context_create(['http' => [
            'method' => 'POST', 'header' => 'Content-Type: application/json',
            'content' => json_encode($payload), 'timeout' => 2,
        ]]);
        @file_get_contents($wsUrl, false, $ctx);
    }

    // ─── Verrouillage de notes ──────────────────────────────────────

    /**
     * Verrouille une note (empêche la modification).
     */
    public function lockNote(int $id, int $lockedBy): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE notes SET locked = 1, locked_by = ?, locked_at = NOW() WHERE id = ? AND (locked IS NULL OR locked = 0)"
        );
        $success = $stmt->execute([$lockedBy, $id]);
        if ($success && $stmt->rowCount() > 0) {
            $this->logNoteAction('note.locked', $id, $lockedBy);
            return true;
        }
        return false;
    }

    /**
     * Déverrouille une note.
     */
    public function unlockNote(int $id, int $unlockedBy): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE notes SET locked = 0, locked_by = NULL, locked_at = NULL WHERE id = ? AND locked = 1"
        );
        $success = $stmt->execute([$id]);
        if ($success && $stmt->rowCount() > 0) {
            $this->logNoteAction('note.unlocked', $id, $unlockedBy);
            return true;
        }
        return false;
    }

    /**
     * Verrouille toutes les notes d'une matière / classe / trimestre.
     */
    public function bulkLockNotes(int $matiereId, string $classe, int $trimestre, int $lockedBy): int
    {
        $stmt = $this->pdo->prepare(
            "UPDATE notes n
             JOIN eleves e ON n.id_eleve = e.id
             SET n.locked = 1, n.locked_by = ?, n.locked_at = NOW()
             WHERE n.id_matiere = ? AND e.classe = ? AND n.trimestre = ? AND (n.locked IS NULL OR n.locked = 0)"
        );
        $stmt->execute([$lockedBy, $matiereId, $classe, $trimestre]);
        $count = $stmt->rowCount();
        if ($count > 0) {
            $this->logNoteAction('note.bulk_locked', 0, $lockedBy, "matiere=$matiereId, classe=$classe, trimestre=$trimestre, count=$count");
        }
        return $count;
    }

    /**
     * Vérifie si une note est verrouillée.
     */
    public function isNoteLocked(int $id): bool
    {
        $stmt = $this->pdo->prepare("SELECT locked FROM notes WHERE id = ?");
        $stmt->execute([$id]);
        return (bool) $stmt->fetchColumn();
    }

    // ─── Historique des modifications ────────────────────────────────

    /**
     * Sauvegarde l'état actuel d'une note avant modification.
     */
    public function saveNoteHistory(int $noteId, int $modifiedBy, string $reason = ''): bool
    {
        $note = $this->getNoteById($noteId);
        if (!$note) return false;

        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO note_history (note_id, note_value, note_sur, coefficient, commentaire, modified_by, modified_at, reason)
                 VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)"
            );
            return $stmt->execute([
                $noteId,
                $note['note'],
                $note['note_sur'],
                $note['coefficient'],
                $note['commentaire'] ?? null,
                $modifiedBy,
                $reason
            ]);
        } catch (\PDOException $e) {
            // Table may not exist yet
            return false;
        }
    }

    /**
     * Récupère l'historique des modifications d'une note.
     */
    public function getNoteHistory(int $noteId): array
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT h.*, CONCAT(p.prenom, ' ', p.nom) AS modified_by_name
                 FROM note_history h
                 LEFT JOIN professeurs p ON h.modified_by = p.id
                 WHERE h.note_id = ?
                 ORDER BY h.modified_at DESC"
            );
            $stmt->execute([$noteId]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return [];
        }
    }

    // ─── Export ──────────────────────────────────────────────────────

    /**
     * Prépare les notes pour export CSV/PDF.
     */
    public function getNotesForExport(int $trimestre, array $filters = []): array
    {
        $notes = [];
        if (!empty($filters['classe']) && !empty($filters['matiere'])) {
            $notes = $this->getNotesClasseMatiere($filters['classe'], (int) $filters['matiere'], $trimestre);
        } else {
            $notes = $this->getAllNotes($trimestre, 10000);
            if (!empty($filters['classe'])) {
                $notes = array_filter($notes, fn($n) => ($n['classe'] ?? '') === $filters['classe']);
            }
            if (!empty($filters['matiere'])) {
                $notes = array_filter($notes, fn($n) => ($n['id_matiere'] ?? 0) == (int) $filters['matiere']);
            }
            $notes = array_values($notes);
        }

        return array_map(function($n) {
            return [
                'eleve'       => $n['eleve_nom'] ?? (($n['prenom'] ?? '') . ' ' . ($n['nom'] ?? '')),
                'classe'      => $n['classe'] ?? '',
                'matiere'     => $n['matiere_nom'] ?? $n['nom_matiere'] ?? '',
                'note'        => $n['note'] . '/' . ($n['note_sur'] ?? 20),
                'coefficient' => $n['coefficient'] ?? 1,
                'type'        => $n['type_evaluation'] ?? '',
                'date'        => !empty($n['date_note']) ? date('d/m/Y', strtotime($n['date_note'])) : '',
                'commentaire' => $n['commentaire'] ?? '',
            ];
        }, $notes);
    }

    /**
     * Récupère les notes d'une classe pour une matière et un trimestre.
     */
    private function getNotesClasseMatiere(string $classe, int $matiereId, int $trimestre): array
    {
        $stmt = $this->pdo->prepare("
            SELECT n.*, m.nom AS matiere_nom, 
                   CONCAT(e.prenom, ' ', e.nom) AS eleve_nom, e.classe
            FROM notes n
            JOIN eleves e ON n.id_eleve = e.id
            LEFT JOIN matieres m ON n.id_matiere = m.id
            WHERE e.classe = ? AND n.id_matiere = ? AND n.trimestre = ?
            ORDER BY e.nom, e.prenom, n.date_note DESC
        ");
        $stmt->execute([$classe, $matiereId, $trimestre]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Log d'une action sur les notes.
     */
    private function logNoteAction(string $action, int $noteId, int $userId, string $details = ''): void
    {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO audit_log (action, user_type, user_id, details, ip_address, created_at)
                 VALUES (?, 'professeur', ?, ?, ?, NOW())"
            );
            $stmt->execute([$action, $userId, json_encode(['note_id' => $noteId, 'details' => $details]), $_SERVER['REMOTE_ADDR'] ?? '']);
        } catch (\PDOException $e) {}
    }

    /**
     * Met à jour une note existante (avec vérification du verrouillage + historique).
     */
    public function updateNote(int $id, array $data): bool
    {
        // Vérifier le verrouillage
        if ($this->isNoteLocked($id)) {
            throw new \RuntimeException("Cette note est verrouillée et ne peut pas être modifiée.");
        }

        // Sauvegarder l'historique avant modification
        $modifiedBy = $data['modified_by'] ?? 0;
        if ($modifiedBy) {
            $this->saveNoteHistory($id, $modifiedBy, $data['modification_reason'] ?? 'Modification manuelle');
        }

        $stmt = $this->pdo->prepare("
            UPDATE notes SET
                note = ?,
                coefficient = ?,
                commentaire = ?,
                date_note = ?,
                trimestre = ?,
                date_modification = NOW()
            WHERE id = ?
        ");
        return $stmt->execute([
            $data['note'],
            $data['coefficient'],
            $data['commentaire'] ?? null,
            $data['date_note'] ?? null,
            $data['trimestre'],
            $id,
        ]);
    }

    /**
     * Supprime une note par ID.
     */
    public function deleteNote(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM notes WHERE id = ?");
        return $stmt->execute([$id]);
    }

    // ─── Référence ───────────────────────────────────────────────────

    /**
     * Récupère les matières actives.
     */
    public function getMatieres(): array
    {
        $stmt = $this->pdo->query("SELECT id, nom, couleur, code FROM matieres WHERE actif = 1 ORDER BY nom");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les classes actives.
     */
    public function getClasses(): array
    {
        $stmt = $this->pdo->query("SELECT DISTINCT nom FROM classes WHERE actif = 1 ORDER BY nom");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Récupère les élèves d'une classe.
     */
    public function getElevesParClasse(string $classe): array
    {
        $stmt = $this->pdo->prepare("SELECT id, nom, prenom FROM eleves WHERE classe = ? AND actif = 1 ORDER BY nom, prenom");
        $stmt->execute([$classe]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── Statistiques rapides ────────────────────────────────────────

    /**
     * Calcule la moyenne globale d'un ensemble de notes (normalisées sur 20).
     */
    public function calculerMoyenneGlobale(array $notes): float
    {
        if (empty($notes)) {
            return 0;
        }
        $sum = 0;
        foreach ($notes as $n) {
            $noteSur = $n['note_sur'] ?: 20;
            $sum += ($n['note'] / $noteSur * 20);
        }
        return round($sum / count($notes), 1);
    }

    /**
     * Statistiques de classe pour une matière et un trimestre.
     * Retourne moyenne, min, max, médiane, nombre de notes, nombre d'élèves.
     */
    public function getStatsClasse(string $classe, int $matiereId, int $trimestre): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                ROUND(AVG(n.note / n.note_sur * 20), 2) AS moyenne_classe,
                ROUND(MIN(n.note / n.note_sur * 20), 2) AS note_min,
                ROUND(MAX(n.note / n.note_sur * 20), 2) AS note_max,
                COUNT(DISTINCT n.id_eleve)               AS nb_eleves,
                COUNT(*)                                  AS nb_notes
            FROM notes n
            JOIN eleves e ON n.id_eleve = e.id
            WHERE e.classe = ? AND n.id_matiere = ? AND n.trimestre = ?
        ");
        $stmt->execute([$classe, $matiereId, $trimestre]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // Calcul de la médiane côté PHP
        $stmtNotes = $this->pdo->prepare("
            SELECT ROUND(n.note / n.note_sur * 20, 2) AS note_norm
            FROM notes n
            JOIN eleves e ON n.id_eleve = e.id
            WHERE e.classe = ? AND n.id_matiere = ? AND n.trimestre = ?
            ORDER BY note_norm
        ");
        $stmtNotes->execute([$classe, $matiereId, $trimestre]);
        $allNotes = $stmtNotes->fetchAll(PDO::FETCH_COLUMN);
        $cnt = count($allNotes);
        $mediane = 0;
        if ($cnt > 0) {
            $mid = intdiv($cnt, 2);
            $mediane = ($cnt % 2 === 0)
                ? round(($allNotes[$mid - 1] + $allNotes[$mid]) / 2, 2)
                : round($allNotes[$mid], 2);
        }
        $row['mediane'] = $mediane;

        return $row;
    }

    /**
     * Moyennes par élève pour une classe / matière / trimestre (classement).
     */
    public function getMoyennesParEleve(string $classe, int $matiereId, int $trimestre): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                e.id, e.nom, e.prenom,
                ROUND(SUM(n.note / n.note_sur * 20 * n.coefficient) / SUM(n.coefficient), 2) AS moyenne
            FROM notes n
            JOIN eleves e ON n.id_eleve = e.id
            WHERE e.classe = ? AND n.id_matiere = ? AND n.trimestre = ?
            GROUP BY e.id, e.nom, e.prenom
            ORDER BY e.nom, e.prenom
        ");
        $stmt->execute([$classe, $matiereId, $trimestre]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── Statistiques avancées (Phase 6) ────────────────────────────

    /**
     * Distribution des notes d'une classe/matière/trimestre par tranches.
     * Retourne un tableau de 10 tranches [0-2, 2-4, ..., 18-20] avec le count.
     */
    public function getDistribution(string $classe, int $matiereId, int $trimestre): array
    {
        $stmt = $this->pdo->prepare("
            SELECT ROUND(n.note / n.note_sur * 20, 2) AS note_norm
            FROM notes n
            JOIN eleves e ON n.id_eleve = e.id
            WHERE e.classe = ? AND n.id_matiere = ? AND n.trimestre = ?
        ");
        $stmt->execute([$classe, $matiereId, $trimestre]);
        $allNotes = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $bins = array_fill(0, 10, 0);
        $labels = [];
        for ($i = 0; $i < 10; $i++) {
            $labels[] = ($i * 2) . '-' . (($i + 1) * 2);
        }

        foreach ($allNotes as $note) {
            $bin = min(9, (int) floor($note / 2));
            $bins[$bin]++;
        }

        return ['labels' => $labels, 'values' => $bins, 'total' => count($allNotes)];
    }

    /**
     * Évolution des moyennes d'un élève par trimestre (courbe).
     * Retourne par matière : [matiere_nom => [T1 => moy, T2 => moy, T3 => moy]].
     */
    public function getEvolutionEleve(int $eleveId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT n.trimestre, m.nom AS matiere_nom, m.couleur,
                   ROUND(SUM(n.note / n.note_sur * 20 * n.coefficient) / SUM(n.coefficient), 2) AS moyenne
            FROM notes n
            LEFT JOIN matieres m ON n.id_matiere = m.id
            WHERE n.id_eleve = ?
            GROUP BY n.trimestre, n.id_matiere, m.nom, m.couleur
            ORDER BY m.nom, n.trimestre
        ");
        $stmt->execute([$eleveId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $result = [];
        foreach ($rows as $r) {
            $key = $r['matiere_nom'];
            if (!isset($result[$key])) {
                $result[$key] = ['couleur' => $r['couleur'] ?? '#3498db', 'trimestres' => [null, null, null]];
            }
            $result[$key]['trimestres'][$r['trimestre'] - 1] = (float) $r['moyenne'];
        }

        return $result;
    }

    /**
     * Box plot data par matière pour une classe et un trimestre.
     * Pour chaque matière : min, Q1, médiane, Q3, max.
     */
    public function getBoxPlotClasse(string $classe, int $trimestre): array
    {
        $stmt = $this->pdo->prepare("
            SELECT n.id_matiere, m.nom AS matiere_nom, m.couleur,
                   ROUND(n.note / n.note_sur * 20, 2) AS note_norm
            FROM notes n
            JOIN eleves e ON n.id_eleve = e.id
            LEFT JOIN matieres m ON n.id_matiere = m.id
            WHERE e.classe = ? AND n.trimestre = ?
            ORDER BY m.nom, note_norm
        ");
        $stmt->execute([$classe, $trimestre]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $grouped = [];
        foreach ($rows as $r) {
            $key = $r['matiere_nom'] ?? 'Inconnu';
            if (!isset($grouped[$key])) {
                $grouped[$key] = ['couleur' => $r['couleur'] ?? '#3498db', 'notes' => []];
            }
            $grouped[$key]['notes'][] = (float) $r['note_norm'];
        }

        $result = [];
        foreach ($grouped as $matiere => $data) {
            $notes = $data['notes'];
            $cnt = count($notes);
            if ($cnt === 0) continue;
            sort($notes);
            $result[] = [
                'matiere'  => $matiere,
                'couleur'  => $data['couleur'],
                'min'      => $notes[0],
                'q1'       => $notes[(int) floor($cnt * 0.25)],
                'median'   => $cnt % 2 === 0
                    ? ($notes[$cnt / 2 - 1] + $notes[$cnt / 2]) / 2
                    : $notes[(int) floor($cnt / 2)],
                'q3'       => $notes[(int) floor($cnt * 0.75)],
                'max'      => $notes[$cnt - 1],
                'count'    => $cnt,
            ];
        }

        return $result;
    }

    /**
     * Auto-save d'un lot de notes via AJAX.
     * Met à jour les notes existantes ou crée de nouvelles entrées.
     *
     * @return array ['updated' => int, 'inserted' => int]
     */
    public function autoSaveBatch(array $notesData, array $common): array
    {
        $updated = 0;
        $inserted = 0;

        $this->pdo->beginTransaction();
        try {
            // Check existing notes for this evaluation
            $checkStmt = $this->pdo->prepare("
                SELECT id FROM notes
                WHERE id_eleve = ? AND id_matiere = ? AND id_professeur = ? AND trimestre = ? AND date_note = ?
                LIMIT 1
            ");

            $updateStmt = $this->pdo->prepare("
                UPDATE notes SET note = ?, coefficient = ?, commentaire = ?, date_modification = NOW()
                WHERE id = ?
            ");

            $insertStmt = $this->pdo->prepare("
                INSERT INTO notes (id_eleve, id_matiere, id_professeur, note, note_sur,
                                   coefficient, type_evaluation, commentaire, trimestre, date_note, date_creation)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            foreach ($notesData as $data) {
                if (!isset($data['note']) || $data['note'] === '') continue;

                $checkStmt->execute([
                    $data['id_eleve'],
                    $common['id_matiere'],
                    $common['id_professeur'],
                    $common['trimestre'],
                    $common['date_note'],
                ]);
                $existingId = $checkStmt->fetchColumn();

                if ($existingId) {
                    $updateStmt->execute([
                        $data['note'],
                        $common['coefficient'] ?? 1,
                        $data['commentaire'] ?? null,
                        $existingId,
                    ]);
                    $updated++;
                } else {
                    $insertStmt->execute([
                        $data['id_eleve'],
                        $common['id_matiere'],
                        $common['id_professeur'],
                        $data['note'],
                        $common['note_sur'] ?? 20,
                        $common['coefficient'] ?? 1,
                        $common['type_evaluation'] ?? 'Contrôle',
                        $data['commentaire'] ?? null,
                        $common['trimestre'],
                        $common['date_note'] ?? date('Y-m-d'),
                    ]);
                    $inserted++;
                }
            }

            $this->pdo->commit();
            return ['updated' => $updated, 'inserted' => $inserted];
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Cache les calculs statistiques dans la table note_calculations.
     */
    public function cacheCalculations(int $classeId, int $matiereId, int $periodeId, int $etabId = 1): void
    {
        try {
            $className = $this->pdo->prepare("SELECT nom FROM classes WHERE id = ?");
            $className->execute([$classeId]);
            $classe = $className->fetchColumn();
            if (!$classe) return;

            $stats = $this->getStatsClasse($classe, $matiereId, $periodeId);
            if (empty($stats) || !$stats['nb_notes']) return;

            $upsert = $this->pdo->prepare("
                INSERT INTO note_calculations (classe_id, matiere_id, periode_id, type, value, etablissement_id)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE value = VALUES(value)
            ");

            $types = [
                'moyenne' => $stats['moyenne_classe'],
                'mediane' => $stats['mediane'],
                'min'     => $stats['note_min'],
                'max'     => $stats['note_max'],
            ];

            foreach ($types as $type => $value) {
                if ($value !== null) {
                    $upsert->execute([$classeId, $matiereId, $periodeId, $type, $value, $etabId]);
                }
            }
        } catch (\PDOException $e) {
            // Silent fail — cache is optional
        }
    }

    /**
     * Détermine le trimestre courant basé sur le mois.
     */
    public static function getTrimestreCourant(): int
    {
        $mois = (int) date('n');
        if ($mois >= 9 && $mois <= 12) return 1;
        if ($mois >= 1 && $mois <= 3) return 2;
        return 3;
    }

    // ─── Import CSV ─────────────────────────────────────────────────

    /**
     * Importe des notes depuis un fichier CSV.
     * Format attendu : id_eleve;note;commentaire (une ligne par élève)
     *
     * @return array ['imported' => int, 'errors' => string[]]
     */
    public function importFromCsv(string $filePath, int $profId, array $common): array
    {
        if (!file_exists($filePath)) {
            return ['imported' => 0, 'errors' => ['Fichier introuvable']];
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return ['imported' => 0, 'errors' => ['Impossible d\'ouvrir le fichier']];
        }

        $header = fgetcsv($handle, 0, ';');
        if (!$header) {
            fclose($handle);
            return ['imported' => 0, 'errors' => ['Fichier vide']];
        }

        $header = array_map('strtolower', array_map('trim', $header));
        $colEleve = array_search('id_eleve', $header);
        $colNote = array_search('note', $header);
        $colComment = array_search('commentaire', $header);

        if ($colEleve === false || $colNote === false) {
            fclose($handle);
            return ['imported' => 0, 'errors' => ['Colonnes id_eleve et note requises']];
        }

        $notesData = [];
        $errors = [];
        $ligne = 1;
        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            $ligne++;
            $eleveId = (int)($row[$colEleve] ?? 0);
            $note = $row[$colNote] ?? '';

            if ($eleveId <= 0) { $errors[] = "Ligne {$ligne}: id_eleve invalide"; continue; }
            if ($note === '' || !is_numeric($note)) { $errors[] = "Ligne {$ligne}: note invalide"; continue; }
            if ((float)$note < 0 || (float)$note > ($common['note_sur'] ?? 20)) { $errors[] = "Ligne {$ligne}: note hors limites"; continue; }

            $notesData[] = [
                'id_eleve' => $eleveId,
                'note' => (float)$note,
                'commentaire' => $colComment !== false ? ($row[$colComment] ?? '') : ''
            ];
        }
        fclose($handle);

        $imported = 0;
        if (!empty($notesData)) {
            $common['id_professeur'] = $profId;
            $imported = $this->bulkInsert($notesData, $common);
        }

        return ['imported' => $imported, 'errors' => $errors];
    }

    // ─── Pondération configurable ───────────────────────────────────

    /**
     * Récupère la pondération par type d'évaluation pour une matière.
     */
    public function getConfigPonderation(int $matiereId, int $etabId): array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT type_evaluation, poids FROM notes_config_ponderation WHERE matiere_id = :mid AND etablissement_id = :eid ORDER BY type_evaluation");
            $stmt->execute([':mid' => $matiereId, ':eid' => $etabId]);
            return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (\PDOException $e) {
            return [];
        }
    }

    /**
     * Définit la pondération d'un type d'évaluation pour une matière.
     */
    public function setConfigPonderation(int $matiereId, int $etabId, string $typeEvaluation, float $poids): void
    {
        $this->pdo->prepare("INSERT INTO notes_config_ponderation (matiere_id, etablissement_id, type_evaluation, poids) VALUES (:mid, :eid, :te, :p) ON DUPLICATE KEY UPDATE poids = VALUES(poids)")
            ->execute([':mid' => $matiereId, ':eid' => $etabId, ':te' => $typeEvaluation, ':p' => $poids]);
    }

    /**
     * Calcule la moyenne pondérée d'un élève en tenant compte des pondérations configurées.
     */
    public function getMoyennePonderee(int $eleveId, int $matiereId, int $trimestre, int $etabId): ?float
    {
        $ponderations = $this->getConfigPonderation($matiereId, $etabId);
        if (empty($ponderations)) {
            $moyennes = $this->getMoyennesParMatiere($eleveId, $trimestre);
            foreach ($moyennes as $m) {
                if ($m['id_matiere'] == $matiereId) return (float)$m['moyenne'];
            }
            return null;
        }

        $stmt = $this->pdo->prepare("SELECT type_evaluation, note, note_sur, coefficient FROM notes WHERE id_eleve = :eid AND id_matiere = :mid AND trimestre = :t");
        $stmt->execute([':eid' => $eleveId, ':mid' => $matiereId, ':t' => $trimestre]);
        $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($notes)) return null;

        $totalPondere = 0;
        $totalCoeff = 0;
        foreach ($notes as $n) {
            $noteNorm = ($n['note'] / ($n['note_sur'] ?: 20)) * 20;
            $coeff = (float)$n['coefficient'];
            $poids = $ponderations[$n['type_evaluation']] ?? 1.0;
            $totalPondere += $noteNorm * $coeff * $poids;
            $totalCoeff += $coeff * $poids;
        }

        return $totalCoeff > 0 ? round($totalPondere / $totalCoeff, 2) : null;
    }

    // ─── Verrouillage par matière ───────────────────────────────────

    /**
     * Verrouille les saisies de notes pour une matière/classe/trimestre.
     */
    public function verrouillerMatiere(int $matiereId, string $classe, int $trimestre, int $verrouillePar, int $etabId): void
    {
        $this->pdo->prepare("INSERT INTO notes_verrous (matiere_id, trimestre, classe, verrouille_par, etablissement_id) VALUES (:mid, :t, :c, :vp, :eid) ON DUPLICATE KEY UPDATE verrouille_par = VALUES(verrouille_par), date_verrouillage = NOW()")
            ->execute([':mid' => $matiereId, ':t' => $trimestre, ':c' => $classe, ':vp' => $verrouillePar, ':eid' => $etabId]);

        $this->bulkLockNotes($matiereId, $classe, $trimestre, $verrouillePar);
    }

    /**
     * Vérifie si une matière est verrouillée.
     */
    public function isMatiereVerrouillee(int $matiereId, string $classe, int $trimestre, int $etabId): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM notes_verrous WHERE matiere_id = :mid AND classe = :c AND trimestre = :t AND etablissement_id = :eid");
        $stmt->execute([':mid' => $matiereId, ':c' => $classe, ':t' => $trimestre, ':eid' => $etabId]);
        return $stmt->fetchColumn() > 0;
    }
}
