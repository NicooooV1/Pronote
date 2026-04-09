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
            $this->cache = app('cache')->remember('modules:all', 300, function () {
                try {
                    $stmt = $this->pdo->query("SELECT * FROM modules_config ORDER BY sort_order, label");
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $result = [];
                    foreach ($rows as $row) {
                        $row['config'] = !empty($row['config_json']) ? json_decode($row['config_json'], true) : [];
                        $row['roles_autorises'] = !empty($row['roles_autorises']) ? json_decode($row['roles_autorises'], true) : null;
                        $result[$row['module_key']] = $row;
                    }
                    return $result;
                } catch (\PDOException $e) {
                    error_log("ModuleService::getAll error: " . $e->getMessage());
                    return [];
                }
            });
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
            $this->cache = null; app('cache')->forget('modules:all');
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
            $this->cache = null; app('cache')->forget('modules:all');
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
            $this->cache = null; app('cache')->forget('modules:all');
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
            $this->cache = null; app('cache')->forget('modules:all');
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
        $this->cache = null; app('cache')->forget('modules:all');
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

    // Static route map and role visibility removed — now sourced from DB
    // (populated by ModuleSDK::syncModule from module.json).

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
     * Reads route_path from DB (populated by ModuleSDK::syncModule from module.json).
     * Falls back to convention: {key}/{key}.php.
     */
    public function getRoute(string $moduleKey): string
    {
        $module = $this->get($moduleKey);
        if ($module !== null && !empty($module['route_path'])) {
            return $module['route_path'];
        }
        return $moduleKey . '/' . $moduleKey . '.php';
    }

    /**
     * Check if a module should be visible for a given role.
     * Uses the roles_autorises column from DB (editable via admin UI,
     * populated initially by ModuleSDK::syncModule from module.json permissions).
     * If no role restriction is set, the module is visible to all roles.
     */
    public function isVisibleForRole(string $moduleKey, string $role): bool
    {
        $module = $this->get($moduleKey);

        if ($module !== null && isset($module['roles_autorises'])) {
            $rolesDb = is_array($module['roles_autorises'])
                ? $module['roles_autorises']
                : json_decode($module['roles_autorises'], true);
            if (is_array($rolesDb) && count($rolesDb) > 0) {
                return in_array($role, $rolesDb, true);
            }
        }

        // No restriction configured — visible to all
        return true;
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
            $mod['route'] = $this->getRoute($key);
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
     * Returns modules grouped by topbar category for horizontal navigation.
     * Uses topbar_category from DB (or falls back to category mapping).
     *
     * @return array<string, array{label: string, icon: string, modules: array}>
     */
    public function getForTopbar(string $role): array
    {
        $all = $this->getAll();
        $categoryMeta = self::categoryMeta();
        $catOverrides = self::sidebarCategoryOverrides();

        // Topbar category labels (display names for dropdown headers)
        $topbarLabels = [
            'scolaire'      => 'Pedagogie',
            'vie_scolaire'  => 'Vie scol.',
            'communication' => 'Communication',
            'sante'         => 'Sante',
            'etablissement' => 'Etablissement',
            'logistique'    => 'Logistique',
            'systeme'       => 'Outils',
        ];

        $grouped = [];

        foreach ($all as $key => $mod) {
            if (empty($mod['enabled'])) continue;
            if (!$this->isVisibleForRole($key, $role)) continue;
            if (in_array($key, ['accueil', 'parametres', 'profil', 'notifications'])) continue;

            // Determine category: topbar_category (DB) > override > category (DB)
            $cat = $mod['topbar_category'] ?? $catOverrides[$key] ?? ($mod['category'] ?? 'systeme');
            if ($cat === 'navigation') continue; // Skip navigation items (handled separately)

            $mod['route'] = $this->getRoute($key);
            $mod['module_key'] = $key;

            if (!isset($grouped[$cat])) {
                $meta = $categoryMeta[$cat] ?? ['label' => ucfirst($cat), 'icon' => 'fas fa-folder', 'order' => 99];
                $grouped[$cat] = [
                    'label' => $topbarLabels[$cat] ?? $meta['label'],
                    'icon'  => $meta['icon'],
                    'order' => $meta['order'],
                    'modules' => [],
                ];
            }

            $grouped[$cat]['modules'][] = $mod;
        }

        // Sort categories by order
        uasort($grouped, fn($a, $b) => ($a['order'] ?? 99) <=> ($b['order'] ?? 99));

        // Sort modules within each category
        foreach ($grouped as &$group) {
            usort($group['modules'], function ($a, $b) {
                $oa = $a['topbar_sort_order'] ?? $a['sort_order'] ?? 100;
                $ob = $b['topbar_sort_order'] ?? $b['sort_order'] ?? 100;
                return $oa <=> $ob;
            });
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
            $mod['route'] = $this->getRoute($key);
        }
        return $all;
    }
}
