# Widget API — Documentation

## Vue d'ensemble

Le dashboard d'accueil de Fronote affiche des widgets configurables par l'utilisateur. Chaque module peut fournir ses propres widgets via `module.json`, sans modifier le code du dashboard.

## Architecture

```
1. module.json → déclare le widget (key, tailles, rôles, provider, template)
2. WidgetDataProvider → fournit les données du widget
3. Template PHP → rend le widget HTML
4. DashboardService → dispatche : résout le provider, appelle getData(), inclut le template
```

## Interface `WidgetDataProvider`

Tout widget doit implémenter cette interface :

```php
namespace API\Contracts;

interface WidgetDataProvider
{
    /**
     * Retourne les données à afficher dans le widget.
     *
     * @param int         $userId   ID de l'utilisateur
     * @param string      $userType Rôle de l'utilisateur
     * @param array|null  $config   Configuration personnalisée du widget
     * @return array      Données libres — passées au template comme $data
     */
    public function getData(int $userId, string $userType, ?array $config = null): array;

    /**
     * Intervalle de rafraîchissement automatique en secondes.
     * Retournez 0 pour désactiver le refresh automatique.
     */
    public function getRefreshInterval(): int;
}
```

## Déclaration dans `module.json`

```json
{
  "widgets": [
    {
      "key": "dernieres_notes",
      "name": { "fr": "Dernières notes", "en": "Latest grades" },
      "type": "list",
      "icon": "fas fa-chart-bar",
      "roles": ["eleve", "parent", "professeur"],
      "default_size": { "width": 2, "height": 1 },
      "min_width": 1,
      "max_width": 4,
      "is_default": true,
      "data_provider": "includes/NoteWidgetProvider.php",
      "template": "widgets/dernieres_notes.php"
    }
  ]
}
```

### Propriétés

| Propriété | Type | Description |
|---|---|---|
| `key` | string | Identifiant unique du widget (préfixé par le module automatiquement) |
| `name` | object | Nom affiché traduit |
| `type` | string | `list`, `chart`, `stat`, `calendar`, `custom` |
| `icon` | string | Classe Font Awesome |
| `roles` | array | Rôles qui voient ce widget par défaut. `["*"]` = tous |
| `default_size` | object | `{ "width": 1-4, "height": 1-3 }` en unités de grille |
| `min_width` / `max_width` | int | Limites de redimensionnement |
| `is_default` | bool | `true` = ajouté automatiquement au dashboard des nouveaux utilisateurs |
| `data_provider` | string | Chemin relatif au dossier module vers la classe PHP |
| `template` | string | Chemin relatif vers le template de rendu |

## Tailles de grille

Le dashboard utilise une grille de 4 colonnes :

| Taille | Colonnes | Usage typique |
|---|---|---|
| 1 | 25% | Statistique unique, compteur |
| 2 | 50% | Liste courte, graphique compact |
| 3 | 75% | Tableau, calendrier |
| 4 | 100% | Widget pleine largeur |

## Cycle de vie d'un widget

1. **Découverte** : `ModuleSDK::syncAll()` scanne les `module.json` et synchronise la table `dashboard_widgets`
2. **Affichage** : Le `DashboardService` récupère les widgets de l'utilisateur depuis `user_dashboard_config`
3. **Données** : Pour chaque widget, le SDK résout le `data_provider` et appelle `getData()`
4. **Rendu** : Le template PHP est inclus avec `$data` comme variable

## Exemple complet

### Provider

```php
<?php
// mon_module/includes/StatsWidgetProvider.php

use API\Contracts\WidgetDataProvider;

class StatsWidgetProvider implements WidgetDataProvider
{
    public function getData(int $userId, string $userType, ?array $config = null): array
    {
        $pdo = getPDO();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM mon_module_items WHERE user_id = ?");
        $stmt->execute([$userId]);
        $total = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM mon_module_items WHERE user_id = ? AND created_at >= CURDATE()");
        $stmt->execute([$userId]);
        $today = (int) $stmt->fetchColumn();

        return [
            'total' => $total,
            'today' => $today,
            'trend' => $today > 0 ? 'up' : 'stable',
        ];
    }

    public function getRefreshInterval(): int
    {
        return 120; // 2 minutes
    }
}
```

### Template

```php
<?php
// mon_module/widgets/stats.php
// $data contient le résultat de getData()
?>
<div class="widget-stat">
    <div class="widget-stat-value"><?= (int) $data['total'] ?></div>
    <div class="widget-stat-label"><?= __('mon_module.total_items') ?></div>
    <div class="widget-stat-footer">
        <span class="widget-stat-trend widget-stat-trend--<?= $data['trend'] ?>">
            <?= (int) $data['today'] ?> <?= __('common.today') ?>
        </span>
    </div>
</div>
```

## API JavaScript (côté client)

Les widgets sont rafraîchis automatiquement si `getRefreshInterval() > 0`. L'endpoint de refresh :

```
GET /accueil/api_dashboard.php?action=widget_data&widget_key=mon_widget
```

Retourne du JSON avec les données mises à jour.
