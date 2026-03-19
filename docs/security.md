# Sécurité — Guide Développeur

## Vue d'ensemble

Fronote implémente plusieurs couches de sécurité. Ce document décrit les mécanismes en place et les exigences pour les développeurs de modules.

## Authentification

### Session (navigateur)

L'authentification par session est gérée par `SessionGuard`. Après connexion :

```php
$_SESSION['user_id']   = 42;
$_SESSION['user_type'] = 'professeur';
$_SESSION['user']      = [...]; // Sans mot de passe
```

Vérifier l'authentification :

```php
requireAuth(); // Redirige vers /login si non authentifié
// OU
$user = getCurrentUser(); // null si non authentifié
```

### Token API (intégrations externes)

Pour les apps mobiles et services tiers, utiliser `TokenGuard` avec Bearer tokens :

```
Authorization: Bearer <64-char-hex-token>
```

Les tokens sont stockés hashés (SHA-256) en base. Le token en clair n'est visible qu'à la création.

```php
$guard = new \API\Auth\TokenGuard($pdo);

// Créer un token
[$plainToken, $record] = $guard->createToken(
    userId: 1,
    userType: 'administrateur',
    name: 'Mon application',
    abilities: ['notes.view', 'absences.view'],
    expiresInDays: 365
);

// Authentifier une requête
$user = $guard->authenticate(); // null si invalide

// Vérifier une ability
$guard->can($user, 'notes.view'); // true/false
```

## Protection CSRF

Toutes les mutations (POST, PUT, DELETE) doivent être protégées par un token CSRF.

### Formulaires HTML

```php
<form method="POST">
    <?= csrf_field() ?>
    <!-- ... -->
</form>
```

### Requêtes AJAX

Le token est disponible dans le meta tag :

```javascript
var csrfToken = document.querySelector('meta[name="csrf-token"]').content;

fetch('/api/endpoint', {
    method: 'POST',
    headers: {
        'X-CSRF-TOKEN': csrfToken,
        'Content-Type': 'application/json'
    },
    body: JSON.stringify(data)
});
```

### Validation côté serveur

```php
csrf_verify(); // Arrête l'exécution si invalide (HTTP 403)
// OU
if (!csrf_validate()) {
    // Gérer l'erreur manuellement
}
```

## Rate Limiting

### API globale

Toutes les requêtes vers `/api/*` sont limitées globalement (configurable via `.env`) :

```
API_RATE_LIMIT=60          # requêtes par fenêtre
API_RATE_LIMIT_WINDOW=60   # fenêtre en secondes
```

### Rate limiting spécifique

```php
use API\Middleware\RateLimitMiddleware;

// Limite standard (API_RATE_LIMIT)
RateLimitMiddleware::handle('api.mon_module');

// Limite stricte (RATE_LIMIT_ATTEMPTS — 5 par défaut)
RateLimitMiddleware::handleStrict('api.mon_module.sensitive');

// Limite personnalisée
RateLimitMiddleware::handle('api.mon_module.export', maxAttempts: 10, windowSeconds: 3600);
```

### Headers de réponse

Les endpoints rate-limités retournent :

```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 57
X-RateLimit-Reset: 1711900000
```

En cas de dépassement : `HTTP 429 Too Many Requests` avec header `Retry-After`.

## Content Security Policy (CSP)

Fronote utilise un CSP strict avec nonce. Les règles :

- **Scripts** : `'self'` + nonce + CDN autorisés
- **Styles** : `'self'` + nonce + CDN autorisés
- **Images** : `'self'` + `data:` + `blob:`
- **Connexions** : `'self'` + WebSocket (`ws:`, `wss:`)
- **Frames** : `frame-ancestors 'none'` (pas d'embedding)
- **Base URI** : `'self'`
- **Form action** : `'self'`

### Conséquences pour les modules

1. **Pas d'inline styles** : Utilisez des classes CSS, jamais `style="..."`
2. **Pas d'inline scripts** : Utilisez des fichiers JS externes, ou le nonce pour les scripts nécessaires
3. **Pas de CDN non autorisés** : Contactez l'administrateur pour ajouter un domaine CDN au CSP

### Utiliser le nonce pour un script inline

```php
<script nonce="<?= $_hdr_nonce ?>">
    // Ce script est autorisé par le CSP
</script>
```

## Subresource Integrity (SRI)

Les ressources CDN utilisent des hash SRI pour garantir leur intégrité :

```html
<script src="https://cdn.example.com/lib.js"
        integrity="sha384-xxxx"
        crossorigin="anonymous"></script>
```

## Headers de sécurité

Fronote envoie automatiquement :

| Header | Valeur |
|---|---|
| `Content-Security-Policy` | Politique stricte avec nonce |
| `X-Frame-Options` | `DENY` |
| `X-Content-Type-Options` | `nosniff` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `Permissions-Policy` | `camera=(), microphone=(), geolocation=()` |
| `Strict-Transport-Security` | `max-age=31536000` (HTTPS uniquement) |

## Audit

Toutes les actions critiques sont loggées dans `audit_log` avec :

- Action, modèle, ID
- Anciennes et nouvelles valeurs (données sensibles redactées)
- IP, User-Agent
- Sévérité (INFO, WARNING, CRITICAL)
- Méthode HTTP, URI

```php
$audit = app('audit');
$audit->log('mon_module.action', $model, [
    'old' => $anciennes,
    'new' => $nouvelles
], \Pronote\Services\AuditService::WARNING);
```

## Checklist sécurité pour les modules

- [ ] Requêtes SQL préparées (jamais d'interpolation)
- [ ] CSRF vérifié sur toutes les mutations
- [ ] Permissions RBAC vérifiées
- [ ] Inputs validés et échappés (`htmlspecialchars()`, `intval()`)
- [ ] Pas d'inline styles ni scripts
- [ ] Uploads : vérifier type MIME, limiter la taille, stocker hors du webroot
- [ ] Données sensibles redactées dans les logs
- [ ] Rate limiting sur les endpoints publics
