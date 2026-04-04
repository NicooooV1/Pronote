<?php
/**
 * DashboardService — Service metier pour le tableau de bord accueil.
 *
 * Centralise toutes les requetes SQL du dashboard afin que accueil.php
 * reste un controleur leger. Utilise des requetes avec JOINs sur les IDs
 * au lieu de filtrer par nom, et evite SELECT *.
 *
 * REF-2 : Plus de SHOW TABLES LIKE — on catch PDOException si la table manque.
 * FEAT-1/2/3/4/6 : Widgets dynamiques, resume, badge messagerie, greeting.
 * M104 : Widget management system (dashboard_widgets + user_dashboard_config).
 */
class DashboardService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // =====================================================================
    //  M104 — WIDGET MANAGEMENT (dashboard_widgets + user_dashboard_config)
    // =====================================================================

    /**
     * Retourne les widgets configurés pour un utilisateur.
     * Si l'utilisateur n'a pas encore personnalisé, renvoie les widgets par défaut.
     */
    public function getUserWidgets(int $userId, string $userType): array
    {
        // Cache session via ClientCache (évite la requête SQL sur chaque page accueil)
        $cc = class_exists('\\API\\Core\\ClientCache') ? new \API\Core\ClientCache() : null;
        $cacheKey = 'widgets_' . $userId . '_' . $userType;

        if ($cc) {
            $cached = $cc->get($cacheKey);
            if ($cached !== null && is_array($cached)) {
                return $cached;
            }
        }

        // 1) Charger la config utilisateur
        $userConfig = $this->safeQuery(
            "SELECT udc.widget_key, udc.position_x, udc.position_y, udc.width, udc.height,
                    udc.visible, udc.config AS user_config,
                    dw.label, dw.description, dw.icon, dw.type, dw.module_key,
                    dw.min_width, dw.max_width, dw.default_width, dw.default_height,
                    dw.default_config
             FROM user_dashboard_config udc
             JOIN dashboard_widgets dw ON dw.widget_key = udc.widget_key AND dw.actif = 1
             WHERE udc.user_id = ? AND udc.user_type = ?
             ORDER BY udc.position_y ASC, udc.position_x ASC",
            [$userId, $userType]
        );

        $result = [];
        if (!empty($userConfig)) {
            foreach ($userConfig as $row) {
                $row['config'] = $row['user_config'] ? json_decode($row['user_config'], true) : null;
                unset($row['user_config']);
                $result[] = $row;
            }
        } else {
            // 2) Pas de config => renvoyer les widgets par defaut pour ce role
            $result = $this->getDefaultWidgetsForRole($userType);
        }

        // Mettre en cache session (5 min) — invalidé par saveWidgetLayout()
        if ($cc) {
            $cc->set($cacheKey, $result, 300);
        }

        return $result;
    }

    /**
     * Retourne les widgets par défaut pour un rôle donné.
     */
    private function getDefaultWidgetsForRole(string $role): array
    {
        // FIX N+1: inclure roles_autorises dans la requête initiale
        $rows = $this->safeQuery(
            "SELECT widget_key, label, description, icon, type, module_key,
                    roles_autorises, min_width, max_width, default_width, default_height,
                    default_config, sort_order
             FROM dashboard_widgets
             WHERE actif = 1 AND is_default = 1
             ORDER BY sort_order ASC"
        );

        $widgets = [];
        $posY = 0;
        $posX = 0;

        foreach ($rows as $row) {
            // Vérifier l'accès par rôle directement depuis les données chargées
            $rolesJson = $row['roles_autorises'] ?? null;
            if ($rolesJson !== null) {
                $allowed = json_decode($rolesJson, true);
                if (is_array($allowed) && !in_array($role, $allowed, true)) {
                    continue;
                }
            }

            $row['position_x'] = $posX;
            $row['position_y'] = $posY;
            $row['width']  = (int) $row['default_width'];
            $row['height'] = (int) $row['default_height'];
            $row['visible'] = 1;
            $row['config']  = $row['default_config'] ? json_decode($row['default_config'], true) : null;

            $posX += (int) $row['default_width'];
            if ($posX >= 4) {
                $posX = 0;
                $posY++;
            }

            $widgets[] = $row;
        }

        return $widgets;
    }

    /**
     * Vérifie si un widget est accessible pour un rôle.
     */
    private function isWidgetAllowedForRole(string $widgetKey, string $role): bool
    {
        $rows = $this->safeQuery(
            "SELECT roles_autorises FROM dashboard_widgets WHERE widget_key = ? AND actif = 1",
            [$widgetKey]
        );

        if (empty($rows)) {
            return false;
        }

        $rolesJson = $rows[0]['roles_autorises'] ?? null;
        if ($rolesJson === null) {
            return true; // null = all roles
        }

        $allowed = json_decode($rolesJson, true);
        if (!is_array($allowed)) {
            return true;
        }

        return in_array($role, $allowed);
    }

    /**
     * Sauvegarde le layout complet des widgets d'un utilisateur.
     * $layout = [ ['widget_key' => '...', 'position_x' => 0, 'position_y' => 0, 'width' => 2, 'height' => 1, 'visible' => 1], ... ]
     */
    public function saveWidgetLayout(int $userId, string $userType, array $layout): bool
    {
        try {
            $this->pdo->beginTransaction();

            // Supprimer l'ancienne config
            $stmt = $this->pdo->prepare(
                "DELETE FROM user_dashboard_config WHERE user_id = ? AND user_type = ?"
            );
            $stmt->execute([$userId, $userType]);

            // Insérer la nouvelle config
            $stmt = $this->pdo->prepare(
                "INSERT INTO user_dashboard_config
                    (user_id, user_type, widget_key, position_x, position_y, width, height, visible, config)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            foreach ($layout as $item) {
                $stmt->execute([
                    $userId,
                    $userType,
                    $item['widget_key'],
                    (int) ($item['position_x'] ?? 0),
                    (int) ($item['position_y'] ?? 0),
                    (int) ($item['width'] ?? 2),
                    (int) ($item['height'] ?? 1),
                    (int) ($item['visible'] ?? 1),
                    isset($item['config']) ? json_encode($item['config']) : null,
                ]);
            }

            $this->pdo->commit();

            // Invalider le cache widget pour cet utilisateur
            $cc = class_exists('\\API\\Core\\ClientCache') ? new \API\Core\ClientCache() : null;
            if ($cc) {
                $cc->forget('widgets_' . $userId . '_' . $userType);
            }

            return true;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("DashboardService::saveWidgetLayout error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Active ou désactive un widget pour un utilisateur.
     */
    public function toggleWidget(int $userId, string $userType, string $widgetKey, bool $visible): bool
    {
        try {
            // Vérifier si la config utilisateur existe déjà
            $rows = $this->safeQuery(
                "SELECT id FROM user_dashboard_config WHERE user_id = ? AND user_type = ? AND widget_key = ?",
                [$userId, $userType, $widgetKey]
            );

            if (!empty($rows)) {
                $stmt = $this->pdo->prepare(
                    "UPDATE user_dashboard_config SET visible = ?, updated_at = NOW()
                     WHERE user_id = ? AND user_type = ? AND widget_key = ?"
                );
                return $stmt->execute([$visible ? 1 : 0, $userId, $userType, $widgetKey]);
            }

            // Pas de config existante : créer l'entrée
            // D'abord, s'assurer que toutes les configs par défaut sont créées
            $this->ensureUserConfig($userId, $userType);

            // Puis mettre à jour
            $stmt = $this->pdo->prepare(
                "UPDATE user_dashboard_config SET visible = ?, updated_at = NOW()
                 WHERE user_id = ? AND user_type = ? AND widget_key = ?"
            );
            return $stmt->execute([$visible ? 1 : 0, $userId, $userType, $widgetKey]);
        } catch (PDOException $e) {
            error_log("DashboardService::toggleWidget error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * S'assure qu'un utilisateur a une config initiale dans user_dashboard_config.
     */
    private function ensureUserConfig(int $userId, string $userType): void
    {
        $existing = $this->safeQuery(
            "SELECT COUNT(*) AS cnt FROM user_dashboard_config WHERE user_id = ? AND user_type = ?",
            [$userId, $userType]
        );

        if (($existing[0]['cnt'] ?? 0) > 0) {
            return;
        }

        // Créer la config à partir des widgets par défaut
        $defaults = $this->getDefaultWidgetsForRole($userType);
        foreach ($defaults as $w) {
            try {
                $stmt = $this->pdo->prepare(
                    "INSERT IGNORE INTO user_dashboard_config
                        (user_id, user_type, widget_key, position_x, position_y, width, height, visible, config)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 1, NULL)"
                );
                $stmt->execute([
                    $userId, $userType, $w['widget_key'],
                    $w['position_x'] ?? 0, $w['position_y'] ?? 0,
                    $w['width'] ?? $w['default_width'] ?? 2,
                    $w['height'] ?? $w['default_height'] ?? 1,
                ]);
            } catch (PDOException $e) {
                // Ignore duplicates
            }
        }
    }

    /**
     * Retourne tous les widgets disponibles pour un rôle (pour la modale de personnalisation).
     */
    public function getAvailableWidgets(string $role): array
    {
        $allWidgets = $this->safeQuery(
            "SELECT widget_key, label, description, icon, type, module_key,
                    roles_autorises, min_width, max_width, default_width, default_height,
                    is_default, sort_order
             FROM dashboard_widgets
             WHERE actif = 1
             ORDER BY sort_order ASC"
        );

        $available = [];
        foreach ($allWidgets as $w) {
            $rolesJson = $w['roles_autorises'] ?? null;
            if ($rolesJson !== null) {
                $allowed = json_decode($rolesJson, true);
                if (is_array($allowed) && !in_array($role, $allowed)) {
                    continue;
                }
            }
            $available[] = $w;
        }

        return $available;
    }

    /**
     * Récupère les données à afficher dans un widget spécifique.
     */
    public function renderWidgetData(string $widgetKey, int $userId, string $userType): array
    {
        // 1) Essayer de résoudre via le ModuleSDK (widgets déclarés dans module.json)
        try {
            if (function_exists('app')) {
                $sdk = app('module_sdk');
                $provider = $sdk->resolveWidgetProvider($widgetKey);
                if ($provider !== null) {
                    return $provider->getData($userId, $userType);
                }
            }
        } catch (\Throwable $e) {
            // Fallback silencieux vers le match hardcodé
            error_log("DashboardService: SDK widget resolution failed for '{$widgetKey}': " . $e->getMessage());
        }

        // 2) Fallback : renderers internes (rétro-compatibilité)
        return match ($widgetKey) {
            'prochains_evenements' => $this->renderProchainEvenements($userId, $userType),
            'devoirs_a_faire'     => $this->renderDevoirsAFaire($userId, $userType),
            'dernieres_notes'     => $this->renderDernieresNotes($userId, $userType),
            'messages_non_lus'    => $this->renderMessagesNonLus($userId, $userType),
            'absences_du_jour'    => $this->renderAbsencesDuJour($userId, $userType),
            'stats_rapides'       => $this->renderStatsRapides($userId, $userType),
            'emploi_du_temps_jour'=> $this->renderEmploiDuTempsJour($userId, $userType),
            'raccourcis'          => $this->renderRaccourcis($userId, $userType),
            'annonces_recentes'   => $this->renderAnnoncesRecentes($userId, $userType),
            'reunions_a_venir'    => $this->renderReunionsAVenir($userId, $userType),
            default               => ['type' => 'empty', 'items' => []],
        };
    }

    // --- Widget data renderers ---

    private function renderProchainEvenements(int $userId, string $userType): array
    {
        $user = $this->getUserInfo($userId, $userType);
        $events = $this->getProchainEvenements($user, 5);
        return ['type' => 'list', 'items' => $events, 'link' => '../agenda/agenda.php', 'link_label' => 'Voir tout'];
    }

    private function renderDevoirsAFaire(int $userId, string $userType): array
    {
        $user = $this->getUserInfo($userId, $userType);
        $devoirs = $this->getDevoirsAFaire($user, 5);
        return ['type' => 'list', 'items' => $devoirs, 'link' => '../cahierdetextes/cahierdetextes.php', 'link_label' => 'Voir tout'];
    }

    private function renderDernieresNotes(int $userId, string $userType): array
    {
        $user = $this->getUserInfo($userId, $userType);
        $notes = $this->getDernieresNotes($user, 5);
        return ['type' => 'list', 'items' => $notes, 'link' => '../notes/notes.php', 'link_label' => 'Voir tout'];
    }

    private function renderMessagesNonLus(int $userId, string $userType): array
    {
        $count = $this->getUnreadMessageCount($userId, $userType);
        return [
            'type'  => 'stat',
            'value' => $count,
            'label' => 'message' . ($count > 1 ? 's' : '') . ' non lu' . ($count > 1 ? 's' : ''),
            'icon'  => 'fas fa-envelope',
            'color' => $count > 0 ? 'warning' : 'success',
            'trend' => null,
            'link'  => '../messagerie/index.php',
            'link_label' => 'Ouvrir la messagerie',
        ];
    }

    private function renderAbsencesDuJour(int $userId, string $userType): array
    {
        $absences = $this->getAbsencesDuJour();
        $count = count($absences);
        return [
            'type'  => 'stat',
            'value' => $count,
            'label' => 'absence' . ($count > 1 ? 's' : '') . ' aujourd\'hui',
            'icon'  => 'fas fa-calendar-times',
            'color' => $count > 0 ? 'danger' : 'success',
            'trend' => null,
            'link'  => '../absences/absences.php',
            'link_label' => 'Voir les absences',
            'items' => $absences,
        ];
    }

    private function renderStatsRapides(int $userId, string $userType): array
    {
        $user = $this->getUserInfo($userId, $userType);
        $resume = $this->getResume($user);
        return ['type' => 'stats_grid', 'items' => $resume];
    }

    private function renderEmploiDuTempsJour(int $userId, string $userType): array
    {
        $jourSemaine = (int) date('N'); // 1=lundi ... 7=dimanche
        $cours = [];

        if ($userType === 'eleve') {
            $cours = $this->safeQuery(
                "SELECT c.heure_debut, c.heure_fin, m.nom AS matiere, s.nom AS salle,
                        CONCAT(p.prenom, ' ', p.nom) AS professeur
                 FROM cours c
                 LEFT JOIN matieres m ON c.matiere_id = m.id
                 LEFT JOIN salles s ON c.salle_id = s.id
                 LEFT JOIN professeurs p ON c.professeur_id = p.id
                 LEFT JOIN eleves e ON c.classe_id = e.classe_id
                 WHERE e.id = ? AND c.jour = ?
                 ORDER BY c.heure_debut ASC",
                [$userId, $jourSemaine]
            );
        } elseif ($userType === 'professeur') {
            $cours = $this->safeQuery(
                "SELECT c.heure_debut, c.heure_fin, m.nom AS matiere, s.nom AS salle,
                        cl.nom AS classe
                 FROM cours c
                 LEFT JOIN matieres m ON c.matiere_id = m.id
                 LEFT JOIN salles s ON c.salle_id = s.id
                 LEFT JOIN classes cl ON c.classe_id = cl.id
                 WHERE c.professeur_id = ? AND c.jour = ?
                 ORDER BY c.heure_debut ASC",
                [$userId, $jourSemaine]
            );
        }

        return [
            'type'  => 'calendar',
            'items' => $cours,
            'link'  => '../emploi_du_temps/emploi_du_temps.php',
            'link_label' => 'Emploi du temps complet',
        ];
    }

    private function renderRaccourcis(int $userId, string $userType): array
    {
        $modules = $this->getModulesForRole($userType);
        // Limiter aux 6 premiers
        $shortcuts = array_slice($modules, 0, 6);
        return ['type' => 'shortcut', 'items' => $shortcuts];
    }

    private function renderAnnoncesRecentes(int $userId, string $userType): array
    {
        $annonces = $this->safeQuery(
            "SELECT id, titre, contenu, date_publication, auteur, priorite
             FROM annonces
             WHERE actif = 1 AND (date_expiration IS NULL OR date_expiration >= CURDATE())
             ORDER BY priorite DESC, date_publication DESC
             LIMIT 5"
        );

        return [
            'type'  => 'list',
            'items' => $annonces,
            'link'  => '../annonces/annonces.php',
            'link_label' => 'Toutes les annonces',
        ];
    }

    private function renderReunionsAVenir(int $userId, string $userType): array
    {
        $reunions = $this->safeQuery(
            "SELECT id, titre, type_evenement, date_debut, date_fin, description
             FROM evenements
             WHERE type_evenement = 'reunion' AND date_debut >= CURDATE()
             ORDER BY date_debut ASC
             LIMIT 5"
        );

        return [
            'type'  => 'list',
            'items' => $reunions,
            'link'  => '../agenda/agenda.php',
            'link_label' => 'Voir l\'agenda',
        ];
    }

    /**
     * Helper : récupère les infos utilisateur minimales pour les méthodes existantes.
     */
    private function getUserInfo(int $userId, string $userType): array
    {
        $user = ['id' => $userId, 'role' => $userType];

        if ($userType === 'eleve') {
            $rows = $this->safeQuery("SELECT classe FROM eleves WHERE id = ?", [$userId]);
            $user['classe'] = $rows[0]['classe'] ?? '';
        } elseif ($userType === 'professeur') {
            $rows = $this->safeQuery("SELECT nom, prenom FROM professeurs WHERE id = ?", [$userId]);
            if (!empty($rows)) {
                $user['nom'] = $rows[0]['nom'];
                $user['prenom'] = $rows[0]['prenom'];
            }
        }

        return $user;
    }

    // =====================================================================
    //  METHODES EXISTANTES (inchangees)
    // =====================================================================

    // --- Evenements a venir ---

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

    // --- Devoirs a faire ---

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

    // --- Dernieres notes ---

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

    // --- Absences du jour (vie scolaire) ---

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

    // --- Resume par role (summary cards) ---

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

        $rows = $this->safeQuery(
            "SELECT ROUND(AVG(n.note / n.note_sur * 20), 1) AS moyenne
             FROM notes n WHERE n.id_eleve = ? AND n.trimestre = ?",
            [$eleveId, $trimestre]
        );
        $moyenne = $rows[0]['moyenne'] ?? null;

        $rows = $this->safeQuery(
            "SELECT COUNT(*) AS cnt FROM devoirs d
             LEFT JOIN eleves e ON d.classe = e.classe
             WHERE e.id = ? AND d.date_rendu BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)",
            [$eleveId]
        );
        $devoirsSemaine = $rows[0]['cnt'] ?? 0;

        $rows = $this->safeQuery(
            "SELECT COUNT(*) AS cnt FROM absences WHERE id_eleve = ? AND trimestre = ?",
            [$eleveId, $trimestre]
        );
        $absences = $rows[0]['cnt'] ?? 0;

        return [
            ['icon' => 'fas fa-chart-line',    'value' => ($moyenne !== null ? $moyenne . '/20' : '-'), 'label' => 'Moyenne generale', 'color' => 'primary'],
            ['icon' => 'fas fa-book',          'value' => $devoirsSemaine,  'label' => 'Devoirs cette semaine',  'color' => 'success'],
            ['icon' => 'fas fa-calendar-times', 'value' => $absences,       'label' => 'Absences ce trimestre',  'color' => 'danger'],
        ];
    }

    // --- Resume parent (M25 multi-enfants) ---

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

        if ($nbEnfants === 0) {
            return [
                ['icon' => 'fas fa-child', 'value' => 0, 'label' => 'Enfant(s) inscrit(s)', 'color' => 'primary'],
            ];
        }

        $trimestre = self::getTrimestreCourant();
        $childIds    = array_column($enfants, 'id');
        $childClasses = array_unique(array_filter(array_column($enfants, 'classe')));

        // Single aggregated query for absences across all children
        $placeholders = implode(',', array_fill(0, count($childIds), '?'));
        $absRows = $this->safeQuery(
            "SELECT COUNT(*) AS cnt FROM absences WHERE id_eleve IN ({$placeholders}) AND trimestre = ?",
            array_merge($childIds, [$trimestre])
        );
        $totalAbsences = $absRows[0]['cnt'] ?? 0;

        // Single aggregated query for devoirs across all children's classes
        $totalDevoirs = 0;
        if (!empty($childClasses)) {
            $clPlaceholders = implode(',', array_fill(0, count($childClasses), '?'));
            $devRows = $this->safeQuery(
                "SELECT COUNT(*) AS cnt FROM devoirs d
                 WHERE d.classe IN ({$clPlaceholders})
                   AND d.date_rendu BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)",
                array_values($childClasses)
            );
            $totalDevoirs = $devRows[0]['cnt'] ?? 0;
        }

        // Aggregated average across all children
        $moyRows = $this->safeQuery(
            "SELECT ROUND(AVG(n.note / n.note_sur * 20), 1) AS moyenne
             FROM notes n WHERE n.id_eleve IN ({$placeholders}) AND n.trimestre = ?",
            array_merge($childIds, [$trimestre])
        );
        $moyenneGlobale = $moyRows[0]['moyenne'] ?? null;

        $cards = [
            ['icon' => 'fas fa-child',          'value' => $nbEnfants,     'label' => 'Enfant(s) inscrit(s)',    'color' => 'primary'],
            ['icon' => 'fas fa-chart-line',     'value' => ($moyenneGlobale !== null ? $moyenneGlobale . '/20' : '-'), 'label' => 'Moyenne globale', 'color' => 'info'],
            ['icon' => 'fas fa-book',           'value' => $totalDevoirs,  'label' => 'Devoirs cette semaine',   'color' => 'success'],
            ['icon' => 'fas fa-calendar-times', 'value' => $totalAbsences, 'label' => 'Absences ce trimestre',  'color' => 'danger'],
        ];

        return $cards;
    }

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
            ['icon' => 'fas fa-users',       'value' => $nbClasses,      'label' => 'Classes assignees',        'color' => 'info'],
            ['icon' => 'fas fa-book',        'value' => $devoirsAVenir,  'label' => 'Devoirs a venir (7j)',     'color' => 'success'],
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
            ['icon' => 'fas fa-exclamation',    'value' => $nonJustifiees, 'label' => 'Non justifiees',         'color' => 'warning'],
            ['icon' => 'fas fa-calendar-check', 'value' => $evenements,    'label' => 'Evenements (7j)',        'color' => 'info'],
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
            ['icon' => 'fas fa-calendar',      'value' => $evenements, 'label' => 'Evenements a venir',  'color' => 'info'],
            ['icon' => 'fas fa-chart-bar',     'value' => $notes,      'label' => 'Notes enregistrees',  'color' => 'success'],
        ];
    }

    // --- Badge messagerie (FEAT-4) ---

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

    // --- Widgets par role (FEAT-1) — legacy, kept for backward compat ---

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

        if (is_array($userPrefs) && !empty($userPrefs)) {
            return array_values(array_intersect($userPrefs, $defaults));
        }

        return $defaults;
    }

    // --- Modules par role (FEAT-3 + UX-1) ---

    public function getModulesForRole(string $role): array
    {
        $all = [
            'notes' => [
                'icon' => 'fas fa-chart-bar', 'title' => 'Notes', 'href' => '../notes/notes.php',
                'css' => 'notes-card',
                'desc' => match ($role) {
                    'professeur'     => 'Saisissez et gerez les notes',
                    'administrateur' => 'Supervisez les notes et moyennes',
                    default          => 'Consultez vos notes et moyennes',
                },
            ],
            'agenda' => [
                'icon' => 'fas fa-calendar', 'title' => 'Agenda', 'href' => '../agenda/agenda.php',
                'css' => 'agenda-card',
                'desc' => match ($role) {
                    'professeur'     => 'Gerez votre planning et vos cours',
                    'administrateur' => 'Planifiez les evenements',
                    default          => 'Consultez votre planning',
                },
            ],
            'cahier' => [
                'icon' => 'fas fa-book', 'title' => 'Cahier de textes', 'href' => '../cahierdetextes/cahierdetextes.php',
                'css' => 'devoirs-card',
                'desc' => match ($role) {
                    'professeur'     => 'Publiez devoirs et contenus de cours',
                    'administrateur' => 'Consultez les cahiers de textes',
                    default          => 'Consultez vos devoirs a faire',
                },
            ],
            'messagerie' => [
                'icon' => 'fas fa-envelope', 'title' => 'Messagerie', 'href' => '../messagerie/index.php',
                'css' => 'messagerie-card',
                'desc' => match ($role) {
                    'administrateur' => 'Gerez les communications',
                    default          => 'Communiquez avec l\'etablissement',
                },
            ],
            'absences' => [
                'icon' => 'fas fa-calendar-times', 'title' => 'Absences', 'href' => '../absences/absences.php',
                'css' => 'absences-card',
                'desc' => match ($role) {
                    'vie_scolaire'   => 'Gerez les absences et retards',
                    'administrateur' => 'Supervisez les absences',
                    default          => 'Consultez vos absences',
                },
            ],
            'emploi_du_temps' => [
                'icon' => 'fas fa-table', 'title' => 'Emploi du temps', 'href' => '../emploi_du_temps/emploi_du_temps.php',
                'css' => 'edt-card',
                'desc' => match ($role) {
                    'administrateur' => 'Gerez les emplois du temps',
                    'professeur'     => 'Consultez votre emploi du temps',
                    default          => 'Consultez l\'emploi du temps',
                },
            ],
            'annonces' => [
                'icon' => 'fas fa-bullhorn', 'title' => 'Annonces', 'href' => '../annonces/annonces.php',
                'css' => 'annonces-card',
                'desc' => match ($role) {
                    'administrateur' => 'Publiez et gerez les annonces',
                    'professeur'     => 'Publiez des annonces',
                    default          => 'Consultez les annonces',
                },
            ],
            'appel' => [
                'icon' => 'fas fa-clipboard-check', 'title' => 'Appel', 'href' => '../appel/appel.php',
                'css' => 'appel-card',
                'desc' => match ($role) {
                    'professeur' => 'Faites l\'appel de vos classes',
                    default      => 'Gerez les appels',
                },
            ],
            'discipline' => [
                'icon' => 'fas fa-gavel', 'title' => 'Discipline', 'href' => '../discipline/incidents.php',
                'css' => 'discipline-card',
                'desc' => match ($role) {
                    'professeur' => 'Signalez des incidents',
                    default      => 'Gerez les incidents et sanctions',
                },
            ],
        ];

        $order = match ($role) {
            'eleve'         => ['cahier', 'notes', 'emploi_du_temps', 'agenda', 'annonces', 'messagerie'],
            'parent'        => ['notes', 'emploi_du_temps', 'cahier', 'annonces', 'messagerie'],
            'professeur'    => ['notes', 'cahier', 'appel', 'emploi_du_temps', 'discipline', 'agenda', 'annonces', 'messagerie'],
            'vie_scolaire'  => ['absences', 'appel', 'discipline', 'emploi_du_temps', 'agenda', 'annonces', 'messagerie', 'notes', 'cahier'],
            'administrateur'=> ['notes', 'absences', 'appel', 'discipline', 'emploi_du_temps', 'annonces', 'agenda', 'cahier', 'messagerie'],
            default         => ['notes', 'emploi_du_temps', 'agenda', 'cahier', 'annonces', 'messagerie'],
        };

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

    // --- Greeting contextuel (FEAT-6) ---

    public static function getGreeting(): string
    {
        $hour = (int) date('H');
        return match (true) {
            $hour < 12  => 'Bonjour',
            $hour < 18  => 'Bon apres-midi',
            default     => 'Bonsoir',
        };
    }

    // --- Etablissement cache (REF-4) ---

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

    // --- Helpers ---

    private function safeQuery(string $sql, array $params = []): array
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
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

    // =====================================================================
    //  NAMED LAYOUTS (dashboard_layouts)
    // =====================================================================

    /**
     * Get all saved layouts for a user.
     */
    public function getLayouts(int $userId, string $userType): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM dashboard_layouts WHERE user_id = ? AND user_type = ? ORDER BY is_active DESC, name"
        );
        $stmt->execute([$userId, $userType]);
        $layouts = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($layouts as &$l) {
            $l['widgets_config'] = json_decode($l['widgets_config'] ?? '[]', true) ?: [];
        }
        return $layouts;
    }

    /**
     * Get the active layout for a user, or null if none.
     */
    public function getActiveLayout(int $userId, string $userType): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM dashboard_layouts WHERE user_id = ? AND user_type = ? AND is_active = 1 LIMIT 1"
        );
        $stmt->execute([$userId, $userType]);
        $layout = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($layout) {
            $layout['widgets_config'] = json_decode($layout['widgets_config'] ?? '[]', true) ?: [];
        }
        return $layout ?: null;
    }

    /**
     * Save a new named layout (snapshot of current widget config).
     */
    public function saveLayout(int $userId, string $userType, string $name, int $columns = 4): int
    {
        // Snapshot current user_dashboard_config
        $stmt = $this->pdo->prepare(
            "SELECT widget_key, position_x, position_y, width, height, visible, config
             FROM user_dashboard_config WHERE user_id = ? AND user_type = ?"
        );
        $stmt->execute([$userId, $userType]);
        $widgetsConfig = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($widgetsConfig as &$w) {
            $w['config'] = $w['config'] ? json_decode($w['config'], true) : null;
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO dashboard_layouts (user_id, user_type, name, columns, widgets_config, is_active)
             VALUES (?, ?, ?, ?, ?, 0)
             ON DUPLICATE KEY UPDATE columns = VALUES(columns), widgets_config = VALUES(widgets_config), updated_at = NOW()"
        );
        $stmt->execute([
            $userId, $userType, $name, $columns,
            json_encode($widgetsConfig, JSON_UNESCAPED_UNICODE),
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Activate a named layout — restores widget positions from the layout snapshot.
     */
    public function activateLayout(int $userId, string $userType, int $layoutId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM dashboard_layouts WHERE id = ? AND user_id = ? AND user_type = ?"
        );
        $stmt->execute([$layoutId, $userId, $userType]);
        $layout = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$layout) return false;

        $widgetsConfig = json_decode($layout['widgets_config'] ?? '[]', true) ?: [];

        $this->pdo->beginTransaction();
        try {
            // Deactivate all layouts
            $this->pdo->prepare(
                "UPDATE dashboard_layouts SET is_active = 0 WHERE user_id = ? AND user_type = ?"
            )->execute([$userId, $userType]);

            // Activate this one
            $this->pdo->prepare(
                "UPDATE dashboard_layouts SET is_active = 1 WHERE id = ?"
            )->execute([$layoutId]);

            // Clear current user config
            $this->pdo->prepare(
                "DELETE FROM user_dashboard_config WHERE user_id = ? AND user_type = ?"
            )->execute([$userId, $userType]);

            // Restore from layout snapshot
            $insertStmt = $this->pdo->prepare(
                "INSERT INTO user_dashboard_config (user_id, user_type, widget_key, position_x, position_y, width, height, visible, config)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            foreach ($widgetsConfig as $w) {
                $insertStmt->execute([
                    $userId, $userType,
                    $w['widget_key'],
                    $w['position_x'] ?? 0,
                    $w['position_y'] ?? 0,
                    $w['width'] ?? 2,
                    $w['height'] ?? 1,
                    $w['visible'] ?? 1,
                    isset($w['config']) ? json_encode($w['config']) : null,
                ]);
            }

            $this->pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    /**
     * Delete a named layout.
     */
    public function deleteLayout(int $userId, string $userType, int $layoutId): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM dashboard_layouts WHERE id = ? AND user_id = ? AND user_type = ?"
        );
        $stmt->execute([$layoutId, $userId, $userType]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Update widget width for a specific widget in the user's current config.
     */
    public function updateWidgetSize(int $userId, string $userType, string $widgetKey, int $width, int $height): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE user_dashboard_config SET width = ?, height = ?, updated_at = NOW()
             WHERE user_id = ? AND user_type = ? AND widget_key = ?"
        );
        $stmt->execute([$width, $height, $userId, $userType, $widgetKey]);
        return $stmt->rowCount() > 0;
    }
}
