<?php
/**
 * EdtService — Service métier pour le module Emploi du Temps (M03).
 *
 * Centralise toutes les requêtes SQL : CRUD cours, détection conflits,
 * modifications ponctuelles, requêtes par rôle.
 */
class EdtService
{
    protected $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ─── Créneaux horaires ───────────────────────────────────────

    /**
     * Retourne tous les créneaux horaires ordonnés.
     */
    public function getCreneaux(): array
    {
        return $this->pdo->query(
            "SELECT * FROM creneaux_horaires ORDER BY ordre ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retourne uniquement les créneaux de type 'cours'.
     */
    public function getCreneauxCours(): array
    {
        return $this->pdo->query(
            "SELECT * FROM creneaux_horaires WHERE type = 'cours' ORDER BY ordre ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── Salles ──────────────────────────────────────────────────

    public function getSalles(): array
    {
        return $this->pdo->query(
            "SELECT * FROM salles WHERE actif = 1 ORDER BY nom ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSalle(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM salles WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function createSalle(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO salles (nom, batiment, capacite, type, equipements) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['nom'], $data['batiment'] ?? null, $data['capacite'] ?? null,
            $data['type'] ?? 'standard', $data['equipements'] ?? null
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    // ─── Cours EDT ───────────────────────────────────────────────

    /**
     * Retourne l'EDT complet d'une classe pour une semaine.
     */
    public function getEdtClasse(int $classeId, ?string $dateRef = null): array
    {
        $sql = "SELECT e.*, m.nom AS matiere_nom, m.couleur AS matiere_couleur,
                       CONCAT(p.prenom, ' ', p.nom) AS professeur_nom,
                       s.nom AS salle_nom, c.label AS creneau_label,
                       c.heure_debut AS creneau_heure_debut, c.heure_fin AS creneau_heure_fin
                FROM emploi_du_temps e
                JOIN matieres m ON e.matiere_id = m.id
                JOIN professeurs p ON e.professeur_id = p.id
                LEFT JOIN salles s ON e.salle_id = s.id
                JOIN creneaux_horaires c ON e.creneau_id = c.id
                WHERE e.classe_id = ? AND e.actif = 1
                ORDER BY FIELD(e.jour, 'lundi','mardi','mercredi','jeudi','vendredi','samedi'), c.ordre";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$classeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retourne l'EDT d'un professeur.
     */
    public function getEdtProfesseur(int $profId): array
    {
        $sql = "SELECT e.*, m.nom AS matiere_nom, m.couleur AS matiere_couleur,
                       cl.nom AS classe_nom,
                       s.nom AS salle_nom, c.label AS creneau_label,
                       c.heure_debut AS creneau_heure_debut, c.heure_fin AS creneau_heure_fin
                FROM emploi_du_temps e
                JOIN matieres m ON e.matiere_id = m.id
                JOIN classes cl ON e.classe_id = cl.id
                LEFT JOIN salles s ON e.salle_id = s.id
                JOIN creneaux_horaires c ON e.creneau_id = c.id
                WHERE e.professeur_id = ? AND e.actif = 1
                ORDER BY FIELD(e.jour, 'lundi','mardi','mercredi','jeudi','vendredi','samedi'), c.ordre";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$profId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retourne l'EDT d'un élève (via sa classe).
     */
    public function getEdtEleve(int $eleveId): array
    {
        $stmt = $this->pdo->prepare("SELECT classe FROM eleves WHERE id = ?");
        $stmt->execute([$eleveId]);
        $classe = $stmt->fetchColumn();
        if (!$classe) return [];

        // Trouver l'id de la classe
        $stmt = $this->pdo->prepare("SELECT id FROM classes WHERE nom = ? AND actif = 1 LIMIT 1");
        $stmt->execute([$classe]);
        $classeId = $stmt->fetchColumn();
        if (!$classeId) return [];

        return $this->getEdtClasse((int)$classeId);
    }

    /**
     * Retourne l'EDT complet selon le rôle.
     */
    public function getEdtByRole(string $role, int $userId): array
    {
        switch ($role) {
            case 'professeur':
                return $this->getEdtProfesseur($userId);
            case 'eleve':
                return $this->getEdtEleve($userId);
            case 'parent':
                return $this->getEdtParent($userId);
            case 'administrateur':
            case 'vie_scolaire':
                return []; // Sélection manuelle via filtre classe
            default:
                return [];
        }
    }

    /**
     * EDT pour un parent (premier enfant par défaut).
     */
    public function getEdtParent(int $parentId, ?int $eleveId = null): array
    {
        if ($eleveId) {
            // Vérifier que c'est bien un enfant du parent
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM parent_eleve WHERE id_parent = ? AND id_eleve = ?");
            $stmt->execute([$parentId, $eleveId]);
            if ($stmt->fetchColumn() > 0) {
                return $this->getEdtEleve($eleveId);
            }
            return [];
        }

        // Premier enfant
        $stmt = $this->pdo->prepare("SELECT id_eleve FROM parent_eleve WHERE id_parent = ? LIMIT 1");
        $stmt->execute([$parentId]);
        $eId = $stmt->fetchColumn();
        return $eId ? $this->getEdtEleve((int)$eId) : [];
    }

    /**
     * Crée un cours dans l'emploi du temps.
     */
    public function createCours(array $data): int
    {
        // Vérifier les conflits
        $conflits = $this->detecterConflits($data);
        if (!empty($conflits)) {
            throw new \RuntimeException('Conflits détectés : ' . implode(', ', $conflits));
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO emploi_du_temps (classe_id, matiere_id, professeur_id, salle_id, jour,
                creneau_id, heure_debut, heure_fin, groupe, type_cours, recurrence, couleur)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['classe_id'], $data['matiere_id'], $data['professeur_id'],
            $data['salle_id'] ?? null, $data['jour'], $data['creneau_id'],
            $data['heure_debut'], $data['heure_fin'],
            $data['groupe'] ?? null, $data['type_cours'] ?? 'cours',
            $data['recurrence'] ?? 'hebdomadaire', $data['couleur'] ?? null
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Met à jour un cours.
     */
    public function updateCours(int $id, array $data): bool
    {
        $data['id_exclude'] = $id;
        $conflits = $this->detecterConflits($data);
        if (!empty($conflits)) {
            throw new \RuntimeException('Conflits détectés : ' . implode(', ', $conflits));
        }

        $stmt = $this->pdo->prepare(
            "UPDATE emploi_du_temps SET classe_id = ?, matiere_id = ?, professeur_id = ?,
                salle_id = ?, jour = ?, creneau_id = ?, heure_debut = ?, heure_fin = ?,
                groupe = ?, type_cours = ?, recurrence = ?, couleur = ?
             WHERE id = ?"
        );
        return $stmt->execute([
            $data['classe_id'], $data['matiere_id'], $data['professeur_id'],
            $data['salle_id'] ?? null, $data['jour'], $data['creneau_id'],
            $data['heure_debut'], $data['heure_fin'],
            $data['groupe'] ?? null, $data['type_cours'] ?? 'cours',
            $data['recurrence'] ?? 'hebdomadaire', $data['couleur'] ?? null,
            $id
        ]);
    }

    /**
     * Supprime (désactive) un cours.
     */
    public function deleteCours(int $id): bool
    {
        $stmt = $this->pdo->prepare("UPDATE emploi_du_temps SET actif = 0 WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Récupère un cours par son ID.
     */
    public function getCours(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT e.*, m.nom AS matiere_nom, CONCAT(p.prenom, ' ', p.nom) AS professeur_nom,
                    s.nom AS salle_nom, c.label AS creneau_label, cl.nom AS classe_nom
             FROM emploi_du_temps e
             JOIN matieres m ON e.matiere_id = m.id
             JOIN professeurs p ON e.professeur_id = p.id
             JOIN classes cl ON e.classe_id = cl.id
             LEFT JOIN salles s ON e.salle_id = s.id
             JOIN creneaux_horaires c ON e.creneau_id = c.id
             WHERE e.id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // ─── Conflits ────────────────────────────────────────────────

    /**
     * Détecte les conflits d'emploi du temps :
     *  - Double affectation enseignant (même jour + même créneau)
     *  - Double affectation salle (même jour + même créneau)
     */
    public function detecterConflits(array $data): array
    {
        $conflits = [];
        $exclude = $data['id_exclude'] ?? 0;

        // Conflit professeur
        $sql = "SELECT e.id, cl.nom AS classe_nom
                FROM emploi_du_temps e
                JOIN classes cl ON e.classe_id = cl.id
                WHERE e.professeur_id = ? AND e.jour = ? AND e.creneau_id = ?
                  AND e.actif = 1 AND e.id != ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$data['professeur_id'], $data['jour'], $data['creneau_id'], $exclude]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $conflits[] = "Professeur déjà affecté le {$data['jour']} sur ce créneau ({$row['classe_nom']})";
        }

        // Conflit salle
        if (!empty($data['salle_id'])) {
            $sql = "SELECT e.id, cl.nom AS classe_nom
                    FROM emploi_du_temps e
                    JOIN classes cl ON e.classe_id = cl.id
                    WHERE e.salle_id = ? AND e.jour = ? AND e.creneau_id = ?
                      AND e.actif = 1 AND e.id != ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$data['salle_id'], $data['jour'], $data['creneau_id'], $exclude]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $conflits[] = "Salle déjà occupée le {$data['jour']} sur ce créneau ({$row['classe_nom']})";
            }
        }

        return $conflits;
    }

    /**
     * Analyse globale de tous les conflits dans l'emploi du temps.
     * Retourne les paires de cours en conflit.
     */
    public function scanAllConflits(): array
    {
        $conflits = [];

        // Conflits professeur : même professeur, même jour, même créneau
        $sql = "SELECT e1.id AS cours1_id, e2.id AS cours2_id,
                       e1.jour, c.label AS creneau,
                       CONCAT(p.prenom, ' ', p.nom) AS professeur,
                       cl1.nom AS classe1, cl2.nom AS classe2,
                       m1.nom AS matiere1, m2.nom AS matiere2
                FROM emploi_du_temps e1
                JOIN emploi_du_temps e2 ON e1.professeur_id = e2.professeur_id
                    AND e1.jour = e2.jour AND e1.creneau_id = e2.creneau_id
                    AND e1.id < e2.id AND e2.actif = 1
                JOIN professeurs p ON e1.professeur_id = p.id
                JOIN classes cl1 ON e1.classe_id = cl1.id
                JOIN classes cl2 ON e2.classe_id = cl2.id
                JOIN matieres m1 ON e1.matiere_id = m1.id
                JOIN matieres m2 ON e2.matiere_id = m2.id
                JOIN creneaux_horaires c ON e1.creneau_id = c.id
                WHERE e1.actif = 1";
        foreach ($this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $row['type'] = 'professeur';
            $row['description'] = "{$row['professeur']} : {$row['classe1']} ({$row['matiere1']}) vs {$row['classe2']} ({$row['matiere2']}) — {$row['jour']} {$row['creneau']}";
            $conflits[] = $row;
        }

        // Conflits salle : même salle, même jour, même créneau
        $sql = "SELECT e1.id AS cours1_id, e2.id AS cours2_id,
                       e1.jour, c.label AS creneau,
                       s.nom AS salle,
                       cl1.nom AS classe1, cl2.nom AS classe2,
                       m1.nom AS matiere1, m2.nom AS matiere2
                FROM emploi_du_temps e1
                JOIN emploi_du_temps e2 ON e1.salle_id = e2.salle_id
                    AND e1.jour = e2.jour AND e1.creneau_id = e2.creneau_id
                    AND e1.id < e2.id AND e2.actif = 1
                JOIN salles s ON e1.salle_id = s.id
                JOIN classes cl1 ON e1.classe_id = cl1.id
                JOIN classes cl2 ON e2.classe_id = cl2.id
                JOIN matieres m1 ON e1.matiere_id = m1.id
                JOIN matieres m2 ON e2.matiere_id = m2.id
                JOIN creneaux_horaires c ON e1.creneau_id = c.id
                WHERE e1.actif = 1 AND e1.salle_id IS NOT NULL";
        foreach ($this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $row['type'] = 'salle';
            $row['description'] = "Salle {$row['salle']} : {$row['classe1']} ({$row['matiere1']}) vs {$row['classe2']} ({$row['matiere2']}) — {$row['jour']} {$row['creneau']}";
            $conflits[] = $row;
        }

        return $conflits;
    }

    /**
     * Retourne le nombre de conflits actifs.
     */
    public function countConflits(): int
    {
        return count($this->scanAllConflits());
    }

    /**
     * Export de l'EDT d'une classe pour ExportService.
     */
    public function getEdtForExport(int $classeId): array
    {
        $cours = $this->getEdtClasse($classeId);
        $jours = ['lundi' => 1, 'mardi' => 2, 'mercredi' => 3, 'jeudi' => 4, 'vendredi' => 5, 'samedi' => 6];
        
        usort($cours, function($a, $b) use ($jours) {
            $dj = ($jours[$a['jour']] ?? 9) - ($jours[$b['jour']] ?? 9);
            return $dj !== 0 ? $dj : strcmp($a['creneau_heure_debut'] ?? '', $b['creneau_heure_debut'] ?? '');
        });

        $result = [];
        foreach ($cours as $c) {
            $result[] = [
                'Jour'       => ucfirst($c['jour']),
                'Créneau'    => $c['creneau_label'] ?? ($c['creneau_heure_debut'] . '-' . $c['creneau_heure_fin']),
                'Matière'    => $c['matiere_nom'],
                'Professeur' => $c['professeur_nom'],
                'Salle'      => $c['salle_nom'] ?? '-',
                'Type'       => ucfirst($c['type_cours'] ?? 'cours'),
            ];
        }
        return $result;
    }

    // ─── Modifications ponctuelles ───────────────────────────────

    /**
     * Crée une modification ponctuelle (annulation, déplacement, remplacement).
     */
    public function createModification(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO edt_modifications (edt_id, date_cours, type_modification,
                nouveau_professeur_id, nouvelle_salle_id, nouvelle_heure_debut,
                nouvelle_heure_fin, motif, createur_id, createur_type)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['edt_id'], $data['date_cours'], $data['type_modification'],
            $data['nouveau_professeur_id'] ?? null, $data['nouvelle_salle_id'] ?? null,
            $data['nouvelle_heure_debut'] ?? null, $data['nouvelle_heure_fin'] ?? null,
            $data['motif'] ?? null, $data['createur_id'] ?? null, $data['createur_type'] ?? null
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Retourne les modifications pour une date donnée d'un cours.
     */
    public function getModifications(int $edtId, string $date): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM edt_modifications WHERE edt_id = ? AND date_cours = ?"
        );
        $stmt->execute([$edtId, $date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── Helpers ─────────────────────────────────────────────────

    public function getClasses(): array
    {
        return $this->pdo->query(
            "SELECT * FROM classes WHERE actif = 1 ORDER BY niveau, nom"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMatieres(): array
    {
        return $this->pdo->query(
            "SELECT * FROM matieres WHERE actif = 1 ORDER BY nom"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProfesseurs(): array
    {
        return $this->pdo->query(
            "SELECT id, nom, prenom, matiere FROM professeurs WHERE actif = 1 ORDER BY nom, prenom"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Organise les cours en grille jour/créneau pour la vue hebdomadaire.
     */
    public function buildGrille(array $cours): array
    {
        $jours = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
        $grille = [];
        foreach ($cours as $c) {
            $grille[$c['creneau_id']][$c['jour']] = $c;
        }
        return $grille;
    }

    /**
     * Organise les cours par jour pour la vue liste.
     */
    public function buildParJour(array $cours): array
    {
        $parJour = [];
        foreach ($cours as $c) {
            $parJour[$c['jour']][] = $c;
        }
        return $parJour;
    }

    /**
     * Statistiques globales.
     */
    public function getStats(): array
    {
        $stats = [];
        $stats['total_cours'] = (int)$this->pdo->query("SELECT COUNT(*) FROM emploi_du_temps WHERE actif = 1")->fetchColumn();
        $stats['total_salles'] = (int)$this->pdo->query("SELECT COUNT(*) FROM salles WHERE actif = 1")->fetchColumn();
        $stats['total_classes'] = (int)$this->pdo->query("SELECT COUNT(*) FROM classes WHERE actif = 1")->fetchColumn();

        // Heures par professeur
        $stats['heures_par_prof'] = $this->pdo->query(
            "SELECT CONCAT(p.prenom, ' ', p.nom) AS prof, COUNT(*) AS nb_cours
             FROM emploi_du_temps e
             JOIN professeurs p ON e.professeur_id = p.id
             WHERE e.actif = 1
             GROUP BY e.professeur_id
             ORDER BY nb_cours DESC"
        )->fetchAll(PDO::FETCH_ASSOC);

        return $stats;
    }
}
