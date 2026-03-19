<?php
/**
 * Module SDK — Découverte, validation et gestion des modules via module.json
 *
 * Scanne les dossiers du projet pour trouver les fichiers module.json,
 * valide leur structure, synchronise avec la base de données (modules_config,
 * dashboard_widgets, module_permissions) et gère le cycle de vie des modules.
 *
 * Cycle de vie : discover → validate → install → enable → boot → disable → uninstall
 */

namespace API\Services;

class ModuleSDK
{
    private \PDO $pdo;
    private string $basePath;

    /** @var array|null Cache des manifestes découverts */
    private ?array $manifests = null;

    /** Champs obligatoires dans module.json */
    private const REQUIRED_FIELDS = ['key', 'name', 'icon', 'category'];

    /** Catégories valides */
    private const VALID_CATEGORIES = [
        'navigation', 'scolaire', 'vie_scolaire', 'communication',
        'etablissement', 'logistique', 'systeme', 'sante', 'custom'
    ];

    public function __construct(\PDO $pdo, string $basePath)
    {
        $this->pdo = $pdo;
        $this->basePath = rtrim($basePath, '/\\');
    }

    /**
     * Découvre tous les modules ayant un module.json
     *
     * @return array<string, array> [module_key => manifest]
     */
    public function discover(): array
    {
        if ($this->manifests !== null) {
            return $this->manifests;
        }

        $this->manifests = [];
        $dirs = glob($this->basePath . '/*/module.json');

        if ($dirs === false) {
            return $this->manifests;
        }

        foreach ($dirs as $jsonPath) {
            $content = file_get_contents($jsonPath);
            $manifest = json_decode($content, true);

            if (!is_array($manifest) || empty($manifest['key'])) {
                error_log("ModuleSDK: Invalid module.json at {$jsonPath}");
                continue;
            }

            $manifest['_path'] = dirname($jsonPath);
            $manifest['_json_path'] = $jsonPath;
            $this->manifests[$manifest['key']] = $manifest;
        }

        return $this->manifests;
    }

    /**
     * Valide la structure d'un manifeste module.json
     *
     * @return array ['valid' => bool, 'errors' => string[]]
     */
    public function validate(array $manifest): array
    {
        $errors = [];

        foreach (self::REQUIRED_FIELDS as $field) {
            if (empty($manifest[$field])) {
                $errors[] = "Champ obligatoire manquant : {$field}";
            }
        }

        // Valider le key (alphanumérique + underscore)
        if (!empty($manifest['key']) && !preg_match('/^[a-z][a-z0-9_]*$/', $manifest['key'])) {
            $errors[] = "Le key du module doit être en minuscules, alphanumérique avec underscores";
        }

        // Valider la catégorie
        if (!empty($manifest['category']) && !in_array($manifest['category'], self::VALID_CATEGORIES, true)) {
            $errors[] = "Catégorie invalide : {$manifest['category']}. Valides : " . implode(', ', self::VALID_CATEGORIES);
        }

        // Valider name (doit être un objet avec au moins 'fr')
        if (isset($manifest['name']) && is_array($manifest['name'])) {
            if (empty($manifest['name']['fr'])) {
                $errors[] = "Le champ name doit contenir au moins la clé 'fr'";
            }
        }

        // Valider les widgets
        if (!empty($manifest['widgets'])) {
            foreach ($manifest['widgets'] as $i => $widget) {
                if (empty($widget['key'])) {
                    $errors[] = "Widget #{$i} : champ 'key' manquant";
                }
                if (empty($widget['name'])) {
                    $errors[] = "Widget #{$i} : champ 'name' manquant";
                }
            }
        }

        // Valider les permissions
        if (!empty($manifest['permissions'])) {
            foreach ($manifest['permissions'] as $action => $config) {
                if (!is_array($config) || !isset($config['default_roles'])) {
                    $errors[] = "Permission '{$action}' : doit contenir 'default_roles'";
                }
            }
        }

        // Valider establishment_types
        if (isset($manifest['establishment_types']) && !is_array($manifest['establishment_types'])) {
            $errors[] = "establishment_types doit être un tableau";
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    /**
     * Synchronise tous les manifestes découverts avec la base de données.
     * Met à jour modules_config, dashboard_widgets et module_permissions.
     *
     * @return array ['synced' => int, 'errors' => string[]]
     */
    public function syncAll(): array
    {
        $manifests = $this->discover();
        $synced = 0;
        $errors = [];

        foreach ($manifests as $key => $manifest) {
            $validation = $this->validate($manifest);
            if (!$validation['valid']) {
                $errors[] = "Module '{$key}' invalide : " . implode(', ', $validation['errors']);
                continue;
            }

            try {
                $this->syncModule($manifest);
                $synced++;
            } catch (\Throwable $e) {
                $errors[] = "Module '{$key}' : " . $e->getMessage();
            }
        }

        return ['synced' => $synced, 'errors' => $errors];
    }

    /**
     * Synchronise un module individuel avec la base de données.
     */
    public function syncModule(array $manifest): void
    {
        $key = $manifest['key'];
        $name = is_array($manifest['name']) ? ($manifest['name']['fr'] ?? $key) : ($manifest['name'] ?? $key);
        $description = '';
        if (isset($manifest['description'])) {
            $description = is_array($manifest['description'])
                ? ($manifest['description']['fr'] ?? '')
                : $manifest['description'];
        }

        // Upsert dans modules_config
        $sql = "INSERT INTO modules_config (module_key, label, description, icon, category, is_core, establishment_types)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    label = VALUES(label),
                    description = VALUES(description),
                    icon = VALUES(icon),
                    category = VALUES(category),
                    establishment_types = VALUES(establishment_types)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $key,
            $name,
            $description,
            $manifest['icon'] ?? 'fas fa-puzzle-piece',
            $manifest['category'] ?? 'custom',
            !empty($manifest['core']) ? 1 : 0,
            isset($manifest['establishment_types']) ? json_encode($manifest['establishment_types']) : null,
        ]);

        // Synchroniser les widgets
        if (!empty($manifest['widgets'])) {
            $this->syncWidgets($key, $manifest['widgets']);
        }

        // Synchroniser les permissions
        if (!empty($manifest['permissions'])) {
            $this->syncPermissions($key, $manifest['permissions']);
        }
    }

    /**
     * Synchronise les widgets d'un module avec dashboard_widgets
     */
    private function syncWidgets(string $moduleKey, array $widgets): void
    {
        foreach ($widgets as $widget) {
            $widgetKey = $widget['key'];
            $label = is_array($widget['name']) ? ($widget['name']['fr'] ?? $widgetKey) : ($widget['name'] ?? $widgetKey);
            $description = '';
            if (isset($widget['description'])) {
                $description = is_array($widget['description'])
                    ? ($widget['description']['fr'] ?? '')
                    : $widget['description'];
            }

            $sql = "INSERT INTO dashboard_widgets (widget_key, label, description, icon, type, module_key, roles_autorises, default_width, is_default, sort_order)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        label = VALUES(label),
                        description = VALUES(description),
                        icon = VALUES(icon),
                        type = VALUES(type),
                        roles_autorises = VALUES(roles_autorises),
                        default_width = VALUES(default_width)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $widgetKey,
                $label,
                $description,
                $widget['icon'] ?? 'fas fa-th',
                $widget['type'] ?? 'list',
                $moduleKey,
                !empty($widget['roles']) ? json_encode($widget['roles']) : null,
                $widget['default_size']['width'] ?? 2,
                !empty($widget['is_default']) ? 1 : 0,
                $widget['sort_order'] ?? 50,
            ]);
        }
    }

    /**
     * Synchronise les permissions d'un module avec module_permissions
     */
    private function syncPermissions(string $moduleKey, array $permissions): void
    {
        foreach ($permissions as $action => $config) {
            $defaultRoles = $config['default_roles'] ?? [];

            // Vérifier si la table module_permissions existe
            try {
                $sql = "INSERT INTO module_permissions (module_key, action_key, default_roles)
                        VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                            default_roles = VALUES(default_roles)";

                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([
                    $moduleKey,
                    $action,
                    json_encode($defaultRoles),
                ]);
            } catch (\Throwable $e) {
                // Table peut ne pas exister encore — silencieux
                error_log("ModuleSDK: Cannot sync permission {$moduleKey}.{$action}: " . $e->getMessage());
            }
        }
    }

    /**
     * Récupère le manifeste d'un module par sa clé.
     */
    public function getManifest(string $key): ?array
    {
        $manifests = $this->discover();
        return $manifests[$key] ?? null;
    }

    /**
     * Retourne la configuration des widgets d'un module.
     * Utilisé par le DashboardService pour résoudre les data_providers.
     *
     * @return array<string, array> [widget_key => widget_config]
     */
    public function getWidgetConfigs(string $moduleKey): array
    {
        $manifest = $this->getManifest($moduleKey);
        if (!$manifest || empty($manifest['widgets'])) {
            return [];
        }

        $result = [];
        foreach ($manifest['widgets'] as $widget) {
            $widget['_module_path'] = $manifest['_path'] ?? '';
            $result[$widget['key']] = $widget;
        }
        return $result;
    }

    /**
     * Retourne toutes les configurations de widgets de tous les modules.
     *
     * @return array<string, array> [widget_key => widget_config_with_module_info]
     */
    public function getAllWidgetConfigs(): array
    {
        $manifests = $this->discover();
        $result = [];

        foreach ($manifests as $moduleKey => $manifest) {
            if (empty($manifest['widgets'])) {
                continue;
            }
            foreach ($manifest['widgets'] as $widget) {
                $widget['_module_key'] = $moduleKey;
                $widget['_module_path'] = $manifest['_path'] ?? '';
                $result[$widget['key']] = $widget;
            }
        }

        return $result;
    }

    /**
     * Résout et instancie le WidgetDataProvider d'un widget.
     *
     * @return \API\Contracts\WidgetDataProvider|null
     */
    public function resolveWidgetProvider(string $widgetKey): ?\API\Contracts\WidgetDataProvider
    {
        $allWidgets = $this->getAllWidgetConfigs();
        $config = $allWidgets[$widgetKey] ?? null;

        if (!$config || empty($config['data_provider'])) {
            return null;
        }

        $modulePath = $config['_module_path'] ?? '';
        $providerPath = $modulePath . '/' . $config['data_provider'];

        if (!file_exists($providerPath)) {
            error_log("ModuleSDK: Widget provider not found at {$providerPath}");
            return null;
        }

        require_once $providerPath;

        // Déduire le nom de la classe depuis le nom du fichier
        $className = pathinfo($config['data_provider'], PATHINFO_FILENAME);

        if (!class_exists($className)) {
            error_log("ModuleSDK: Class '{$className}' not found in {$providerPath}");
            return null;
        }

        $instance = new $className($this->pdo);

        if (!($instance instanceof \API\Contracts\WidgetDataProvider)) {
            error_log("ModuleSDK: Class '{$className}' does not implement WidgetDataProvider");
            return null;
        }

        return $instance;
    }

    /**
     * Résout le chemin du template d'un widget.
     */
    public function resolveWidgetTemplate(string $widgetKey): ?string
    {
        $allWidgets = $this->getAllWidgetConfigs();
        $config = $allWidgets[$widgetKey] ?? null;

        if (!$config || empty($config['template'])) {
            return null;
        }

        $modulePath = $config['_module_path'] ?? '';
        $templatePath = $modulePath . '/' . $config['template'];

        if (!file_exists($templatePath)) {
            return null;
        }

        return $templatePath;
    }

    /**
     * Vide le cache des manifestes (après modification de fichiers).
     */
    public function clearCache(): void
    {
        $this->manifests = null;
    }
}
