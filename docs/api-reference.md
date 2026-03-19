# API Reference — Endpoints REST

## Authentification

Deux modes d'authentification sont supportés :

### Session (navigateur)

Les requêtes depuis le navigateur utilisent le cookie de session. Les mutations nécessitent un token CSRF.

### Bearer Token (API externe)

```
Authorization: Bearer <token>
```

Créez un token via l'interface Paramètres ou programmatiquement via `TokenGuard::createToken()`.

## Format de réponse

Toutes les réponses sont en JSON. Format standard :

### Succès

```json
{
  "success": true,
  "data": { ... }
}
```

### Erreur

```json
{
  "error": "Error type",
  "message": "Description détaillée",
  "code": 400
}
```

## Endpoints

### Santé

```
GET /api/health
```

Retourne l'état du système. Pas d'authentification requise.

```json
{
  "status": "ok",
  "version": "2.0.0",
  "timestamp": "2026-03-19T10:00:00Z"
}
```

### Messages

```
GET  /api/messages?conversation_id=X
POST /api/messages
```

Auth : Session. CSRF requis pour POST.

### Agenda

```
GET /api/agenda/persons?q=search_term
```

Recherche de personnes pour l'agenda. Auth : Session.

### Notes

```
GET /api/notes/eleves?classe_id=X&trimestre=Y
```

Notes des élèves. Auth : Session. Permissions : `notes.view`.

### Modules

```
GET /api/v1/modules
```

Liste tous les modules installés et leur configuration.

```json
{
  "data": [
    {
      "key": "notes",
      "name": "Notes",
      "version": "1.0.0",
      "enabled": true,
      "icon": "fas fa-chart-bar"
    }
  ]
}
```

### Dashboard

```
GET /api/v1/dashboard/widgets
PUT /api/v1/dashboard/layout
```

Récupère ou met à jour le layout de widgets de l'utilisateur.

### Utilisateur courant

```
GET /api/v1/users/me
```

```json
{
  "data": {
    "id": 1,
    "type": "professeur",
    "nom": "Dupont",
    "prenom": "Jean",
    "email": "jean.dupont@example.com",
    "role": "professeur"
  }
}
```

## Rate Limiting

Toutes les requêtes API sont limitées :

- **Limite par défaut** : 60 requêtes / minute (configurable via `API_RATE_LIMIT`)
- **Endpoints sensibles** (login, reset) : 5 requêtes / minute

Headers retournés :

```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 55
X-RateLimit-Reset: 1711900060
```

Réponse en cas de dépassement :

```
HTTP/1.1 429 Too Many Requests
Retry-After: 60

{
  "error": "Too Many Requests",
  "message": "Rate limit exceeded. Please retry after 60 seconds.",
  "retry_after": 60
}
```

## Codes d'erreur HTTP

| Code | Signification |
|---|---|
| `200` | Succès |
| `201` | Créé |
| `400` | Requête invalide |
| `401` | Non authentifié |
| `403` | Non autorisé (CSRF invalide ou permissions insuffisantes) |
| `404` | Ressource non trouvée |
| `429` | Trop de requêtes |
| `500` | Erreur serveur |

## Gestion des tokens API

### Créer un token

Via les paramètres utilisateur ou programmatiquement :

```php
$guard = new \API\Auth\TokenGuard($pdo);

[$token, $record] = $guard->createToken(
    userId: 1,
    userType: 'administrateur',
    name: 'Mon application mobile',
    abilities: ['notes.view', 'absences.view', 'messages.view'],
    expiresInDays: 365
);

// $token = "a1b2c3..." — à communiquer à l'utilisateur (affiché une seule fois)
```

### Révoquer un token

```php
$guard->revokeToken(tokenId: 5, userId: 1);
```

### Abilities disponibles

Les abilities correspondent aux permissions du module : `{module_key}.{permission}`.

Exemples : `notes.view`, `notes.manage`, `absences.view`, `messagerie.view`.

Le wildcard `*` donne accès à tout.
