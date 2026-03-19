# Database — Conventions et Structure

## Connexion

```php
// Via le container (recommandé)
$pdo = app('db')->getConnection();

// Via le helper global
$pdo = getPDO();
```

## Conventions de nommage

| Élément | Convention | Exemple |
|---|---|---|
| Tables | snake_case, pluriel | `notes`, `user_settings`, `audit_log` |
| Colonnes | snake_case | `user_id`, `created_at`, `mot_de_passe` |
| Clé primaire | `id` (INT AUTO_INCREMENT) | `id` |
| Clés étrangères | `{table_singulier}_id` | `eleve_id`, `classe_id` |
| Timestamps | `created_at`, `updated_at` | `DATETIME DEFAULT CURRENT_TIMESTAMP` |
| Booléens | Préfixe `is_` ou `has_` ou descriptif | `is_active`, `traite`, `lu` |
| Index | `idx_{description}` | `idx_user_type`, `idx_created_at` |
| Foreign keys | `fk_{table}_{ref}` | `fk_notes_eleve` |

## Charset

Toujours UTF-8 MB4 :

```sql
ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
```

## Requêtes préparées

**Obligatoire**. Ne jamais interpoler de variables dans le SQL :

```php
// BON
$stmt = $pdo->prepare("SELECT * FROM notes WHERE eleve_id = ? AND trimestre = ?");
$stmt->execute([$eleveId, $trimestre]);

// MAUVAIS — injection SQL possible
$stmt = $pdo->query("SELECT * FROM notes WHERE eleve_id = $eleveId");
```

## Tables principales

### Utilisateurs

| Table | Description |
|---|---|
| `administrateurs` | Comptes administrateur |
| `professeurs` | Comptes enseignant |
| `eleves` | Comptes élève |
| `parents` | Comptes parent |
| `vie_scolaire` | Comptes CPE / AED |
| `user_settings` | Préférences utilisateur (thème, langue, notifications) |

### Modules

| Table | Description |
|---|---|
| `modules_config` | Configuration des modules (activé/désactivé, établissements) |
| `module_permissions` | Permissions RBAC par module et rôle |
| `dashboard_widgets` | Définitions de widgets |
| `user_dashboard_config` | Layout de widgets par utilisateur |

### Sécurité

| Table | Description |
|---|---|
| `audit_log` | Journal d'audit avec sévérité et contexte HTTP |
| `api_rate_limits` | Compteurs de rate limiting |
| `api_tokens` | Tokens d'authentification API (hashés) |
| `session_security` | Métadonnées de session pour détection d'anomalies |

### Établissement

| Table | Description |
|---|---|
| `etablissement_info` | Informations de l'établissement (nom, type, locale) |
| `feature_flags` | Feature flags par type d'établissement |

## Schéma de migration

Fronote n'utilise pas de système de migration séparé. Toutes les modifications de schéma sont dans `pronote.sql`.

Pour ajouter une table dans un module :

1. Ajoutez le `CREATE TABLE IF NOT EXISTS` dans `pronote.sql`
2. Créez un script `includes/install.php` dans votre module qui exécute le même SQL
3. Le `ModuleSDK` exécutera ce script lors de l'installation

## Bonnes pratiques

1. **Toujours utiliser `IF NOT EXISTS`** pour les `CREATE TABLE` et `ADD COLUMN IF NOT EXISTS` pour les `ALTER TABLE`
2. **Indexer les colonnes** utilisées dans les `WHERE`, `JOIN`, et `ORDER BY`
3. **Éviter les requêtes N+1** : utilisez des `JOIN` ou des requêtes batch
4. **Limiter les résultats** : toujours utiliser `LIMIT` pour les listes
5. **Transactions** pour les opérations multi-tables :

```php
$pdo->beginTransaction();
try {
    // Opérations...
    $pdo->commit();
} catch (\Exception $e) {
    $pdo->rollBack();
    throw $e;
}
```
