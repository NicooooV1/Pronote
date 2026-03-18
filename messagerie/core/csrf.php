<?php
/**
 * Protection CSRF centralisée pour la messagerie
 * Génère et valide des tokens CSRF par session.
 *
 * Toutes les fonctions sont protégées par function_exists()
 * pour éviter les conflits avec l'API centralisée (API/core.php).
 * Ce fichier doit être chargé AVANT l'API pour que l'implémentation
 * session-unique (plus simple et compatible AJAX) prenne le dessus.
 */

if (!function_exists('csrf_token')) {
    /**
     * Génère ou récupère le token CSRF de la session courante
     * @return string Token CSRF
     */
    function csrf_token(): string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Retourne un champ hidden HTML contenant le token CSRF
     * @return string HTML du champ hidden
     */
    function csrf_field(): string {
        return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
    }
}

if (!function_exists('csrf_meta')) {
    /**
     * Retourne un meta tag HTML contenant le token CSRF (pour les requêtes AJAX)
     * @return string HTML du meta tag
     */
    function csrf_meta(): string {
        return '<meta name="csrf-token" content="' . htmlspecialchars(csrf_token()) . '">';
    }
}

if (!function_exists('csrf_validate')) {
    /**
     * Valide le token CSRF envoyé avec la requête.
     * Compatible avec le token session-unique (_csrf_token) ET
     * le token-bucket de l'API (csrf_tokens) en fallback.
     *
     * @param string|null $token Token à valider (si null, cherche dans POST et header)
     * @return bool  true si le token est valide
     */
    function csrf_validate(?string $token = null): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if ($token === null) {
            // Chercher dans POST (champ messagerie puis champ API)
            $token = $_POST['_csrf_token'] ?? $_POST['csrf_token'] ?? null;

            // Chercher dans le header X-CSRF-Token (AJAX)
            if ($token === null) {
                $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
            }

            // Chercher dans le body JSON
            if ($token === null) {
                $input = json_decode(file_get_contents('php://input'), true);
                $token = $input['_csrf_token'] ?? $input['csrf_token'] ?? null;
            }
        }

        if (empty($token)) {
            return false;
        }

        // 1) Vérification session-unique (implémentation messagerie) — rotation après usage
        if (!empty($_SESSION['_csrf_token']) && hash_equals($_SESSION['_csrf_token'], $token)) {
            // Rotation immédiate : invalide le token usagé et en génère un nouveau
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
            return true;
        }

        // 2) Fallback : vérification token-bucket (implémentation API)
        if (!empty($_SESSION['csrf_tokens']) && is_array($_SESSION['csrf_tokens'])) {
            if (isset($_SESSION['csrf_tokens'][$token])) {
                $timestamp = $_SESSION['csrf_tokens'][$token];
                // Token valide si moins d'1 heure
                if ((time() - $timestamp) <= 3600) {
                    unset($_SESSION['csrf_tokens'][$token]);
                    return true;
                }
                unset($_SESSION['csrf_tokens'][$token]);
            }
        }

        return false;
    }
}

if (!function_exists('csrf_verify')) {
    /**
     * Vérifie le token CSRF et arrête l'exécution si invalide.
     * À appeler en début de traitement POST/PUT/DELETE.
     */
    function csrf_verify(): void {
        if (!csrf_validate()) {
            http_response_code(403);
            if (isAjaxRequest()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Token CSRF invalide. Veuillez rafraîchir la page.']);
            } else {
                echo 'Erreur de sécurité : token CSRF invalide. <a href="javascript:location.reload()">Rafraîchir</a>';
            }
            exit;
        }
    }
}

if (!function_exists('isAjaxRequest')) {
    /**
     * Détecte si la requête est AJAX
     * @return bool
     */
    function isAjaxRequest(): bool {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}
