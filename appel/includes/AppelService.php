<?php
/**
 * AppelService — Service métier pour le module Appel / Présence (M04).
 *
 * Gère les sessions d'appel, le pointage des élèves,
 * les statistiques de présence et les notifications parents.
 */
class AppelService
{
    protected $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ─── Sessions d'appel ────────────────────────────────────────

    /**
     * Crée une nouvelle session d'appel.
     */
    public function createAppel(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO appels (edt_id, classe_id, professeur_id, matiere_id,
                date_appel, heure_debut, heure_fin, type_appel, commentaire)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['edt_id'] ?? null, $data['classe_id'], $data['professeur_id'],
            $data['matiere_id'] ?? null, $data['date_appel'],
            $data['heure_debut'], $data['heure_fin'],
            $data['type_appel'] ?? 'cours', $data['commentaire'] ?? null
        ]);
        $appelId = (int)$this->pdo->lastInsertId();

        // Pré-remplir avec tous les élèves de la classe (présents par défaut)
        $this->initialiserEleves($appelId, $data['classe_id']);

        return $appelId;
    }

    /**
     * Pré-remplit la liste des élèves pour un appel.
     */
    protected function initialiserEleves(int $appelId, int $classeId): void
    {
        // Trouver le nom de la classe
        $stmt = $this->pdo->prepare("SELECT nom FROM classes WHERE id = ?");
        $stmt->execute([$classeId]);
        $nomClasse = $stmt->fetchColumn();
        if (!$nomClasse) return;

        $stmt = $this->pdo->prepare(
            "INSERT INTO appel_eleves (appel_id, eleve_id, statut)
             SELECT ?, id, 'present' FROM eleves WHERE classe = ? AND actif = 1"
        );
        $stmt->execute([$appelId, $nomClasse]);
    }

    /**
     * Retourne un appel par ID.
     */
    public function getAppel(int $id): ?array
    {
        $sql = "SELECT a.*,
                       cl.nom AS classe_nom,
                       CONCAT(p.prenom, ' ', p.nom) AS professeur_nom,
                       m.nom AS matiere_nom
                FROM appels a
                JOIN classes cl ON a.classe_id = cl.id
                JOIN professeurs p ON a.professeur_id = p.id
                LEFT JOIN matieres m ON a.matiere_id = m.id
                WHERE a.id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Retourne les élèves d'un appel avec leur statut.
     */
    public function getAppelEleves(int $appelId): array
    {
        $sql = "SELECT ae.*, e.nom, e.prenom, e.classe
                FROM appel_eleves ae
                JOIN eleves e ON ae.eleve_id = e.id
                WHERE ae.appel_id = ?
                ORDER BY e.nom, e.prenom";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$appelId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Met à jour le statut d'un élève dans un appel.
     */
    public function updateStatutEleve(int $appelId, int $eleveId, string $statut, ?array $extras = null): bool
    {
        $validStatuts = ['present', 'absent', 'retard', 'dispense', 'exclu'];
        if (!in_array($statut, $validStatuts)) return false;

        $sql = "UPDATE appel_eleves SET statut = ?";
        $params = [$statut];

        if ($statut === 'retard' && isset($extras['heure_arrivee'])) {
            $sql .= ", heure_arrivee = ?, duree_retard = ?";
            $params[] = $extras['heure_arrivee'];
            $params[] = $extras['duree_retard'] ?? null;
        }

        if (isset($extras['motif'])) {
            $sql .= ", motif = ?";
            $params[] = $extras['motif'];
        }

        if (isset($extras['commentaire'])) {
            $sql .= ", commentaire = ?";
            $params[] = $extras['commentaire'];
        }

        $sql .= " WHERE appel_id = ? AND eleve_id = ?";
        $params[] = $appelId;
        $params[] = $eleveId;

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Sauvegarde en masse les statuts d'un appel (formulaire complet).
     */
    public function sauvegarderAppel(int $appelId, array $statuts): int
    {
        $count = 0;
        foreach ($statuts as $eleveId => $data) {
            $statut = $data['statut'] ?? 'present';
            $extras = [
                'heure_arrivee' => $data['heure_arrivee'] ?? null,
                'duree_retard'  => $data['duree_retard'] ?? null,
                'motif'         => $data['motif'] ?? null,
                'commentaire'   => $data['commentaire'] ?? null,
            ];
            if ($this->updateStatutEleve($appelId, (int)$eleveId, $statut, $extras)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Valide (clôture) un appel.
     */
    public function validerAppel(int $appelId): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE appels SET statut = 'valide', date_validation = NOW() WHERE id = ?"
        );
        $ok = $stmt->execute([$appelId]);

        // Créer automatiquement les absences/retards dans le module absences
        if ($ok) {
            $this->synchroniserAbsences($appelId);
        }

        return $ok;
    }

    /**
     * Synchronise les absences/retards vers les tables absences et retards.
     */
    protected function synchroniserAbsences(int $appelId): void
    {
        $appel = $this->getAppel($appelId);
        if (!$appel) return;

        $eleves = $this->getAppelEleves($appelId);

        foreach ($eleves as $ae) {
            if ($ae['statut'] === 'absent') {
                // Créer une absence
                $stmt = $this->pdo->prepare(
                    "INSERT INTO absences (id_eleve, date_debut, date_fin, type_absence, motif, justifie, signale_par)
                     VALUES (?, ?, ?, 'absence', ?, 0, ?)"
                );
                $dateDebut = $appel['date_appel'] . ' ' . $appel['heure_debut'];
                $dateFin   = $appel['date_appel'] . ' ' . $appel['heure_fin'];
                $stmt->execute([
                    $ae['eleve_id'], $dateDebut, $dateFin,
                    $ae['motif'] ?? null,
                    $appel['professeur_nom'] ?? 'Système'
                ]);
            } elseif ($ae['statut'] === 'retard') {
                // Créer un retard
                $stmt = $this->pdo->prepare(
                    "INSERT INTO retards (id_eleve, date_retard, duree_minutes, motif, justifie, signale_par)
                     VALUES (?, ?, ?, ?, 0, ?)"
                );
                $dateRetard = $appel['date_appel'] . ' ' . ($ae['heure_arrivee'] ?? $appel['heure_debut']);
                $stmt->execute([
                    $ae['eleve_id'], $dateRetard,
                    $ae['duree_retard'] ?? 0,
                    $ae['motif'] ?? null,
                    $appel['professeur_nom'] ?? 'Système'
                ]);
            }
        }
    }

    // ─── Listes d'appels ─────────────────────────────────────────

    /**
     * Retourne les appels d'un professeur pour une date.
     */
    public function getAppelsProfesseur(int $profId, ?string $date = null): array
    {
        $date = $date ?: date('Y-m-d');
        $sql = "SELECT a.*, cl.nom AS classe_nom, m.nom AS matiere_nom
                FROM appels a
                JOIN classes cl ON a.classe_id = cl.id
                LEFT JOIN matieres m ON a.matiere_id = m.id
                WHERE a.professeur_id = ? AND a.date_appel = ?
                ORDER BY a.heure_debut";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$profId, $date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retourne tous les appels (admin/vie scolaire).
     */
    public function getAppelsJour(?string $date = null, ?int $classeId = null): array
    {
        $date = $date ?: date('Y-m-d');
        $sql = "SELECT a.*, cl.nom AS classe_nom,
                       CONCAT(p.prenom, ' ', p.nom) AS professeur_nom,
                       m.nom AS matiere_nom,
                       (SELECT COUNT(*) FROM appel_eleves ae WHERE ae.appel_id = a.id AND ae.statut = 'absent') AS nb_absents,
                       (SELECT COUNT(*) FROM appel_eleves ae WHERE ae.appel_id = a.id AND ae.statut = 'retard') AS nb_retards,
                       (SELECT COUNT(*) FROM appel_eleves ae WHERE ae.appel_id = a.id) AS nb_total
                FROM appels a
                JOIN classes cl ON a.classe_id = cl.id
                JOIN professeurs p ON a.professeur_id = p.id
                LEFT JOIN matieres m ON a.matiere_id = m.id
                WHERE a.date_appel = ?";
        $params = [$date];

        if ($classeId) {
            $sql .= " AND a.classe_id = ?";
            $params[] = $classeId;
        }

        $sql .= " ORDER BY a.heure_debut, cl.nom";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Génère les sessions d'appel à partir de l'emploi du temps.
     */
    public function genererAppelsDepuisEDT(int $profId, string $date): array
    {
        $jourMap = [1 => 'lundi', 2 => 'mardi', 3 => 'mercredi', 4 => 'jeudi', 5 => 'vendredi', 6 => 'samedi'];
        $dow = (int)date('N', strtotime($date));
        $jour = $jourMap[$dow] ?? null;
        if (!$jour) return [];

        // Cours du professeur ce jour
        $sql = "SELECT e.*, cl.nom AS classe_nom, m.nom AS matiere_nom, c.heure_debut, c.heure_fin
                FROM emploi_du_temps e
                JOIN classes cl ON e.classe_id = cl.id
                JOIN matieres m ON e.matiere_id = m.id
                JOIN creneaux_horaires c ON e.creneau_id = c.id
                WHERE e.professeur_id = ? AND e.jour = ? AND e.actif = 1
                ORDER BY c.ordre";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$profId, $jour]);
        $coursJour = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $appelsGeneres = [];
        foreach ($coursJour as $cours) {
            // Vérifier qu'un appel n'existe pas déjà
            $stmtCheck = $this->pdo->prepare(
                "SELECT id FROM appels WHERE edt_id = ? AND date_appel = ?"
            );
            $stmtCheck->execute([$cours['id'], $date]);
            if ($stmtCheck->fetch()) continue;

            // Créer l'appel
            $appelId = $this->createAppel([
                'edt_id'        => $cours['id'],
                'classe_id'     => $cours['classe_id'],
                'professeur_id' => $profId,
                'matiere_id'    => $cours['matiere_id'],
                'date_appel'    => $date,
                'heure_debut'   => $cours['heure_debut'],
                'heure_fin'     => $cours['heure_fin'],
                'type_appel'    => 'cours',
            ]);
            $appelsGeneres[] = $appelId;
        }

        return $appelsGeneres;
    }

    // ─── Statistiques ────────────────────────────────────────────

    /**
     * Statistiques de présence pour une classe sur une période.
     */
    public function getStatsClasse(int $classeId, ?string $dateDebut = null, ?string $dateFin = null): array
    {
        $dateDebut = $dateDebut ?: date('Y-m-01');
        $dateFin   = $dateFin ?: date('Y-m-t');

        $sql = "SELECT ae.eleve_id,
                       e.nom, e.prenom,
                       SUM(CASE WHEN ae.statut = 'present' THEN 1 ELSE 0 END) AS nb_present,
                       SUM(CASE WHEN ae.statut = 'absent' THEN 1 ELSE 0 END) AS nb_absent,
                       SUM(CASE WHEN ae.statut = 'retard' THEN 1 ELSE 0 END) AS nb_retard,
                       COUNT(*) AS nb_total
                FROM appel_eleves ae
                JOIN appels a ON ae.appel_id = a.id
                JOIN eleves e ON ae.eleve_id = e.id
                WHERE a.classe_id = ? AND a.date_appel BETWEEN ? AND ?
                GROUP BY ae.eleve_id, e.nom, e.prenom
                ORDER BY e.nom, e.prenom";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$classeId, $dateDebut, $dateFin]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Taux de présence global.
     */
    public function getTauxPresence(?string $dateDebut = null, ?string $dateFin = null): float
    {
        $dateDebut = $dateDebut ?: date('Y-m-01');
        $dateFin   = $dateFin ?: date('Y-m-t');

        $sql = "SELECT
                    SUM(CASE WHEN ae.statut = 'present' THEN 1 ELSE 0 END) AS presents,
                    COUNT(*) AS total
                FROM appel_eleves ae
                JOIN appels a ON ae.appel_id = a.id
                WHERE a.date_appel BETWEEN ? AND ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$dateDebut, $dateFin]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || $row['total'] == 0) return 100.0;
        return round(($row['presents'] / $row['total']) * 100, 1);
    }

    // ─── Helpers ─────────────────────────────────────────────────

    public function getClasses(): array
    {
        return $this->pdo->query("SELECT * FROM classes WHERE actif = 1 ORDER BY niveau, nom")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMatieres(): array
    {
        return $this->pdo->query("SELECT * FROM matieres WHERE actif = 1 ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getElevesClasse(int $classeId): array
    {
        $stmt = $this->pdo->prepare("SELECT nom FROM classes WHERE id = ?");
        $stmt->execute([$classeId]);
        $nomClasse = $stmt->fetchColumn();
        if (!$nomClasse) return [];

        $stmt = $this->pdo->prepare(
            "SELECT id, nom, prenom, classe FROM eleves WHERE classe = ? AND actif = 1 ORDER BY nom, prenom"
        );
        $stmt->execute([$nomClasse]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── QR BATCH PRÉSENCE ───

    public function genererQrCours(int $edtId): string
    {
        return 'APPEL-' . $edtId . '-' . substr(md5($edtId . date('Y-m-d') . 'fronote'), 0, 12);
    }

    public function enregistrerRetardPrecis(int $appelId, int $eleveId, string $heureArrivee): void
    {
        $this->pdo->prepare("UPDATE appel_details SET statut = 'retard', heure_arrivee = :h WHERE appel_id = :aid AND eleve_id = :eid")
            ->execute([':h' => $heureArrivee, ':aid' => $appelId, ':eid' => $eleveId]);
    }

    public function exportPresencesPeriode(int $classeId, int $periodeId): array
    {
        $stmt = $this->pdo->prepare("SELECT CONCAT(e.prenom,' ',e.nom) AS eleve, ad.statut, a.date_appel FROM appel_details ad JOIN appels a ON ad.appel_id = a.id JOIN eleves e ON ad.eleve_id = e.id WHERE a.classe_id = :c AND a.periode_id = :p ORDER BY e.nom, a.date_appel");
        $stmt->execute([':c' => $classeId, ':p' => $periodeId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
