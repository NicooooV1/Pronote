# Hook Reference — Système d'Événements

## Vue d'ensemble

Le `HookManager` permet aux modules de s'abonner à des événements système et de communiquer entre eux sans couplage direct.

## Usage

### Écouter un événement

```php
$hooks = app('hooks');

$hooks->register('user.login', function(array $user) {
    // Exécuté après chaque connexion réussie
    logActivity($user['id'], 'login');
}, priority: 10);
```

### Déclencher un événement

```php
app('hooks')->fire('user.login', $user);
```

### Filtrer une valeur

Les filtres permettent de modifier une valeur en chaîne :

```php
// Enregistrer un filtre
$hooks->register('sidebar.items', function(array $items) {
    $items[] = ['label' => 'Mon lien', 'url' => '/mon_module/'];
    return $items;
});

// Appliquer le filtre
$items = app('hooks')->filter('sidebar.items', $defaultItems);
```

## Priorités

Les callbacks sont exécutés par ordre de priorité croissant (10 avant 20). Par défaut : `10`.

```php
$hooks->register('event', $callbackA, priority: 5);   // Exécuté en premier
$hooks->register('event', $callbackB, priority: 10);  // Exécuté en second
$hooks->register('event', $callbackC, priority: 20);  // Exécuté en dernier
```

## Événements système

### Authentification

| Événement | Arguments | Quand |
|---|---|---|
| `user.login` | `array $user` | Après connexion réussie |
| `user.logout` | `int $userId` | Après déconnexion |
| `user.login.failed` | `string $username, string $ip` | Après échec de connexion |
| `user.lockout` | `string $username, string $ip` | Après verrouillage du compte |

### Gestion des utilisateurs

| Événement | Arguments | Quand |
|---|---|---|
| `user.create` | `array $user` | Après création d'un utilisateur |
| `user.update` | `array $user, array $changes` | Après modification d'un utilisateur |
| `user.delete` | `int $userId, string $userType` | Avant suppression d'un utilisateur |
| `user.password.change` | `int $userId` | Après changement de mot de passe |

### Modules

| Événement | Arguments | Quand |
|---|---|---|
| `module.install` | `string $moduleKey` | Après installation |
| `module.uninstall` | `string $moduleKey` | Avant désinstallation |
| `module.enable` | `string $moduleKey` | Après activation |
| `module.disable` | `string $moduleKey` | Après désactivation |

### Dashboard

| Événement | Arguments | Quand |
|---|---|---|
| `dashboard.widgets.collect` | `array $widgets` | Lors de la collecte des widgets |
| `dashboard.render.before` | `int $userId` | Avant le rendu du dashboard |
| `dashboard.render.after` | `int $userId` | Après le rendu du dashboard |

### Sidebar

| Événement | Arguments | Quand |
|---|---|---|
| `sidebar.render` | `array $items` | Avant le rendu de la sidebar |

### Sécurité

| Événement | Arguments | Quand |
|---|---|---|
| `security.csrf.fail` | `string $ip` | Échec de validation CSRF |
| `security.ratelimit.hit` | `string $key, string $ip` | Rate limit atteint |

## Gestion des erreurs

Les erreurs dans un callback sont isolées — elles sont loggées mais n'empêchent pas l'exécution des autres callbacks :

```php
$hooks->register('user.login', function($user) {
    throw new \Exception('Erreur dans mon callback');
    // L'erreur est loggée, les autres callbacks continuent
});
```

## Bonnes pratiques

1. Utilisez des noms d'événements en dot-notation : `module.action`
2. Gardez les callbacks légers — déléguez le travail lourd
3. Ne modifiez pas les arguments reçus dans `fire()` — utilisez `filter()` pour les transformations
4. Documentez les événements que votre module déclenche
5. Utilisez les priorités pour contrôler l'ordre d'exécution
