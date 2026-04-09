<?php
/**
 * RenduService — Gestion des rendus de devoirs en ligne (M08)
 * S'articule avec le module cahierdetextes existant
 */
class RenduService {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    // ─── SOUMISSION ÉLÈVE ───
    public function soumettre(int $devoirId, int $eleveId, string $contenu = '', ?array $fichier = null): int {
        $devoir = $this->getDevoir($devoirId);
        if (!$devoir) throw new Exception("Devoir introuvable");

        // Vérifier si la date de rendu est dépassée
        $enRetard = (strtotime($devoir['date_rendu']) < time()) ? 1 : 0;

        $fichierNom = null;
        $fichierChemin = null;
        $fichierType = null;
        $fichierTaille = 0;

        if ($fichier && !empty($fichier['name']) && $fichier['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../uploads/rendus/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $ext = pathinfo($fichier['name'], PATHINFO_EXTENSION);
            $nomServeur = uniqid('rendu_') . '.' . $ext;
            move_uploaded_file($fichier['tmp_name'], $uploadDir . $nomServeur);

            $fichierNom = $fichier['name'];
            $fichierChemin = 'uploads/rendus/' . $nomServeur;
            $fichierType = $fichier['type'];
            $fichierTaille = $fichier['size'];
        }

        // Upsert
        $existing = $this->getRendu($devoirId, $eleveId);
        if ($existing) {
            $stmt = $this->pdo->prepare("
                UPDATE devoirs_rendus SET contenu = ?, fichier_nom = COALESCE(?, fichier_nom),
                    fichier_chemin = COALESCE(?, fichier_chemin), fichier_type = COALESCE(?, fichier_type),
                    fichier_taille = COALESCE(?, fichier_taille), date_rendu = NOW(), en_retard = ?, statut = 'rendu'
                WHERE id = ?
            ");
            $stmt->execute([$contenu, $fichierNom, $fichierChemin, $fichierType, $fichierTaille ?: null, $enRetard, $existing['id']]);
            return $existing['id'];
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO devoirs_rendus (devoir_id, eleve_id, contenu, fichier_nom, fichier_chemin, fichier_type, fichier_taille, en_retard)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$devoirId, $eleveId, $contenu, $fichierNom, $fichierChemin, $fichierType, $fichierTaille, $enRetard]);
            return (int)$this->pdo->lastInsertId();
        }
    }

    // ─── CORRECTION PROFESSEUR ───
    public function corriger(int $renduId, ?float $note, ?float $noteSur, string $commentaire): void {
        $stmt = $this->pdo->prepare("
            UPDATE devoirs_rendus SET note = ?, note_sur = ?, commentaire_prof = ?, date_correction = NOW(), statut = 'corrige'
            WHERE id = ?
        ");
        $stmt->execute([$note, $noteSur ?? 20, $commentaire, $renduId]);
    }

    public function demanderRefaire(int $renduId, string $commentaire): void {
        $stmt = $this->pdo->prepare("
            UPDATE devoirs_rendus SET commentaire_prof = ?, statut = 'a_refaire' WHERE id = ?
        ");
        $stmt->execute([$commentaire, $renduId]);
    }

    // ─── LECTURE ───
    public function getRendu(int $devoirId, int $eleveId): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM devoirs_rendus WHERE devoir_id = ? AND eleve_id = ?");
        $stmt->execute([$devoirId, $eleveId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getRenduById(int $id): ?array {
        $stmt = $this->pdo->prepare("
            SELECT r.*, e.nom AS eleve_nom, e.prenom AS eleve_prenom, e.classe,
                   d.titre AS devoir_titre, d.nom_matiere, d.nom_professeur, d.date_rendu AS date_echeance
            FROM devoirs_rendus r
            JOIN eleves e ON r.eleve_id = e.id
            JOIN devoirs d ON r.devoir_id = d.id
            WHERE r.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getRendusDevoir(int $devoirId): array {
        $stmt = $this->pdo->prepare("
            SELECT r.*, e.nom AS eleve_nom, e.prenom AS eleve_prenom, e.classe
            FROM devoirs_rendus r
            JOIN eleves e ON r.eleve_id = e.id
            WHERE r.devoir_id = ?
            ORDER BY e.nom, e.prenom
        ");
        $stmt->execute([$devoirId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRendusEleve(int $eleveId): array {
        $stmt = $this->pdo->prepare("
            SELECT r.*, d.titre AS devoir_titre, d.nom_matiere, d.date_rendu AS date_echeance, d.classe
            FROM devoirs_rendus r
            JOIN devoirs d ON r.devoir_id = d.id
            WHERE r.eleve_id = ?
            ORDER BY r.date_rendu DESC
        ");
        $stmt->execute([$eleveId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDevoir(int $id): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM devoirs WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getDevoirsARendreEleve(int $eleveId): array {
        $stmt = $this->pdo->prepare("
            SELECT d.*, r.id AS rendu_id, r.statut AS rendu_statut, r.note, r.date_rendu AS date_soumission
            FROM devoirs d
            LEFT JOIN devoirs_rendus r ON d.id = r.devoir_id AND r.eleve_id = ?
            WHERE d.classe = (SELECT classe FROM eleves WHERE id = ?)
            ORDER BY d.date_rendu ASC
        ");
        $stmt->execute([$eleveId, $eleveId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── STATISTIQUES ───
    public function getStatsDevoir(int $devoirId): array {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) AS total_rendus,
                   SUM(CASE WHEN statut = 'corrige' THEN 1 ELSE 0 END) AS corriges,
                   SUM(CASE WHEN en_retard = 1 THEN 1 ELSE 0 END) AS en_retard,
                   AVG(note * 20 / note_sur) AS moyenne_notes,
                   MIN(note * 20 / note_sur) AS note_min,
                   MAX(note * 20 / note_sur) AS note_max
            FROM devoirs_rendus WHERE devoir_id = ?
        ");
        $stmt->execute([$devoirId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        // Nombre d'élèves attendus
        $devoir = $this->getDevoir($devoirId);
        if ($devoir) {
            $stmt2 = $this->pdo->prepare("SELECT COUNT(*) FROM eleves WHERE classe = ? AND actif = 1");
            $stmt2->execute([$devoir['classe']]);
            $stats['total_eleves'] = (int)$stmt2->fetchColumn();
        }

        return $stats;
    }

    // ─── ANNOTATIONS ───

    /**
     * Save annotation on a submission (prof comments by section).
     */
    public function saveAnnotation(int $renduId, array $annotations, int $profId): void
    {
        $json = json_encode($annotations, JSON_UNESCAPED_UNICODE);
        $stmt = $this->pdo->prepare("
            UPDATE devoirs_rendus SET annotations_json = ?, annotated_by = ?, annotated_at = NOW() WHERE id = ?
        ");
        $stmt->execute([$json, $profId, $renduId]);
    }

    /**
     * Get annotations for a submission.
     */
    public function getAnnotations(int $renduId): array
    {
        $stmt = $this->pdo->prepare("SELECT annotations_json FROM devoirs_rendus WHERE id = ?");
        $stmt->execute([$renduId]);
        $json = $stmt->fetchColumn();
        return $json ? json_decode($json, true) : [];
    }

    // ─── AUTO-REMINDERS ───

    /**
     * Get devoirs with upcoming deadlines that need reminders.
     * Returns devoirs where deadline is in the next 24h and no reminder was sent.
     */
    public function getDevoirsNeedingReminders(): array
    {
        $now = date('Y-m-d H:i:s');
        $in24h = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $stmt = $this->pdo->prepare("
            SELECT d.*, COUNT(DISTINCT e.id) AS nb_eleves,
                   COUNT(DISTINCT r.id) AS nb_rendus
            FROM devoirs d
            LEFT JOIN eleves e ON e.classe = d.classe AND e.actif = 1
            LEFT JOIN devoirs_rendus r ON r.devoir_id = d.id
            WHERE d.date_rendu BETWEEN ? AND ?
              AND (d.reminder_sent IS NULL OR d.reminder_sent = 0)
            GROUP BY d.id
        ");
        $stmt->execute([$now, $in24h]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Mark a devoir as having sent its reminder.
     */
    public function markReminderSent(int $devoirId): void
    {
        $this->pdo->prepare("UPDATE devoirs SET reminder_sent = 1 WHERE id = ?")->execute([$devoirId]);
    }

    /**
     * Get students who haven't submitted for a devoir.
     */
    public function getElevesNonRendus(int $devoirId): array
    {
        $devoir = $this->getDevoir($devoirId);
        if (!$devoir) return [];

        $stmt = $this->pdo->prepare("
            SELECT e.id, e.nom, e.prenom
            FROM eleves e
            WHERE e.classe = ? AND e.actif = 1
              AND e.id NOT IN (SELECT eleve_id FROM devoirs_rendus WHERE devoir_id = ?)
            ORDER BY e.nom, e.prenom
        ");
        $stmt->execute([$devoir['classe'], $devoirId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ─── LABELS ───
    public static function statutLabels(): array {
        return [
            'rendu' => 'Rendu',
            'corrige' => 'Corrigé',
            'a_refaire' => 'À refaire',
        ];
    }

    public static function statutBadge(string $statut): string {
        $map = ['rendu' => 'badge-info', 'corrige' => 'badge-success', 'a_refaire' => 'badge-warning'];
        $label = self::statutLabels()[$statut] ?? $statut;
        $class = $map[$statut] ?? 'badge-secondary';
        return "<span class=\"badge {$class}\">{$label}</span>";
    }

    // ─── DÉTECTION PLAGIAT ───

    /**
     * Détecte le plagiat entre les rendus d'un même devoir par comparaison de hash de texte.
     * Retourne les paires de rendus avec un score de similarité élevé.
     */
    public function detecterPlagiat(int $devoirId, float $seuil = 0.7): array
    {
        $rendus = $this->getRendusDevoir($devoirId);
        $textes = [];
        foreach ($rendus as $r) {
            if (!empty($r['contenu'])) {
                $textes[$r['id']] = $this->normaliserTexte($r['contenu']);
            }
        }

        $suspects = [];
        $ids = array_keys($textes);
        for ($i = 0; $i < count($ids) - 1; $i++) {
            for ($j = $i + 1; $j < count($ids); $j++) {
                $sim = $this->similariteShingles($textes[$ids[$i]], $textes[$ids[$j]]);
                if ($sim >= $seuil) {
                    $rI = $rendus[array_search($ids[$i], array_column($rendus, 'id'))];
                    $rJ = $rendus[array_search($ids[$j], array_column($rendus, 'id'))];
                    $suspects[] = [
                        'rendu_a' => ['id' => $ids[$i], 'eleve' => ($rI['eleve_prenom'] ?? '') . ' ' . ($rI['eleve_nom'] ?? '')],
                        'rendu_b' => ['id' => $ids[$j], 'eleve' => ($rJ['eleve_prenom'] ?? '') . ' ' . ($rJ['eleve_nom'] ?? '')],
                        'similarite' => round($sim * 100, 1)
                    ];
                }
            }
        }

        usort($suspects, fn($a, $b) => $b['similarite'] <=> $a['similarite']);
        return $suspects;
    }

    private function normaliserTexte(string $texte): string
    {
        $texte = strip_tags($texte);
        $texte = mb_strtolower($texte);
        $texte = preg_replace('/\s+/', ' ', $texte);
        return trim($texte);
    }

    private function similariteShingles(string $a, string $b, int $k = 5): float
    {
        $shinglesA = $this->getShingles($a, $k);
        $shinglesB = $this->getShingles($b, $k);
        if (empty($shinglesA) || empty($shinglesB)) return 0;

        $intersection = count(array_intersect($shinglesA, $shinglesB));
        $union = count(array_unique(array_merge($shinglesA, $shinglesB)));
        return $union > 0 ? $intersection / $union : 0;
    }

    private function getShingles(string $text, int $k): array
    {
        $words = explode(' ', $text);
        $shingles = [];
        for ($i = 0; $i <= count($words) - $k; $i++) {
            $shingles[] = implode(' ', array_slice($words, $i, $k));
        }
        return $shingles;
    }

    // ─── PEER REVIEW ───

    /**
     * Soumet une évaluation par les pairs.
     */
    public function soumettreAvisPair(int $renduId, int $reviewerEleveId, int $note, string $commentaire = ''): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO devoirs_peer_reviews (rendu_id, reviewer_eleve_id, note, commentaire) VALUES (:rid, :reid, :n, :c) ON DUPLICATE KEY UPDATE note = VALUES(note), commentaire = VALUES(commentaire)");
        $stmt->execute([':rid' => $renduId, ':reid' => $reviewerEleveId, ':n' => $note, ':c' => $commentaire]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Récupère les avis pairs pour un rendu.
     */
    public function getAvisPairs(int $renduId): array
    {
        $stmt = $this->pdo->prepare("SELECT pr.*, CONCAT(e.prenom,' ',e.nom) AS reviewer_nom FROM devoirs_peer_reviews pr JOIN eleves e ON pr.reviewer_eleve_id = e.id WHERE pr.rendu_id = :rid ORDER BY pr.created_at");
        $stmt->execute([':rid' => $renduId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Moyenne des notes attribuées par les pairs.
     */
    public function getMoyennePeerReview(int $renduId): ?float
    {
        $stmt = $this->pdo->prepare("SELECT ROUND(AVG(note),2) FROM devoirs_peer_reviews WHERE rendu_id = :rid");
        $stmt->execute([':rid' => $renduId]);
        $avg = $stmt->fetchColumn();
        return $avg !== false ? (float)$avg : null;
    }

    // ─── GRILLES DE CRITÈRES ───

    /**
     * Crée une grille de critères pour un devoir.
     */
    public function creerGrille(int $devoirId, string $titre, array $criteres): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO devoirs_grilles (devoir_id, titre) VALUES (:did, :t)");
        $stmt->execute([':did' => $devoirId, ':t' => $titre]);
        $grilleId = (int)$this->pdo->lastInsertId();

        $insert = $this->pdo->prepare("INSERT INTO devoirs_grille_criteres (grille_id, critere, points_max, description, ordre) VALUES (:gid, :c, :pm, :d, :o)");
        foreach ($criteres as $idx => $c) {
            $insert->execute([':gid' => $grilleId, ':c' => $c['critere'], ':pm' => $c['points_max'] ?? 5, ':d' => $c['description'] ?? '', ':o' => $idx + 1]);
        }

        return $grilleId;
    }

    /**
     * Note un rendu selon la grille de critères.
     */
    public function noterParGrille(int $renduId, int $grilleId, array $notes): float
    {
        $total = 0;
        $maxTotal = 0;
        $insert = $this->pdo->prepare("INSERT INTO devoirs_grille_notes (rendu_id, critere_id, note) VALUES (:rid, :cid, :n) ON DUPLICATE KEY UPDATE note = VALUES(note)");
        foreach ($notes as $critereId => $note) {
            $insert->execute([':rid' => $renduId, ':cid' => $critereId, ':n' => $note]);
            $total += (float)$note;
        }

        $criteres = $this->pdo->prepare("SELECT SUM(points_max) FROM devoirs_grille_criteres WHERE grille_id = :gid");
        $criteres->execute([':gid' => $grilleId]);
        $maxTotal = (float)$criteres->fetchColumn();

        return $maxTotal > 0 ? round(($total / $maxTotal) * 20, 2) : 0;
    }
}
