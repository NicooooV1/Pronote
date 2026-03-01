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
     * Récupère toutes les notes d'un trimestre (admin / vie scolaire).
     */
    public function getAllNotes(int $trimestre, int $limit = 200): array
    {
        $stmt = $this->pdo->prepare("
            SELECT n.*, m.nom AS matiere_nom, m.couleur AS matiere_couleur,
                   CONCAT(e.prenom, ' ', e.nom) AS eleve_nom, e.classe,
                   CONCAT(p.prenom, ' ', p.nom) AS professeur_nom
            FROM notes n
            LEFT JOIN matieres m ON n.id_matiere = m.id
            LEFT JOIN eleves e ON n.id_eleve = e.id
            LEFT JOIN professeurs p ON n.id_professeur = p.id
            WHERE n.trimestre = ?
            ORDER BY n.date_note DESC
            LIMIT " . (int) $limit . "
        ");
        $stmt->execute([$trimestre]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                $count++;
            }

            $this->pdo->commit();
            return $count;
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Met à jour une note existante.
     */
    public function updateNote(int $id, array $data): bool
    {
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
}
