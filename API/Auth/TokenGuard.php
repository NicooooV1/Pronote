<?php
declare(strict_types=1);

namespace API\Auth;

use PDO;

/**
 * Token Guard — Authentification par Bearer token pour l'API REST
 *
 * Chaque token est stocké hashé (SHA-256) en base. Le token en clair
 * n'est visible qu'une seule fois, à la création.
 *
 * Usage :
 *   $guard = new TokenGuard($pdo);
 *   $user  = $guard->authenticate();  // null si non authentifié
 *
 * Création de token :
 *   [$plainToken, $record] = $guard->createToken($userId, $userType, 'Mon app', ['notes.view']);
 */
class TokenGuard
{
	protected PDO $pdo;
	protected string $table = 'api_tokens';

	public function __construct(PDO $pdo)
	{
		$this->pdo = $pdo;
	}

	/**
	 * Authentifie la requête courante via le header Authorization: Bearer <token>
	 *
	 * @return array|null Données utilisateur + abilities si authentifié, null sinon
	 */
	public function authenticate(): ?array
	{
		$token = $this->extractBearerToken();
		if ($token === null) {
			return null;
		}

		$hash = hash('sha256', $token);

		$stmt = $this->pdo->prepare("
			SELECT id, user_id, user_type, name, abilities, expires_at
			FROM {$this->table}
			WHERE token_hash = ?
			LIMIT 1
		");
		$stmt->execute([$hash]);
		$record = $stmt->fetch(PDO::FETCH_ASSOC);

		if (!$record) {
			return null;
		}

		// Vérifier l'expiration
		if ($record['expires_at'] !== null && strtotime($record['expires_at']) < time()) {
			return null;
		}

		// Mettre à jour last_used_at
		$update = $this->pdo->prepare("UPDATE {$this->table} SET last_used_at = NOW() WHERE id = ?");
		$update->execute([$record['id']]);

		// Résoudre l'utilisateur
		$userProvider = new UserProvider($this->pdo);
		$user = $userProvider->retrieveById((int) $record['user_id'], $record['user_type']);

		if (!$user) {
			return null;
		}

		$user['_token_id'] = (int) $record['id'];
		$user['_token_name'] = $record['name'];
		$user['_token_abilities'] = json_decode($record['abilities'] ?? 'null', true);

		return $user;
	}

	/**
	 * Vérifie si le token courant possède une ability donnée
	 */
	public function can(array $user, string $ability): bool
	{
		$abilities = $user['_token_abilities'] ?? null;

		// null = toutes les permissions (wildcard token)
		if ($abilities === null) {
			return true;
		}

		return in_array('*', $abilities, true) || in_array($ability, $abilities, true);
	}

	/**
	 * Crée un nouveau token API pour un utilisateur
	 *
	 * @param int         $userId    ID utilisateur
	 * @param string      $userType  Type (administrateur, professeur, etc.)
	 * @param string      $name      Nom descriptif du token
	 * @param array|null  $abilities Permissions (null = toutes)
	 * @param int|null    $expiresInDays Nombre de jours avant expiration (null = jamais)
	 * @return array [plainToken, record]
	 */
	public function createToken(
		int $userId,
		string $userType,
		string $name,
		?array $abilities = null,
		?int $expiresInDays = null
	): array {
		$plainToken = bin2hex(random_bytes(32)); // 64 chars hex
		$hash = hash('sha256', $plainToken);
		$expiresAt = $expiresInDays !== null
			? date('Y-m-d H:i:s', time() + ($expiresInDays * 86400))
			: null;

		$stmt = $this->pdo->prepare("
			INSERT INTO {$this->table} (user_id, user_type, token_hash, name, abilities, expires_at, created_at)
			VALUES (?, ?, ?, ?, ?, ?, NOW())
		");
		$stmt->execute([
			$userId,
			$userType,
			$hash,
			$name,
			$abilities !== null ? json_encode($abilities) : null,
			$expiresAt,
		]);

		$record = [
			'id' => (int) $this->pdo->lastInsertId(),
			'user_id' => $userId,
			'user_type' => $userType,
			'name' => $name,
			'abilities' => $abilities,
			'expires_at' => $expiresAt,
		];

		return [$plainToken, $record];
	}

	/**
	 * Révoque un token par son ID
	 */
	public function revokeToken(int $tokenId, int $userId): bool
	{
		$stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = ? AND user_id = ?");
		$stmt->execute([$tokenId, $userId]);
		return $stmt->rowCount() > 0;
	}

	/**
	 * Liste les tokens d'un utilisateur (sans les hashes)
	 */
	public function listTokens(int $userId, string $userType): array
	{
		$stmt = $this->pdo->prepare("
			SELECT id, name, abilities, last_used_at, expires_at, created_at
			FROM {$this->table}
			WHERE user_id = ? AND user_type = ?
			ORDER BY created_at DESC
		");
		$stmt->execute([$userId, $userType]);
		$tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);

		foreach ($tokens as &$t) {
			$t['abilities'] = json_decode($t['abilities'] ?? 'null', true);
		}

		return $tokens;
	}

	/**
	 * Supprime tous les tokens expirés (maintenance)
	 */
	public function purgeExpired(): int
	{
		$stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE expires_at IS NOT NULL AND expires_at < NOW()");
		$stmt->execute();
		return $stmt->rowCount();
	}

	/**
	 * Extrait le Bearer token du header Authorization
	 */
	protected function extractBearerToken(): ?string
	{
		$header = $_SERVER['HTTP_AUTHORIZATION']
			?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
			?? null;

		// Apache avec mod_rewrite peut perdre le header — fallback apache_request_headers
		if ($header === null && function_exists('apache_request_headers')) {
			$headers = apache_request_headers();
			$header = $headers['Authorization'] ?? $headers['authorization'] ?? null;
		}

		if ($header === null || !str_starts_with($header, 'Bearer ')) {
			return null;
		}

		$token = trim(substr($header, 7));
		return $token !== '' ? $token : null;
	}
}
