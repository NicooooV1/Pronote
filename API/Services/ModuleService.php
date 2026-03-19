<?php
declare(strict_types=1);

namespace API\Services;

use PDO;

/**
 * Service de gestion des modules
 * 
 * Permet d'activer/désactiver/configurer chaque module de l'application.
 * Les modules « core » ne peuvent pas être désactivés.
 * La sidebar consulte ce service pour savoir quels liens afficher.
 */
class ModuleService
{
    private PDO $pdo;
    private ?array $cache = null;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ─── Lecture ──────────────────────────────────────────────────────────

    /**
     * Récupère tous les modules indexés par module_key
     */
    public function getAll(): array
    {
        if ($this->cache === null) {
            try {
                $stmt = $this->pdo->query("SELECT * FROM modules_config ORDER BY sort_order, label");
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $this->cache = [];
                foreach ($rows as $row) {
                    $row['config'] = !empty($row['config_json']) ? json_decode($row['config_json'], true) : [];
                    $row['roles_autorises'] = !empty($row['roles_autorises']) ? json_decode($row['roles_autorises'], true) : null;
                    $this->cache[$row['module_key']] = $row;
                }
            } catch (\PDOException $e) {
                error_log("ModuleService::getAll error: " . $e->getMessage());
                $this->cache = [];
            }
        }
        return $this->cache;
    }

    /**
     * Récupère un module par sa clé
     */
    public function get(string $key): ?array
    {
        $all = $this->getAll();
        return $all[$key] ?? null;
    }

    /**
     * Vérifie si un module est activé
     */
    public function isEnabled(string $key): bool
    {
        $module = $this->get($key);
        // Module inconnu → considéré activé (rétrocompat)
        if ($module === null) return true;
        return !empty($module['enabled']);
    }

    /**
     * Vérifie si un module est « core » (ne peut pas être désactivé)
     */
    public function isCore(string $key): bool
    {
        $module = $this->get($key);
        return $module !== null && !empty($module['is_core']);
    }

    /**
     * Récupère les modules par catégorie
     */
    public function getByCategory(): array
    {
        $all = $this->getAll();
        $categories = [];
        foreach ($all as $m) {
            $cat = $m['category'] ?? 'general';
            $categories[$cat][] = $m;
        }
        return $categories;
    }

    /**
     * Labels des catégories
     */
    public static function categoryLabels(): array
    {
        return [
            'navigation'    => 'Accueil',
            'scolaire'      => 'Pédagogie',
            'vie_scolaire'  => 'Vie scolaire',
            'communication' => 'Communication',
            'sante'         => 'Santé',
            'etablissement' => 'Établissement',
            'logistique'    => 'Outils',
            'systeme'       => 'Outils',
            'administration'=> 'Administration',
        ];
    }

    // ─── Écriture ────────────────────────────────────────────────────────

    /**
     * Active ou désactive un module
     */
    public function setEnabled(string $key, bool $enabled): bool
    {
        if ($this->isCore($key)) {
            return false; // Modules core ne peuvent pas être désactivés
        }

        try {
            $stmt = $this->pdo->prepare("UPDATE modules_config SET enabled = ? WHERE module_key = ? AND is_core = 0");
            $result = $stmt->execute([(int)$enabled, $key]);
            $this->cache = null; // Reset cache
            return $result;
        } catch (\PDOException $e) {
            error_log("ModuleService::setEnabled error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Active/désactive plusieurs modules en batch
     * @param array $states ['module_key' => bool, ...]
     */
    public function batchSetEnabled(array $states): int
    {
        $count = 0;
        foreach ($states as $key => $enabled) {
            if ($this->setEnabled($key, (bool)$enabled)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Met à jour la configuration JSON d'un module
     */
    public function updateConfig(string $key, array $config): bool
    {
        try {
            $stmt = $this->pdo->prepare("UPDATE modules_config SET config_json = ? WHERE module_key = ?");
            $result = $stmt->execute([json_encode($config, JSON_UNESCAPED_UNICODE), $key]);
            $this->cache = null;
            return $result;
        } catch (\PDOException $e) {
            error_log("ModuleService::updateConfig error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupère la configuration JSON d'un module
     */
    public function getConfig(string $key): array
    {
        $module = $this->get($key);
        if ($module === null) return [];
        return $module['config'] ?? [];
    }

    /**
     * Met à jour les rôles autorisés à voir un module.
     * Passer un tableau vide ou null pour revenir au comportement par défaut (tous les rôles).
     */
    public function updateRolesAutorises(string $key, ?array $roles): bool
    {
        try {
            $value = ($roles !== null && count($roles) > 0) ? json_encode(array_values($roles)) : null;
            $stmt = $this->pdo->prepare("UPDATE modules_config SET roles_autorises = ? WHERE module_key = ?");
            $result = $stmt->execute([$value, $key]);
            $this->cache = null;
            return $result;
        } catch (\PDOException $e) {
            error_log("ModuleService::updateRolesAutorises error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Met à jour le label et la description d'un module
     */
    public function updateInfo(string $key, array $data): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE modules_config SET
                    label = COALESCE(?, label),
                    description = COALESCE(?, description),
                    icon = COALESCE(?, icon),
                    sort_order = COALESCE(?, sort_order)
                WHERE module_key = ?
            ");
            $result = $stmt->execute([
                $data['label'] ?? null,
                $data['description'] ?? null,
                $data['icon'] ?? null,
                isset($data['sort_order']) ? (int)$data['sort_order'] : null,
                $key,
            ]);
            $this->cache = null;
            return $result;
        } catch (\PDOException $e) {
            error_log("ModuleService::updateInfo error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Vide le cache
     */
    public function clearCache(): void
    {
        $this->cache = null;
    }

    // ─── Stats ───────────────────────────────────────────────────────────

    /**
     * Compteurs rapides pour le dashboard admin
     */
    public function getStats(): array
    {
        $all = $this->getAll();
        $total = count($all);
        $enabled = count(array_filter($all, fn($m) => !empty($m['enabled'])));
        $core = count(array_filter($all, fn($m) => !empty($m['is_core'])));
        return compact('total', 'enabled', 'core');
    }

    // ─── Sidebar Integration ─────────────────────────────────────────────

    /**
     * Route map: module_key → relative path from project root.
     * Used to build sidebar links dynamically.
     */
    private static array $routeMap = [
        'accueil'              => 'accueil/accueil.php',
        'notes'                => 'notes/notes.php',
        'agenda'               => 'agenda/agenda.php',
        'cahierdetextes'       => 'cahierdetextes/cahierdetextes.php',
        'messagerie'           => 'messagerie/index.php',
        'annonces'             => 'annonces/annonces.php',
        'emploi_du_temps'      => 'emploi_du_temps/emploi_du_temps.php',
        'absences'             => 'absences/absences.php',
        'appel'                => 'appel/appel.php',
        'discipline'           => 'discipline/incidents.php',
        'vie_scolaire'         => 'vie_scolaire/dashboard.php',
        'reporting'            => 'reporting/reporting.php',
        'bulletins'            => 'bulletins/bulletins.php',
        'devoirs'              => 'devoirs/mes_devoirs.php',
        'competences'          => 'competences/competences.php',
        'trombinoscope'        => 'trombinoscope/trombinoscope.php',
        'documents'            => 'documents/documents.php',
        'notifications'        => 'notifications/notifications.php',
        'reunions'             => 'reunions/reunions.php',
        'bibliotheque'         => 'bibliotheque/catalogue.php',
        'clubs'                => 'clubs/clubs.php',
        'orientation'          => 'orientation/orientation.php',
        'inscriptions'         => 'inscriptions/inscriptions.php',
        'signalements'         => 'signalements/signaler.php',
        'infirmerie'           => 'infirmerie/infirmerie.php',
        'examens'              => 'examens/examens.php',
        'ressources'           => 'ressources/ressources.php',
        'diplomes'             => 'diplomes/diplomes.php',
        'periscolaire'         => 'periscolaire/services.php',
        'cantine'              => 'cantine/menus.php',
        'internat'             => 'internat/chambres.php',
        'garderie'             => 'garderie/creneaux.php',
        'stages'               => 'stages/stages.php',
        'transports'           => 'transports/lignes.php',
        'facturation'          => 'facturation/factures.php',
        'salles'               => 'salles/reservations.php',
        'personnel'            => 'personnel/absences.php',
        'besoins'              => 'besoins/besoins.php',
        'archivage'            => 'archivage/archivage.php',
        'rgpd'                 => 'rgpd/demandes.php',
        'support'              => 'support/aide.php',
        'projets_pedagogiques' => 'projets_pedagogiques/projets.php',
        'parcours_educatifs'   => 'parcours_educatifs/parcours.php',
        'vie_associative'      => 'vie_associative/associations.php',
        'parametres'           => 'parametres/parametres.php',
        'profil'               => 'profil/index.php',
    ];

    /**
     * Role-based visibility: module_key → list of roles that can see it.
     * If a module is NOT in this map, it is visible to ALL roles.
     */
    private static array $roleVisibility = [
        'absences'       => ['administrateur', 'vie_scolaire', 'professeur'],
        'appel'          => ['administrateur', 'vie_scolaire', 'professeur'],
        'discipline'     => ['administrateur', 'vie_scolaire', 'professeur'],
        'vie_scolaire'   => ['administrateur', 'vie_scolaire', 'professeur'],
        'reporting'      => ['administrateur', 'vie_scolaire', 'professeur'],
        'besoins'        => ['administrateur', 'vie_scolaire', 'professeur'],
        'salles'         => ['administrateur', 'vie_scolaire', 'professeur'],
        'personnel'      => ['administrateur', 'vie_scolaire'],
        'rgpd'           => ['administrateur', 'vie_scolaire'],
        'archivage'      => ['administrateur'],
        'facturation'    => ['administrateur', 'vie_scolaire', 'parent'],
        'internat'       => ['administrateur', 'vie_scolaire'],
        'infirmerie'     => ['administrateur', 'vie_scolaire', 'parent', 'eleve'],
    ];

    /**
     * Category display order + icons for sidebar headings.
     * Includes both new sidebar categories and legacy DB category keys.
     */
    public static function categoryMeta(): array
    {
        return [
            'navigation'    => ['label' => 'Accueil',              'icon' => 'fas fa-home',           'order' => 0],
            'scolaire'      => ['label' => 'Pédagogie',           'icon' => 'fas fa-graduation-cap', 'order' => 1],
            'vie_scolaire'  => ['label' => 'Vie scolaire',        'icon' => 'fas fa-school',         'order' => 2],
            'communication' => ['label' => 'Communication',       'icon' => 'fas fa-comments',       'order' => 3],
            'sante'         => ['label' => 'Santé',               'icon' => 'fas fa-heartbeat',      'order' => 4],
            'etablissement' => ['label' => 'Établissement',       'icon' => 'fas fa-building',       'order' => 5],
            'logistique'    => ['label' => 'Logistique',          'icon' => 'fas fa-cogs',           'order' => 6],
            'systeme'       => ['label' => 'Outils',              'icon' => 'fas fa-tools',          'order' => 7],
        ];
    }

    /**
     * Remap legacy DB category keys to new sidebar categories.
     * Modules whose module_key matches a key here get moved to the specified category.
     * This lets us reorganise the sidebar without changing the DB.
     */
    public static function sidebarCategoryOverrides(): array
    {
        return [
            // Move messagerie & notifications from navigation -> communication
            'messagerie'      => 'communication',
            'notifications'   => 'communication',
            // Move infirmerie from etablissement -> sante
            'infirmerie'      => 'sante',
            // Move vie_associative from etablissement -> systeme (outils)
            'vie_associative' => 'systeme',
        ];
    }

    /**
     * Get the route URL for a module.
     */
    public static function getRoute(string $moduleKey): string
    {
        return self::$routeMap[$moduleKey] ?? ($moduleKey . '/' . $moduleKey . '.php');
    }

    /**
     * Check if a module should be visible for a given role.
     * Prioritises roles_autorises from DB (editable via admin UI);
     * falls back to the hardcoded $roleVisibility map for modules that
     * have not yet been configured in the DB.
     */
    public function isVisibleForRole(string $moduleKey, string $role): bool
    {
        $module = $this->get($moduleKey);

        // DB column takes priority when present
        if ($module !== null && isset($module['roles_autorises'])) {
            $rolesDb = is_array($module['roles_autorises'])
                ? $module['roles_autorises']
                : json_decode($module['roles_autorises'], true);
            if (is_array($rolesDb) && count($rolesDb) > 0) {
                return in_array($role, $rolesDb, true);
            }
        }

        // Fallback to hardcoded map
        if (!isset(self::$roleVisibility[$moduleKey])) {
            return true;
        }
        return in_array($role, self::$roleVisibility[$moduleKey], true);
    }

    /**
     * Returns modules grouped by category for sidebar display.
     * Only includes enabled modules visible to the given role.
     * Skips 'accueil' and 'parametres' (handled separately in sidebar template).
     *
     * @return array<string, array> Keyed by category
     */
    public function getForSidebar(string $role): array
    {
        $all = $this->getAll();
        $grouped = [];
        $categoryMeta = self::categoryMeta();
        $catOverrides = self::sidebarCategoryOverrides();

        foreach ($all as $key => $mod) {
            if (empty($mod['enabled'])) continue;
            if (!$this->isVisibleForRole($key, $role)) continue;
            if (in_array($key, ['accueil', 'parametres'])) continue;

            // Apply sidebar category override if defined, otherwise use DB category
            $cat = $catOverrides[$key] ?? ($mod['category'] ?? 'general');
            $mod['route'] = self::getRoute($key);
            $mod['module_key'] = $key;
            $grouped[$cat][] = $mod;
        }

        // Sort categories by meta order
        uksort($grouped, function ($a, $b) use ($categoryMeta) {
            $oa = $categoryMeta[$a]['order'] ?? 99;
            $ob = $categoryMeta[$b]['order'] ?? 99;
            return $oa <=> $ob;
        });

        // Sort modules within each category by sort_order
        foreach ($grouped as &$modules) {
            usort($modules, fn($a, $b) => ($a['sort_order'] ?? 100) <=> ($b['sort_order'] ?? 100));
        }

        return $grouped;
    }

    /**
     * Returns a flat list of ALL modules with route info (for admin management).
     */
    public function getAllWithRoutes(): array
    {
        $all = $this->getAll();
        foreach ($all as $key => &$mod) {
            $mod['route'] = self::getRoute($key);
        }
        return $all;
    }
}
