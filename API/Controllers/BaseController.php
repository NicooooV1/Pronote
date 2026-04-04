<?php
declare(strict_types=1);

namespace API\Controllers;

use API\Auth\TokenGuard;

/**
 * Base Controller pour les endpoints REST v1
 */
abstract class BaseController
{
	protected ?\PDO $pdo = null;
	protected ?array $currentUser = null;

	private float $_startTime;

	public function __construct()
	{
		$this->pdo = getPDO();
		$this->_startTime = microtime(true);

		register_shutdown_function(function (): void {
			try {
				$metrics = app('metrics');
				if (!$metrics) return;
				$elapsed  = (microtime(true) - $this->_startTime) * 1000;
				$endpoint = preg_replace('/\/\d+/', '/{id}', $_SERVER['REQUEST_URI'] ?? 'unknown');
				$endpoint = strtok($endpoint, '?');
				$metrics->recordResponseTime($endpoint, round($elapsed, 2));
			} catch (\Throwable) {}
		});
	}

	/**
	 * Authentifie la requête (session OU bearer token)
	 * Retourne l'utilisateur ou arrête avec 401
	 */
	protected function authenticate(): array
	{
		// Essayer d'abord le Bearer token
		$guard = new TokenGuard($this->pdo);
		$user = $guard->authenticate();

		if ($user) {
			$this->currentUser = $user;
			return $user;
		}

		// Fallback sur la session
		if (!empty($_SESSION['user_id']) && !empty($_SESSION['user_type'])) {
			$this->currentUser = getCurrentUser();
			if ($this->currentUser) {
				return $this->currentUser;
			}
		}

		$this->error('Unauthorized', 401);
	}

	/**
	 * Vérifie que l'utilisateur a la permission requise
	 */
	protected function authorize(string $ability): void
	{
		$user = $this->currentUser;
		if (!$user) {
			$this->error('Unauthorized', 401);
		}

		// Si authentifié par token, vérifier les abilities du token
		if (isset($user['_token_abilities'])) {
			$guard = new TokenGuard($this->pdo);
			if (!$guard->can($user, $ability)) {
				$this->error('Forbidden', 403);
			}
			return;
		}

		// Vérifier via RBAC
		$role = $user['role'] ?? $user['profil'] ?? '';
		if ($role !== 'administrateur' && function_exists('hasPermission') && !hasPermission($ability)) {
			$this->error('Forbidden', 403);
		}
	}

	/**
	 * Envoie une réponse JSON de succès
	 */
	protected function json(mixed $data, int $code = 200): void
	{
		http_response_code($code);
		echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
		exit;
	}

	/**
	 * Envoie une réponse JSON paginée
	 */
	protected function paginated(array $items, int $total, int $page, int $perPage): void
	{
		$this->json([
			'items' => $items,
			'pagination' => [
				'total' => $total,
				'page' => $page,
				'per_page' => $perPage,
				'total_pages' => (int) ceil($total / max($perPage, 1)),
			],
		]);
	}

	/**
	 * Envoie une réponse JSON d'erreur et arrête l'exécution
	 */
	protected function error(string $message, int $code = 400): never
	{
		http_response_code($code);
		echo json_encode(['error' => $message, 'code' => $code], JSON_UNESCAPED_UNICODE);
		exit;
	}

	/**
	 * Récupère le body JSON de la requête
	 */
	protected function jsonBody(): array
	{
		$input = file_get_contents('php://input');
		$data = json_decode($input ?: '', true);
		return is_array($data) ? $data : [];
	}

	/**
	 * Envoie une réponse JSON avec headers de cache HTTP (H3).
	 */
	protected function jsonCached(mixed $data, int $maxAge = 60): void
	{
		$json = json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
		$etag = '"' . md5($json) . '"';

		// 304 Not Modified si ETag correspond
		if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
			http_response_code(304);
			exit;
		}

		header("Cache-Control: private, max-age={$maxAge}");
		header("ETag: {$etag}");
		http_response_code(200);
		echo $json;
		exit;
	}

	/**
	 * Récupère un paramètre GET avec valeur par défaut
	 */
	protected function query(string $key, mixed $default = null): mixed
	{
		return $_GET[$key] ?? $default;
	}

	/**
	 * Récupère page et per_page depuis les query params
	 */
	protected function pagination(): array
	{
		$page = max(1, (int) ($this->query('page', 1)));
		$perPage = min(100, max(1, (int) ($this->query('per_page', 20))));
		$offset = ($page - 1) * $perPage;
		return [$page, $perPage, $offset];
	}
}
