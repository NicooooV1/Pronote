# Module SDK — Guide du Développeur

## Introduction

Fronote utilise une architecture modulaire. Chaque module est un dossier autonome à la racine du projet, déclaré via un fichier `module.json`. Ce guide explique comment créer, configurer et publier un module.

## Structure d'un module

```
mon_module/
├── module.json              # Manifeste obligatoire
├── mon_module.php           # Page principale
├── api.php                  # Endpoints API du module (optionnel)
├── includes/
│   ├── MonWidgetProvider.php # Fournisseur de données widget
│   └── install.php          # Script d'installation (optionnel)
├── widgets/
│   └── mon_widget.php       # Template de rendu du widget
├── assets/
│   ├── css/
│   │   └── mon_module.css
│   └── js/
│       └── mon_module.js
└── lang/
    ├── fr.json              # Traductions françaises
    └── en.json              # Traductions anglaises
```

## Le manifeste `module.json`

Chaque module **doit** contenir un fichier `module.json` à sa racine :

```json
{
  "key": "mon_module",
  "version": "1.0.0",
  "name": {
    "fr": "Mon Module",
    "en": "My Module"
  },
  "description": {
    "fr": "Description du module",
    "en": "Module description"
  },
  "icon": "fas fa-puzzle-piece",
  "category": "scolaire",
  "core": false,
  "requires_php": ">=8.0",
  "dependencies": [],
  "permissions": {
    "view": { "default_roles": ["*"] },
    "manage": { "default_roles": ["administrateur", "professeur"] },
    "edit": { "default_roles": ["administrateur"] },
    "delete": { "default_roles": ["administrateur"] }
  },
  "routes": {
    "main": "mon_module.php",
    "api": "api.php"
  },
  "widgets": [],
  "hooks": {},
  "establishment_types": null,
  "sidebar": { "sort_order": 50 },
  "settings_schema": {}
}
```

### Champs du manifeste

| Champ | Type | Requis | Description |
|---|---|---|---|
| `key` | string | Oui | Identifiant unique (snake_case, doit correspondre au nom du dossier) |
| `version` | string | Oui | Version SemVer |
| `name` | object | Oui | Nom traduit (`{ "fr": "...", "en": "..." }`) |
| `description` | object | Oui | Description traduite |
| `icon` | string | Oui | Classe Font Awesome pour l'icône sidebar |
| `category` | string | Oui | Catégorie sidebar : `navigation`, `scolaire`, `communication`, `administration`, `outils` |
| `core` | bool | Non | `true` si le module ne peut pas être désactivé |
| `permissions` | object | Oui | Map permission_key → `{ default_roles: [...] }` |
| `routes.main` | string | Oui | Fichier PHP principal (point d'entrée) |
| `widgets` | array | Non | Widgets fournis par le module (voir section Widgets) |
| `hooks` | object | Non | Hooks de lifecycle : `on_install`, `on_uninstall`, `on_user_delete` |
| `establishment_types` | array\|null | Non | `null` = tous types. Sinon : `["college", "lycee", "superieur"]` |
| `sidebar.sort_order` | int | Non | Ordre d'affichage dans la sidebar (défaut: 50) |
| `settings_schema` | object | Non | Configuration admin du module |

### Catégories disponibles

| Clé | Label | Icône |
|---|---|---|
| `navigation` | Navigation | `fas fa-compass` |
| `scolaire` | Scolarité | `fas fa-graduation-cap` |
| `communication` | Communication | `fas fa-comments` |
| `administration` | Administration | `fas fa-building` |
| `outils` | Outils | `fas fa-tools` |

## Boot d'un module

Chaque page PHP d'un module commence par :

```php
<?php
require_once __DIR__ . '/../API/module_boot.php';

// Variables disponibles automatiquement :
// $user          — données utilisateur courant
// $user_role     — rôle (administrateur, professeur, etc.)
// $user_fullname — nom complet
// $user_initials — initiales
// $isAdmin       — bool
// $pdo           — connexion PDO
// $rootPrefix    — chemin relatif vers la racine (ex: '../')

$activePage = 'mon_module';
$pageTitle  = __('mon_module.title');
include __DIR__ . '/../templates/shared_header.php';
include __DIR__ . '/../templates/shared_sidebar.php';
?>

<div class="main-content">
    <!-- Contenu du module -->
</div>

<?php include __DIR__ . '/../templates/shared_footer.php'; ?>
```

## Permissions (RBAC)

Les permissions sont déclarées dans `module.json` → `permissions` et synchronisées en base par le ModuleSDK.

### Vérifier une permission

```php
// Vérifie si l'utilisateur courant a la permission
if (hasPermission('mon_module.manage')) {
    // Afficher les boutons d'édition
}

// Ou via le service
$rbac = app('rbac');
if ($rbac->can($userId, $userType, 'mon_module', 'edit')) {
    // ...
}
```

### Rôles disponibles

| Rôle | Description |
|---|---|
| `administrateur` | Accès total |
| `professeur` | Enseignant |
| `vie_scolaire` | CPE / Assistant d'éducation |
| `eleve` | Élève |
| `parent` | Parent d'élève |

Le wildcard `"*"` accorde la permission à tous les rôles.

## Widgets

Un module peut fournir un ou plusieurs widgets pour le dashboard d'accueil.

### Déclaration dans `module.json`

```json
{
  "widgets": [
    {
      "key": "mon_widget",
      "name": { "fr": "Mon Widget", "en": "My Widget" },
      "type": "list",
      "icon": "fas fa-list",
      "roles": ["eleve", "professeur"],
      "default_size": { "width": 2, "height": 1 },
      "min_width": 1,
      "max_width": 4,
      "is_default": true,
      "data_provider": "includes/MonWidgetProvider.php",
      "template": "widgets/mon_widget.php"
    }
  ]
}
```

### Créer un WidgetDataProvider

```php
<?php
// mon_module/includes/MonWidgetProvider.php

use API\Contracts\WidgetDataProvider;

class MonWidgetProvider implements WidgetDataProvider
{
    public function getData(int $userId, string $userType, ?array $config = null): array
    {
        $pdo = getPDO();
        // Récupérer les données...

        return [
            'items' => [...],
            'count' => 5,
            'title' => __('mon_module.widget_title'),
        ];
    }

    public function getRefreshInterval(): int
    {
        return 300; // Rafraîchir toutes les 5 minutes (0 = pas de refresh auto)
    }
}
```

### Template du widget

```php
<?php
// mon_module/widgets/mon_widget.php
// Variable $data contient le résultat de getData()
?>
<div class="widget-content">
    <h3><?= htmlspecialchars($data['title']) ?></h3>
    <ul>
        <?php foreach ($data['items'] as $item): ?>
        <li><?= htmlspecialchars($item['label']) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
```

### Types de widgets

| Type | Description |
|---|---|
| `list` | Liste d'éléments |
| `chart` | Graphique (Chart.js) |
| `stat` | Statistique avec icône |
| `calendar` | Mini-calendrier |
| `custom` | Template personnalisé |

### Tailles

Les tailles sont exprimées en unités de grille (grid units).

- `default_size.width` : 1 à 4 colonnes
- `default_size.height` : 1 à 3 rangées
- L'utilisateur peut redimensionner entre `min_width` et `max_width`

## Internationalisation (i18n)

### Utiliser les traductions

```php
// Traduction simple
echo __('mon_module.title');

// Avec paramètres
echo __('mon_module.welcome', ['name' => $user_fullname]);
// Fichier JSON : "mon_module.welcome": "Bienvenue :name"

// Pluralisation
echo _n('mon_module.items', $count, ['count' => $count]);
// Fichier JSON : "mon_module.items": "Aucun élément|:count élément|:count éléments"
```

### Fichiers de traduction

Créez `lang/fr.json` et `lang/en.json` dans le dossier de votre module, ou ajoutez vos clés dans `lang/fr/modules/mon_module.json` à la racine du projet.

```json
{
  "mon_module.title": "Mon Module",
  "mon_module.welcome": "Bienvenue :name",
  "mon_module.items": "Aucun élément|:count élément|:count éléments"
}
```

## Hooks (événements)

### Écouter un événement

```php
// Dans le boot de votre module
$hooks = app('hooks');

$hooks->register('user.login', function($user) {
    // Logique après connexion
}, priority: 10);

$hooks->register('user.delete', function($userId) {
    // Nettoyer les données du module pour cet utilisateur
});
```

### Événements disponibles

| Événement | Arguments | Description |
|---|---|---|
| `user.login` | `$user` | Après connexion réussie |
| `user.logout` | `$userId` | Après déconnexion |
| `user.create` | `$user` | Après création d'utilisateur |
| `user.delete` | `$userId` | Avant suppression d'utilisateur |
| `module.install` | `$moduleKey` | Après installation d'un module |
| `module.uninstall` | `$moduleKey` | Avant désinstallation |
| `module.enable` | `$moduleKey` | Après activation |
| `module.disable` | `$moduleKey` | Après désactivation |
| `dashboard.widgets.collect` | `$widgets` | Collecte des widgets dashboard |
| `sidebar.render` | `$items` | Avant rendu de la sidebar |

### Déclencher un événement personnalisé

```php
app('hooks')->fire('mon_module.custom_event', $data);
```

## Feature Flags (types d'établissement)

Si votre module est spécifique à certains types d'établissement :

```json
{
  "establishment_types": ["lycee", "superieur"]
}
```

Pour vérifier un feature flag dans le code :

```php
if (app('features')->isEnabled('stages.enabled')) {
    // Afficher la section stages
}
```

## API REST

### Créer un endpoint

```php
<?php
// mon_module/api.php
require_once __DIR__ . '/../API/module_boot.php';

use API\Middleware\RateLimitMiddleware;

header('Content-Type: application/json');

// Rate limiting
RateLimitMiddleware::handle('api.mon_module');

// CSRF pour les mutations
$method = $_SERVER['REQUEST_METHOD'];
if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
    csrf_verify();
}

// Router simple
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'list':
        echo json_encode(getItems());
        break;
    case 'create':
        echo json_encode(createItem($_POST));
        break;
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Action not found']);
}
```

### Authentification API par token

Pour les intégrations externes (apps mobiles, services tiers) :

```php
use API\Auth\TokenGuard;

$guard = new TokenGuard(getPDO());
$user = $guard->authenticate();

if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Vérifier les abilities du token
if (!$guard->can($user, 'mon_module.view')) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}
```

## Audit

Loggez les actions critiques de votre module :

```php
$audit = app('audit');

// Action simple
$audit->log('mon_module.action', null, ['new' => $data]);

// Création
$audit->logCreated($model);

// Modification
$audit->logUpdated($model, ['champ' => $nouvelleValeur]);

// Suppression
$audit->logDeleted($model);

// Action de sécurité
$audit->logSecurity('mon_module.access_denied', ['reason' => 'Insufficient permissions']);
```

## Cache

```php
$cache = app('cache');

// Stocker
$cache->put('mon_module.data', $data, 300); // 5 minutes

// Récupérer
$data = $cache->get('mon_module.data');

// Remember pattern (lazy-load)
$data = $cache->remember('mon_module.expensive', 600, function() {
    return expensiveCalculation();
});

// Invalider
$cache->forget('mon_module.data');
```

## Installation / Désinstallation

### Script d'installation

```php
<?php
// mon_module/includes/install.php
// Exécuté automatiquement par le ModuleSDK lors de l'installation

$pdo = getPDO();

$pdo->exec("
    CREATE TABLE IF NOT EXISTS `mon_module_data` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `content` TEXT,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_user` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
```

### Script de désinstallation

```php
<?php
// mon_module/includes/uninstall.php
$pdo = getPDO();
$pdo->exec("DROP TABLE IF EXISTS `mon_module_data`");
```

## Bonnes pratiques

1. **Nommage** : Utilisez le `key` du module comme préfixe pour vos tables SQL, clés de cache, et clés de traduction
2. **Sécurité** : Toujours valider les inputs, utiliser des requêtes préparées, vérifier le CSRF sur les mutations
3. **Performance** : Utilisez le cache pour les données coûteuses, évitez les requêtes N+1
4. **i18n** : Ne hardcodez jamais de texte en français — utilisez `__()` partout
5. **RBAC** : Vérifiez les permissions avant chaque action sensible
6. **Audit** : Loggez les créations, modifications et suppressions de données
7. **CSP** : N'utilisez pas d'inline styles ni de scripts inline — utilisez des classes CSS et des fichiers JS séparés
