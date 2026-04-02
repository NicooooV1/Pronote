<?php
declare(strict_types=1);

namespace API\Middleware;

use API\Security\RateLimiter;

/**
 * Middleware de rate limiting pour les endpoints API
 *
 * Applique une limite de requêtes par minute basée sur l'IP + endpoint.
 * Configurable via .env : API_RATE_LIMIT (défaut 60) et API_RATE_LIMIT_WINDOW (défaut 60s).
 *
 * Usage dans un endpoint :
 *   RateLimitMiddleware::handle('api.notes');
 *   // Si la limite est dépassée, renvoie 429 et arrête l'exécution
 */
class RateLimitMiddleware
{
	/**
	 * Vérifie le rate limit pour la clé donnée.
	 * Arrête l'exécution avec un HTTP 429 si la limite est dépassée.
	 *
	 * @param string   $key       Identifiant logique (ex: 'api.notes', 'api.messages')
	 * @param int|null $maxAttempts Limite (null = valeur .env API_RATE_LIMIT)
	 * @param int|null $windowSeconds Fenêtre en secondes (null = valeur .env API_RATE_LIMIT_WINDOW)
	 */
	public static function handle(
		string $key,
		?int $maxAttempts = null,
		?int $windowSeconds = null
	): void {
		$maxAttempts = $maxAttempts ?? (int) (env('API_RATE_LIMIT', '60') ?: 60);
		$windowSec = $windowSeconds ?? (int) (env('API_RATE_LIMIT_WINDOW', '60') ?: 60);
		$windowMinutes = (int) ceil($windowSec / 60);

		$limiter = new RateLimiter();
		$limiter->setMaxAttempts($maxAttempts);
		$limiter->setDecayMinutes($windowMinutes);

		// Enregistrer la tentative
		$limiter->hit($key);

		$remaining = max(0, $maxAttempts - $limiter->attempts($key));

		// Headers informatifs (RFC 6585 / draft-ietf-httpapi-ratelimit-headers)
		if (!headers_sent()) {
			header("X-RateLimit-Limit: {$maxAttempts}");
			header("X-RateLimit-Remaining: {$remaining}");
			header("X-RateLimit-Reset: " . (time() + $windowSec));
		}

		if ($limiter->tooManyAttempts($key)) {
			$retryAfter = $windowSec;
			if (!headers_sent()) {
				header("Retry-After: {$retryAfter}");
				http_response_code(429);
			}
			echo json_encode([
				'error' => 'Too Many Requests',
				'message' => 'Rate limit exceeded. Please retry after ' . $retryAfter . ' seconds.',
				'retry_after' => $retryAfter,
			]);
			exit;
		}
	}

	/**
	 * Applique le rate limiting global pour toute requête API.
	 * Appelé une seule fois dans le point d'entrée API (index.php).
	 */
	public static function handleGlobal(): void
	{
		// Clé globale par IP — limite toutes les requêtes API confondues
		self::handle('api.global');
	}

	/**
	 * Rate limiting spécifique pour les endpoints sensibles (login, reset, etc.)
	 */
	public static function handleStrict(string $key): void
	{
		self::handle(
			$key,
			maxAttempts: (int) (env('RATE_LIMIT_ATTEMPTS', '5') ?: 5),
			windowSeconds: (int) (env('RATE_LIMIT_DECAY', '1') ?: 1) * 60
		);
	}
}
