<?php
/**
 * DashboardService — Service métier pour le tableau de bord accueil.
 *
 * Centralise toutes les requêtes SQL du dashboard afin que accueil.php
 * reste un contrôleur léger. Utilise des requêtes avec JOINs sur les IDs
 * au lieu de filtrer par nom, et évite SELECT *.
 *
 * REF-2 : Plus de SHOW TABLES LIKE — on catch PDOException si la table manque.
 * FEAT-1/2/3/4/6 : Widgets dynamiques, résumé, badge messagerie, greeting.
 */
class DashboardService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ─── Événements à venir ──────────────────────────────────────────

    public function getProchainEvenements(array $user, int $limit = 3): array
    {
        $role = $user['role'] ?? '';
        $date = date('Y-m-d');

        $sql = "SELECT id, titre, type_evenement, date_debut, date_fin, description
                FROM evenements
                WHERE date_debut >= ?";
        $params = [$date];

        if ($role === 'eleve') {
            $classe = $user['classe'] ?? '';
            $sql .= " AND (visibilite = 'public' OR visibilite = 'eleves'
                       OR classes LIKE ?)";
            $params[] = '%' . $this->escapeLike($classe) . '%';
        } elseif ($role === 'professeur') {
            $sql .= " AND (visibilite = 'public' OR visibilite = 'professeurs'
                       OR id_professeur = ?)";
            $params[] = $user['id'];
        }

        $sql .= " ORDER BY date_debut ASC LIMIT " . (int) $limit;

        return $this->safeQuery($sql, $params);
    }

    // ─── Devoirs à faire ─────────────────────────────────────────────

    public function getDevoirsAFaire(array $user, int $limit = 3): array
    {
        $role = $user['role'] ?? '';
        $date = date('Y-m-d');

        $sql = "SELECT d.id, d.titre, d.date_rendu, d.description,
                       d.nom_matiere, d.nom_professeur
                FROM devoirs d
                WHERE d.date_rendu >= ?";
        $params = [$date];

        if ($role === 'eleve') {
            $sql .= " AND d.classe = ?";
            $params[] = $user['classe'] ?? '';
        } elseif ($role === 'professeur') {
            $sql .= " AND d.nom_professeur = ?";
            $params[] = trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? ''));
        }

        $sql .= " ORDER BY d.date_rendu ASC LIMIT " . (int) $limit;

        return $this->safeQuery($sql, $params);
    }

    // ─── Dernières notes ─────────────────────────────────────────────

    public function getDernieresNotes(array $user, int $limit = 3): array
    {
        $role = $user['role'] ?? '';

        $sql = "SELECT n.id, n.note, n.note_sur, n.date_note, n.date_creation,
                       m.nom AS nom_matiere,
                       CONCAT(e.prenom, ' ', e.nom) AS nom_eleve
                FROM notes n
                LEFT JOIN matieres m ON n.id_matiere = m.id
                LEFT JOIN eleves e ON n.id_eleve = e.id";
        $params = [];

        if ($role === 'eleve') {
            $sql .= " WHERE n.id_eleve = ?";
            $params[] = $user['id'];
        } elseif ($role === 'professeur') {
            $sql .= " WHERE n.id_professeur = ?";
            $params[] = $user['id'];
        }

        $sql .= " ORDER BY n.date_creation DESC LIMIT " . (int) $limit;

        return $this->safeQuery($sql, $params);
    }

    // ─── Absences du jour (vie scolaire) ─────────────────────────────

    public function getAbsencesDuJour(): array
    {
        $sql = "SELECT a.id, a.motif, a.statut,
                       CONCAT(e.prenom, ' ', e.nom) AS nom_eleve, e.classe
                FROM absences a
                LEFT JOIN eleves e ON a.id_eleve = e.id
                WHERE DATE(a.date_debut) <= CURDATE() AND DATE(a.date_fin) >= CURDATE()
                ORDER BY a.date_debut DESC LIMIT 5";
        return $this->safeQuery($sql);
    }

    // ─── Résumé par rôle (summary cards) ─────────────────────────────

    /**
     * FEAT-2 : Cartes de résumé adaptées au rôle.
     */
    public function getResume(array $user): array
    {
        $role = $user['role'] ?? '';
        $id   = $user['id'] ?? 0;

        switch ($role) {
            case 'eleve':
                return $this->getResumeEleve($id);
            case 'parent':
                return $this->getResumeParent($id);
            case 'professeur':
                return $this->getResumeProfesseur($id);
            case 'vie_scolaire':
                return $this->getResumeVieScolaire();
            case 'administrateur':
                return $this->getResumeAdmin();
            default:
                return [];
        }
    }

    private function getResumeEleve(int $eleveId): array
    {
        $trimestre = self::getTrimestreCourant();

        // Moyenne générale
        $rows = $this->safeQuery(
            "SELECT ROUND(AVG(n.note / n.note_sur * 20), 1) AS moyenne
             FROM notes n WHERE n.id_eleve = ? AND n.trimestre = ?",
            [$eleveId, $trimestre]
        );
        $moyenne = $rows[0]['moyenne'] ?? null;

        // Devoirs cette semaine
        $rows = $this->safeQuery(
            "SELECT COUNT(*) AS cnt FROM devoirs d
             LEFT JOIN eleves e ON d.classe = e.classe
             WHERE e.id = ? AND d.date_rendu BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)",
            [$eleveId]
        );
        $devoirsSemaine = $rows[0]['cnt'] ?? 0;

        // Absences trimestre
        $rows = $this->safeQuery(
            "SELECT COUNT(*) AS cnt FROM absences WHERE id_eleve = ? AND trimestre = ?",
            [$eleveId, $trimestre]
        );
        $absences = $rows[0]['cnt'] ?? 0;

        return [
            ['icon' => 'fas fa-chart-line',    'value' => ($moyenne !== null ? $moyenne . '/20' : '-'), 'label' => 'Moyenne générale', 'color' => 'primary'],
            ['icon' => 'fas fa-book',          'value' => $devoirsSemaine,  'label' => 'Devoirs cette semaine',  'color' => 'success'],
            ['icon' => 'fas fa-calendar-times', 'value' => $absences,       'label' => 'Absences ce trimestre',  'color' => 'danger'],
        ];
    }

    // ─── Résumé parent (M25 multi-enfants) ───────────────────────────

    /**
     * Récupère les enfants associés à un parent.
     */
    public function getEnfantsParent(int $parentId): array
    {
        return $this->safeQuery(
            "SELECT e.id, e.nom, e.prenom, e.classe, e.date_naissance, pe.lien
             FROM parent_eleve pe
             JOIN eleves e ON pe.id_eleve = e.id
             WHERE pe.id_parent = ? AND e.actif = 1
             ORDER BY e.prenom, e.nom",
            [$parentId]
        );
    }

    private function getResumeParent(int $parentId): array
    {
        $enfants = $this->getEnfantsParent($parentId);
        $nbEnfants = count($enfants);

        // Absences totales de tous les enfants
        $trimestre = self::getTrimestreCourant();
        $totalAbsences = 0;
        $totalDevoirs = 0;
        foreach ($enfants as $e) {
            $rows = $this->safeQuery(
                "SELECT COUNT(*) AS cnt FROM absences WHERE id_eleve = ? AND trimestre = ?",
                [$e['id'], $trimestre]
            );
            $totalAbsences += $rows[0]['cnt'] ?? 0;

            $rows = $this->safeQuery(
                "SELECT COUNT(*) AS cnt FROM devoirs d
                 WHERE d.classe = ? AND d.date_rendu BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)",
                [$e['classe'] ?? '']
            );
            $totalDevoirs += $rows[0]['cnt'] ?? 0;
        }

        return [
            ['icon' => 'fas fa-child',          'value' => $nbEnfants,     'label' => 'Enfant(s) inscrit(s)',    'color' => 'primary'],
            ['icon' => 'fas fa-book',            'value' => $totalDevoirs,  'label' => 'Devoirs cette semaine',   'color' => 'success'],
            ['icon' => 'fas fa-calendar-times',  'value' => $totalAbsences, 'label' => 'Absences ce trimestre',  'color' => 'danger'],
        ];
    }

    /**
     * Résumé pour un enfant spécifique (vue parent → enfant sélectionné).
     */
    public function getResumeEnfant(int $eleveId): array
    {
        return $this->getResumeEleve($eleveId);
    }

    private function getResumeProfesseur(int $profId): array
    {
        $trimestre = self::getTrimestreCourant();

        $rows = $this->safeQuery(
            "SELECT COUNT(*) AS cnt FROM notes WHERE id_professeur = ? AND trimestre = ?",
            [$profId, $trimestre]
        );
        $notesSaisies = $rows[0]['cnt'] ?? 0;

        $rows = $this->safeQuery(
            "SELECT COUNT(DISTINCT classe) AS cnt FROM devoirs WHERE id_professeur = ?",
            [$profId]
        );
        $nbClasses = $rows[0]['cnt'] ?? 0;

        $rows = $this->safeQuery(
            "SELECT COUNT(*) AS cnt FROM devoirs WHERE id_professeur = ? AND date_rendu BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)",
            [$profId]
        );
        $devoirsAVenir = $rows[0]['cnt'] ?? 0;

        return [
            ['icon' => 'fas fa-chart-bar',   'value' => $notesSaisies,   'label' => 'Notes saisies',           'color' => 'primary'],
            ['icon' => 'fas fa-users',       'value' => $nbClasses,      'label' => 'Classes assignées',        'color' => 'info'],
            ['icon' => 'fas fa-book',        'value' => $devoirsAVenir,  'label' => 'Devoirs à venir (7j)',     'color' => 'success'],
        ];
    }

    private function getResumeVieScolaire(): array
    {
        $rows = $this->safeQuery(
            "SELECT COUNT(*) AS cnt FROM absences
             WHERE DATE(date_debut) <= CURDATE() AND DATE(date_fin) >= CURDATE()"
        );
        $absencesJour = $rows[0]['cnt'] ?? 0;

        $rows = $this->safeQuery(
            "SELECT COUNT(*) AS cnt FROM absences WHERE statut = 'non_justifiee'"
        );
        $nonJustifiees = $rows[0]['cnt'] ?? 0;

        $rows = $this->safeQuery(
            "SELECT COUNT(*) AS cnt FROM evenements WHERE date_debut >= CURDATE() AND date_debut < DATE_ADD(CURDATE(), INTERVAL 7 DAY)"
        );
        $evenements = $rows[0]['cnt'] ?? 0;

        return [
            ['icon' => 'fas fa-user-times',    'value' => $absencesJour,  'label' => 'Absences aujourd\'hui',   'color' => 'danger'],
            ['icon' => 'fas fa-exclamation',    'value' => $nonJustifiees, 'label' => 'Non justifiées',         'color' => 'warning'],
            ['icon' => 'fas fa-calendar-check', 'value' => $evenements,    'label' => 'Événements (7j)',        'color' => 'info'],
        ];
    }

    private function getResumeAdmin(): array
    {
        $total = 0;
        foreach (['eleves', 'professeurs', 'vie_scolaire', 'administrateurs'] as $table) {
            $rows = $this->safeQuery("SELECT COUNT(*) AS cnt FROM {$table} WHERE actif = 1");
            $total += $rows[0]['cnt'] ?? 0;
        }

        $rows = $this->safeQuery(
            "SELECT COUNT(*) AS cnt FROM evenements WHERE date_debut >= CURDATE()"
        );
        $evenements = $rows[0]['cnt'] ?? 0;

        $rows = $this->safeQuery("SELECT COUNT(*) AS cnt FROM notes");
        $notes = $rows[0]['cnt'] ?? 0;

        return [
            ['icon' => 'fas fa-users',        'value' => $total,      'label' => 'Utilisateurs actifs', 'color' => 'primary'],
            ['icon' => 'fas fa-calendar',      'value' => $evenements, 'label' => 'Événements à venir',  'color' => 'info'],
            ['icon' => 'fas fa-chart-bar',     'value' => $notes,      'label' => 'Notes enregistrées',  'color' => 'success'],
        ];
    }

    // ─── Badge messagerie (FEAT-4) ───────────────────────────────────

    public function getUnreadMessageCount(int $userId, string $userType): int
    {
        $rows = $this->safeQuery(
            "SELECT COALESCE(SUM(cp.unread_count), 0) AS total
             FROM conversation_participants cp
             WHERE cp.user_id = ? AND cp.user_type = ? AND cp.is_deleted = 0",
            [$userId, $userType]
        );
        return (int) ($rows[0]['total'] ?? 0);
    }

    // ─── Widgets par rôle (FEAT-1) ───────────────────────────────────

    /**
     * Retourne la liste des widgets à afficher pour un rôle.
     */
    public function getWidgetsForRole(string $role, ?array $userPrefs = null): array
    {
        $defaults = match ($role) {
            'eleve'         => ['evenements', 'devoirs', 'notes'],
            'parent'        => ['enfants', 'evenements', 'devoirs'],
            'professeur'    => ['notes', 'devoirs', 'evenements'],
            'vie_scolaire'  => ['absences', 'evenements', 'devoirs'],
            'administrateur'=> ['evenements', 'devoirs', 'notes'],
            default         => ['evenements', 'devoirs', 'notes'],
        };

        // If user has custom prefs, intersect with role defaults
        if (is_array($userPrefs) && !empty($userPrefs)) {
            return array_values(array_intersect($userPrefs, $defaults));
        }

        return $defaults;
    }

    // ─── Modules par rôle (FEAT-3 + UX-1) ───────────────────────────

    /**
     * Retourne les modules à afficher, ordonnés par fréquence d'usage du rôle.
     * Chaque module a titre, icône, description adaptée et lien.
     */
    public function getModulesForRole(string $role): array
    {
        $all = [
            'notes' => [
                'icon' => 'fas fa-chart-bar', 'title' => 'Notes', 'href' => '../notes/notes.php',
                'css' => 'notes-card',
                'desc' => match ($role) {
                    'professeur'     => 'Saisissez et gérez les notes',
                    'administrateur' => 'Supervisez les notes et moyennes',
                    default          => 'Consultez vos notes et moyennes',
                },
            ],
            'agenda' => [
                'icon' => 'fas fa-calendar', 'title' => 'Agenda', 'href' => '../agenda/agenda.php',
                'css' => 'agenda-card',
                'desc' => match ($role) {
                    'professeur'     => 'Gérez votre planning et vos cours',
                    'administrateur' => 'Planifiez les événements',
                    default          => 'Consultez votre planning',
                },
            ],
            'cahier' => [
                'icon' => 'fas fa-book', 'title' => 'Cahier de textes', 'href' => '../cahierdetextes/cahierdetextes.php',
                'css' => 'devoirs-card',
                'desc' => match ($role) {
                    'professeur'     => 'Publiez devoirs et contenus de cours',
                    'administrateur' => 'Consultez les cahiers de textes',
                    default          => 'Consultez vos devoirs à faire',
                },
            ],
            'messagerie' => [
                'icon' => 'fas fa-envelope', 'title' => 'Messagerie', 'href' => '../messagerie/index.php',
                'css' => 'messagerie-card',
                'desc' => match ($role) {
                    'administrateur' => 'Gérez les communications',
                    default          => 'Communiquez avec l\'établissement',
                },
            ],
            'absences' => [
                'icon' => 'fas fa-calendar-times', 'title' => 'Absences', 'href' => '../absences/absences.php',
                'css' => 'absences-card',
                'desc' => match ($role) {
                    'vie_scolaire'   => 'Gérez les absences et retards',
                    'administrateur' => 'Supervisez les absences',
                    default          => 'Consultez vos absences',
                },
            ],
            'emploi_du_temps' => [
                'icon' => 'fas fa-table', 'title' => 'Emploi du temps', 'href' => '../emploi_du_temps/emploi_du_temps.php',
                'css' => 'edt-card',
                'desc' => match ($role) {
                    'administrateur' => 'Gérez les emplois du temps',
                    'professeur'     => 'Consultez votre emploi du temps',
                    default          => 'Consultez l\'emploi du temps',
                },
            ],
            'annonces' => [
                'icon' => 'fas fa-bullhorn', 'title' => 'Annonces', 'href' => '../annonces/annonces.php',
                'css' => 'annonces-card',
                'desc' => match ($role) {
                    'administrateur' => 'Publiez et gérez les annonces',
                    'professeur'     => 'Publiez des annonces',
                    default          => 'Consultez les annonces',
                },
            ],
            'appel' => [
                'icon' => 'fas fa-clipboard-check', 'title' => 'Appel', 'href' => '../appel/appel.php',
                'css' => 'appel-card',
                'desc' => match ($role) {
                    'professeur' => 'Faites l\'appel de vos classes',
                    default      => 'Gérez les appels',
                },
            ],
            'discipline' => [
                'icon' => 'fas fa-gavel', 'title' => 'Discipline', 'href' => '../discipline/incidents.php',
                'css' => 'discipline-card',
                'desc' => match ($role) {
                    'professeur' => 'Signalez des incidents',
                    default      => 'Gérez les incidents et sanctions',
                },
            ],
        ];

        // Ordre par rôle (UX-1)
        $order = match ($role) {
            'eleve'         => ['cahier', 'notes', 'emploi_du_temps', 'agenda', 'annonces', 'messagerie'],
            'parent'        => ['notes', 'emploi_du_temps', 'cahier', 'annonces', 'messagerie'],
            'professeur'    => ['notes', 'cahier', 'appel', 'emploi_du_temps', 'discipline', 'agenda', 'annonces', 'messagerie'],
            'vie_scolaire'  => ['absences', 'appel', 'discipline', 'emploi_du_temps', 'agenda', 'annonces', 'messagerie', 'notes', 'cahier'],
            'administrateur'=> ['notes', 'absences', 'appel', 'discipline', 'emploi_du_temps', 'annonces', 'agenda', 'cahier', 'messagerie'],
            default         => ['notes', 'emploi_du_temps', 'agenda', 'cahier', 'annonces', 'messagerie'],
        };

        // Modules réservés à certains rôles
        $staffOnly = ['absences', 'appel', 'discipline'];
        if (!in_array($role, ['vie_scolaire', 'administrateur', 'professeur'])) {
            $order = array_filter($order, fn($k) => !in_array($k, $staffOnly));
        }

        $modules = [];
        foreach ($order as $key) {
            if (isset($all[$key])) {
                $modules[] = $all[$key];
            }
        }
        return $modules;
    }

    // ─── Greeting contextuel (FEAT-6) ────────────────────────────────

    public static function getGreeting(): string
    {
        $hour = (int) date('H');
        return match (true) {
            $hour < 12  => 'Bonjour',
            $hour < 18  => 'Bon après-midi',
            default     => 'Bonsoir',
        };
    }

    // ─── Établissement cache (REF-4) ─────────────────────────────────

    public static function getEtablissementData(): array
    {
        if (isset($_SESSION['etablissement_cache'])) {
            return $_SESSION['etablissement_cache'];
        }
        $json_file = dirname(__DIR__, 2) . '/login/data/etablissement.json';
        $data = (file_exists($json_file) && is_readable($json_file))
            ? (json_decode(file_get_contents($json_file), true) ?: [])
            : [];
        $_SESSION['etablissement_cache'] = $data;
        return $data;
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    /**
     * Exécute une requête préparée et retourne les résultats.
     * Retourne [] si la table n'existe pas (PDOException).
     */
    private function safeQuery(string $sql, array $params = []): array
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Table inexistante ou erreur SQL → widget vide
            error_log("DashboardService query error: " . $e->getMessage());
            return [];
        }
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['%', '_'], ['\\%', '\\_'], $value);
    }

    public static function getTrimestreCourant(): int
    {
        $mois = (int) date('n');
        if ($mois >= 9 && $mois <= 12) return 1;
        if ($mois >= 1 && $mois <= 3)  return 2;
        return 3;
    }
}
