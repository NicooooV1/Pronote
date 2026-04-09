<?php
declare(strict_types=1);

namespace API\Core;

/**
 * ClientCache — Cache client hybride (session PHP + cookies signés HMAC).
 *
 * Objectifs :
 *  - Réduire les requêtes SQL en cachant les données éphémères (theme, settings, widget config)
 *  - Double sauvegarde : session serveur + cookie navigateur
 *  - Isolation multi-instance via INSTANCE_ID (préfixe cookie)
 *  - Protection anti-tamper via HMAC-SHA256
 *  - Compatible mobile (cookies légers, pas de localStorage requis)
 *
 * Hiérarchie de lecture : Session → Cookie → DB (fallback)
 * Hiérarchie d'écriture : DB → Session + Cookie (sync)
 *
 * Usage :
 *   $cc = new ClientCache();
 *   $cc->set('theme', 'glass', 86400);        // Écrit session + cookie signé
 *   $theme = $cc->get('theme', 'classic');      // Lit session, sinon cookie vérifié
 *   $cc->setGroup('widget_config', [...], 0);   // Données JSON groupées
 */
class ClientCache
{
    private string $instanceId;
    private string $cookiePath;
    private string $hmacKey;
    private bool $secure;

    /** Taille max d'un cookie (4 Ko moins overhead headers) */
    private const MAX_COOKIE_SIZE = 3800;

    /** Préfixe session pour éviter collisions */
    private const SESSION_NS = '_cc_';

    public function __construct()
    {
        $this->instanceId = defined('INSTANCE_ID') ? INSTANCE_ID : 'default';
        $this->cookiePath = defined('INSTANCE_COOKIE_PATH') ? INSTANCE_COOKIE_PATH : '/';
        $this->secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        // Clé HMAC dérivée de APP_KEY (ou fallback sur instance + salt fixe)
        $appKey = getenv('APP_KEY') ?: '';
        $this->hmacKey = $appKey
            ? hash('sha256', $appKey . ':client_cache:' . $this->instanceId, true)
            : hash('sha256', 'fronote_cc_salt:' . $this->instanceId . ':' . realpath(defined('BASE_PATH') ? BASE_PATH : __DIR__), true);
    }

    // ─── Public API ─────────────────────────────────────────────────

    /**
     * Récupère une valeur du cache client.
     * Priorité : session > cookie signé > default
     */
    public function get(string $key, mixed $default = null): mixed
    {
        // 1) Session (le plus rapide, pas de I/O)
        $sessKey = self::SESSION_NS . $key;
        if (isset($_SESSION[$sessKey])) {
            $entry = $_SESSION[$sessKey];
            if ($this->isValid($entry)) {
                return $entry['v'];
            }
            unset($_SESSION[$sessKey]);
        }

        // 2) Cookie signé (fallback si session perdue/expirée)
        $cookieVal = $this->readCookie($key);
        if ($cookieVal !== null) {
            // Remettre en session pour les requêtes suivantes
            $_SESSION[$sessKey] = [
                'v'   => $cookieVal,
                'exp' => 0, // Le cookie gère son propre TTL
                't'   => time(),
            ];
            return $cookieVal;
        }

        return $default;
    }

    /**
     * Stocke une valeur en cache client (session + cookie).
     *
     * @param string $key   Clé unique
     * @param mixed  $value Valeur (doit être JSON-serializable)
     * @param int    $ttl   Durée cookie en secondes (0 = session cookie, disparaît à la fermeture)
     */
    public function set(string $key, mixed $value, int $ttl = 0): void
    {
        // Session
        $_SESSION[self::SESSION_NS . $key] = [
            'v'   => $value,
            'exp' => $ttl > 0 ? time() + $ttl : 0,
            't'   => time(),
        ];

        // Cookie signé (si pas trop gros)
        $this->writeCookie($key, $value, $ttl);
    }

    /**
     * Stocke un groupe de données (ex: widget_config, user_settings).
     * Identique à set() mais avec un nom sémantique pour la clarté.
     */
    public function setGroup(string $group, array $data, int $ttl = 0): void
    {
        $this->set($group, $data, $ttl);
    }

    /**
     * Récupère un groupe de données.
     */
    public function getGroup(string $group, array $default = []): array
    {
        $val = $this->get($group, $default);
        return is_array($val) ? $val : $default;
    }

    /**
     * Supprime une clé du cache client (session + cookie).
     */
    public function forget(string $key): void
    {
        unset($_SESSION[self::SESSION_NS . $key]);
        $this->deleteCookie($key);
    }

    /**
     * Invalide tout le cache client de l'utilisateur courant.
     */
    public function flush(): void
    {
        // Nettoyer toutes les clés session avec notre préfixe
        foreach ($_SESSION as $k => $v) {
            if (str_starts_with($k, self::SESSION_NS)) {
                unset($_SESSION[$k]);
            }
        }

        // Supprimer les cookies avec notre préfixe
        $prefix = 'fn_' . $this->instanceId . '_';
        foreach ($_COOKIE as $name => $val) {
            if (str_starts_with($name, $prefix)) {
                $this->rawDeleteCookie($name);
            }
        }
    }

    /**
     * Vérifie si une clé existe et est valide.
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * Récupère ou calcule et met en cache.
     * Vérifie session/cookie avant d'appeler le callback (évite la requête SQL).
     */
    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        $value = $this->get($key);
        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        if ($value !== null) {
            $this->set($key, $value, $ttl);
        }
        return $value;
    }

    // ─── User cache population ────────────────────────────────────────

    /**
     * Populates client cache with user-scoped data after login.
     * Called once after authentication. Stores: role, permissions, modules, locale, etab info, badges.
     */
    public function populateUserCache(): void
    {
        $user = $_SESSION['user'] ?? null;
        if (!$user) {
            return;
        }

        $ttl = 1800; // 30 minutes

        // User role
        $this->set('user_role', $user['type'] ?? 'eleve', $ttl);

        // Establishment ID & info
        $etabId = \API\Core\EstablishmentContext::id();
        $this->set('user_etab_id', $etabId, $ttl);

        try {
            $etabService = app('etablissement');
            $etabInfo = $etabService->getById($etabId);
            if ($etabInfo) {
                $this->set('user_etab_info', [
                    'nom' => $etabInfo['nom'] ?? '',
                    'type' => $etabInfo['type'] ?? 'college',
                    'code' => $etabInfo['code'] ?? 'default',
                ], $ttl);
            }
        } catch (\Throwable $e) {
            // Non-critical
        }

        // Locale
        try {
            $locale = app('translator')->locale();
            $this->set('user_locale', $locale, $ttl);
        } catch (\Throwable $e) {
            $this->set('user_locale', 'fr', $ttl);
        }

        // RBAC permissions (compact list)
        try {
            $rbac = app('rbac');
            $permissions = $rbac->getAllPermissions();
            $this->set('user_permissions', $permissions, $ttl);
        } catch (\Throwable $e) {
            // Non-critical
        }

        // Enabled modules for this user
        try {
            $modules = app('modules');
            $role = $user['type'] ?? 'eleve';
            $enabledModules = $modules->getEnabledForRole($role);
            $this->set('user_modules', array_column($enabledModules, 'module_key'), $ttl);
        } catch (\Throwable $e) {
            // Non-critical
        }
    }

    // ─── Cookie helpers ─────────────────────────────────────────────

    /**
     * Nom de cookie scopé par instance : fn_{instance}_{key}
     */
    private function cookieName(string $key): string
    {
        return 'fn_' . $this->instanceId . '_' . $key;
    }

    /**
     * Écrit un cookie signé HMAC.
     * Format : base64(json_payload) . '.' . hmac_hex
     */
    private function writeCookie(string $key, mixed $value, int $ttl): void
    {
        if (headers_sent()) {
            return;
        }

        $payload = json_encode($value, JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            return;
        }

        $encoded = base64_encode($payload);
        $signature = hash_hmac('sha256', $encoded, $this->hmacKey);
        $cookieValue = $encoded . '.' . $signature;

        // Vérifier la taille (limite ~4 Ko par cookie)
        if (strlen($cookieValue) > self::MAX_COOKIE_SIZE) {
            // Trop gros pour un cookie, on garde uniquement en session
            return;
        }

        $expires = $ttl > 0 ? time() + $ttl : 0;
        setcookie($this->cookieName($key), $cookieValue, [
            'expires'  => $expires,
            'path'     => $this->cookiePath,
            'secure'   => $this->secure,
            'httponly'  => true,
            'samesite' => 'Lax',
        ]);
    }

    /**
     * Lit et vérifie un cookie signé.
     * Retourne null si absent, corrompu ou signature invalide.
     */
    private function readCookie(string $key): mixed
    {
        $name = $this->cookieName($key);
        if (!isset($_COOKIE[$name])) {
            return null;
        }

        $raw = $_COOKIE[$name];
        $dotPos = strrpos($raw, '.');
        if ($dotPos === false) {
            // Format invalide — supprimer le cookie corrompu
            $this->rawDeleteCookie($name);
            return null;
        }

        $encoded = substr($raw, 0, $dotPos);
        $signature = substr($raw, $dotPos + 1);

        // Vérification HMAC (timing-safe)
        $expected = hash_hmac('sha256', $encoded, $this->hmacKey);
        if (!hash_equals($expected, $signature)) {
            // Tampered — supprimer
            $this->rawDeleteCookie($name);
            return null;
        }

        $decoded = base64_decode($encoded, true);
        if ($decoded === false) {
            $this->rawDeleteCookie($name);
            return null;
        }

        $value = json_decode($decoded, true);
        // json_decode retourne null pour "null" string, ce qui est OK
        // Mais on distingue une erreur de parsing
        if ($value === null && $decoded !== 'null') {
            $this->rawDeleteCookie($name);
            return null;
        }

        return $value;
    }

    /**
     * Supprime un cookie par sa clé logique.
     */
    private function deleteCookie(string $key): void
    {
        $this->rawDeleteCookie($this->cookieName($key));
    }

    /**
     * Supprime un cookie par son nom brut.
     */
    private function rawDeleteCookie(string $name): void
    {
        if (headers_sent()) {
            return;
        }
        setcookie($name, '', [
            'expires'  => time() - 3600,
            'path'     => $this->cookiePath,
            'secure'   => $this->secure,
            'httponly'  => true,
            'samesite' => 'Lax',
        ]);
        unset($_COOKIE[$name]);
    }

    /**
     * Vérifie la validité d'une entrée session (TTL).
     */
    private function isValid(array $entry): bool
    {
        if (!isset($entry['v'])) {
            return false;
        }
        // exp = 0 signifie pas d'expiration côté session
        if (($entry['exp'] ?? 0) > 0 && $entry['exp'] < time()) {
            return false;
        }
        return true;
    }
}
