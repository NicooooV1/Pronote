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
}
