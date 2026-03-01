<?php
/**
 * VieScolaireService — Tableau de bord consolidé pour la vie scolaire (M10)
 * Agrège les données absences, retards, incidents, appels 
 */
class VieScolaireService {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    // ─── STATISTIQUES GLOBALES ───
    public function getStatsJour(string $date = null): array {
        $date = $date ?? date('Y-m-d');
        $stats = [];

        // Absences du jour
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM absences WHERE DATE(date_debut) <= ? AND DATE(date_fin) >= ?");
        $stmt->execute([$date, $date]);
        $stats['absences_jour'] = (int)$stmt->fetchColumn();

        // Retards du jour
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM retards WHERE DATE(date_retard) = ?");
        $stmt->execute([$date]);
        $stats['retards_jour'] = (int)$stmt->fetchColumn();

        // Incidents non traités
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM incidents WHERE statut IN ('signale','en_traitement')");
        $stats['incidents_ouverts'] = (int)$stmt->fetchColumn();

        // Justificatifs en attente
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM justificatifs WHERE traite = 0");
        $stats['justificatifs_attente'] = (int)$stmt->fetchColumn();

        // Appels non validés aujourd'hui
        if ($this->tableExists('appels')) {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM appels WHERE date_appel = ? AND statut = 'en_cours'");
            $stmt->execute([$date]);
            $stats['appels_en_cours'] = (int)$stmt->fetchColumn();
        }

        // Sanctions en cours
        if ($this->tableExists('sanctions')) {
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM sanctions WHERE statut = 'prononcee'");
            $stats['sanctions_actives'] = (int)$stmt->fetchColumn();
        }

        // Retenues planifiées
        if ($this->tableExists('retenues')) {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM retenues WHERE date_retenue >= ? AND statut = 'planifiee'");
            $stmt->execute([$date]);
            $stats['retenues_planifiees'] = (int)$stmt->fetchColumn();
        }

        return $stats;
    }

    // ─── ÉLÈVES À SURVEILLER ───
    public function getElevesASurveiller(int $limit = 10): array {
        $stmt = $this->pdo->prepare("
            SELECT e.id, e.nom, e.prenom, e.classe,
                (SELECT COUNT(*) FROM absences WHERE id_eleve = e.id AND justifie = 0) AS abs_injustifiees,
                (SELECT COUNT(*) FROM retards WHERE id_eleve = e.id) AS nb_retards,
                (SELECT COUNT(*) FROM incidents WHERE eleve_id = e.id) AS nb_incidents
            FROM eleves e
            WHERE e.actif = 1
            HAVING abs_injustifiees > 3 OR nb_retards > 5 OR nb_incidents > 2
            ORDER BY (abs_injustifiees * 3 + nb_retards + nb_incidents * 2) DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── ABSENCES RÉCENTES ───
    public function getAbsencesRecentes(int $limit = 20): array {
        $stmt = $this->pdo->prepare("
            SELECT a.*, e.nom, e.prenom, e.classe
            FROM absences a
            JOIN eleves e ON a.id_eleve = e.id
            ORDER BY a.date_signalement DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── INCIDENTS RÉCENTS ───
    public function getIncidentsRecents(int $limit = 10): array {
        if (!$this->tableExists('incidents')) return [];
        $stmt = $this->pdo->prepare("
            SELECT i.*, e.nom, e.prenom, e.classe
            FROM incidents i
            JOIN eleves e ON i.eleve_id = e.id
            ORDER BY i.date_creation DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── STATS PAR CLASSE ───
    public function getStatsParClasse(): array {
        $stmt = $this->pdo->query("
            SELECT e.classe,
                COUNT(DISTINCT e.id) AS nb_eleves,
                (SELECT COUNT(*) FROM absences a WHERE a.id_eleve IN (SELECT id FROM eleves WHERE classe = e.classe)) AS nb_absences,
                (SELECT COUNT(*) FROM retards r WHERE r.id_eleve IN (SELECT id FROM eleves WHERE classe = e.classe)) AS nb_retards
            FROM eleves e
            WHERE e.actif = 1
            GROUP BY e.classe
            ORDER BY e.classe
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── FICHE ÉLÈVE CONSOLIDÉE ───
    public function getFicheEleve(int $eleveId): array {
        $fiche = [];

        // Info élève
        $stmt = $this->pdo->prepare("SELECT * FROM eleves WHERE id = ?");
        $stmt->execute([$eleveId]);
        $fiche['eleve'] = $stmt->fetch(PDO::FETCH_ASSOC);

        // Absences
        $stmt = $this->pdo->prepare("SELECT * FROM absences WHERE id_eleve = ? ORDER BY date_debut DESC");
        $stmt->execute([$eleveId]);
        $fiche['absences'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Retards
        $stmt = $this->pdo->prepare("SELECT * FROM retards WHERE id_eleve = ? ORDER BY date_retard DESC");
        $stmt->execute([$eleveId]);
        $fiche['retards'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Incidents
        if ($this->tableExists('incidents')) {
            $stmt = $this->pdo->prepare("SELECT * FROM incidents WHERE eleve_id = ? ORDER BY date_incident DESC");
            $stmt->execute([$eleveId]);
            $fiche['incidents'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Sanctions
        if ($this->tableExists('sanctions')) {
            $stmt = $this->pdo->prepare("SELECT * FROM sanctions WHERE eleve_id = ? ORDER BY date_sanction DESC");
            $stmt->execute([$eleveId]);
            $fiche['sanctions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Compteurs
        $fiche['stats'] = [
            'absences' => count($fiche['absences']),
            'abs_injustifiees' => count(array_filter($fiche['absences'], fn($a) => !$a['justifie'])),
            'retards' => count($fiche['retards']),
            'incidents' => count($fiche['incidents'] ?? []),
            'sanctions' => count($fiche['sanctions'] ?? []),
        ];

        return $fiche;
    }

    // ─── RECHERCHE ÉLÈVE ───
    public function rechercherEleves(string $q): array {
        $like = "%{$q}%";
        $stmt = $this->pdo->prepare("
            SELECT id, nom, prenom, classe FROM eleves
            WHERE actif = 1 AND (nom LIKE ? OR prenom LIKE ? OR classe LIKE ?)
            ORDER BY nom LIMIT 20
        ");
        $stmt->execute([$like, $like, $like]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── TIMELINE ACTIVITÉ ───
    public function getTimeline(int $limit = 30): array {
        $events = [];

        // Absences récentes
        $stmt = $this->pdo->prepare("SELECT a.id, 'absence' AS type, a.date_signalement AS date_event, CONCAT(e.prenom, ' ', e.nom) AS eleve, e.classe, a.type_absence AS detail FROM absences a JOIN eleves e ON a.id_eleve = e.id ORDER BY a.date_signalement DESC LIMIT ?");
        $stmt->execute([$limit]);
        $events = array_merge($events, $stmt->fetchAll(PDO::FETCH_ASSOC));

        // Retards
        $stmt = $this->pdo->prepare("SELECT r.id, 'retard' AS type, r.date_signalement AS date_event, CONCAT(e.prenom, ' ', e.nom) AS eleve, e.classe, CONCAT(r.duree_minutes, ' min') AS detail FROM retards r JOIN eleves e ON r.id_eleve = e.id ORDER BY r.date_signalement DESC LIMIT ?");
        $stmt->execute([$limit]);
        $events = array_merge($events, $stmt->fetchAll(PDO::FETCH_ASSOC));

        // Incidents
        if ($this->tableExists('incidents')) {
            $stmt = $this->pdo->prepare("SELECT i.id, 'incident' AS type, i.date_creation AS date_event, CONCAT(e.prenom, ' ', e.nom) AS eleve, e.classe, i.type_incident AS detail FROM incidents i JOIN eleves e ON i.eleve_id = e.id ORDER BY i.date_creation DESC LIMIT ?");
            $stmt->execute([$limit]);
            $events = array_merge($events, $stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        // Trier par date
        usort($events, fn($a, $b) => strtotime($b['date_event']) - strtotime($a['date_event']));
        return array_slice($events, 0, $limit);
    }

    private function tableExists(string $name): bool {
        try {
            $this->pdo->query("SELECT 1 FROM `{$name}` LIMIT 1");
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
