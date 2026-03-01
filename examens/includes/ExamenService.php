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
}
