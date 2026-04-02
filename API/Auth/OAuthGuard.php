<?php
declare(strict_types=1);

namespace API\Auth;

/**
 * OAuth2 Guard — Authentification SSO via Google, Microsoft, ou autre fournisseur OAuth2
 *
 * Configuration via .env :
 *   OAUTH_PROVIDER=google|microsoft|custom
 *   OAUTH_CLIENT_ID=xxx
 *   OAUTH_CLIENT_SECRET=xxx
 *   OAUTH_REDIRECT_URI=https://fronote.example.com/login/oauth_callback.php
 *   OAUTH_AUTHORIZE_URL=  (pour custom)
 *   OAUTH_TOKEN_URL=      (pour custom)
 *   OAUTH_USERINFO_URL=   (pour custom)
 *
 * Flow :
 *   1. Redirect vers getAuthorizationUrl()
 *   2. Callback reçoit ?code=... → handleCallback($code)
 *   3. handleCallback échange le code contre un token, récupère l'identité,
 *      fait le mapping vers un utilisateur local, et connecte via SessionGuard
 */
class OAuthGuard
{
	protected string $provider;
	protected string $clientId;
	protected string $clientSecret;
	protected string $redirectUri;
	protected string $authorizeUrl;
	protected string $tokenUrl;
	protected string $userinfoUrl;

	protected \PDO $pdo;

	/** Presets pour les providers connus */
	protected const PROVIDERS = [
		'google' => [
			'authorize' => 'https://accounts.google.com/o/oauth2/v2/auth',
			'token'     => 'https://oauth2.googleapis.com/token',
			'userinfo'  => 'https://www.googleapis.com/oauth2/v3/userinfo',
			'scopes'    => 'openid email profile',
		],
		'microsoft' => [
			'authorize' => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
			'token'     => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
			'userinfo'  => 'https://graph.microsoft.com/v1.0/me',
			'scopes'    => 'openid email profile User.Read',
		],
	];

	public function __construct(\PDO $pdo)
	{
		$this->pdo = $pdo;
		$this->provider = env('OAUTH_PROVIDER', '') ?: '';
		$this->clientId = env('OAUTH_CLIENT_ID', '') ?: '';
		$this->clientSecret = env('OAUTH_CLIENT_SECRET', '') ?: '';
		$this->redirectUri = env('OAUTH_REDIRECT_URI', '') ?: '';

		$preset = self::PROVIDERS[$this->provider] ?? null;
		$this->authorizeUrl = env('OAUTH_AUTHORIZE_URL', '') ?: ($preset['authorize'] ?? '');
		$this->tokenUrl = env('OAUTH_TOKEN_URL', '') ?: ($preset['token'] ?? '');
		$this->userinfoUrl = env('OAUTH_USERINFO_URL', '') ?: ($preset['userinfo'] ?? '');
	}

	/**
	 * Vérifie que le SSO est configuré
	 */
	public function isConfigured(): bool
	{
		return $this->clientId !== ''
			&& $this->clientSecret !== ''
			&& $this->authorizeUrl !== ''
			&& $this->tokenUrl !== '';
	}

	/**
	 * Génère l'URL de redirection vers le fournisseur OAuth2
	 */
	public function getAuthorizationUrl(): string
	{
		$state = bin2hex(random_bytes(16));
		$_SESSION['oauth_state'] = $state;

		$preset = self::PROVIDERS[$this->provider] ?? null;
		$scopes = env('OAUTH_SCOPES', '') ?: ($preset['scopes'] ?? 'openid email profile');

		$params = http_build_query([
			'client_id'     => $this->clientId,
			'redirect_uri'  => $this->redirectUri,
			'response_type' => 'code',
			'scope'         => $scopes,
			'state'         => $state,
			'access_type'   => 'offline',
			'prompt'        => 'select_account',
		]);

		return $this->authorizeUrl . '?' . $params;
	}

	/**
	 * Traite le callback OAuth2 (échange code → token → userinfo)
	 *
	 * @param string $code Le code d'autorisation reçu
	 * @param string $state Le state pour la vérification CSRF
	 * @return array{user: array, is_new: bool} Utilisateur local + indicateur de première connexion
	 * @throws \RuntimeException en cas d'erreur
	 */
	public function handleCallback(string $code, string $state): array
	{
		// Vérifier le state (CSRF protection)
		if (!isset($_SESSION['oauth_state']) || !hash_equals($_SESSION['oauth_state'], $state)) {
			throw new \RuntimeException('Invalid OAuth state — possible CSRF attack');
		}
		unset($_SESSION['oauth_state']);

		// Échanger le code contre un access token
		$tokenData = $this->exchangeCodeForToken($code);
		$accessToken = $tokenData['access_token'] ?? null;

		if (!$accessToken) {
			throw new \RuntimeException('Failed to obtain access token');
		}

		// Récupérer les infos utilisateur
		$oauthUser = $this->getUserInfo($accessToken);

		// Mapper vers un utilisateur local
		return $this->mapToLocalUser($oauthUser);
	}

	/**
	 * Échange le code d'autorisation contre un access token
	 */
	protected function exchangeCodeForToken(string $code): array
	{
		$postData = http_build_query([
			'client_id'     => $this->clientId,
			'client_secret' => $this->clientSecret,
			'code'          => $code,
			'grant_type'    => 'authorization_code',
			'redirect_uri'  => $this->redirectUri,
		]);

		$context = stream_context_create([
			'http' => [
				'method'  => 'POST',
				'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
				'content' => $postData,
				'timeout' => 10,
			],
		]);

		$response = @file_get_contents($this->tokenUrl, false, $context);
		if ($response === false) {
			throw new \RuntimeException('Failed to exchange OAuth code for token');
		}

		$data = json_decode($response, true);
		if (isset($data['error'])) {
			throw new \RuntimeException('OAuth token error: ' . ($data['error_description'] ?? $data['error']));
		}

		return $data;
	}

	/**
	 * Récupère les informations utilisateur depuis le provider
	 */
	protected function getUserInfo(string $accessToken): array
	{
		$context = stream_context_create([
			'http' => [
				'method'  => 'GET',
				'header'  => "Authorization: Bearer {$accessToken}\r\n",
				'timeout' => 10,
			],
		]);

		$response = @file_get_contents($this->userinfoUrl, false, $context);
		if ($response === false) {
			throw new \RuntimeException('Failed to fetch user info from OAuth provider');
		}

		$data = json_decode($response, true);
		if (!is_array($data)) {
			throw new \RuntimeException('Invalid user info response from OAuth provider');
		}

		// Normaliser les champs selon le provider
		return $this->normalizeUserInfo($data);
	}

	/**
	 * Normalise les champs utilisateur entre les providers
	 */
	protected function normalizeUserInfo(array $data): array
	{
		if ($this->provider === 'microsoft') {
			return [
				'email'      => $data['mail'] ?? $data['userPrincipalName'] ?? '',
				'given_name' => $data['givenName'] ?? '',
				'family_name' =>$data['surname'] ?? '',
				'name'       => $data['displayName'] ?? '',
				'sub'        => $data['id'] ?? '',
				'picture'    => null,
			];
		}

		// Google et format standard OpenID Connect
		return [
			'email'      => $data['email'] ?? '',
			'given_name' => $data['given_name'] ?? '',
			'family_name' =>$data['family_name'] ?? '',
			'name'       => $data['name'] ?? '',
			'sub'        => $data['sub'] ?? $data['id'] ?? '',
			'picture'    => $data['picture'] ?? null,
		];
	}

	/**
	 * Mappe l'identité OAuth vers un utilisateur local.
	 *
	 * Recherche par email dans toutes les tables utilisateur.
	 * Si aucun utilisateur local n'est trouvé, enregistre le binding pour traitement admin.
	 */
	protected function mapToLocalUser(array $oauthUser): array
	{
		$email = $oauthUser['email'] ?? '';
		if (empty($email)) {
			throw new \RuntimeException('OAuth provider did not return an email address');
		}

		$tables = [
			'administrateurs' => 'administrateur',
			'professeurs'     => 'professeur',
			'vie_scolaire'    => 'vie_scolaire',
			'eleves'          => 'eleve',
			'parents'         => 'parent',
		];

		foreach ($tables as $table => $type) {
			$stmt = $this->pdo->prepare("SELECT * FROM `{$table}` WHERE email = ? LIMIT 1");
			$stmt->execute([$email]);
			$user = $stmt->fetch(\PDO::FETCH_ASSOC);

			if ($user) {
				$user['type'] = $type;
				$user['role'] = $type;
				// Stocker l'association SSO
				$this->saveOAuthBinding($user['id'], $type, $this->provider, $oauthUser['sub']);
				return ['user' => $user, 'is_new' => false];
			}
		}

		// Aucun utilisateur local trouvé — retourner les infos pour traitement
		return [
			'user' => null,
			'is_new' => true,
			'oauth_data' => $oauthUser,
			'error' => 'No local account found for this email. Contact your administrator.',
		];
	}

	/**
	 * Sauvegarde l'association OAuth (pour les connexions futures)
	 */
	protected function saveOAuthBinding(int $userId, string $userType, string $provider, string $providerId): void
	{
		try {
			// Créer la table si elle n'existe pas (auto-setup)
			$this->pdo->exec("
				CREATE TABLE IF NOT EXISTS `oauth_bindings` (
					`id` INT AUTO_INCREMENT PRIMARY KEY,
					`user_id` INT NOT NULL,
					`user_type` VARCHAR(20) NOT NULL,
					`provider` VARCHAR(50) NOT NULL,
					`provider_user_id` VARCHAR(255) NOT NULL,
					`created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
					UNIQUE KEY `uk_binding` (`provider`, `provider_user_id`),
					KEY `idx_user` (`user_id`, `user_type`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
			");

			$stmt = $this->pdo->prepare("
				INSERT INTO oauth_bindings (user_id, user_type, provider, provider_user_id)
				VALUES (?, ?, ?, ?)
				ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), user_type = VALUES(user_type)
			");
			$stmt->execute([$userId, $userType, $provider, $providerId]);
		} catch (\Throwable $e) {
			// Non-critical — log and continue
			error_log('OAuthGuard: Failed to save binding: ' . $e->getMessage());
		}
	}
}
