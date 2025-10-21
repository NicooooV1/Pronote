<?php
/**
 * Legacy Bridge - Pont de compatibilité avec l'ancien code
 * Les fonctions sont créées uniquement si elles n'existent pas déjà.
 */

if (defined('PRONOTE_LEGACY_BRIDGE_LOADED')) {
	return;
}
define('PRONOTE_LEGACY_BRIDGE_LOADED', true);

// ==================== AUTHENTIFICATION ====================

if (!function_exists('isLoggedIn')) {
	function isLoggedIn() {
		return app('auth')->check();
	}
}

if (!function_exists('getCurrentUser')) {
	function getCurrentUser() {
		return app('auth')->user();
	}
}

if (!function_exists('getUserRole')) {
	function getUserRole() {
		$user = app('auth')->user();
		return $user['profil'] ?? $user['type'] ?? null;
	}
}

if (!function_exists('requireLogin')) {
	function requireLogin() {
		if (!app('auth')->check()) {
			$base = env('APP_URL', defined('BASE_URL') ? BASE_URL : '');
			$login = rtrim((string)$base, '/') . '/login/public/index.php';
			header('Location: ' . $login);
			exit;
		}
		return app('auth')->user();
	}
}

if (!function_exists('requireAuth')) {
	function requireAuth() {
		return requireLogin();
	}
}

if (!function_exists('logout')) {
	function logout() {
		app('auth')->logout();
	}
}

if (!function_exists('login')) {
	function login($profil, $identifiant, $password) {
		// Map legacy fields to current credentials keys
		return app('auth')->attempt([
			'type' => $profil,
			'login' => $identifiant,
			'password' => $password
		]);
	}
}

// ==================== VÉRIFICATIONS DE RÔLES ====================

if (!function_exists('isAdmin')) {
	function isAdmin() {
		return getUserRole() === 'administrateur';
	}
}
if (!function_exists('isTeacher')) {
	function isTeacher() {
		return getUserRole() === 'professeur';
	}
}
if (!function_exists('isProfesseur')) {
	function isProfesseur() {
		return isTeacher();
	}
}
if (!function_exists('isStudent')) {
	function isStudent() {
		return getUserRole() === 'eleve';
	}
}
if (!function_exists('isParent')) {
	function isParent() {
		return getUserRole() === 'parent';
	}
}
if (!function_exists('isVieScolaire')) {
	function isVieScolaire() {
		return getUserRole() === 'vie_scolaire';
	}
}

// ==================== PERMISSIONS (simples) ====================

if (!function_exists('canManageNotes')) {
	function canManageNotes() {
		return in_array(getUserRole(), ['administrateur', 'professeur', 'vie_scolaire'], true);
	}
}
if (!function_exists('canManageAbsences')) {
	function canManageAbsences() {
		return in_array(getUserRole(), ['administrateur', 'professeur', 'vie_scolaire'], true);
	}
}
if (!function_exists('canManageDevoirs')) {
	function canManageDevoirs() {
		return in_array(getUserRole(), ['administrateur', 'professeur'], true);
	}
}

// ==================== UTILITAIRES UTILISATEUR ====================

if (!function_exists('getUserFullName')) {
	function getUserFullName() {
		$user = app('auth')->user();
		return $user ? trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? '')) : '';
	}
}

if (!function_exists('getUserInitials')) {
	function getUserInitials() {
		$user = app('auth')->user();
		if (!$user) return '??';
		$i1 = !empty($user['prenom']) ? strtoupper(mb_substr($user['prenom'], 0, 1)) : '';
		$i2 = !empty($user['nom']) ? strtoupper(mb_substr($user['nom'], 0, 1)) : '';
		return ($i1 . $i2) ?: '??';
	}
}

if (!function_exists('getUserId')) {
	function getUserId() {
		$user = app('auth')->user();
		return $user['id'] ?? null;
	}
}

// ==================== CSRF ====================

if (!function_exists('generateCSRFToken')) {
	function generateCSRFToken() {
		return app('csrf')->generate();
	}
}
if (!function_exists('validateCSRFToken')) {
	function validateCSRFToken($token = null) {
		if ($token === null && isset($_POST['csrf_token'])) {
			$token = $_POST['csrf_token'];
		}
		return $token ? app('csrf')->validate($token) : false;
	}
}
if (!function_exists('csrfField')) {
	function csrfField() {
		return app('csrf')->field();
	}
}

// ==================== DATABASE ====================

if (!function_exists('executeQuery')) {
	function executeQuery($sql, $params = [], $fetchMode = \PDO::FETCH_ASSOC) {
		$pdo = app('db')->getConnection();
		$stmt = $pdo->prepare($sql);
		$stmt->execute($params);

		$head = strtoupper(strtok(ltrim($sql), " \t\n\r"));
		if (in_array($head, ['SELECT','SHOW','DESCRIBE','EXPLAIN'], true)) {
			return $stmt->fetchAll($fetchMode);
		}
		if ($head === 'INSERT') {
			return $pdo->lastInsertId();
		}
		return $stmt->rowCount();
	}
}

if (!function_exists('tableExists')) {
	function tableExists($tableName) {
		try {
			$pdo = app('db')->getConnection();
			$stmt = $pdo->prepare("SHOW TABLES LIKE ?");
			$stmt->execute([$tableName]);
			return $stmt->rowCount() > 0;
		} catch (\Throwable $e) {
			return false;
		}
	}
}

// ==================== LOGGING ====================

if (!function_exists('logError')) {
	function logError($message, $context = []) {
		try { app('log')->error($message, (array)$context); } catch (\Throwable $e) { error_log("[ERROR] $message " . json_encode($context)); }
	}
}
if (!function_exists('logInfo')) {
	function logInfo($message, $context = []) {
		try { app('log')->info($message, (array)$context); } catch (\Throwable $e) { error_log("[INFO] $message " . json_encode($context)); }
	}
}
if (!function_exists('logSecurityEvent')) {
	function logSecurityEvent($event, $data = []) {
		// Toujours logger dans les logs système
		error_log("SECURITY EVENT: {$event} " . json_encode($data));
		
		try {
			logInfo("Security event: {$event}", (array)$data);
			
			// Tenter l'audit en base (critique)
			$audit = app('audit');
			if ($audit) { 
				$result = $audit->logSecurity($event, (array)$data);
				if (!$result) {
					// Échec critique : notifier
					error_log("CRITICAL: Audit security logging failed for event '{$event}'");
				}
			} else {
				error_log("WARNING: Audit service not available for security event '{$event}'");
			}
		} catch (\Throwable $e) {
			// Erreur critique : TOUJOURS logger
			error_log("CRITICAL: Audit security exception for event '{$event}': " . $e->getMessage());
			error_log("Stack trace: " . $e->getTraceAsString());
		}
	}
}

// ==================== RATE LIMITING ====================

if (!function_exists('checkRateLimit')) {
	function checkRateLimit($key, $maxAttempts = 5, $decaySeconds = 60) {
		// Our RateLimiter exposes tooManyAttempts/hit/clear. Decay is session-based.
		$limiter = app('rate_limiter');
		if ($limiter->tooManyAttempts($key)) {
			return false;
		}
		$limiter->hit($key);
		return true;
	}
}

// ==================== REDIRECTION ====================

if (!function_exists('redirect')) {
	function redirect($path, $message = null, $type = 'info') {
		if ($message) {
			$_SESSION['flash'][$type] = $message;
		}
		if (strpos($path, 'http') === 0) {
			header("Location: {$path}");
			exit;
		}
		$baseUrl = defined('BASE_URL') ? BASE_URL : (env('APP_URL', '') ?: '');
		$url = rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
		header("Location: {$url}");
		exit;
	}
}

// ==================== SESSION CLEANUP ====================

if (!function_exists('cleanExpiredSessions')) {
	function cleanExpiredSessions() {
		try {
			$pdo = app('db')->getConnection();
			$lifetime = (int)(config('security.session_lifetime', 7200));
			$sql = "DELETE FROM session_security WHERE last_activity < DATE_SUB(NOW(), INTERVAL ? SECOND)";
			$stmt = $pdo->prepare($sql);
			$stmt->execute([$lifetime]);
		} catch (\Throwable $e) {
			// Silent fail
		}
	}
}

// ==================== VALIDATION HELPERS ====================

if (!function_exists('sanitizeInput')) {
	function sanitizeInput($input, $type = 'string') {
		if ($input === null) return null;
		switch ($type) {
			case 'email': return filter_var(trim($input), FILTER_SANITIZE_EMAIL);
			case 'int': return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
			case 'float': return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
			case 'url': return filter_var(trim($input), FILTER_SANITIZE_URL);
			default: return htmlspecialchars(trim((string)$input), ENT_QUOTES, 'UTF-8');
		}
	}
}

if (!function_exists('validateEmail')) {
	function validateEmail($email) {
		return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
	}
}

// ==================== GLOBAL PDO HELPER (remplacement de $GLOBALS) ====================

if (!function_exists('getPDO')) {
	/**
	 * Récupère la connexion PDO (remplace $GLOBALS['pdo'])
	 * @return PDO
	 */
	function getPDO(): PDO {
		return app('db')->getConnection();
	}
}

// ==================== FIN DU BRIDGE ====================
