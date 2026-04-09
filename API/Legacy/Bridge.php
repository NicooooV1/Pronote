<?php
/**
 * Legacy Bridge - Pont de compatibilité avec l'ancien code
 * Les fonctions sont créées uniquement si elles n'existent pas déjà.
 */

if (defined('PRONOTE_LEGACY_BRIDGE_LOADED')) {
	return;
}
define('PRONOTE_LEGACY_BRIDGE_LOADED', true);

// Ensure bootstrap (app(), helpers, autoloader) is loaded first
if (!defined('PRONOTE_BOOTSTRAP_LOADED')) {
	require_once __DIR__ . '/../bootstrap.php';
}

// Charger la classe User legacy (utilisée par les pages admin)
require_once __DIR__ . '/User.php';

// ==================== CONSTANTES GLOBALES ====================

if (!defined('LOGIN_URL')) {
	define('LOGIN_URL', '../login/index.php');
}

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

if (!function_exists('checkAuth')) {
	/**
	 * Alias de getCurrentUser() pour compatibilité messagerie
	 */
	function checkAuth() {
		return getCurrentUser();
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
			redirect('login/index.php');
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

if (!function_exists('loginUser')) {
	/**
	 * Crée la session pour un utilisateur déjà validé (après 2FA ou login unifié).
	 * @param array $user Tableau utilisateur avec au moins 'id' et 'type'
	 */
	function loginUser(array $user): void {
		app('auth')->loginUser($user);
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
if (!function_exists('isEleve')) {
	function isEleve() {
		return isStudent();
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

// ==================== PERMISSIONS (centralisées via RBAC) ====================

if (!function_exists('hasPermission')) {
	/**
	 * Vérifie si l'utilisateur connecté a la permission pour une action donnée.
	 * Délègue au système RBAC centralisé (API\Security\RBAC).
	 * Accepte les formats legacy "notes" et RBAC "notes.manage".
	 * @param string $action Clé de permission
	 * @return bool
	 */
	function hasPermission(string $action): bool {
		try {
			$rbac = app('rbac');
			// Si la permission est déjà au format RBAC (contient un point), vérifier directement
			if (str_contains($action, '.')) {
				return $rbac->can($action);
			}
			// Format legacy "notes" → vérifier "notes.manage" (gestion)
			return $rbac->can($action . '.manage');
		} catch (\Throwable $e) {
			return false;
		}
	}
}

/**
 * Fonctions legacy de vérification de permissions par module.
 * @deprecated Utiliser hasPermission('module.action') ou canModule('module', 'action') à la place.
 *
 * Le mapping est centralisé dans un tableau unique. Les fonctions sont générées
 * dans un fichier cache (storage/cache/_legacy_perms.php) et incluses une seule fois.
 * Cela élimine l'usage d'eval() tout en gardant la compatibilité totale.
 */
$_legacyPermissionMap = [
    'canManageNotes'       => 'notes',
    'canManageAbsences'    => 'absences',
    'canManageDevoirs'     => 'devoirs',
    'canManageEDT'         => 'edt',
    'canManageAppel'       => 'appel',
    'canManageDiscipline'  => 'discipline',
    'canSignalerIncident'  => 'signaler_incident',
    'canManageAnnonces'    => 'annonces',
    'canManageBulletins'   => 'bulletins',
    'canManageRendus'      => 'rendus',
    'canAccessVieScolaire' => 'vie_scolaire',
    'canManageDocuments'   => 'documents',
    'canManageCompetences' => 'competences',
    'canAccessReporting'   => 'reporting',
    'canManageReunions'    => 'reunions',
    'canManageInscriptions'=> 'inscriptions',
    'canManageOrientation' => 'orientation',
    'canManageSignalements'=> 'signalements',
    'canManageBibliotheque'=> 'bibliotheque',
    'canManageClubs'       => 'clubs',
    'canAccessInfirmerie'  => 'infirmerie',
    'canManageArchives'    => 'archives',
    'canManageSupport'     => 'support',
    'canManageExamens'     => 'examens',
    'canManageBesoins'     => 'besoins',
    'canManagePersonnel'   => 'personnel',
    'canManageSalles'      => 'salles',
    'canManagePeriscolaire'=> 'periscolaire',
    'canManageStages'      => 'stages',
    'canManageTransports'  => 'transports',
    'canManageFacturation' => 'facturation',
    'canManageRessources'  => 'ressources',
    'canManageDiplomes'    => 'diplomes',
];

// Générer un fichier cache contenant toutes les fonctions (évite eval)
$_cacheDir = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/storage/cache';
$_cacheFile = $_cacheDir . '/_legacy_perms.php';

if (!file_exists($_cacheFile)) {
    if (!is_dir($_cacheDir)) {
        @mkdir($_cacheDir, 0755, true);
    }
    $_code = "<?php\n// Auto-generated legacy permission functions — do not edit\n";
    foreach ($_legacyPermissionMap as $_fn => $_pk) {
        $_code .= "if (!function_exists('{$_fn}')) {\n";
        $_code .= "    /** @deprecated Use hasPermission('{$_pk}') */ \n";
        $_code .= "    function {$_fn}(): bool { return hasPermission('{$_pk}'); }\n";
        $_code .= "}\n";
    }
    file_put_contents($_cacheFile, $_code);
}

require_once $_cacheFile;
unset($_legacyPermissionMap, $_cacheDir, $_cacheFile, $_code, $_fn, $_pk);

if (!function_exists('isPersonnelVS')) {
    /** @deprecated Utiliser isVieScolaire() */
    function isPersonnelVS() { return getUserRole() === 'vie_scolaire'; }
}

// ==================== RBAC ====================

if (!function_exists('requireRole')) {
	/**
	 * Bloque l'accès si le rôle courant n'est pas dans la liste
	 * @param string ...$roles Rôles autorisés
	 */
	function requireRole(string ...$roles) {
		$userRole = getUserRole();
		if (!in_array($userRole, $roles, true)) {
			$_SESSION['error_message'] = 'Vous n\'avez pas les droits nécessaires.';
			$base = defined('BASE_URL') ? BASE_URL : '';
			header('Location: ' . $base . '/accueil/accueil.php');
			exit;
		}
	}
}

if (!function_exists('can')) {
	/**
	 * Vérifie une permission RBAC
	 */
	function can(string $permission): bool {
		try {
			return app('rbac')->can($permission);
		} catch (\Throwable $e) {
			return false;
		}
	}
}

if (!function_exists('authorize')) {
	/**
	 * Vérifie une permission RBAC — bloque si refusée
	 */
	function authorize(string $permission): void {
		try {
			app('rbac')->authorize($permission);
		} catch (\Throwable $e) {
			$_SESSION['error_message'] = 'Accès refusé.';
			$base = defined('BASE_URL') ? BASE_URL : '';
			header('Location: ' . $base . '/accueil/accueil.php');
			exit;
		}
	}
}

if (!function_exists('canModule')) {
	/**
	 * Vérifie une permission CRUD sur un module.
	 * Ex: canModule('messagerie', 'send'), canModule('notes', 'create')
	 */
	function canModule(string $moduleKey, string $action = 'view'): bool {
		try {
			return app('rbac')->canModule($moduleKey, $action);
		} catch (\Throwable $e) {
			return false;
		}
	}
}

if (!function_exists('requireAdmin')) {
	/**
	 * Bloque l'accès au back-office si non-admin ou technicien
	 */
	function requireAdmin(): void {
		$role = getUserRole();
		if ($role === 'technicien') {
			// Technicien has limited admin access, verify it's still valid
			if (!isTechnicienValid()) {
				$_SESSION['error_message'] = 'Accès technicien expiré.';
				header('Location: ' . (defined('BASE_URL') ? BASE_URL : '') . '/login/index.php');
				exit;
			}
			return;
		}
		requireRole('administrateur');
	}
}

if (!function_exists('isTechnicienValid')) {
	/**
	 * Vérifie si l'accès technicien est encore valide (actif + non expiré)
	 */
	function isTechnicienValid(): bool {
		if (getUserRole() !== 'technicien') return false;
		try {
			$pdo = getPDO();
			$stmt = $pdo->prepare("SELECT id FROM technicien_access WHERE id = ? AND actif = 1 AND date_expiration > NOW() AND revoked_at IS NULL LIMIT 1");
			$stmt->execute([getUserId()]);
			return (bool)$stmt->fetchColumn();
		} catch (\Throwable $e) {
			return false;
		}
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

// ==================== INTERNATIONALISATION (i18n) ====================

if (!function_exists('__')) {
	/**
	 * Traduit une clé avec interpolation de paramètres.
	 * @param string $key     Clé de traduction (ex: 'btn.save', 'modules/notes.title')
	 * @param array  $params  Paramètres (ex: ['name' => 'Jean'] → ':name' remplacé)
	 * @param string|null $locale Forcer une locale
	 * @return string Texte traduit ou la clé si non trouvée
	 */
	function __(string $key, array $params = [], ?string $locale = null): string {
		try {
			return app('translator')->get($key, $params, $locale);
		} catch (\Throwable $e) {
			return $key;
		}
	}
}

if (!function_exists('_n')) {
	/**
	 * Pluralisation. Le fichier de traduction utilise des variantes séparées par |
	 * Ex: "Aucun élément|:count élément|:count éléments"
	 * @param string $key    Clé de traduction
	 * @param int    $count  Nombre pour la pluralisation
	 * @param array  $params Paramètres supplémentaires
	 * @param string|null $locale Locale forcée
	 * @return string
	 */
	function _n(string $key, int $count, array $params = [], ?string $locale = null): string {
		try {
			return app('translator')->choice($key, $count, $params, $locale);
		} catch (\Throwable $e) {
			return $key;
		}
	}
}

if (!function_exists('currentLocale')) {
	/**
	 * Retourne la locale active
	 */
	function currentLocale(): string {
		try {
			return app('translator')->locale();
		} catch (\Throwable $e) {
			return 'fr';
		}
	}
}

// ==================== CSRF (helpers supplémentaires, ex-core.php) ====================

if (!function_exists('csrf_meta')) {
    function csrf_meta() {
        return app('csrf')->meta();
    }
}

if (!function_exists('csrf_validate')) {
    function csrf_validate(?string $token = null): bool {
        if ($token !== null) {
            return app('csrf')->validate($token);
        }
        return app('csrf')->validateFromRequest();
    }
}

if (!function_exists('csrf_verify')) {
    function csrf_verify(): void {
        app('csrf')->verifyOrFail();
    }
}

if (!function_exists('isAjaxRequest')) {
    function isAjaxRequest(): bool {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}

// ==================== CSRF ====================

if (!function_exists('csrf_token')) {
	function csrf_token() {
		return app('csrf')->getToken();
	}
}
if (!function_exists('csrf_field')) {
	function csrf_field() {
		return app('csrf')->field();
	}
}
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

if (!function_exists('redirectTo')) {
	/**
	 * Redirige vers une URL avec message flash optionnel
	 * Alias simplifié de redirect()
	 */
	function redirectTo($url, $message = null) {
		if ($message) {
			$_SESSION['error_message'] = $message;
		}
		header("Location: {$url}");
		exit;
	}
}

if (!function_exists('setFlashMessage')) {
	/**
	 * Définit un message flash en session
	 */
	function setFlashMessage($type, $message) {
		$_SESSION[$type . '_message'] = $message;
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

// ==================== FONCTIONS UTILITAIRES COMMUNES ====================

if (!function_exists('formatDate')) {
	/**
	 * Formate une date au format français
	 * @param string $date La date au format SQL
	 * @param string $format Le format de sortie (défaut: d/m/Y)
	 * @return string La date formatée
	 */
	function formatDate($date, $format = 'd/m/Y') {
		if (empty($date)) return '';
		$timestamp = strtotime($date);
		return $timestamp ? date($format, $timestamp) : '';
	}
}

if (!function_exists('formatDateTime')) {
	/**
	 * Formate une date et heure au format français
	 * @param string $datetime La date et heure au format SQL
	 * @return string La date et heure formatée
	 */
	function formatDateTime($datetime) {
		if (empty($datetime)) return '';
		$timestamp = strtotime($datetime);
		return $timestamp ? date('d/m/Y à H:i', $timestamp) : '';
	}
}

if (!function_exists('getTrimestre')) {
	/**
	 * Détermine le trimestre scolaire actuel
	 * @return string Trimestre actuel
	 */
	function getTrimestre() {
		$mois = date('n');
		if ($mois >= 9 && $mois <= 12) return "1er trimestre";
		if ($mois >= 1 && $mois <= 3) return "2ème trimestre";
		if ($mois >= 4 && $mois <= 6) return "3ème trimestre";
		return "Période estivale";
	}
}

// ==================== ADMIN ====================

if (!function_exists('isAdminManagementAllowed')) {
	/**
	 * Vérifie si la gestion des comptes administrateurs est autorisée
	 * @return bool
	 */
	function isAdminManagementAllowed() {
		// Par défaut, seul un administrateur connecté peut gérer les admins
		return isLoggedIn() && getUserRole() === 'administrateur';
	}
}

if (!function_exists('validateStrongPassword')) {
	/**
	 * Valide la robustesse d'un mot de passe
	 * @param string $password Le mot de passe à valider
	 * @return array ['valid' => bool, 'errors' => string[]]
	 */
	function validateStrongPassword($password) {
		$errors = [];
		if (strlen($password) < 8) {
			$errors[] = "Le mot de passe doit contenir au moins 8 caractères";
		}
		if (!preg_match('/[A-Z]/', $password)) {
			$errors[] = "Le mot de passe doit contenir au moins une lettre majuscule";
		}
		if (!preg_match('/[a-z]/', $password)) {
			$errors[] = "Le mot de passe doit contenir au moins une lettre minuscule";
		}
		if (!preg_match('/[0-9]/', $password)) {
			$errors[] = "Le mot de passe doit contenir au moins un chiffre";
		}
		if (!preg_match('/[^A-Za-z0-9]/', $password)) {
			$errors[] = "Le mot de passe doit contenir au moins un caractère spécial";
		}
		return ['valid' => empty($errors), 'errors' => $errors];
	}
}

// ==================== AUTH HELPERS (déplacés depuis core.php) ====================

if (!function_exists('authenticateUser')) {
	function authenticateUser($username, $password, $userType, $rememberMe = false) {
		try {
			$auth = app('auth');
			$credentials = [
				'email' => $username,
				'password' => $password,
				'type' => $userType
			];
			if ($auth->attempt($credentials)) {
				$user = $auth->user();
				return ['success' => true, 'user' => $user, 'message' => 'Connexion réussie'];
			}
			return ['success' => false, 'message' => 'Identifiant ou mot de passe incorrect'];
		} catch (\Exception $e) {
			error_log("Authentication error: " . $e->getMessage());
			return ['success' => false, 'message' => 'Erreur lors de l\'authentification'];
		}
	}
}

if (!function_exists('logoutUser')) {
	function logoutUser() {
		app('auth')->logout();
		redirect('login/index.php');
	}
}

if (!function_exists('createUser')) {
	function createUser($profil, $userData) {
		try {
			$userService = app()->make('API\Services\UserService');
			return $userService->create($profil, $userData);
		} catch (\Exception $e) {
			error_log("User creation error: " . $e->getMessage());
			return ['success' => false, 'message' => 'Erreur lors de la création de l\'utilisateur'];
		}
	}
}

if (!function_exists('changePassword')) {
	function changePassword($userId, $newPassword) {
		try {
			$userService = app()->make('API\Services\UserService');
			return $userService->changePassword($userId, $newPassword);
		} catch (\Exception $e) {
			error_log("Password change error: " . $e->getMessage());
			return ['success' => false, 'message' => 'Erreur lors du changement de mot de passe'];
		}
	}
}

if (!function_exists('getEtablissementData')) {
	function getEtablissementData() {
		try {
			$etablissementService = app()->make('API\Services\EtablissementService');
			return $etablissementService->getData();
		} catch (\Exception $e) {
			error_log("Etablissement data error: " . $e->getMessage());
			return ['info' => null, 'classes' => [], 'matieres' => [], 'periodes' => []];
		}
	}
}

if (!function_exists('getEstablishmentId')) {
	/**
	 * Returns the current establishment ID from the context.
	 */
	function getEstablishmentId(): int {
		return \API\Core\EstablishmentContext::id();
	}
}

if (!function_exists('isSuperAdmin')) {
	/**
	 * Check if the current user is a super-admin.
	 */
	function isSuperAdmin(): bool {
		return \API\Services\SuperAdminService::isSuperAdmin();
	}
}

if (!function_exists('findUserByCredentials')) {
	function findUserByCredentials($username, $email, $phone, $userType) {
		try {
			$userService = app()->make('API\Services\UserService');
			return $userService->findByCredentials($username, $email, $phone, $userType);
		} catch (\Exception $e) {
			error_log("Find user error: " . $e->getMessage());
			return null;
		}
	}
}

if (!function_exists('createResetRequest')) {
	function createResetRequest($userId, $userType) {
		try {
			$userService = app()->make('API\Services\UserService');
			return $userService->createResetRequest($userId, $userType);
		} catch (\Exception $e) {
			error_log("Reset request error: " . $e->getMessage());
			return false;
		}
	}
}

if (!function_exists('validateUserData')) {
	function validateUserData() {
		if (!isset($_SESSION['user'])) {
			return false;
		}
		try {
			$userProvider = app('auth.provider');
			$user = $userProvider->retrieveById($_SESSION['user']['id'], $_SESSION['user']['type']);
			return $user !== null;
		} catch (\Exception $e) {
			error_log("User validation error: " . $e->getMessage());
			return false;
		}
	}
}

if (!function_exists('getErrorMessage')) {
	function getErrorMessage() {
		return $_SESSION['error_message'] ?? 'Une erreur est survenue';
	}
}

if (!function_exists('getDatabaseConnection')) {
	function getDatabaseConnection() {
		return app('db')->getConnection();
	}
}

if (!function_exists('validate')) {
	function validate($data, $rules) {
		$validator = app('validator');
		return $validator->validate($data, $rules);
	}
}

// ==================== FIN DU BRIDGE ====================
