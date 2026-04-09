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

    // ─── LIVE PREVIEW ───

    /**
     * Generate HTML preview of a bulletin (for live preview AJAX).
     */
    public function generatePreviewHtml(int $bulletinId): string
    {
        $bulletin = $this->getBulletin($bulletinId);
        if (!$bulletin) return '<p>Bulletin introuvable.</p>';

        $lignes = $this->getLignesMatieres($bulletinId);
        $compBilan = $this->getCompetencesBulletin($bulletinId);

        $html = '<div class="bulletin-preview">';
        $html .= '<div class="bp-header">';
        $html .= '<h2>' . htmlspecialchars($bulletin['eleve_prenom'] . ' ' . $bulletin['eleve_nom']) . '</h2>';
        $html .= '<p>Classe: ' . htmlspecialchars($bulletin['classe_nom'] ?? $bulletin['classe'] ?? '') . ' — ' . htmlspecialchars($bulletin['periode_nom'] ?? '') . '</p>';
        if ($bulletin['moyenne_generale']) {
            $html .= '<div class="bp-moyenne">Moyenne générale: <strong>' . $bulletin['moyenne_generale'] . '/20</strong></div>';
        }
        if ($bulletin['rang']) {
            $html .= '<div class="bp-rang">Rang: ' . $bulletin['rang'] . '</div>';
        }
        $html .= '</div>';

        // Matières
        if (!empty($lignes)) {
            $html .= '<table class="bp-table"><thead><tr><th>Matière</th><th>Moyenne</th><th>Classe</th><th>Min</th><th>Max</th><th>Appréciation</th></tr></thead><tbody>';
            foreach ($lignes as $l) {
                $html .= '<tr>';
                $html .= '<td><strong>' . htmlspecialchars($l['matiere_nom']) . '</strong><br><small>' . htmlspecialchars($l['professeur_nom'] ?? '') . '</small></td>';
                $html .= '<td>' . ($l['moyenne_eleve'] ?? '-') . '</td>';
                $html .= '<td>' . ($l['moyenne_classe'] ?? '-') . '</td>';
                $html .= '<td>' . ($l['moyenne_min'] ?? '-') . '</td>';
                $html .= '<td>' . ($l['moyenne_max'] ?? '-') . '</td>';
                $html .= '<td>' . htmlspecialchars($l['appreciation'] ?? '') . '</td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
        }

        // Compétences
        if (!empty($compBilan)) {
            $html .= '<div class="bp-section"><h4>Bilan de compétences</h4>';
            foreach ($compBilan as $cb) {
                $html .= '<div class="bp-comp-row">';
                $html .= '<span>' . htmlspecialchars($cb['domaine']) . '</span>';
                $html .= '<span class="badge badge-sm">' . htmlspecialchars($cb['niveau_moyen'] ?? '') . '</span>';
                $html .= '</div>';
            }
            $html .= '</div>';
        }

        // Appréciations
        if (!empty($bulletin['appreciation_generale'])) {
            $html .= '<div class="bp-section"><h4>Appréciation générale</h4><p>' . htmlspecialchars($bulletin['appreciation_generale']) . '</p></div>';
        }
        if (!empty($bulletin['avis_conseil']) && $bulletin['avis_conseil'] !== 'aucun') {
            $avisLabels = self::avisLabels();
            $html .= '<div class="bp-section"><h4>Avis du conseil</h4><p>' . htmlspecialchars($avisLabels[$bulletin['avis_conseil']] ?? $bulletin['avis_conseil']) . '</p></div>';
        }

        // Absences/retards
        $html .= '<div class="bp-footer">';
        $html .= 'Absences: ' . ($bulletin['nb_absences'] ?? 0) . ' — Retards: ' . ($bulletin['nb_retards'] ?? 0);
        $html .= '</div>';

        $html .= '</div>';
        return $html;
    }

    /**
     * Get appreciation progress for a class (how many profs have filled their appreciation).
     */
    public function getAppreciationProgress(int $classeId, int $periodeId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT bm.professeur_id, CONCAT(p.prenom, ' ', p.nom) AS prof_nom,
                   COUNT(*) AS total_lignes,
                   SUM(CASE WHEN bm.appreciation IS NOT NULL AND bm.appreciation != '' THEN 1 ELSE 0 END) AS avec_appreciation
            FROM bulletin_matieres bm
            JOIN bulletins b ON bm.bulletin_id = b.id
            JOIN professeurs p ON bm.professeur_id = p.id
            WHERE b.classe_id = ? AND b.periode_id = ?
            GROUP BY bm.professeur_id, p.prenom, p.nom
            ORDER BY p.nom
        ");
        $stmt->execute([$classeId, $periodeId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get available bulletin templates.
     */
    public function getTemplates(): array
    {
        try {
            return $this->pdo->query("SELECT * FROM bulletin_templates ORDER BY name")->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return [];
        }
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

    // ─── MULTI-TEMPLATE PDF ───

    /**
     * Fetch bulletin data and template config for PDF rendering.
     */
    public function generatePdfWithTemplate(int $bulletinId, string $templateKey): array
    {
        $bulletin = $this->getBulletin($bulletinId);
        if (!$bulletin) {
            throw new Exception("Bulletin introuvable");
        }

        $stmt = $this->pdo->prepare("
            SELECT * FROM bulletin_templates WHERE template_key = :template_key LIMIT 1
        ");
        $stmt->execute([':template_key' => $templateKey]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$template) {
            throw new Exception("Template introuvable : " . $templateKey);
        }

        $lignes = $this->getLignesMatieres($bulletinId);
        $competences = $this->getCompetencesBulletin($bulletinId);

        return [
            'bulletin'    => $bulletin,
            'template'    => $template,
            'matieres'    => $lignes,
            'competences' => $competences,
        ];
    }

    // ─── SIGNATURE NUMÉRIQUE WORKFLOW ───

    /**
     * Récupère toutes les signatures associées à un bulletin.
     */
    public function getSignatures(int $bulletinId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT bs.*, CONCAT(u.prenom, ' ', u.nom) AS signataire_nom
            FROM bulletin_signatures bs
            LEFT JOIN utilisateurs u ON bs.signataire_id = u.id
            WHERE bs.bulletin_id = :bulletin_id
            ORDER BY bs.date_signature DESC
        ");
        $stmt->execute([':bulletin_id' => $bulletinId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Enregistre ou met à jour la signature d'un signataire sur un bulletin.
     */
    public function signerBulletin(int $bulletinId, int $signataire_id, string $role, int $signatureId): void
    {
        $stmt = $this->pdo->prepare("
            SELECT id FROM bulletin_signatures
            WHERE bulletin_id = :bulletin_id AND signataire_id = :signataire_id AND role = :role
            LIMIT 1
        ");
        $stmt->execute([
            ':bulletin_id'   => $bulletinId,
            ':signataire_id' => $signataire_id,
            ':role'          => $role,
        ]);
        $existing = $stmt->fetchColumn();

        if ($existing) {
            $stmt = $this->pdo->prepare("
                UPDATE bulletin_signatures
                SET signature_id = :signature_id, date_signature = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                ':signature_id' => $signatureId,
                ':id'           => $existing,
            ]);
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO bulletin_signatures (bulletin_id, signataire_id, role, signature_id, date_signature)
                VALUES (:bulletin_id, :signataire_id, :role, :signature_id, NOW())
            ");
            $stmt->execute([
                ':bulletin_id'   => $bulletinId,
                ':signataire_id' => $signataire_id,
                ':role'          => $role,
                ':signature_id'  => $signatureId,
            ]);
        }
    }

    // ─── ACQUITTEMENT PARENT ───

    /**
     * Marque un bulletin comme consulté par le parent.
     */
    public function acquitterParent(int $bulletinId, int $parentId): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE bulletins
            SET consulte_par_parent = 1, date_consultation_parent = NOW()
            WHERE id = :bulletin_id AND eleve_id IN (
                SELECT eleve_id FROM eleve_parents WHERE parent_id = :parent_id
            )
        ");
        $stmt->execute([
            ':bulletin_id' => $bulletinId,
            ':parent_id'   => $parentId,
        ]);
    }

    // ─── ANALYTICS COMPARATIFS ───

    /**
     * Retourne la distribution des moyennes générales pour une classe et une période.
     */
    public function getDistributionClasse(int $classeId, int $periodeId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                CASE
                    WHEN moyenne_generale < 5  THEN '00-05'
                    WHEN moyenne_generale < 8  THEN '05-08'
                    WHEN moyenne_generale < 10 THEN '08-10'
                    WHEN moyenne_generale < 12 THEN '10-12'
                    WHEN moyenne_generale < 14 THEN '12-14'
                    WHEN moyenne_generale < 16 THEN '14-16'
                    WHEN moyenne_generale < 18 THEN '16-18'
                    ELSE '18-20'
                END AS tranche,
                COUNT(*) AS nb_eleves
            FROM bulletins
            WHERE classe_id = :classe_id AND periode_id = :periode_id AND moyenne_generale IS NOT NULL
            GROUP BY tranche
            ORDER BY tranche
        ");
        $stmt->execute([
            ':classe_id'  => $classeId,
            ':periode_id' => $periodeId,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── GÉNÉRATION PAR LOT ASYNC ───

    /**
     * Insère un job de génération en lot pour traitement asynchrone.
     * Retourne l'identifiant du job créé.
     */
    public function queueBulkGeneration(int $classeId, int $periodeId): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO jobs (queue, payload, status, created_at)
            VALUES (:queue, :payload, 'pending', NOW())
        ");
        $payload = json_encode([
            'action'     => 'bulk_generation_bulletins',
            'classe_id'  => $classeId,
            'periode_id' => $periodeId,
        ], JSON_UNESCAPED_UNICODE);

        $stmt->execute([
            ':queue'   => 'bulletins',
            ':payload' => $payload,
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
