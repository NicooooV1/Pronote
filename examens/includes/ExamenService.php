<?php
/**
 * M27 – Examens & Épreuves — Service
 */
class ExamenService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /* ───────── EXAMENS ───────── */

    public function getExamens(string $statut = null): array
    {
        $sql = "SELECT e.*, (SELECT COUNT(*) FROM epreuves ep WHERE ep.examen_id = e.id) AS nb_epreuves FROM examens e WHERE 1=1";
        $params = [];
        if ($statut) { $sql .= ' AND e.statut = ?'; $params[] = $statut; }
        $sql .= ' ORDER BY e.date_debut DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getExamen(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM examens WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function creerExamen(array $d): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO examens (nom, type, date_debut, date_fin, description, statut, created_by) VALUES (?,?,?,?,?,?,?)");
        $stmt->execute([$d['nom'], $d['type'], $d['date_debut'], $d['date_fin'] ?: null, $d['description'] ?? null, 'planifie', $d['created_by'] ?? null]);
        return $this->pdo->lastInsertId();
    }

    public function modifierExamen(int $id, array $d): void
    {
        $stmt = $this->pdo->prepare("UPDATE examens SET nom=?, type=?, date_debut=?, date_fin=?, description=?, statut=? WHERE id=?");
        $stmt->execute([$d['nom'], $d['type'], $d['date_debut'], $d['date_fin'], $d['description'], $d['statut'], $id]);
    }

    public function supprimerExamen(int $id): void
    {
        $this->pdo->prepare("DELETE FROM examens WHERE id = ?")->execute([$id]);
    }

    /* ───────── ÉPREUVES ───────── */

    public function getEpreuves(int $examenId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT ep.*, m.nom AS matiere_nom, s.nom AS salle_nom,
                   (SELECT COUNT(*) FROM epreuve_convocations ec WHERE ec.epreuve_id = ep.id) AS nb_convocations,
                   (SELECT COUNT(*) FROM epreuve_surveillants es WHERE es.epreuve_id = ep.id) AS nb_surveillants
            FROM epreuves ep
            LEFT JOIN matieres m ON ep.matiere_id = m.id
            LEFT JOIN salles s ON ep.salle_id = s.id
            WHERE ep.examen_id = ?
            ORDER BY ep.date_epreuve
        ");
        $stmt->execute([$examenId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getEpreuve(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT ep.*, m.nom AS matiere_nom, s.nom AS salle_nom FROM epreuves ep LEFT JOIN matieres m ON ep.matiere_id = m.id LEFT JOIN salles s ON ep.salle_id = s.id WHERE ep.id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function creerEpreuve(array $d): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO epreuves (examen_id, matiere_id, intitule, date_epreuve, duree_minutes, salle_id, coefficient, type, consignes) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$d['examen_id'], $d['matiere_id'] ?: null, $d['intitule'], $d['date_epreuve'], $d['duree_minutes'], $d['salle_id'] ?: null, $d['coefficient'] ?? 1, $d['type'], $d['consignes'] ?? null]);
        return $this->pdo->lastInsertId();
    }

    public function supprimerEpreuve(int $id): void
    {
        $this->pdo->prepare("DELETE FROM epreuves WHERE id = ?")->execute([$id]);
    }

    /* ───────── CONVOCATIONS ───────── */

    public function getConvocations(int $epreuveId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT ec.*, e.prenom, e.nom AS eleve_nom, cl.nom AS classe_nom
            FROM epreuve_convocations ec
            JOIN eleves e ON ec.eleve_id = e.id
            LEFT JOIN classes cl ON e.classe_id = cl.id
            WHERE ec.epreuve_id = ?
            ORDER BY e.nom
        ");
        $stmt->execute([$epreuveId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function ajouterConvocation(int $epreuveId, int $eleveId, ?string $place): void
    {
        $stmt = $this->pdo->prepare("INSERT IGNORE INTO epreuve_convocations (epreuve_id, eleve_id, place) VALUES (?,?,?)");
        $stmt->execute([$epreuveId, $eleveId, $place]);
    }

    public function convoquerClasse(int $epreuveId, int $classeId): int
    {
        $eleves = $this->pdo->prepare("SELECT id FROM eleves WHERE classe_id = ? ORDER BY nom");
        $eleves->execute([$classeId]);
        $count = 0;
        $place = 1;
        foreach ($eleves->fetchAll(PDO::FETCH_ASSOC) as $e) {
            $this->ajouterConvocation($epreuveId, $e['id'], (string)$place++);
            $count++;
        }
        return $count;
    }

    public function saisirPresenceNote(int $convocationId, ?bool $present, ?float $note): void
    {
        $stmt = $this->pdo->prepare("UPDATE epreuve_convocations SET present = ?, note = ? WHERE id = ?");
        $stmt->execute([$present !== null ? ($present ? 1 : 0) : null, $note, $convocationId]);
    }

    /* ───────── SURVEILLANTS ───────── */

    public function getSurveillants(int $epreuveId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT es.*, CONCAT(p.prenom, ' ', p.nom) AS prof_nom
            FROM epreuve_surveillants es
            JOIN professeurs p ON es.professeur_id = p.id
            WHERE es.epreuve_id = ?
        ");
        $stmt->execute([$epreuveId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function ajouterSurveillant(int $epreuveId, int $profId, string $role = 'surveillant'): void
    {
        $stmt = $this->pdo->prepare("INSERT IGNORE INTO epreuve_surveillants (epreuve_id, professeur_id, role) VALUES (?,?,?)");
        $stmt->execute([$epreuveId, $profId, $role]);
    }

    /* ───────── HELPERS ───────── */

    public function getMatieres(): array
    {
        return $this->pdo->query("SELECT id, nom FROM matieres ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSalles(): array
    {
        return $this->pdo->query("SELECT id, nom, capacite FROM salles ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getClasses(): array
    {
        return $this->pdo->query("SELECT id, nom FROM classes ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProfesseurs(): array
    {
        return $this->pdo->query("SELECT id, prenom, nom FROM professeurs ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getConvocationsEleve(int $eleveId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT ec.*, ep.intitule, ep.date_epreuve, ep.duree_minutes, ep.type AS type_epreuve,
                   ex.nom AS examen_nom, s.nom AS salle_nom, m.nom AS matiere_nom
            FROM epreuve_convocations ec
            JOIN epreuves ep ON ec.epreuve_id = ep.id
            JOIN examens ex ON ep.examen_id = ex.id
            LEFT JOIN salles s ON ep.salle_id = s.id
            LEFT JOIN matieres m ON ep.matiere_id = m.id
            WHERE ec.eleve_id = ?
            ORDER BY ep.date_epreuve
        ");
        $stmt->execute([$eleveId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function typesExamen(): array
    {
        return ['brevet' => 'Brevet', 'bac' => 'Baccalauréat', 'bts' => 'BTS', 'partiel' => 'Partiel', 'controle' => 'Contrôle', 'autre' => 'Autre'];
    }

    public static function typesEpreuve(): array
    {
        return ['ecrit' => 'Écrit', 'oral' => 'Oral', 'pratique' => 'Pratique', 'tp' => 'TP'];
    }

    public static function statutBadge(string $s): string
    {
        $map = ['planifie' => 'info', 'en_cours' => 'warning', 'termine' => 'success', 'annule' => 'danger'];
        return '<span class="badge badge-' . ($map[$s] ?? 'secondary') . '">' . ucfirst(str_replace('_', ' ', $s)) . '</span>';
    }

    /* ───────── AUTO ROOM ASSIGNMENT ───────── */

    /**
     * Automatically assign students to rooms for an exam based on room capacity.
     * @return array ['assigned' => int, 'rooms' => [...]]
     */
    public function autoAssignRooms(int $epreuveId): array
    {
        $epreuve = $this->getEpreuve($epreuveId);
        if (!$epreuve) throw new \Exception("Épreuve introuvable");

        // Get all convocated students
        $convocations = $this->getConvocations($epreuveId);
        if (empty($convocations)) return ['assigned' => 0, 'rooms' => []];

        // Get available rooms sorted by capacity
        $salles = $this->pdo->query("SELECT * FROM salles WHERE actif = 1 ORDER BY capacite DESC")->fetchAll(\PDO::FETCH_ASSOC);
        if (empty($salles)) throw new \Exception("Aucune salle disponible");

        $assigned = 0;
        $roomAssignments = [];
        $studentIndex = 0;
        $totalStudents = count($convocations);

        foreach ($salles as $salle) {
            if ($studentIndex >= $totalStudents) break;

            $capacity = (int) ($salle['capacite'] ?? 30);
            $roomStudents = 0;

            while ($studentIndex < $totalStudents && $roomStudents < $capacity) {
                $conv = $convocations[$studentIndex];
                $place = $roomStudents + 1;

                // Update convocation with room and place
                $stmt = $this->pdo->prepare("
                    UPDATE epreuve_convocations SET salle_id = ?, place = ? WHERE id = ?
                ");
                $stmt->execute([$salle['id'], (string) $place, $conv['id']]);

                $roomStudents++;
                $studentIndex++;
                $assigned++;
            }

            $roomAssignments[] = [
                'salle_id'   => $salle['id'],
                'salle_nom'  => $salle['nom'],
                'nb_places'  => $roomStudents,
                'capacite'   => $capacity,
            ];
        }

        return ['assigned' => $assigned, 'rooms' => $roomAssignments];
    }

    /**
     * Generate surveillance planning for an exam (rotate profs across rooms/epreuves).
     */
    public function generateSurveillancePlanning(int $examenId): array
    {
        $epreuves = $this->getEpreuves($examenId);
        $profs = $this->getProfesseurs();
        if (empty($epreuves) || empty($profs)) return [];

        $planning = [];
        $profIndex = 0;
        $totalProfs = count($profs);

        foreach ($epreuves as $ep) {
            // Need at least 1 surveillant per 30 students
            $nbConvocations = $ep['nb_convocations'] ?? 0;
            $nbSurveillants = max(1, (int) ceil($nbConvocations / 30));

            $epPlanRow = [
                'epreuve_id' => $ep['id'],
                'intitule'   => $ep['intitule'],
                'date'       => $ep['date_epreuve'],
                'surveillants' => [],
            ];

            for ($i = 0; $i < $nbSurveillants && $i < $totalProfs; $i++) {
                $prof = $profs[$profIndex % $totalProfs];
                $role = ($i === 0) ? 'responsable' : 'surveillant';

                $this->ajouterSurveillant($ep['id'], $prof['id'], $role);

                $epPlanRow['surveillants'][] = [
                    'prof_id'  => $prof['id'],
                    'prof_nom' => $prof['prenom'] . ' ' . $prof['nom'],
                    'role'     => $role,
                ];
                $profIndex++;
            }

            $planning[] = $epPlanRow;
        }

        return $planning;
    }

    /* ───────── STATISTIQUES RÉSULTATS ───────── */

    /**
     * Résultats synthétiques d'un examen
     */
    public function getResultatsExamen(int $examenId): array
    {
        $epreuves = $this->getEpreuves($examenId);
        $results = [];
        foreach ($epreuves as $ep) {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) AS total,
                       SUM(CASE WHEN present = 1 THEN 1 ELSE 0 END) AS presents,
                       AVG(CASE WHEN note IS NOT NULL THEN note END) AS moyenne,
                       MIN(CASE WHEN note IS NOT NULL THEN note END) AS note_min,
                       MAX(CASE WHEN note IS NOT NULL THEN note END) AS note_max
                FROM examen_convocations WHERE epreuve_id = ?
            ");
            $stmt->execute([$ep['id']]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            $results[] = array_merge($ep, [
                'total_convoques' => (int)$data['total'],
                'presents' => (int)$data['presents'],
                'moyenne' => $data['moyenne'] ? round($data['moyenne'], 2) : null,
                'note_min' => $data['note_min'],
                'note_max' => $data['note_max'],
            ]);
        }
        return $results;
    }

    /* ───────── EXPORT ───────── */

    public function getExamensForExport(?string $statut = null): array
    {
        $examens = $this->getExamens($statut);
        $types = self::typesExamen();
        $rows = [];
        foreach ($examens as $e) {
            $rows[] = [
                $e['nom'] ?? $e['titre'] ?? '',
                $types[$e['type'] ?? ''] ?? $e['type'] ?? '',
                $e['date_debut'] ?? '',
                $e['date_fin'] ?? '',
                $e['statut'] ?? '',
                $e['nb_epreuves'] ?? 0,
            ];
        }
        return $rows;
    }

    public function getConvocationsForExport(int $epreuveId): array
    {
        $convocations = $this->getConvocations($epreuveId);
        $rows = [];
        foreach ($convocations as $c) {
            $rows[] = [
                $c['eleve_nom'] ?? ($c['nom'] ?? ''),
                $c['eleve_prenom'] ?? ($c['prenom'] ?? ''),
                $c['classe_nom'] ?? '',
                $c['place'] ?? '',
                isset($c['present']) ? ($c['present'] ? 'Oui' : 'Non') : '',
                $c['note'] ?? '',
            ];
        }
        return $rows;
    }

    // ─── PLAN DE SALLE AUTO ───

    /**
     * Génère un plan de salle automatique pour une épreuve.
     * Méthodes : 'alphabetique', 'aleatoire', 'alterne' (une place sur deux).
     */
    public function genererPlanSalle(int $epreuveId, string $methode = 'alphabetique'): array
    {
        $convocations = $this->getConvocations($epreuveId);
        if (empty($convocations)) return [];

        $eleves = $convocations;
        switch ($methode) {
            case 'aleatoire': shuffle($eleves); break;
            case 'alterne':
                usort($eleves, fn($a, $b) => strcmp($a['eleve_nom'] ?? $a['nom'] ?? '', $b['eleve_nom'] ?? $b['nom'] ?? ''));
                break;
            default: // alphabetique
                usort($eleves, fn($a, $b) => strcmp($a['eleve_nom'] ?? $a['nom'] ?? '', $b['eleve_nom'] ?? $b['nom'] ?? ''));
        }

        $places = [];
        $numero = 1;
        $increment = $methode === 'alterne' ? 2 : 1;
        foreach ($eleves as $e) {
            $place = str_pad((string)$numero, 3, '0', STR_PAD_LEFT);
            $places[] = ['convocation_id' => $e['id'], 'eleve_nom' => ($e['eleve_prenom'] ?? $e['prenom'] ?? '') . ' ' . ($e['eleve_nom'] ?? $e['nom'] ?? ''), 'place' => $place];

            $this->pdo->prepare("INSERT INTO examen_places (epreuve_id, convocation_id, numero_place) VALUES (:eid, :cid, :np) ON DUPLICATE KEY UPDATE numero_place = VALUES(numero_place)")
                ->execute([':eid' => $epreuveId, ':cid' => $e['id'], ':np' => $place]);

            $numero += $increment;
        }

        return $places;
    }

    // ─── CONVOCATIONS PDF PAR LOT ───

    /**
     * Génère les données de convocations pour PDF en lot.
     */
    public function genererConvocationsPdfData(int $examenId): array
    {
        $examen = $this->pdo->prepare("SELECT * FROM examens WHERE id = :id");
        $examen->execute([':id' => $examenId]);
        $ex = $examen->fetch(\PDO::FETCH_ASSOC);

        $epreuves = $this->pdo->prepare("SELECT * FROM epreuves WHERE examen_id = :eid ORDER BY date_epreuve, heure_debut");
        $epreuves->execute([':eid' => $examenId]);

        $convocationsData = [];
        foreach ($epreuves as $ep) {
            $convocations = $this->getConvocations($ep['id']);
            foreach ($convocations as $c) {
                $convocationsData[] = [
                    'examen' => $ex,
                    'epreuve' => $ep,
                    'eleve' => $c,
                    'numero_copie' => $c['numero_copie'] ?? null
                ];
            }
        }

        return $convocationsData;
    }

    // ─── NUMÉROS COPIES ANONYMES ───

    /**
     * Attribue des numéros de copie anonymes pour une épreuve.
     */
    public function attribuerNumerosCopies(int $epreuveId): int
    {
        $convocations = $this->getConvocations($epreuveId);
        $numeros = range(1, count($convocations));
        shuffle($numeros);

        $count = 0;
        foreach ($convocations as $idx => $c) {
            $num = str_pad((string)$numeros[$idx], 4, '0', STR_PAD_LEFT);
            $this->pdo->prepare("UPDATE epreuve_convocations SET numero_copie = :nc WHERE id = :id")
                ->execute([':nc' => $num, ':id' => $c['id']]);
            $count++;
        }

        return $count;
    }

    // ─── IMPORT RÉSULTATS CSV ───

    /**
     * Importe les résultats d'une épreuve depuis un CSV.
     * Format : numero_copie;note ou id_eleve;note
     */
    public function importResultats(int $epreuveId, string $filePath): array
    {
        if (!file_exists($filePath)) return ['imported' => 0, 'errors' => ['Fichier introuvable']];

        $handle = fopen($filePath, 'r');
        $header = fgetcsv($handle, 0, ';');
        $header = array_map('strtolower', array_map('trim', $header));

        $errors = [];
        $imported = 0;
        $ligne = 1;

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            $ligne++;
            $identifiant = $row[0] ?? '';
            $note = $row[1] ?? '';

            if (!is_numeric($note)) { $errors[] = "Ligne {$ligne}: note invalide"; continue; }

            // Trouver la convocation par numéro de copie ou par ID élève
            $conv = $this->pdo->prepare("SELECT id FROM epreuve_convocations WHERE epreuve_id = :eid AND (numero_copie = :nc OR eleve_id = :elid) LIMIT 1");
            $conv->execute([':eid' => $epreuveId, ':nc' => $identifiant, ':elid' => is_numeric($identifiant) ? (int)$identifiant : 0]);
            $convId = $conv->fetchColumn();

            if (!$convId) { $errors[] = "Ligne {$ligne}: élève non trouvé ({$identifiant})"; continue; }

            $this->pdo->prepare("UPDATE epreuve_convocations SET note = :n WHERE id = :id")
                ->execute([':n' => (float)$note, ':id' => $convId]);
            $imported++;
        }

        fclose($handle);
        return ['imported' => $imported, 'errors' => $errors];
    }
}
