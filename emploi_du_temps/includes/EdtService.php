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

    // ─── Détection conflits horaires ─────────────────────────────

    /**
     * Détecte les chevauchements horaires pour une classe sur un jour donné.
     * Compare les plages heure_debut/heure_fin pour trouver les cours qui se superposent.
     *
     * @return array Liste des paires de cours en conflit avec détails.
     */
    public function detectConflicts(string $classeId, string $jour): array
    {
        $sql = "SELECT e.id, e.heure_debut, e.heure_fin,
                       m.nom AS matiere_nom,
                       CONCAT(p.prenom, ' ', p.nom) AS professeur_nom,
                       s.nom AS salle_nom
                FROM emploi_du_temps e
                JOIN matieres m ON e.matiere_id = m.id
                JOIN professeurs p ON e.professeur_id = p.id
                LEFT JOIN salles s ON e.salle_id = s.id
                WHERE e.classe_id = :classeId AND e.jour = :jour AND e.actif = 1
                ORDER BY e.heure_debut ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':classeId' => $classeId, ':jour' => $jour]);
        $cours = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $conflits = [];
        $count = count($cours);
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                // Chevauchement : A commence avant la fin de B ET B commence avant la fin de A
                if ($cours[$i]['heure_debut'] < $cours[$j]['heure_fin']
                    && $cours[$j]['heure_debut'] < $cours[$i]['heure_fin']) {
                    $conflits[] = [
                        'cours_a' => $cours[$i],
                        'cours_b' => $cours[$j],
                        'description' => "{$cours[$i]['matiere_nom']} ({$cours[$i]['heure_debut']}-{$cours[$i]['heure_fin']}) "
                            . "chevauche {$cours[$j]['matiere_nom']} ({$cours[$j]['heure_debut']}-{$cours[$j]['heure_fin']})",
                    ];
                }
            }
        }

        return $conflits;
    }

    // ─── Créneaux libres ─────────────────────────────────────────

    /**
     * Retourne les créneaux horaires disponibles pour une classe sur un jour donné.
     * Calcule les « trous » entre les cours existants dans la plage horaire spécifiée.
     *
     * @return array Liste de créneaux libres ['heure_debut' => ..., 'heure_fin' => ...].
     */
    public function findFreeSlots(string $classeId, string $jour, string $heureMin = '08:00', string $heureMax = '18:00'): array
    {
        $sql = "SELECT e.heure_debut, e.heure_fin
                FROM emploi_du_temps e
                WHERE e.classe_id = :classeId AND e.jour = :jour AND e.actif = 1
                ORDER BY e.heure_debut ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':classeId' => $classeId, ':jour' => $jour]);
        $occupied = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fusionner les créneaux qui se chevauchent
        $merged = [];
        foreach ($occupied as $slot) {
            $debut = max($slot['heure_debut'], $heureMin);
            $fin = min($slot['heure_fin'], $heureMax);
            if ($debut >= $fin) continue;

            if (empty($merged)) {
                $merged[] = ['heure_debut' => $debut, 'heure_fin' => $fin];
            } else {
                $last = &$merged[count($merged) - 1];
                if ($debut <= $last['heure_fin']) {
                    $last['heure_fin'] = max($last['heure_fin'], $fin);
                } else {
                    $merged[] = ['heure_debut' => $debut, 'heure_fin' => $fin];
                }
                unset($last);
            }
        }

        // Calculer les trous
        $free = [];
        $cursor = $heureMin;
        foreach ($merged as $slot) {
            if ($cursor < $slot['heure_debut']) {
                $free[] = ['heure_debut' => $cursor, 'heure_fin' => $slot['heure_debut']];
            }
            $cursor = $slot['heure_fin'];
        }
        if ($cursor < $heureMax) {
            $free[] = ['heure_debut' => $cursor, 'heure_fin' => $heureMax];
        }

        return $free;
    }

    // ─── Semaines A/B ────────────────────────────────────────────

    /**
     * Détermine le type de semaine (A ou B) à partir d'une date.
     * Basé sur le numéro de semaine ISO : impair = A, pair = B.
     *
     * @param string $date Date au format Y-m-d.
     * @return string 'A' ou 'B'.
     */
    public function getWeekType(string $date): string
    {
        $weekNumber = (int)(new \DateTime($date))->format('W');
        return ($weekNumber % 2 !== 0) ? 'A' : 'B';
    }

    // ─── Export ICS ──────────────────────────────────────────────

    /**
     * Génère une chaîne ICS (iCalendar) à partir de l'EDT d'un utilisateur.
     * Compatible avec Outlook, Google Calendar, Apple Calendar.
     *
     * @param int    $userId   Identifiant de l'utilisateur.
     * @param string $userType Type : 'professeur', 'eleve', 'parent'.
     * @return string Contenu ICS complet.
     */
    public function exportIcs(int $userId, string $userType): string
    {
        $cours = $this->getEdtByRole($userType, $userId);

        $ics  = "BEGIN:VCALENDAR\r\n";
        $ics .= "VERSION:2.0\r\n";
        $ics .= "PRODID:-//Fronote//EDT//FR\r\n";
        $ics .= "CALSCALE:GREGORIAN\r\n";
        $ics .= "METHOD:PUBLISH\r\n";
        $ics .= "X-WR-CALNAME:Emploi du temps Fronote\r\n";

        $joursMap = [
            'lundi' => 'MO', 'mardi' => 'TU', 'mercredi' => 'WE',
            'jeudi' => 'TH', 'vendredi' => 'FR', 'samedi' => 'SA',
        ];

        // Calculer le lundi de la semaine courante pour ancrer les événements
        $now = new \DateTime();
        $dayOfWeek = (int)$now->format('N'); // 1=lundi
        $monday = (clone $now)->modify('-' . ($dayOfWeek - 1) . ' days');

        foreach ($cours as $c) {
            $jourOffset = array_search($c['jour'], array_keys($joursMap));
            if ($jourOffset === false) continue;

            $dateJour = (clone $monday)->modify('+' . $jourOffset . ' days')->format('Ymd');
            $heureDebut = str_replace(':', '', $c['heure_debut'] ?? $c['creneau_heure_debut'] ?? '0800');
            $heureFin   = str_replace(':', '', $c['heure_fin'] ?? $c['creneau_heure_fin'] ?? '0900');

            $summary = $c['matiere_nom'] ?? 'Cours';
            $location = $c['salle_nom'] ?? '';
            $description = '';
            if (!empty($c['professeur_nom'])) $description .= 'Prof: ' . $c['professeur_nom'];
            if (!empty($c['classe_nom'])) $description .= ($description ? ' | ' : '') . 'Classe: ' . $c['classe_nom'];

            $uid = 'fronote-edt-' . ($c['id'] ?? uniqid()) . '@fronote';

            $ics .= "BEGIN:VEVENT\r\n";
            $ics .= "UID:{$uid}\r\n";
            $ics .= "DTSTART:{$dateJour}T{$heureDebut}00\r\n";
            $ics .= "DTEND:{$dateJour}T{$heureFin}00\r\n";
            $ics .= "SUMMARY:{$summary}\r\n";
            if ($location) $ics .= "LOCATION:{$location}\r\n";
            if ($description) $ics .= "DESCRIPTION:{$description}\r\n";
            $ics .= "RRULE:FREQ=WEEKLY;BYDAY={$joursMap[$c['jour']]}\r\n";
            $ics .= "END:VEVENT\r\n";
        }

        $ics .= "END:VCALENDAR\r\n";

        return $ics;
    }

    // ─── Notifications remplacement ──────────────────────────────

    /**
     * Récupère les données nécessaires pour notifier une modification d'EDT.
     * Retourne les informations de la modification, du cours original,
     * et la liste des destinataires (élèves + parents de la classe).
     *
     * @param int $modificationId Identifiant de la modification dans edt_modifications.
     * @return array Données structurées pour le dispatch de notifications.
     */
    public function notifyModification(int $modificationId): array
    {
        // Récupérer la modification avec le cours original
        $sql = "SELECT mod.*, mod.type_modification, mod.date_cours, mod.motif,
                       e.classe_id, e.matiere_id, e.professeur_id, e.jour,
                       e.heure_debut, e.heure_fin,
                       m.nom AS matiere_nom,
                       CONCAT(p.prenom, ' ', p.nom) AS professeur_nom,
                       cl.nom AS classe_nom,
                       s.nom AS salle_nom
                FROM edt_modifications mod
                JOIN emploi_du_temps e ON mod.edt_id = e.id
                JOIN matieres m ON e.matiere_id = m.id
                JOIN professeurs p ON e.professeur_id = p.id
                JOIN classes cl ON e.classe_id = cl.id
                LEFT JOIN salles s ON e.salle_id = s.id
                WHERE mod.id = :modificationId";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':modificationId' => $modificationId]);
        $modification = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$modification) {
            return [];
        }

        // Nouveau professeur (si remplacement)
        $nouveauProf = null;
        if (!empty($modification['nouveau_professeur_id'])) {
            $stmt = $this->pdo->prepare(
                "SELECT id, CONCAT(prenom, ' ', nom) AS nom_complet
                 FROM professeurs WHERE id = :profId"
            );
            $stmt->execute([':profId' => $modification['nouveau_professeur_id']]);
            $nouveauProf = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        // Nouvelle salle (si déplacement)
        $nouvelleSalle = null;
        if (!empty($modification['nouvelle_salle_id'])) {
            $stmt = $this->pdo->prepare(
                "SELECT id, nom FROM salles WHERE id = :salleId"
            );
            $stmt->execute([':salleId' => $modification['nouvelle_salle_id']]);
            $nouvelleSalle = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        // Récupérer les élèves de la classe concernée
        $stmt = $this->pdo->prepare(
            "SELECT e.id, e.nom, e.prenom, e.email
             FROM eleves e
             JOIN classes cl ON e.classe = cl.nom
             WHERE cl.id = :classeId AND e.actif = 1"
        );
        $stmt->execute([':classeId' => $modification['classe_id']]);
        $eleves = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Récupérer les parents des élèves
        $parents = [];
        if (!empty($eleves)) {
            $eleveIds = array_column($eleves, 'id');
            $placeholders = implode(',', array_fill(0, count($eleveIds), '?'));
            $stmt = $this->pdo->prepare(
                "SELECT DISTINCT pa.id, pa.nom, pa.prenom, pa.email
                 FROM parents pa
                 JOIN parent_eleve pe ON pa.id = pe.id_parent
                 WHERE pe.id_eleve IN ({$placeholders})"
            );
            $stmt->execute($eleveIds);
            $parents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Construire le message de notification
        $typeLabels = [
            'annulation'   => 'Cours annulé',
            'deplacement'  => 'Cours déplacé',
            'remplacement' => 'Remplacement de professeur',
        ];
        $label = $typeLabels[$modification['type_modification']] ?? 'Modification EDT';

        $message = "{$label} : {$modification['matiere_nom']} du {$modification['date_cours']}";
        if ($modification['motif']) {
            $message .= " — {$modification['motif']}";
        }
        if ($nouveauProf) {
            $message .= " (Remplaçant : {$nouveauProf['nom_complet']})";
        }
        if ($nouvelleSalle) {
            $message .= " (Nouvelle salle : {$nouvelleSalle['nom']})";
        }

        return [
            'modification'    => $modification,
            'nouveau_prof'    => $nouveauProf,
            'nouvelle_salle'  => $nouvelleSalle,
            'message'         => $message,
            'destinataires'   => [
                'eleves'  => $eleves,
                'parents' => $parents,
            ],
        ];
    }
}
