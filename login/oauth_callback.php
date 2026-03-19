<?php
/**
 * OAuth2 SSO Callback
 *
 * Ce fichier est appelé par le provider OAuth2 après l'authentification.
 * Il échange le code d'autorisation contre un token, résout l'utilisateur
 * local, et connecte via SessionGuard.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/API/bootstrap.php';

$code  = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';
$error = $_GET['error'] ?? '';

// Erreur du provider
if ($error) {
	$_SESSION['login_error'] = 'SSO authentication cancelled or failed: ' . htmlspecialchars($error);
	header('Location: index.php');
	exit;
}

if (empty($code) || empty($state)) {
	$_SESSION['login_error'] = 'Invalid OAuth callback parameters.';
	header('Location: index.php');
	exit;
}

try {
	$guard = new \API\Auth\OAuthGuard(getPDO());

	if (!$guard->isConfigured()) {
		$_SESSION['login_error'] = 'SSO is not configured. Contact your administrator.';
		header('Location: index.php');
		exit;
	}

	$result = $guard->handleCallback($code, $state);

	if ($result['user'] === null) {
		// Pas d'utilisateur local trouvé
		$_SESSION['login_error'] = $result['error'] ?? 'No local account found for this email.';
		header('Location: index.php');
		exit;
	}

	$user = $result['user'];

	// Connecter via SessionGuard
	$sessionGuard = app('auth');
	$sessionGuard->login($user);

	// Audit log
	try {
		app('audit')->logAuth('sso_login', $user['email'] ?? '', true, [
			'provider' => env('OAUTH_PROVIDER', 'unknown'),
			'is_new' => $result['is_new'],
		]);
	} catch (\Throwable $e) { /* non-critical */ }

	// Rediriger vers le dashboard
	header('Location: ../accueil/accueil.php');
	exit;

} catch (\RuntimeException $e) {
	$_SESSION['login_error'] = 'SSO error: ' . $e->getMessage();
	header('Location: index.php');
	exit;
} catch (\Throwable $e) {
	error_log('OAuth callback error: ' . $e->getMessage());
	$_SESSION['login_error'] = 'An unexpected error occurred during SSO authentication.';
	header('Location: index.php');
	exit;
}
