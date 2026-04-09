<?php
/**
 * AjaxResponse — Standardized JSON responses for AJAX endpoints.
 *
 * Usage:
 *   AjaxResponse::success('Saved!');
 *   AjaxResponse::success('Created', ['id' => 42]);
 *   AjaxResponse::error('Invalid input', ['field' => 'required'], 422);
 *   AjaxResponse::redirect('/dashboard');
 *   AjaxResponse::paginated($items, $total, $page, $perPage);
 */

namespace API\Core;

class AjaxResponse
{
    /**
     * Send a success JSON response and exit.
     */
    public static function success(string $message = 'OK', array $data = [], int $httpCode = 200): void
    {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array_merge([
            'success' => true,
            'message' => $message,
        ], $data), JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Send an error JSON response and exit.
     */
    public static function error(string $message = 'Erreur', array $errors = [], int $httpCode = 400): void
    {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
        $payload = [
            'success' => false,
            'error'   => $message,
        ];
        if (!empty($errors)) {
            $payload['errors'] = $errors;
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Send a redirect instruction.
     */
    public static function redirect(string $url, string $message = ''): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success'  => true,
            'redirect' => $url,
            'message'  => $message,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Send a paginated response.
     */
    public static function paginated(array $items, int $total, int $page, int $perPage): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success'    => true,
            'data'       => $items,
            'pagination' => [
                'total'        => $total,
                'page'         => $page,
                'per_page'     => $perPage,
                'total_pages'  => (int) ceil($total / max($perPage, 1)),
                'has_more'     => ($page * $perPage) < $total,
            ],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Validate that the request is an AJAX request. Sends 400 and exits if not.
     */
    public static function requireAjax(): void
    {
        if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
            self::error('AJAX request required', [], 400);
        }
    }

    /**
     * Validate CSRF token from POST or header. Sends 403 and exits if invalid.
     */
    public static function requireCsrf(): void
    {
        $token = $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        try {
            $csrf = app('csrf');
            if (!$csrf->validate($token)) {
                self::error('Token CSRF invalide', [], 403);
            }
        } catch (\Throwable $e) {
            self::error('Token CSRF invalide', [], 403);
        }
    }

    /**
     * Convenience: validate both AJAX + CSRF in one call.
     */
    public static function guard(): void
    {
        self::requireAjax();
        self::requireCsrf();
    }
}
