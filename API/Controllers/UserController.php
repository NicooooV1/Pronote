<?php
declare(strict_types=1);

namespace API\Controllers;

/**
 * User Controller
 * GET /api/v1/users/me
 * GET /api/v1/users/me/tokens
 * POST /api/v1/users/me/tokens
 * DELETE /api/v1/users/me/tokens/:id
 */
class UserController extends BaseController
{
	public function me(): void
	{
		$user = $this->authenticate();

		// Nettoyer les champs sensibles
		unset($user['mot_de_passe'], $user['password'], $user['_token_id'], $user['_token_name'], $user['_token_abilities']);

		$this->json($user);
	}

	public function listTokens(): void
	{
		$user = $this->authenticate();

		$guard = new \API\Auth\TokenGuard($this->pdo);
		$tokens = $guard->listTokens(
			(int) $user['id'],
			$user['type'] ?? $user['profil'] ?? $user['role'] ?? ''
		);

		$this->json($tokens);
	}

	public function createToken(): void
	{
		$user = $this->authenticate();
		$body = $this->jsonBody();

		$name = trim($body['name'] ?? '');
		if ($name === '') {
			$this->error('Token name is required', 400);
		}

		$abilities = $body['abilities'] ?? null;
		$expiresInDays = isset($body['expires_in_days']) ? (int) $body['expires_in_days'] : null;

		$guard = new \API\Auth\TokenGuard($this->pdo);
		[$plainToken, $record] = $guard->createToken(
			(int) $user['id'],
			$user['type'] ?? $user['profil'] ?? $user['role'] ?? '',
			$name,
			$abilities,
			$expiresInDays
		);

		$this->json([
			'token' => $plainToken,
			'id' => $record['id'],
			'name' => $record['name'],
			'abilities' => $record['abilities'],
			'expires_at' => $record['expires_at'],
			'warning' => 'Store this token securely. It will not be shown again.',
		], 201);
	}

	public function revokeToken(array $params): void
	{
		$user = $this->authenticate();
		$tokenId = (int) ($params['id'] ?? 0);

		if ($tokenId <= 0) {
			$this->error('Invalid token ID', 400);
		}

		$guard = new \API\Auth\TokenGuard($this->pdo);
		$deleted = $guard->revokeToken($tokenId, (int) $user['id']);

		if (!$deleted) {
			$this->error('Token not found', 404);
		}

		$this->json(['message' => 'Token revoked']);
	}
}
