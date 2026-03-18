<?php
/**
 * BulletinService — Service métier pour les bulletins scolaires (M07)
 */
class BulletinService {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    // ─── PÉRIODES ───
    public function getPeriodes(): array {
        return $this->pdo->query("SELECT * FROM periodes ORDER BY numero")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPeriode(int $id): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM periodes WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getPeriodeCourante(): ?array {
        $today = date('Y-m-d');
        $stmt = $this->pdo->prepare("SELECT * FROM periodes WHERE date_debut <= ? AND date_fin >= ? LIMIT 1");
        $stmt->execute([$today, $today]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // ─── BULLETINS ───
    public function getBulletin(int $id): ?array {
        $stmt = $this->pdo->prepare("
            SELECT b.*, e.nom AS eleve_nom, e.prenom AS eleve_prenom, e.classe,
                   c.nom AS classe_nom, p.nom AS periode_nom
            FROM bulletins b
            JOIN eleves e ON b.eleve_id = e.id
            JOIN classes c ON b.classe_id = c.id
            JOIN periodes p ON b.periode_id = p.id
            WHERE b.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getBulletinEleve(int $eleveId, int $periodeId): ?array {
        $stmt = $this->pdo->prepare("
            SELECT b.*, p.nom AS periode_nom
            FROM bulletins b
            JOIN periodes p ON b.periode_id = p.id
            WHERE b.eleve_id = ? AND b.periode_id = ?
        ");
        $stmt->execute([$eleveId, $periodeId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getBulletinsClasse(int $classeId, int $periodeId): array {
        $stmt = $this->pdo->prepare("
            SELECT b.*, e.nom AS eleve_nom, e.prenom AS eleve_prenom
            FROM bulletins b
            JOIN eleves e ON b.eleve_id = e.id
            WHERE b.classe_id = ? AND b.periode_id = ?
            ORDER BY e.nom, e.prenom
        ");
        $stmt->execute([$classeId, $periodeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getBulletinsEleve(int $eleveId): array {
        $stmt = $this->pdo->prepare("
            SELECT b.*, p.nom AS periode_nom, p.numero AS periode_numero
            FROM bulletins b
            JOIN periodes p ON b.periode_id = p.id
            WHERE b.eleve_id = ?
            ORDER BY p.numero
        ");
        $stmt->execute([$eleveId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── GÉNÉRATION ───
    public function genererBulletinsClasse(int $classeId, int $periodeId): int {
        // Récupérer période
        $periode = $this->getPeriode($periodeId);
        if (!$periode) throw new Exception("Période introuvable");

        // Récupérer les élèves
        $stmt = $this->pdo->prepare("SELECT id FROM eleves WHERE classe = (SELECT nom FROM classes WHERE id = ?) AND actif = 1");
        $stmt->execute([$classeId]);
        $eleves = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $count = 0;
        foreach ($eleves as $eleveId) {
            if ($this->genererBulletinEleve($eleveId, $classeId, $periodeId, $periode['numero'])) {
                $count++;
            }
        }

        // Calculer les rangs
        $this->calculerRangs($classeId, $periodeId);

        return $count;
    }

    private function genererBulletinEleve(int $eleveId, int $classeId, int $periodeId, int $trimestre): bool {
        // Vérifier si déjà existant
        $existing = $this->getBulletinEleve($eleveId, $periodeId);
        
        // Calculer moyenne générale
        $stmt = $this->pdo->prepare("
            SELECT AVG(n.note * 20 / n.note_sur) AS moyenne
            FROM notes n
            WHERE n.id_eleve = ? AND n.trimestre = ?
        ");
        $stmt->execute([$eleveId, $trimestre]);
        $moy = $stmt->fetch(PDO::FETCH_ASSOC);
        $moyenne = $moy['moyenne'] ? round($moy['moyenne'], 2) : null;

        // Compter absences/retards sur la période
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM absences WHERE id_eleve = ? AND date_debut >= (SELECT date_debut FROM periodes WHERE id = ?) AND date_fin <= (SELECT date_fin FROM periodes WHERE id = ?)");
        $stmt->execute([$eleveId, $periodeId, $periodeId]);
        $nbAbsences = (int)$stmt->fetchColumn();

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM retards WHERE id_eleve = ? AND date_retard >= (SELECT date_debut FROM periodes WHERE id = ?) AND date_retard <= (SELECT date_fin FROM periodes WHERE id = ?)");
        $stmt->execute([$eleveId, $periodeId, $periodeId]);
        $nbRetards = (int)$stmt->fetchColumn();

        if ($existing) {
            $stmt = $this->pdo->prepare("
                UPDATE bulletins SET moyenne_generale = ?, nb_absences = ?, nb_retards = ?, date_modification = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$moyenne, $nbAbsences, $nbRetards, $existing['id']]);
            $bulletinId = $existing['id'];
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO bulletins (eleve_id, classe_id, periode_id, annee_scolaire, moyenne_generale, nb_absences, nb_retards)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$eleveId, $classeId, $periodeId, '2025-2026', $moyenne, $nbAbsences, $nbRetards]);
            $bulletinId = (int)$this->pdo->lastInsertId();
        }

        // Générer lignes matières
        $this->genererLignesMatieres($bulletinId, $eleveId, $classeId, $trimestre);

        // Intégrer le bilan de compétences dans le bulletin
        $this->genererBilanCompetences($bulletinId, $eleveId, $periodeId);

        return true;
    }

    private function genererLignesMatieres(int $bulletinId, int $eleveId, int $classeId, int $trimestre): void {
        $nomClasse = $this->pdo->prepare("SELECT nom FROM classes WHERE id = ?");
        $nomClasse->execute([$classeId]);
        $classe = $nomClasse->fetchColumn();

        // Récupérer toutes les matières avec notes pour cet élève
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT n.id_matiere, n.id_professeur,
                   m.nom AS matiere_nom, m.coefficient
            FROM notes n
            JOIN matieres m ON n.id_matiere = m.id
            WHERE n.id_eleve = ? AND n.trimestre = ?
        ");
        $stmt->execute([$eleveId, $trimestre]);
        $matieres = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($matieres as $mat) {
            // Moyenne élève
            $s = $this->pdo->prepare("SELECT AVG(note * 20 / note_sur) FROM notes WHERE id_eleve = ? AND id_matiere = ? AND trimestre = ?");
            $s->execute([$eleveId, $mat['id_matiere'], $trimestre]);
            $moyEleve = round((float)$s->fetchColumn(), 2);

            // Moyenne classe
            $s = $this->pdo->prepare("
                SELECT AVG(n.note * 20 / n.note_sur) FROM notes n
                JOIN eleves e ON n.id_eleve = e.id
                WHERE e.classe = ? AND n.id_matiere = ? AND n.trimestre = ?
            ");
            $s->execute([$classe, $mat['id_matiere'], $trimestre]);
            $moyClasse = round((float)$s->fetchColumn(), 2);

            // Min/Max classe
            $s = $this->pdo->prepare("
                SELECT MIN(avg_note) AS min_moy, MAX(avg_note) AS max_moy FROM (
                    SELECT AVG(n.note * 20 / n.note_sur) AS avg_note FROM notes n
                    JOIN eleves e ON n.id_eleve = e.id
                    WHERE e.classe = ? AND n.id_matiere = ? AND n.trimestre = ?
                    GROUP BY n.id_eleve
                ) sub
            ");
            $s->execute([$classe, $mat['id_matiere'], $trimestre]);
            $minMax = $s->fetch(PDO::FETCH_ASSOC);

            // Upsert
            $s = $this->pdo->prepare("
                INSERT INTO bulletin_matieres (bulletin_id, matiere_id, professeur_id, moyenne_eleve, moyenne_classe, moyenne_min, moyenne_max, coefficient)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    moyenne_eleve = VALUES(moyenne_eleve),
                    moyenne_classe = VALUES(moyenne_classe),
                    moyenne_min = VALUES(moyenne_min),
                    moyenne_max = VALUES(moyenne_max)
            ");
            $s->execute([
                $bulletinId, $mat['id_matiere'], $mat['id_professeur'],
                $moyEleve, $moyClasse,
                $minMax['min_moy'] ? round($minMax['min_moy'], 2) : null,
                $minMax['max_moy'] ? round($minMax['max_moy'], 2) : null,
                $mat['coefficient'] ?? 1
            ]);
        }
    }

    private function calculerRangs(int $classeId, int $periodeId): void {
        $stmt = $this->pdo->prepare("
            SELECT id, moyenne_generale FROM bulletins
            WHERE classe_id = ? AND periode_id = ? AND moyenne_generale IS NOT NULL
            ORDER BY moyenne_generale DESC
        ");
        $stmt->execute([$classeId, $periodeId]);
        $bulletins = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $rang = 1;
        foreach ($bulletins as $b) {
            $u = $this->pdo->prepare("UPDATE bulletins SET rang = ? WHERE id = ?");
            $u->execute([$rang, $b['id']]);
            $rang++;
        }
    }

    /**
     * Génère le bilan de compétences stocké en JSON dans le bulletin.
     */
    private function genererBilanCompetences(int $bulletinId, int $eleveId, int $periodeId): void
    {
        try {
            require_once __DIR__ . '/../../competences/includes/CompetenceService.php';
            $compService = new \CompetenceService($this->pdo);
            $bilan = $compService->getBilanEleve($eleveId, $periodeId);

            if (!empty($bilan)) {
                // Stocker le résumé par domaine dans un champ JSON
                $resume = [];
                foreach ($bilan as $domaine => $data) {
                    $resume[] = [
                        'domaine'       => $domaine,
                        'niveau_moyen'  => $data['niveau_moyen'] ?? 'non_evalue',
                        'nb_evaluations' => $data['count'] ?? 0,
                    ];
                }
                $stmt = $this->pdo->prepare("UPDATE bulletins SET competences_bilan = ? WHERE id = ?");
                $stmt->execute([json_encode($resume, JSON_UNESCAPED_UNICODE), $bulletinId]);
            }
        } catch (\Exception $e) {
            // Non-fatal : le bilan compétences est optionnel
        }
    }

    /**
     * Récupère le bilan de compétences intégré dans un bulletin.
     */
    public function getCompetencesBulletin(int $bulletinId): array
    {
        $stmt = $this->pdo->prepare("SELECT competences_bilan FROM bulletins WHERE id = ?");
        $stmt->execute([$bulletinId]);
        $json = $stmt->fetchColumn();
        return $json ? json_decode($json, true) : [];
    }

    // ─── APPRÉCIATIONS ───
    public function sauvegarderAppreciation(int $bulletinId, string $appreciation): void {
        $stmt = $this->pdo->prepare("UPDATE bulletins SET appreciation_generale = ? WHERE id = ?");
        $stmt->execute([$appreciation, $bulletinId]);
    }

    public function sauvegarderAppreciationMatiere(int $bulletinMatiereId, string $appreciation): void {
        $stmt = $this->pdo->prepare("UPDATE bulletin_matieres SET appreciation = ? WHERE id = ?");
        $stmt->execute([$appreciation, $bulletinMatiereId]);
    }

    public function sauvegarderAvisConseil(int $bulletinId, string $avis): void {
        $stmt = $this->pdo->prepare("UPDATE bulletins SET avis_conseil = ? WHERE id = ?");
        $stmt->execute([$avis, $bulletinId]);
    }

    // ─── VALIDATION / PUBLICATION ───
    public function validerBulletin(int $bulletinId, int $adminId): void {
        $stmt = $this->pdo->prepare("
            UPDATE bulletins SET statut = 'valide', valide_par = ?, date_validation = NOW() WHERE id = ?
        ");
        $stmt->execute([$adminId, $bulletinId]);
    }

    public function publierBulletins(int $classeId, int $periodeId): int {
        $stmt = $this->pdo->prepare("
            UPDATE bulletins SET statut = 'publie', date_publication = NOW()
            WHERE classe_id = ? AND periode_id = ? AND statut = 'valide'
        ");
        $stmt->execute([$classeId, $periodeId]);
        return $stmt->rowCount();
    }

    // ─── LIGNES MATIÈRES ───
    public function getLignesMatieres(int $bulletinId): array {
        $stmt = $this->pdo->prepare("
            SELECT bm.*, m.nom AS matiere_nom, m.code AS matiere_code, m.couleur,
                   CONCAT(p.prenom, ' ', p.nom) AS professeur_nom
            FROM bulletin_matieres bm
            JOIN matieres m ON bm.matiere_id = m.id
            JOIN professeurs p ON bm.professeur_id = p.id
            WHERE bm.bulletin_id = ?
            ORDER BY m.nom
        ");
        $stmt->execute([$bulletinId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── STATISTIQUES ───
    public function getStatsClasse(int $classeId, int $periodeId): array {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) AS total,
                   AVG(moyenne_generale) AS moy_classe,
                   MIN(moyenne_generale) AS moy_min,
                   MAX(moyenne_generale) AS moy_max,
                   SUM(CASE WHEN statut = 'publie' THEN 1 ELSE 0 END) AS publies,
                   SUM(CASE WHEN statut = 'valide' THEN 1 ELSE 0 END) AS valides,
                   SUM(CASE WHEN statut = 'brouillon' THEN 1 ELSE 0 END) AS brouillons
            FROM bulletins WHERE classe_id = ? AND periode_id = ?
        ");
        $stmt->execute([$classeId, $periodeId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function getClasses(): array {
        return $this->pdo->query("SELECT * FROM classes WHERE actif = 1 ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getClasseId(string $nomClasse): ?int {
        $stmt = $this->pdo->prepare("SELECT id FROM classes WHERE nom = ? LIMIT 1");
        $stmt->execute([$nomClasse]);
        $id = $stmt->fetchColumn();
        return $id !== false ? (int)$id : null;
    }

    // ─── LABELS ───
    public static function avisLabels(): array {
        return [
            'aucun' => 'Aucun',
            'felicitations' => 'Félicitations',
            'compliments' => 'Compliments',
            'encouragements' => 'Encouragements',
            'avertissement_travail' => 'Avertissement travail',
            'avertissement_conduite' => 'Avertissement conduite',
        ];
    }

    public static function statutLabels(): array {
        return [
            'brouillon' => 'Brouillon',
            'valide' => 'Validé',
            'publie' => 'Publié',
            'archive' => 'Archivé',
        ];
    }

    public static function statutBadge(string $statut): string {
        $map = [
            'brouillon' => 'badge-warning',
            'valide' => 'badge-info',
            'publie' => 'badge-success',
            'archive' => 'badge-secondary',
        ];
        $label = self::statutLabels()[$statut] ?? $statut;
        $class = $map[$statut] ?? 'badge-secondary';
        return "<span class=\"badge {$class}\">{$label}</span>";
    }
}
