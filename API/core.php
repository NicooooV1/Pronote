<?php
/**
 * Point d'entrée principal de l'API Fronote
 *
 * Charge le bootstrap (autoloader, container, services, session)
 * et le Bridge legacy (tous les helpers : auth, RBAC, CSRF, DB, etc.).
 *
 * Usage dans les modules :
 *   require_once __DIR__ . '/../API/core.php';
 *   requireAuth();
 *   $user = getCurrentUser();
 */

require_once __DIR__ . '/bootstrap.php';

// Les helpers CSRF supplémentaires (csrf_meta, csrf_validate, csrf_verify, isAjaxRequest)
// sont définis ici car Bridge.php ne les inclut pas.

/**
 * Fonction helper pour générer un meta tag CSRF (pour AJAX)
 */
if (!function_exists('csrf_meta')) {
    function csrf_meta() {
        return app('csrf')->meta();
    }
}

/**
 * Valide le token CSRF depuis la requête courante
 */
if (!function_exists('csrf_validate')) {
    function csrf_validate(?string $token = null): bool {
        if ($token !== null) {
            return app('csrf')->validate($token);
        }
        return app('csrf')->validateFromRequest();
    }
}

/**
 * Vérifie le token CSRF et arrête l'exécution si invalide
 */
if (!function_exists('csrf_verify')) {
    function csrf_verify(): void {
        app('csrf')->verifyOrFail();
    }
}

/**
 * Détecte si la requête courante est AJAX
 */
if (!function_exists('isAjaxRequest')) {
    function isAjaxRequest(): bool {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}
