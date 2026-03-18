<?php
/**
 * Installation de Fronote — Assistant étape par étape
 * Chaque étape doit être validée avant de passer à la suivante.
 *
 * Étape 1 : Pré-requis (PHP, extensions, répertoires, fichiers)
 * Étape 2 : Base de données (connexion testée en temps réel)
 * Étape 3 : Application (nom, env, sécurité)
 * Étape 4 : Administrateur (compte principal)
 * Étape 5 : Récapitulatif → exécution → résultat
 */

// ─── Configuration initiale ─────────────────────────────────────────────────
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(300);

// ─── Protection : déjà installé ? ───────────────────────────────────────────
$installDir = __DIR__;
$lockFile   = $installDir . '/install.lock';
if (file_exists($lockFile)) {
    http_response_code(403);
    die('<div style="font-family:Arial;max-width:600px;margin:80px auto;background:#f8d7da;color:#721c24;padding:30px;border-radius:8px;text-align:center;">
        <h2>🔒 Installation déjà effectuée</h2>
        <p>Supprimez <code>install.lock</code> pour relancer l\'installateur.</p>
    </div>');
}

// ─── Vérification version PHP ────────────────────────────────────────────────
if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    die('PHP 7.4+ requis. Version actuelle : ' . PHP_VERSION);
}

// ─── Session ─────────────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
}

// ─── Protection IP (réseau local uniquement) ─────────────────────────────────
$clientIP = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: '0.0.0.0';

function isLocalIP(string $ip): bool {
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return in_array($ip, ['::1'], true);
    }
    $ranges = [
        ['10.0.0.0', '10.255.255.255'],
        ['172.16.0.0', '172.31.255.255'],
        ['192.168.0.0', '192.168.255.255'],
        ['127.0.0.0', '127.255.255.255'],
    ];
    $long = ip2long($ip);
    foreach ($ranges as [$lo, $hi]) {
        if ($long >= ip2long($lo) && $long <= ip2long($hi)) return true;
    }
    return false;
}

$allowedIPs = ['127.0.0.1', '::1'];
$envFilePath = $installDir . '/.env';
if (file_exists($envFilePath) && is_readable($envFilePath)) {
    $envRaw = file_get_contents($envFilePath);
    if (preg_match('/ALLOWED_INSTALL_IP\s*=\s*(.+)/', $envRaw, $m)) {
        foreach (array_map('trim', explode(',', $m[1])) as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP)) $allowedIPs[] = $ip;
        }
    }
}

if (!in_array($clientIP, $allowedIPs, true) && !isLocalIP($clientIP)) {
    http_response_code(403);
    die('Accès refusé depuis : ' . htmlspecialchars($clientIP));
}

// ─── CSRF ────────────────────────────────────────────────────────────────────
if (empty($_SESSION['_csrf']) || (time() - ($_SESSION['_csrf_t'] ?? 0)) > 1800) {
    $_SESSION['_csrf']   = bin2hex(random_bytes(32));
    $_SESSION['_csrf_t'] = time();
}
$csrfToken = $_SESSION['_csrf'];

function checkCsrf(): void {
    if (($_POST['_csrf'] ?? '') !== ($_SESSION['_csrf'] ?? '')) {
        throw new RuntimeException('Jeton CSRF invalide — rechargez la page.');
    }
}

// ─── Détection auto des URLs ─────────────────────────────────────────────────
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
if ($basePath === '/.') $basePath = '';
$fullUrl = $protocol . '://' . $host . $basePath;

// ─── Données d'installation en session ───────────────────────────────────────
// Réinitialisation automatique : si pas de .env ni de install.lock,
// c'est un environnement vierge → purger toute session d'installation précédente.
// Permet aussi un reset manuel via ?reset dans l'URL.
$envExists  = file_exists($installDir . '/.env') && filesize($installDir . '/.env') > 10;
$forceReset = isset($_GET['reset']);
// Ne réinitialiser que si l'installation précédente était TERMINÉE
// (pas pendant un flux en cours où .env n'existe pas encore)
$previouslyCompleted = !empty($_SESSION['install']['installed']);

if ($forceReset || (!$envExists && $previouslyCompleted)) {
    unset($_SESSION['install']);
}

if (!isset($_SESSION['install'])) {
    $_SESSION['install'] = ['step' => 1];
}
$inst = &$_SESSION['install'];

// ─── Vérification d'intégrité de la session ──────────────────────────────────
// Si l'étape avancée requiert des données manquantes, on rétrograde.
function getMaxValidStep(array $inst): int {
    // Étape 1 → toujours accessible
    if (empty($inst['db']))    return 1; // pas de données DB → max étape 1
    if (empty($inst['app']))   return 2; // pas de config app → max étape 2
    if (empty($inst['admin'])) return 3; // pas d'admin → max étape 3
    return 5; // tout est renseigné → peut aller jusqu'à 5
}
$maxValid = getMaxValidStep($inst);
if ($inst['step'] > $maxValid + 1) {
    $inst['step'] = $maxValid + 1;
}

// ─── Navigation : on peut reculer mais jamais sauter en avant ────────────────
$requestedStep = (int)($_GET['step'] ?? $_POST['step'] ?? $inst['step']);
if ($requestedStep < 1) $requestedStep = 1;
if ($requestedStep > 5) $requestedStep = 5;
if ($requestedStep > $inst['step']) $requestedStep = $inst['step'];

$currentStep = $requestedStep;
$error   = '';
$success = '';

// ═══════════════════════════════════════════════════════════════════════════════
// FONCTIONS UTILITAIRES
// ═══════════════════════════════════════════════════════════════════════════════

function validatePasswordStrength(string $pw): array {
    $e = [];
    if (strlen($pw) < 12)                  $e[] = 'Au moins 12 caractères';
    if (!preg_match('/[A-Z]/', $pw))        $e[] = 'Au moins une majuscule';
    if (!preg_match('/[a-z]/', $pw))        $e[] = 'Au moins une minuscule';
    if (!preg_match('/[0-9]/', $pw))        $e[] = 'Au moins un chiffre';
    if (!preg_match('/[^A-Za-z0-9]/', $pw)) $e[] = 'Au moins un caractère spécial';
    return $e;
}

function ensureDir(string $path): array {
    if (!is_dir($path) && !@mkdir($path, 0755, true)) {
        return ['ok' => false, 'msg' => 'Impossible de créer'];
    }
    $t = $path . '/.probe_' . uniqid();
    if (@file_put_contents($t, 'x') === false) {
        return ['ok' => false, 'msg' => 'Non inscriptible'];
    }
    @unlink($t);
    return ['ok' => true, 'msg' => 'OK'];
}

function writeEnvFile(string $dir, array $c): bool {
    $dbHost = (strtolower($c['db_host']) === 'localhost') ? '127.0.0.1' : $c['db_host'];
    $lines = [
        "# Fronote — généré le " . date('Y-m-d H:i:s'),
        "# NE PAS PARTAGER CE FICHIER", "",
        "# Base de données",
        "DB_HOST={$dbHost}",
        "DB_PORT={$c['db_port']}",
        "DB_NAME={$c['db_name']}",
        "DB_USER={$c['db_user']}",
        "DB_PASS={$c['db_pass']}",
        "DB_CHARSET={$c['db_charset']}", "",
        "# Application",
        "APP_NAME={$c['app_name']}",
        "APP_ENV={$c['app_env']}",
        "APP_DEBUG=" . ($c['app_debug'] ? 'true' : 'false'),
        "APP_URL={$c['app_url']}",
        "APP_BASE_PATH={$dir}",
        "BASE_URL={$c['base_url']}", "",
        "# Sécurité",
        "CSRF_LIFETIME={$c['csrf_lifetime']}",
        "CSRF_MAX_TOKENS=10",
        "SESSION_NAME={$c['session_name']}",
        "SESSION_LIFETIME={$c['session_lifetime']}",
        "SESSION_SECURE=" . ($c['protocol'] === 'https' ? 'true' : 'false'),
        "SESSION_HTTPONLY=true",
        "SESSION_SAMESITE=Lax",
        "MAX_LOGIN_ATTEMPTS={$c['max_login_attempts']}",
        "LOGIN_LOCKOUT_TIME=900",
        "RATE_LIMIT_ATTEMPTS={$c['rate_limit_attempts']}",
        "RATE_LIMIT_DECAY={$c['rate_limit_decay']}", "",
        "# Chemins",
        "LOGS_PATH={$dir}/API/logs",
        "UPLOADS_PATH={$dir}/uploads",
        "TEMP_PATH={$dir}/temp", "",
        "# Mail (à configurer)",
        "MAIL_MAILER=smtp", "MAIL_HOST=", "MAIL_PORT=587",
        "MAIL_USERNAME=", "MAIL_PASSWORD=", "MAIL_ENCRYPTION=tls",
        "MAIL_FROM_ADDRESS={$c['admin_mail']}",
        "MAIL_FROM_NAME={$c['app_name']}", "",
        "# Divers",
        "APP_TIMEZONE=Europe/Paris",
        "ALLOWED_INSTALL_IP={$c['client_ip']}",
        "JWT_SECRET=" . bin2hex(random_bytes(32)),
    ];
    return @file_put_contents($dir . '/.env', implode("\n", $lines), LOCK_EX) !== false;
}

// ═══════════════════════════════════════════════════════════════════════════════
// TRAITEMENT POST (chaque étape valide indépendamment)
// ═══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        checkCsrf();
        $postStep = (int)($_POST['step'] ?? 0);

        // ── Étape 1 → 2 : validation des pré-requis ─────────────────────────
        if ($postStep === 1) {
            $exts = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'session'];
            foreach ($exts as $e) {
                if (!extension_loaded($e)) throw new RuntimeException("Extension PHP manquante : {$e}");
            }
            $dirs = ['API/logs', 'API/config', 'uploads', 'uploads/messagerie', 'uploads/devoirs', 'uploads/justificatifs', 'temp'];
            foreach ($dirs as $d) {
                $r = ensureDir($installDir . '/' . $d);
                if (!$r['ok']) throw new RuntimeException("Répertoire {$d} : {$r['msg']}");
            }
            $files = ['API/bootstrap.php', 'API/core.php', 'pronote.sql'];
            foreach ($files as $f) {
                if (!file_exists($installDir . '/' . $f)) {
                    throw new RuntimeException("Fichier requis manquant : {$f}");
                }
            }
            $inst['step'] = 2;
            $currentStep  = 2;
        }

        // ── Étape 2 : test de connexion MySQL ────────────────────────────────
        elseif ($postStep === 2) {
            $dbHost    = trim($_POST['db_host'] ?? 'localhost');
            $dbPort    = (int)($_POST['db_port'] ?? 3306);
            $dbUser    = trim($_POST['db_user'] ?? '');
            $dbPass    = $_POST['db_pass'] ?? '';
            $dbName    = trim($_POST['db_name'] ?? '');
            $dbCharset = trim($_POST['db_charset'] ?? 'utf8mb4');

            if ($dbUser === '') throw new RuntimeException("L'utilisateur MySQL est requis.");
            if ($dbName === '') throw new RuntimeException("Le nom de la base de données est requis.");

            // Forcer TCP/IP (localhost → 127.0.0.1)
            $dbHostTcp = (strtolower($dbHost) === 'localhost') ? '127.0.0.1' : $dbHost;
            $serverIP  = $_SERVER['SERVER_ADDR'] ?? gethostbyname(gethostname());

            try {
                $dsn = "mysql:host={$dbHostTcp};port={$dbPort};charset={$dbCharset}";
                $pdo = new PDO($dsn, $dbUser, $dbPass, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE  => PDO::FETCH_ASSOC,
                    PDO::ATTR_TIMEOUT             => 5,
                ]);
                $pdo->query('SELECT 1');
            } catch (PDOException $e) {
                $msg = $e->getMessage();
                $hint = '';
                if (strpos($msg, '1045') !== false || stripos($msg, 'Access denied') !== false) {
                    $seen = $serverIP;
                    if (preg_match("/'[^']*'@'([^']+)'/", $msg, $m)) $seen = $m[1];
                    $hint = "\n\nL'utilisateur MySQL n'a pas le droit de se connecter depuis le serveur web ({$seen})."
                          . "\n→ Sur le serveur MySQL, exécutez :"
                          . "\n  CREATE USER IF NOT EXISTS '{$dbUser}'@'{$seen}' IDENTIFIED BY 'votre_mot_de_passe';"
                          . "\n  GRANT ALL PRIVILEGES ON *.* TO '{$dbUser}'@'{$seen}';"
                          . "\n  FLUSH PRIVILEGES;"
                          . "\n\nℹ️ Navigateur : {$clientIP} — Serveur web : {$serverIP}";
                } elseif (strpos($msg, '2002') !== false || strpos($msg, '2006') !== false) {
                    $hint = "\n\nServeur MySQL injoignable sur {$dbHost}:{$dbPort}."
                          . "\nVérifiez que le service est démarré et que le pare-feu autorise le port {$dbPort}.";
                }
                throw new RuntimeException("Connexion MySQL échouée : {$msg}{$hint}");
            }
            $pdo = null; // fermer la connexion de test

            $inst['db'] = compact('dbHost', 'dbPort', 'dbUser', 'dbPass', 'dbName', 'dbCharset');
            $inst['step'] = 3;
            $currentStep  = 3;
            $success = '✅ Connexion MySQL vérifiée avec succès';
        }

        // ── Étape 3 : configuration application + établissement ────────────
        elseif ($postStep === 3) {
            $appName           = trim($_POST['app_name'] ?? 'Fronote');
            $appEnv            = $_POST['app_env'] ?? 'production';
            $appDebug          = !empty($_POST['app_debug']);
            $appUrl            = trim($_POST['app_url'] ?? $fullUrl);
            $baseUrlIn         = trim($_POST['base_url'] ?? $basePath);
            $csrfLifetime      = max(300,  (int)($_POST['csrf_lifetime'] ?? 3600));
            $sessionLifetime   = max(600,  (int)($_POST['session_lifetime'] ?? 7200));
            $sessionName       = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['session_name'] ?? 'pronote_session') ?: 'pronote_session';
            $maxLoginAttempts  = max(3,  (int)($_POST['max_login_attempts'] ?? 5));
            $rateLimitAttempts = max(3,  (int)($_POST['rate_limit_attempts'] ?? 5));
            $rateLimitDecay    = max(1,  (int)($_POST['rate_limit_decay'] ?? 1));

            if (!in_array($appEnv, ['production', 'development', 'test'], true)) {
                throw new RuntimeException('Environnement invalide.');
            }

            // Établissement
            $etabNom       = trim($_POST['etab_nom'] ?? '');
            $etabAdresse   = trim($_POST['etab_adresse'] ?? '');
            $etabCp        = trim($_POST['etab_cp'] ?? '');
            $etabVille     = trim($_POST['etab_ville'] ?? '');
            $etabTel       = trim($_POST['etab_tel'] ?? '');
            $etabEmail     = trim($_POST['etab_email'] ?? '');
            $etabAcademie  = trim($_POST['etab_academie'] ?? '');
            $etabType      = $_POST['etab_type'] ?? 'college';

            if ($etabNom === '') throw new RuntimeException("Le nom de l'établissement est requis.");

            // Périodes
            $periodeSystem = $_POST['periode_system'] ?? 'trimestre';
            $periodes = [];
            if ($periodeSystem === 'trimestre') {
                $periodes = [
                    ['nom' => '1er trimestre',  'debut' => $_POST['p1_debut'] ?? '', 'fin' => $_POST['p1_fin'] ?? ''],
                    ['nom' => '2ème trimestre', 'debut' => $_POST['p2_debut'] ?? '', 'fin' => $_POST['p2_fin'] ?? ''],
                    ['nom' => '3ème trimestre', 'debut' => $_POST['p3_debut'] ?? '', 'fin' => $_POST['p3_fin'] ?? ''],
                ];
            } else {
                $periodes = [
                    ['nom' => '1er semestre',  'debut' => $_POST['s1_debut'] ?? '', 'fin' => $_POST['s1_fin'] ?? ''],
                    ['nom' => '2ème semestre', 'debut' => $_POST['s2_debut'] ?? '', 'fin' => $_POST['s2_fin'] ?? ''],
                ];
            }

            $inst['app'] = compact(
                'appName', 'appEnv', 'appDebug', 'appUrl', 'baseUrlIn',
                'csrfLifetime', 'sessionLifetime', 'sessionName',
                'maxLoginAttempts', 'rateLimitAttempts', 'rateLimitDecay'
            );
            $inst['etab'] = compact(
                'etabNom', 'etabAdresse', 'etabCp', 'etabVille', 'etabTel',
                'etabEmail', 'etabAcademie', 'etabType', 'periodeSystem', 'periodes'
            );

            // SMTP (optionnel — peut être configuré après l'installation)
            $smtpEnabled = !empty($_POST['smtp_enabled']);
            $inst['smtp'] = [
                'enabled'      => $smtpEnabled,
                'host'         => trim($_POST['smtp_host'] ?? ''),
                'port'         => (int)($_POST['smtp_port'] ?? 587),
                'username'     => trim($_POST['smtp_username'] ?? ''),
                'password'     => $_POST['smtp_password'] ?? '',
                'encryption'   => $_POST['smtp_encryption'] ?? 'tls',
                'from_address' => trim($_POST['smtp_from_address'] ?? $etabEmail),
                'from_name'    => trim($_POST['smtp_from_name'] ?? $appName),
            ];

            $inst['step'] = 4;
            $currentStep  = 4;
        }

        // ── Étape 4 : compte administrateur ──────────────────────────────────
        elseif ($postStep === 4) {
            $nom    = trim($_POST['admin_nom'] ?? '');
            $prenom = trim($_POST['admin_prenom'] ?? '');
            $mail   = trim($_POST['admin_mail'] ?? '');
            $pw     = $_POST['admin_password'] ?? '';

            if ($nom === '' || $prenom === '' || $mail === '' || $pw === '') {
                throw new RuntimeException('Tous les champs sont requis.');
            }
            if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Adresse email invalide.');
            }
            $pwErrors = validatePasswordStrength($pw);
            if (!empty($pwErrors)) {
                throw new RuntimeException("Mot de passe non conforme :\n• " . implode("\n• ", $pwErrors));
            }

            $inst['admin'] = compact('nom', 'prenom', 'mail', 'pw');
            $inst['step'] = 5;
            $currentStep  = 5;
        }

        // ── Étape 5 : exécution complète ─────────────────────────────────────
        elseif ($postStep === 5) {
            if (empty($inst['db']) || empty($inst['app']) || empty($inst['admin'])) {
                $inst['step'] = 1;
                throw new RuntimeException("Données d'installation incomplètes — la session a expiré.\nL'installateur a été réinitialisé, veuillez recommencer depuis l'étape 1.");
            }
            $db  = $inst['db'];
            $ap  = $inst['app'];
            $adm = $inst['admin'];
            $log = [];

            // 5a — Fichier .env
            $envOk = writeEnvFile($installDir, [
                'db_host'      => $db['dbHost'],  'db_port'  => $db['dbPort'],
                'db_name'      => $db['dbName'],  'db_user'  => $db['dbUser'],
                'db_pass'      => $db['dbPass'],  'db_charset' => $db['dbCharset'],
                'app_name'     => $ap['appName'], 'app_env'  => $ap['appEnv'],
                'app_debug'    => $ap['appDebug'],'app_url'  => $ap['appUrl'],
                'base_url'     => $ap['baseUrlIn'],
                'csrf_lifetime'      => $ap['csrfLifetime'],
                'session_name'       => $ap['sessionName'],
                'session_lifetime'   => $ap['sessionLifetime'],
                'max_login_attempts' => $ap['maxLoginAttempts'],
                'rate_limit_attempts'=> $ap['rateLimitAttempts'],
                'rate_limit_decay'   => $ap['rateLimitDecay'],
                'admin_mail' => $adm['mail'], 'protocol' => $protocol,
                'client_ip'  => $clientIP,
            ]);
            if (!$envOk) throw new RuntimeException("Impossible d'écrire le fichier .env — vérifiez les permissions du répertoire.");
            $log[] = ['ok', 'Fichier .env créé'];

            // 5b — Fichiers de protection
            $protections = [
                '.htaccess' => implode("\n", [
                    '<FilesMatch "^(\\.env|install\\.php|install\\.lock)$">',
                    '  Order allow,deny', '  Deny from all', '</FilesMatch>',
                    '<FilesMatch "\\.(ini|conf|bak|sql|db)$">',
                    '  Order allow,deny', '  Deny from all', '</FilesMatch>',
                    'Options -Indexes',
                ]),
                'uploads/.htaccess'  => 'Deny from all',
                'temp/.htaccess'     => 'Deny from all',
                'API/logs/.htaccess' => 'Deny from all',
            ];
            foreach ($protections as $file => $content) {
                $dir = dirname($installDir . '/' . $file);
                if (!is_dir($dir)) @mkdir($dir, 0755, true);
                @file_put_contents($installDir . '/' . $file, $content, LOCK_EX);
            }
            $log[] = ['ok', 'Fichiers de protection créés (.htaccess)'];

            // 5b-bis — Répertoires uploads par contexte
            foreach (['messagerie', 'devoirs', 'justificatifs'] as $ctx) {
                $ctxDir = $installDir . '/uploads/' . $ctx;
                if (!is_dir($ctxDir)) @mkdir($ctxDir, 0755, true);
            }
            $log[] = ['ok', 'Répertoires uploads créés (messagerie, devoirs, justificatifs)'];

            // 5c — Connexion MySQL
            $dbHostTcp = (strtolower($db['dbHost']) === 'localhost') ? '127.0.0.1' : $db['dbHost'];
            $dsn = "mysql:host={$dbHostTcp};port={$db['dbPort']};charset=utf8mb4";
            try {
                $pdo = new PDO($dsn, $db['dbUser'], $db['dbPass'], [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE  => PDO::FETCH_ASSOC,
                    PDO::ATTR_TIMEOUT             => 10,
                ]);
            } catch (PDOException $e) {
                throw new RuntimeException("Connexion MySQL échouée à l'exécution : " . $e->getMessage()
                    . "\n\nRetournez à l'étape 2 pour vérifier vos identifiants.");
            }
            $log[] = ['ok', 'Connexion MySQL établie'];

            // 5d — Créer / recréer la base
            $dbNameSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $db['dbName']);
            $stmt = $pdo->prepare("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?");
            $stmt->execute([$db['dbName']]);
            if ($stmt->fetch()) {
                $pdo->exec("DROP DATABASE `{$dbNameSafe}`");
                $log[] = ['ok', 'Ancienne base supprimée'];
            }
            $pdo->exec("CREATE DATABASE `{$dbNameSafe}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$dbNameSafe}`");
            $log[] = ['ok', "Base de données <code>{$dbNameSafe}</code> créée"];

            // 5e — Importer pronote.sql
            $sqlFile = $installDir . '/pronote.sql';
            if (!file_exists($sqlFile)) throw new RuntimeException('Fichier pronote.sql introuvable.');
            $sql = file_get_contents($sqlFile);
            if ($sql === false) throw new RuntimeException('Impossible de lire pronote.sql.');

            $tableCount = 0;
            $errors = [];
            foreach (explode(';', $sql) as $q) {
                $q = trim($q);
                if ($q === '') continue;
                // Ignorer CREATE DATABASE / USE (on le gère nous-mêmes)
                if (preg_match('/^\s*(CREATE\s+DATABASE|USE\s+)/i', $q)) continue;
                try {
                    $pdo->exec($q);
                    if (stripos($q, 'CREATE TABLE') !== false) $tableCount++;
                } catch (PDOException $e) {
                    $errors[] = $e->getMessage();
                }
            }
            if ($tableCount === 0 && !empty($errors)) {
                throw new RuntimeException("Aucune table créée. Première erreur SQL : " . $errors[0]);
            }
            $log[] = ['ok', "Structure importée ({$tableCount} tables créées)"];
            if (!empty($errors)) {
                $log[] = ['warn', count($errors) . " requête(s) SQL en erreur (non bloquant)"];
            }

            // 5e-bis — Exécuter les migrations incrémentales
            try {
                require_once $installDir . '/API/Database/Migrator.php';
                $migrator = new \API\Database\Migrator($pdo, $installDir . '/migrations');
                $migrated = $migrator->run();
                if ($migrated > 0) {
                    $log[] = ['ok', "$migrated migration(s) exécutée(s)"];
                }
            } catch (Throwable $migErr) {
                $log[] = ['warn', 'Migrations : ' . $migErr->getMessage()];
            }

            // 5f — Compte administrateur
            $hash = password_hash($adm['pw'], PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $pdo->prepare(
                "INSERT INTO administrateurs (nom, prenom, mail, identifiant, mot_de_passe, role, actif)
                 VALUES (?, ?, ?, 'admin', ?, 'administrateur', 1)"
            );
            $stmt->execute([$adm['nom'], $adm['prenom'], $adm['mail'], $hash]);
            $adminId = $pdo->lastInsertId();
            $log[] = ['ok', "Administrateur créé (ID {$adminId}) — identifiant : <strong>admin</strong>"];

            // 5f-bis — Établissement en BDD (table etablissement_info)
            $etab = $inst['etab'] ?? [];
            if (!empty($etab)) {
                $type = $etab['etabType'] ?? 'college';

                // Mettre à jour la ligne par défaut (id=1) créée par pronote.sql
                try {
                    $stmt = $pdo->prepare("
                        UPDATE etablissement_info SET
                            nom = ?, adresse = ?, code_postal = ?, ville = ?,
                            telephone = ?, email = ?, academie = ?, type = ?
                        WHERE id = 1
                    ");
                    $stmt->execute([
                        $etab['etabNom'], $etab['etabAdresse'], $etab['etabCp'],
                        $etab['etabVille'], $etab['etabTel'], $etab['etabEmail'],
                        $etab['etabAcademie'], $type,
                    ]);
                    $log[] = ['ok', 'Établissement configuré en BDD — <strong>' . htmlspecialchars($etab['etabNom']) . '</strong>'];
                } catch (PDOException $e) {
                    $log[] = ['warn', 'Établissement BDD : ' . $e->getMessage()];
                }

                // Périodes scolaires
                try {
                    $periodeType = $etab['periodeSystem'] ?? 'trimestre';
                    $periodesData = $etab['periodes'] ?? [];
                    $stmtP = $pdo->prepare("INSERT INTO periodes (numero, nom, type, date_debut, date_fin) VALUES (?, ?, ?, ?, ?)");
                    foreach ($periodesData as $idx => $p) {
                        if (!empty($p['debut']) && !empty($p['fin'])) {
                            $stmtP->execute([$idx + 1, $p['nom'], $periodeType, $p['debut'], $p['fin']]);
                        }
                    }
                    $log[] = ['ok', 'Périodes scolaires configurées (' . count($periodesData) . ' ' . $periodeType . 's)'];
                } catch (PDOException $e) {
                    $log[] = ['warn', 'Périodes : ' . $e->getMessage()];
                }

                // Classes par défaut selon le type
                $defaultClasses = [];
                if (in_array($type, ['primaire', 'tout'])) {
                    $defaultClasses += ['CP' => 'CPA,CPB', 'CE1' => 'CE1A,CE1B', 'CE2' => 'CE2A,CE2B', 'CM1' => 'CM1A,CM1B', 'CM2' => 'CM2A,CM2B'];
                }
                if (in_array($type, ['college', 'tout'])) {
                    $defaultClasses += ['6eme' => '6A,6B,6C', '5eme' => '5A,5B,5C', '4eme' => '4A,4B,4C', '3eme' => '3A,3B,3C'];
                }
                if (in_array($type, ['lycee', 'tout'])) {
                    $defaultClasses += ['2nde' => '2A,2B,2C', '1ere' => '1A,1B,1C', 'Tle' => 'TA,TB,TC'];
                }
                try {
                    $stmtC = $pdo->prepare("INSERT INTO classes (niveau, nom) VALUES (?, ?)");
                    $classCount = 0;
                    foreach ($defaultClasses as $niveau => $noms) {
                        foreach (explode(',', $noms) as $nom) {
                            $stmtC->execute([$niveau, trim($nom)]);
                            $classCount++;
                        }
                    }
                    $log[] = ['ok', "{$classCount} classes créées par défaut"];
                } catch (PDOException $e) {
                    $log[] = ['warn', 'Classes : ' . $e->getMessage()];
                }

                // Matières par défaut
                $defaultMatieres = [
                    ['FRAN','Français'], ['MATH','Mathématiques'], ['HG','Histoire-Géographie'],
                    ['ANG','Anglais'], ['ESP','Espagnol'], ['PC','Physique-Chimie'],
                    ['SVT','SVT'], ['EPS','EPS'], ['ART','Arts plastiques'],
                    ['MUS','Musique'], ['TECH','Technologie'],
                ];
                try {
                    $stmtM = $pdo->prepare("INSERT INTO matieres (code, nom) VALUES (?, ?)");
                    foreach ($defaultMatieres as [$code, $nom]) {
                        $stmtM->execute([$code, $nom]);
                    }
                    $log[] = ['ok', count($defaultMatieres) . ' matières créées par défaut'];
                } catch (PDOException $e) {
                    $log[] = ['warn', 'Matières : ' . $e->getMessage()];
                }

                // Écrire aussi le JSON pour rétrocompatibilité
                $etabJson = [
                    'nom' => $etab['etabNom'], 'adresse' => $etab['etabAdresse'],
                    'code_postal' => $etab['etabCp'], 'ville' => $etab['etabVille'],
                    'telephone' => $etab['etabTel'], 'email' => $etab['etabEmail'],
                    'academie' => $etab['etabAcademie'], 'type' => $type,
                ];
                $jsonDir = $installDir . '/login/data';
                if (!is_dir($jsonDir)) @mkdir($jsonDir, 0755, true);
                @file_put_contents($jsonDir . '/etablissement.json', json_encode($etabJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
            }

            // 5f-ter — Configuration SMTP
            $smtp = $inst['smtp'] ?? [];
            if (!empty($smtp['enabled']) && !empty($smtp['host'])) {
                try {
                    $stmt = $pdo->prepare("
                        UPDATE smtp_config SET
                            host = ?, port = ?, username = ?, password = ?,
                            encryption = ?, from_address = ?, from_name = ?, enabled = 1
                        WHERE id = 1
                    ");
                    $stmt->execute([
                        $smtp['host'], (int)$smtp['port'], $smtp['username'], $smtp['password'],
                        $smtp['encryption'], $smtp['from_address'], $smtp['from_name'],
                    ]);
                    $log[] = ['ok', 'Configuration SMTP enregistrée — <strong>' . htmlspecialchars($smtp['host']) . ':' . $smtp['port'] . '</strong>'];
                } catch (PDOException $e) {
                    $log[] = ['warn', 'SMTP : ' . $e->getMessage()];
                }
            } else {
                $log[] = ['warn', 'SMTP non configuré — les emails ne seront pas envoyés (configurable depuis l\'administration)'];
            }

            // 5g — Bootstrap API
            $bootstrapFile = $installDir . '/API/bootstrap.php';
            $apiOk = false;
            if (file_exists($bootstrapFile)) {
                try {
                    $appInstance = require $bootstrapFile;
                    $apiOk = true;
                    $log[] = ['ok', 'API bootstrap chargée'];

                    // Test authentification
                    try {
                        $auth = $appInstance->make('auth');
                        if ($auth->attempt(['login' => 'admin', 'password' => $adm['pw'], 'type' => 'administrateur'])) {
                            $auth->logout();
                            $log[] = ['ok', 'Test d\'authentification réussi'];
                        } else {
                            $log[] = ['warn', 'Test auth échoué (non bloquant)'];
                        }
                    } catch (Throwable $e) {
                        $log[] = ['warn', 'Test auth : ' . $e->getMessage()];
                    }

                    // Test rate limiter
                    try {
                        $lim = $appInstance->make('rate_limiter');
                        $k = 'install_probe_' . uniqid();
                        $lim->hit($k); $lim->clear($k);
                        $log[] = ['ok', 'RateLimiter opérationnel'];
                    } catch (Throwable $e) {
                        $log[] = ['warn', 'RateLimiter : ' . $e->getMessage()];
                    }

                    // Test CSRF
                    try {
                        $csrf = $appInstance->make('csrf');
                        $tok  = $csrf->generate();
                        if ($csrf->validate($tok)) $log[] = ['ok', 'Protection CSRF opérationnelle'];
                    } catch (Throwable $e) {
                        $log[] = ['warn', 'CSRF : ' . $e->getMessage()];
                    }

                } catch (Throwable $e) {
                    $log[] = ['warn', 'Bootstrap API : ' . $e->getMessage()];
                }
            } else {
                $log[] = ['warn', 'API/bootstrap.php introuvable'];
            }

            // 5h — Audit log
            try {
                $pdo->exec("USE `{$dbNameSafe}`");
                $stmt = $pdo->prepare(
                    "INSERT INTO audit_log (action, model, user_id, user_type, new_values, ip_address, user_agent, created_at)
                     VALUES ('system.installed', 'system', ?, 'administrateur', ?, ?, ?, NOW())"
                );
                $stmt->execute([
                    $adminId,
                    json_encode(['version' => '1.0.0', 'php' => PHP_VERSION, 'date' => date('c')]),
                    $clientIP,
                    $_SERVER['HTTP_USER_AGENT'] ?? '',
                ]);
                $log[] = ['ok', 'Événement d\'installation enregistré dans l\'audit'];
            } catch (Throwable $e) {
                $log[] = ['warn', 'Audit : ' . $e->getMessage()];
            }

            // 5i — Fichier install.lock
            file_put_contents($lockFile, json_encode([
                'installed_at' => date('c'),
                'version'      => '1.0.0',
                'php'          => PHP_VERSION,
            ], JSON_PRETTY_PRINT), LOCK_EX);
            $log[] = ['ok', 'Fichier install.lock créé'];

            // 5j — Sécuriser .env
            @chmod($installDir . '/.env', $ap['appEnv'] === 'production' ? 0400 : 0600);

            $inst['log']       = $log;
            $inst['installed'] = true;
            $inst['step']      = 6;
            $currentStep = 5;
        }

    } catch (Throwable $e) {
        $error = $e->getMessage();
        $currentStep = isset($postStep) ? ($postStep ?: $currentStep) : $currentStep;
    }
}

// Raccourcis pour le rendu
$installed = !empty($inst['installed']);
$stepMax   = $inst['step'];
$steps     = [
    1 => 'Pré-requis',
    2 => 'Base de données',
    3 => 'Application',
    4 => 'Administrateur',
    5 => 'Installation',
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Installation Fronote</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{--primary:#667eea;--primary-dark:#5a67d8;--success:#48bb78;--warning:#ed8936;--danger:#e53e3e;--bg:#f7fafc;--text:#2d3748;--border:#e2e8f0;--card:#fff;--radius:8px}
body{font-family:'Segoe UI',system-ui,-apple-system,sans-serif;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);min-height:100vh;padding:20px;color:var(--text)}

.container{max-width:860px;margin:0 auto}
.card{background:var(--card);border-radius:12px;box-shadow:0 10px 40px rgba(0,0,0,.15);overflow:hidden}

/* Header */
.header{background:#2d3748;color:#fff;padding:28px 32px;text-align:center}
.header h1{font-size:1.6em;margin-bottom:4px}
.header p{opacity:.7;font-size:.95em}

/* Stepper */
.stepper{display:flex;padding:16px 24px;background:#f8fafc;border-bottom:1px solid var(--border);gap:4px}
.step-item{flex:1;text-align:center;padding:8px 4px;font-size:.78em;font-weight:600;color:#a0aec0;position:relative}
.step-num{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:50%;border:2px solid #cbd5e0;margin-bottom:4px;font-size:.85em;transition:.2s}
.step-item.active{color:var(--primary)}
.step-item.active .step-num{border-color:var(--primary);background:var(--primary);color:#fff}
.step-item.done{color:var(--success)}
.step-item.done .step-num{border-color:var(--success);background:var(--success);color:#fff}

/* Body */
.body{padding:32px}
.section-title{font-size:1.2em;font-weight:700;margin-bottom:6px}
.section-sub{font-size:.9em;color:#718096;margin-bottom:24px}

/* Messages */
.msg{padding:14px 18px;border-radius:var(--radius);margin-bottom:20px;font-size:.92em;line-height:1.6;white-space:pre-wrap;word-break:break-word}
.msg-error{background:#fff5f5;border:1px solid #feb2b2;color:#c53030}
.msg-success{background:#f0fff4;border:1px solid #9ae6b4;color:#276749}
.msg-warn{background:#fffff0;border:1px solid #fefcbf;color:#975a16}

/* Form */
.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
@media(max-width:640px){.grid{grid-template-columns:1fr}}
.form-group{margin-bottom:18px}
.form-group label{display:block;font-size:.88em;font-weight:600;margin-bottom:5px;color:#4a5568}
.form-group input,.form-group select{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:6px;font-size:.92em;transition:.2s}
.form-group input:focus,.form-group select:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px rgba(102,126,234,.15)}
.help{font-size:.78em;color:#a0aec0;margin-top:3px}

/* Buttons */
.btn{display:inline-flex;align-items:center;gap:6px;padding:12px 24px;border:none;border-radius:6px;font-size:.95em;font-weight:600;cursor:pointer;transition:.15s;text-decoration:none}
.btn-primary{background:var(--primary);color:#fff}
.btn-primary:hover{background:var(--primary-dark)}
.btn-primary:disabled{background:#a0aec0;cursor:not-allowed}
.btn-secondary{background:#edf2f7;color:#4a5568}
.btn-secondary:hover{background:#e2e8f0}
.btn-success{background:var(--success);color:#fff}
.btn-success:hover{background:#38a169}
.actions{display:flex;justify-content:space-between;margin-top:28px;gap:12px}

/* Check list */
.check-list{list-style:none;padding:0;margin:20px 0}
.check-list li{padding:10px 14px;border-bottom:1px solid #f0f0f0;display:flex;align-items:center;gap:10px;font-size:.9em}
.check-list li:last-child{border:none}
.badge{display:inline-block;padding:2px 10px;border-radius:10px;font-size:.75em;font-weight:700;min-width:48px;text-align:center}
.badge-ok{background:#c6f6d5;color:#276749}
.badge-fail{background:#fed7d7;color:#9b2c2c}

/* Log */
.log-list{list-style:none;padding:0;margin:16px 0}
.log-list li{padding:10px 14px;border-left:4px solid;margin-bottom:2px;font-size:.9em;background:#fafafa;border-radius:0 4px 4px 0}
.log-list li.log-ok{border-color:var(--success)}
.log-list li.log-warn{border-color:var(--warning)}

/* Password */
.pw-wrap{position:relative}
.pw-toggle{position:absolute;right:10px;top:31px;background:none;border:none;cursor:pointer;font-size:1.1em;padding:4px}
.pw-bar-track{height:4px;background:#edf2f7;border-radius:2px;margin-top:6px;overflow:hidden}
.pw-bar{height:100%;width:0;transition:.3s;border-radius:2px}
.pw-reqs{background:#f7fafc;padding:10px 14px;border-radius:6px;margin-top:8px;font-size:.82em}
.pw-req{padding:2px 0}
.pw-req.ok{color:var(--success)}
.pw-req.fail{color:var(--danger)}
.pw-gen{background:#edf2f7;border:none;padding:7px 16px;border-radius:4px;cursor:pointer;font-size:.85em;margin-top:6px}
.pw-gen:hover{background:#e2e8f0}

/* Result */
.result-card{background:#f0fff4;border:2px solid #9ae6b4;border-radius:12px;padding:28px;text-align:center;margin-bottom:24px}
.result-card h2{color:#276749;margin-bottom:8px;font-size:1.4em}
.info-box{background:#f7fafc;border:1px solid var(--border);border-radius:var(--radius);padding:16px 18px;text-align:left;margin:16px 0}
.info-box h4{margin-bottom:8px;color:#2d3748;font-size:.95em}
code{background:#edf2f7;padding:1px 6px;border-radius:3px;font-size:.88em;font-family:'Cascadia Code','Fira Code',Consolas,monospace}
</style>
</head>
<body>
<div class="container">
<div class="card">

<!-- Header -->
<div class="header">
    <h1>🎓 Installation de Fronote</h1>
    <p>Assistant de configuration — étape par étape</p>
</div>

<!-- Stepper -->
<div class="stepper">
<?php foreach ($steps as $n => $label): ?>
    <?php
        $cls = '';
        if ($installed)              $cls = 'done';
        elseif ($n < $currentStep)   $cls = 'done';
        elseif ($n === $currentStep) $cls = 'active';
    ?>
    <div class="step-item <?= $cls ?>">
        <div class="step-num"><?= $cls === 'done' ? '✓' : $n ?></div>
        <div><?= htmlspecialchars($label) ?></div>
    </div>
<?php endforeach; ?>
</div>

<div class="body">

<?php if ($error): ?>
<div class="msg msg-error">❌ <?= nl2br(htmlspecialchars($error)) ?></div>
<?php endif; ?>

<?php if ($success): ?>
<div class="msg msg-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>


<?php // ═══════════════════════════════════════════════════════════════════════
      // ÉTAPE 1 : PRÉ-REQUIS
      // ═══════════════════════════════════════════════════════════════════════ ?>
<?php if ($currentStep === 1 && !$installed): ?>
<h2 class="section-title">Vérification des pré-requis</h2>
<p class="section-sub">PHP, extensions, répertoires et fichiers nécessaires.</p>

<?php
    $phpOk = version_compare(PHP_VERSION, '7.4.0', '>=');

    $requiredExts = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'session'];
    $extResults = [];
    foreach ($requiredExts as $ext) $extResults[$ext] = extension_loaded($ext);

    $requiredDirs = ['API/logs', 'API/config', 'API/Core', 'API/Database', 'API/Auth', 'API/Providers', 'uploads', 'uploads/messagerie', 'uploads/devoirs', 'uploads/justificatifs', 'temp'];
    $dirResults = [];
    foreach ($requiredDirs as $d) $dirResults[$d] = ensureDir($installDir . '/' . $d);

    $requiredFiles = ['API/bootstrap.php', 'API/core.php', 'pronote.sql'];
    $fileResults = [];
    foreach ($requiredFiles as $f) $fileResults[$f] = file_exists($installDir . '/' . $f);

    $allOk = $phpOk
        && !in_array(false, $extResults, true)
        && !in_array(false, array_column($dirResults, 'ok'), true)
        && !in_array(false, $fileResults, true);
?>

<ul class="check-list">
    <li>
        <span class="badge <?= $phpOk ? 'badge-ok' : 'badge-fail' ?>"><?= $phpOk ? 'OK' : 'FAIL' ?></span>
        PHP <?= PHP_VERSION ?> (≥ 7.4 requis)
    </li>
    <?php foreach ($extResults as $ext => $ok): ?>
    <li>
        <span class="badge <?= $ok ? 'badge-ok' : 'badge-fail' ?>"><?= $ok ? 'OK' : 'ABSENT' ?></span>
        Extension <code><?= $ext ?></code>
    </li>
    <?php endforeach; ?>
    <?php foreach ($dirResults as $dir => $r): ?>
    <li>
        <span class="badge <?= $r['ok'] ? 'badge-ok' : 'badge-fail' ?>"><?= $r['ok'] ? 'OK' : 'ERREUR' ?></span>
        Répertoire <code><?= $dir ?></code>
        <?php if (!$r['ok']): ?><small style="color:var(--danger);margin-left:6px">— <?= htmlspecialchars($r['msg']) ?></small><?php endif; ?>
    </li>
    <?php endforeach; ?>
    <?php foreach ($fileResults as $file => $ok): ?>
    <li>
        <span class="badge <?= $ok ? 'badge-ok' : 'badge-fail' ?>"><?= $ok ? 'OK' : 'ABSENT' ?></span>
        Fichier <code><?= $file ?></code>
    </li>
    <?php endforeach; ?>
</ul>

<?php if (!$allOk): ?>
<?php
    // Construire les commandes correctives
    $fixDirs = [];
    $fixPerms = [];
    foreach ($dirResults as $dir => $r) {
        if (!$r['ok']) {
            $fullPath = $installDir . '/' . $dir;
            if (!is_dir($fullPath)) {
                $fixDirs[] = $dir;
            } else {
                $fixPerms[] = $dir;
            }
        }
    }
    $hasFixableDirs = !empty($fixDirs) || !empty($fixPerms);
?>
<?php if ($hasFixableDirs): ?>
<div class="info-box" style="border-color:#feb2b2;background:#fff5f5;margin-bottom:16px">
    <h4 style="color:#c53030">🛠️ Commandes correctives à exécuter sur le serveur</h4>
    <p style="font-size:.88em;color:#718096;margin-bottom:10px">Connectez-vous en SSH au serveur et exécutez :</p>
    <pre style="background:#2d3748;color:#e2e8f0;padding:14px 16px;border-radius:6px;font-size:.85em;overflow-x:auto;line-height:1.7"><code><?php
        if (!empty($fixDirs)) {
            echo "# Créer les répertoires manquants\n";
            echo "mkdir -p";
            foreach ($fixDirs as $d) echo " " . htmlspecialchars($installDir . '/' . $d);
            echo "\n\n";
        }
        if (!empty($fixPerms)) {
            echo "# Rendre les répertoires inscriptibles\n";
            foreach ($fixPerms as $d) {
                echo "chmod 755 " . htmlspecialchars($installDir . '/' . $d) . "\n";
            }
            echo "\n";
        }
        // Toujours proposer un chown global
        $allBroken = array_merge($fixDirs, $fixPerms);
        echo "# Donner la propriété au serveur web\n";
        echo "chown -R www-data:www-data " . htmlspecialchars($installDir) . "\n";
    ?></code></pre>
</div>
<?php endif; ?>
<div class="msg msg-error">Certains pré-requis ne sont pas remplis. Corrigez-les puis <strong>rechargez cette page</strong>.</div>
<?php else: ?>
<form method="post">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">
    <input type="hidden" name="step" value="1">
    <div class="actions" style="justify-content:flex-end">
        <button type="submit" class="btn btn-primary">Continuer →</button>
    </div>
</form>
<?php endif; ?>
<?php endif; ?>


<?php // ═══════════════════════════════════════════════════════════════════════
      // ÉTAPE 2 : BASE DE DONNÉES
      // ═══════════════════════════════════════════════════════════════════════ ?>
<?php if ($currentStep === 2 && !$installed): ?>
<h2 class="section-title">Base de données MySQL</h2>
<p class="section-sub">La connexion sera <strong>testée</strong> avant de passer à la suite. Aucune base ne sera créée ici.</p>

<form method="post">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">
    <input type="hidden" name="step" value="2">
    <div class="grid">
        <div class="form-group">
            <label>Hôte MySQL</label>
            <input type="text" name="db_host" value="<?= htmlspecialchars($inst['db']['dbHost'] ?? 'localhost') ?>" required>
            <div class="help"><code>localhost</code> sera converti en <code>127.0.0.1</code> (TCP obligatoire)</div>
        </div>
        <div class="form-group">
            <label>Port</label>
            <input type="number" name="db_port" value="<?= (int)($inst['db']['dbPort'] ?? 3306) ?>" required>
        </div>
        <div class="form-group">
            <label>Utilisateur</label>
            <input type="text" name="db_user" value="<?= htmlspecialchars($inst['db']['dbUser'] ?? '') ?>" required>
        </div>
        <div class="form-group">
            <label>Mot de passe</label>
            <input type="password" name="db_pass" value="<?= htmlspecialchars($inst['db']['dbPass'] ?? '') ?>">
            <div class="help">Laisser vide si pas de mot de passe</div>
        </div>
        <div class="form-group">
            <label>Nom de la base à créer</label>
            <input type="text" name="db_name" value="<?= htmlspecialchars($inst['db']['dbName'] ?? '') ?>" placeholder="pronote" required>
            <div class="help">Sera créée à l'étape 5 (recréée si elle existe déjà)</div>
        </div>
        <div class="form-group">
            <label>Charset</label>
            <select name="db_charset">
                <option value="utf8mb4" <?= ($inst['db']['dbCharset'] ?? 'utf8mb4') === 'utf8mb4' ? 'selected' : '' ?>>utf8mb4 (recommandé)</option>
                <option value="utf8" <?= ($inst['db']['dbCharset'] ?? '') === 'utf8' ? 'selected' : '' ?>>utf8</option>
            </select>
        </div>
    </div>
    <div class="actions">
        <a href="?step=1" class="btn btn-secondary">← Retour</a>
        <button type="submit" class="btn btn-primary">🔌 Tester la connexion →</button>
    </div>
</form>
<?php endif; ?>


<?php // ═══════════════════════════════════════════════════════════════════════
      // ÉTAPE 3 : APPLICATION
      // ═══════════════════════════════════════════════════════════════════════ ?>
<?php if ($currentStep === 3 && !$installed): ?>
<h2 class="section-title">Configuration de l'application</h2>
<p class="section-sub">Paramètres généraux et sécurité.</p>

<form method="post">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">
    <input type="hidden" name="step" value="3">
    <div class="grid">
        <div class="form-group">
            <label>Nom de l'application</label>
            <input type="text" name="app_name" value="<?= htmlspecialchars($inst['app']['appName'] ?? 'Fronote') ?>" required>
        </div>
        <div class="form-group">
            <label>Environnement</label>
            <select name="app_env">
                <?php $env = $inst['app']['appEnv'] ?? 'production'; ?>
                <option value="production" <?= $env === 'production' ? 'selected' : '' ?>>Production</option>
                <option value="development" <?= $env === 'development' ? 'selected' : '' ?>>Développement</option>
                <option value="test" <?= $env === 'test' ? 'selected' : '' ?>>Test</option>
            </select>
        </div>
        <div class="form-group">
            <label>Mode debug</label>
            <select name="app_debug">
                <option value="0" <?= empty($inst['app']['appDebug']) ? 'selected' : '' ?>>Désactivé</option>
                <option value="1" <?= !empty($inst['app']['appDebug']) ? 'selected' : '' ?>>Activé</option>
            </select>
            <div class="help">Désactiver en production</div>
        </div>
        <div class="form-group">
            <label>URL complète</label>
            <input type="url" name="app_url" value="<?= htmlspecialchars($inst['app']['appUrl'] ?? $fullUrl) ?>" required>
        </div>
    </div>
    <div class="form-group">
        <label>Chemin de base (relatif)</label>
        <input type="text" name="base_url" value="<?= htmlspecialchars($inst['app']['baseUrlIn'] ?? $basePath) ?>">
        <div class="help">Laisser vide si Fronote est à la racine du domaine</div>
    </div>

    <h3 style="margin:24px 0 14px;font-size:1em;color:#4a5568">🔒 Sécurité & sessions</h3>
    <div class="grid">
        <div class="form-group">
            <label>Nom de session</label>
            <input type="text" name="session_name" value="<?= htmlspecialchars($inst['app']['sessionName'] ?? 'pronote_session') ?>" required>
        </div>
        <div class="form-group">
            <label>Durée de session (sec)</label>
            <input type="number" name="session_lifetime" value="<?= (int)($inst['app']['sessionLifetime'] ?? 7200) ?>" min="600" required>
            <div class="help">7200 = 2 heures</div>
        </div>
        <div class="form-group">
            <label>Durée token CSRF (sec)</label>
            <input type="number" name="csrf_lifetime" value="<?= (int)($inst['app']['csrfLifetime'] ?? 3600) ?>" min="300" required>
        </div>
        <div class="form-group">
            <label>Tentatives de login max</label>
            <input type="number" name="max_login_attempts" value="<?= (int)($inst['app']['maxLoginAttempts'] ?? 5) ?>" min="3" max="10" required>
        </div>
        <div class="form-group">
            <label>Rate limit (requêtes)</label>
            <input type="number" name="rate_limit_attempts" value="<?= (int)($inst['app']['rateLimitAttempts'] ?? 5) ?>" min="3" required>
        </div>
        <div class="form-group">
            <label>Rate limit (période, min)</label>
            <input type="number" name="rate_limit_decay" value="<?= (int)($inst['app']['rateLimitDecay'] ?? 1) ?>" min="1" required>
        </div>
    </div>

    <h3 style="margin:24px 0 14px;font-size:1em;color:#4a5568">🏫 Établissement</h3>
    <div class="grid">
        <div class="form-group" style="grid-column:1/-1">
            <label>Nom de l'établissement <span style="color:#e53e3e;">*</span></label>
            <input type="text" name="etab_nom" value="<?= htmlspecialchars($inst['etab']['etabNom'] ?? '') ?>" required placeholder="Ex: Lycée Jean Monnet">
        </div>
        <div class="form-group" style="grid-column:1/-1">
            <label>Adresse</label>
            <input type="text" name="etab_adresse" value="<?= htmlspecialchars($inst['etab']['etabAdresse'] ?? '') ?>" placeholder="1 rue de l'Éducation">
        </div>
        <div class="form-group">
            <label>Code postal</label>
            <input type="text" name="etab_cp" value="<?= htmlspecialchars($inst['etab']['etabCp'] ?? '') ?>" placeholder="75001">
        </div>
        <div class="form-group">
            <label>Ville</label>
            <input type="text" name="etab_ville" value="<?= htmlspecialchars($inst['etab']['etabVille'] ?? '') ?>" placeholder="Paris">
        </div>
        <div class="form-group">
            <label>Téléphone</label>
            <input type="text" name="etab_tel" value="<?= htmlspecialchars($inst['etab']['etabTel'] ?? '') ?>" placeholder="01 23 45 67 89">
        </div>
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="etab_email" value="<?= htmlspecialchars($inst['etab']['etabEmail'] ?? '') ?>" placeholder="contact@etablissement.fr">
        </div>
        <div class="form-group">
            <label>Académie</label>
            <input type="text" name="etab_academie" value="<?= htmlspecialchars($inst['etab']['etabAcademie'] ?? '') ?>" placeholder="Paris">
        </div>
        <div class="form-group">
            <label>Type d'établissement</label>
            <?php $etType = $inst['etab']['etabType'] ?? 'college'; ?>
            <select name="etab_type">
                <option value="primaire" <?= $etType === 'primaire' ? 'selected' : '' ?>>Primaire</option>
                <option value="college" <?= $etType === 'college' ? 'selected' : '' ?>>Collège</option>
                <option value="lycee" <?= $etType === 'lycee' ? 'selected' : '' ?>>Lycée</option>
                <option value="tout" <?= $etType === 'tout' ? 'selected' : '' ?>>Tout (Primaire + Collège + Lycée)</option>
            </select>
        </div>
    </div>

    <h3 style="margin:24px 0 14px;font-size:1em;color:#4a5568">📅 Périodes scolaires</h3>
    <?php $pSys = $inst['etab']['periodeSystem'] ?? 'trimestre'; ?>
    <div class="form-group">
        <label>Système de périodes</label>
        <select name="periode_system" id="periodeSystem" onchange="togglePeriodes()">
            <option value="trimestre" <?= $pSys === 'trimestre' ? 'selected' : '' ?>>Trimestres (3 périodes)</option>
            <option value="semestre" <?= $pSys === 'semestre' ? 'selected' : '' ?>>Semestres (2 périodes)</option>
        </select>
    </div>
    <?php
    $curYear = date('Y');
    $nextYear = $curYear + (date('n') >= 9 ? 1 : 0);
    $baseYear = date('n') >= 9 ? $curYear : $curYear - 1;
    $defaultTrimestres = [
        ['debut' => "$baseYear-09-01", 'fin' => "$baseYear-12-15"],
        ['debut' => "$nextYear-01-03", 'fin' => "$nextYear-03-15"],
        ['debut' => "$nextYear-03-16", 'fin' => "$nextYear-06-30"],
    ];
    $defaultSemestres = [
        ['debut' => "$baseYear-09-01", 'fin' => "$nextYear-01-31"],
        ['debut' => "$nextYear-02-01", 'fin' => "$nextYear-06-30"],
    ];
    ?>
    <div id="trimestre-fields" style="<?= $pSys === 'semestre' ? 'display:none' : '' ?>">
        <div class="grid">
            <?php for ($i = 0; $i < 3; $i++): 
                $pData = $inst['etab']['periodes'][$i] ?? $defaultTrimestres[$i];
            ?>
            <div class="form-group" style="grid-column:1/-1;display:flex;gap:12px;align-items:center;">
                <strong style="min-width:120px"><?= ($i+1) === 1 ? '1er' : ($i+1).'ème' ?> trimestre</strong>
                <label style="font-size:12px;margin:0">Du</label>
                <input type="date" name="p<?= $i+1 ?>_debut" value="<?= htmlspecialchars($pData['debut'] ?? '') ?>" style="flex:1">
                <label style="font-size:12px;margin:0">Au</label>
                <input type="date" name="p<?= $i+1 ?>_fin" value="<?= htmlspecialchars($pData['fin'] ?? '') ?>" style="flex:1">
            </div>
            <?php endfor; ?>
        </div>
    </div>
    <div id="semestre-fields" style="<?= $pSys === 'trimestre' ? 'display:none' : '' ?>">
        <div class="grid">
            <?php for ($i = 0; $i < 2; $i++): 
                $sData = ($pSys === 'semestre' && isset($inst['etab']['periodes'][$i])) ? $inst['etab']['periodes'][$i] : $defaultSemestres[$i];
            ?>
            <div class="form-group" style="grid-column:1/-1;display:flex;gap:12px;align-items:center;">
                <strong style="min-width:120px"><?= ($i+1) === 1 ? '1er' : '2ème' ?> semestre</strong>
                <label style="font-size:12px;margin:0">Du</label>
                <input type="date" name="s<?= $i+1 ?>_debut" value="<?= htmlspecialchars($sData['debut'] ?? '') ?>" style="flex:1">
                <label style="font-size:12px;margin:0">Au</label>
                <input type="date" name="s<?= $i+1 ?>_fin" value="<?= htmlspecialchars($sData['fin'] ?? '') ?>" style="flex:1">
            </div>
            <?php endfor; ?>
        </div>
    </div>
    <script>
    function togglePeriodes() {
        var sys = document.getElementById('periodeSystem').value;
        document.getElementById('trimestre-fields').style.display = sys === 'trimestre' ? '' : 'none';
        document.getElementById('semestre-fields').style.display = sys === 'semestre' ? '' : 'none';
    }
    function toggleSmtp() {
        var on = document.getElementById('smtpEnabled').checked;
        document.getElementById('smtp-fields').style.display = on ? '' : 'none';
    }
    </script>

    <h3 style="margin:24px 0 14px;font-size:1em;color:#4a5568">📧 Serveur SMTP (optionnel)</h3>
    <div class="form-group">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
            <input type="checkbox" name="smtp_enabled" id="smtpEnabled" value="1"
                   onchange="toggleSmtp()"
                   <?= !empty($inst['smtp']['enabled']) ? 'checked' : '' ?>>
            Configurer un serveur SMTP pour l'envoi d'emails
        </label>
        <div class="help">Si désactivé, les emails seront envoyés via la fonction mail() de PHP (souvent bloquée par les hébergeurs).</div>
    </div>
    <div id="smtp-fields" style="<?= empty($inst['smtp']['enabled']) ? 'display:none' : '' ?>">
        <div class="grid">
            <div class="form-group">
                <label>Serveur SMTP</label>
                <input type="text" name="smtp_host" value="<?= htmlspecialchars($inst['smtp']['host'] ?? '') ?>" placeholder="smtp.gmail.com">
            </div>
            <div class="form-group">
                <label>Port</label>
                <input type="number" name="smtp_port" value="<?= (int)($inst['smtp']['port'] ?? 587) ?>" placeholder="587">
                <div class="help">587 (TLS) ou 465 (SSL) ou 25 (non chiffré)</div>
            </div>
            <div class="form-group">
                <label>Utilisateur SMTP</label>
                <input type="text" name="smtp_username" value="<?= htmlspecialchars($inst['smtp']['username'] ?? '') ?>" placeholder="contact@etablissement.fr">
            </div>
            <div class="form-group">
                <label>Mot de passe SMTP</label>
                <input type="password" name="smtp_password" value="<?= htmlspecialchars($inst['smtp']['password'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Chiffrement</label>
                <?php $smtpEnc = $inst['smtp']['encryption'] ?? 'tls'; ?>
                <select name="smtp_encryption">
                    <option value="tls" <?= $smtpEnc === 'tls' ? 'selected' : '' ?>>STARTTLS (port 587)</option>
                    <option value="ssl" <?= $smtpEnc === 'ssl' ? 'selected' : '' ?>>SSL/TLS (port 465)</option>
                    <option value="none" <?= $smtpEnc === 'none' ? 'selected' : '' ?>>Aucun (non recommandé)</option>
                </select>
            </div>
            <div class="form-group">
                <label>Adresse d'expédition</label>
                <input type="email" name="smtp_from_address" value="<?= htmlspecialchars($inst['smtp']['from_address'] ?? '') ?>" placeholder="noreply@etablissement.fr">
            </div>
            <div class="form-group" style="grid-column:1/-1">
                <label>Nom de l'expéditeur</label>
                <input type="text" name="smtp_from_name" value="<?= htmlspecialchars($inst['smtp']['from_name'] ?? ($inst['app']['appName'] ?? 'Fronote')) ?>" placeholder="Fronote">
            </div>
        </div>
    </div>

    <div class="actions">
        <a href="?step=2" class="btn btn-secondary">← Retour</a>
        <button type="submit" class="btn btn-primary">Continuer →</button>
    </div>
</form>
<?php endif; ?>


<?php // ═══════════════════════════════════════════════════════════════════════
      // ÉTAPE 4 : ADMINISTRATEUR
      // ═══════════════════════════════════════════════════════════════════════ ?>
<?php if ($currentStep === 4 && !$installed): ?>
<h2 class="section-title">Compte administrateur</h2>
<p class="section-sub">Ce sera le compte principal. L'identifiant sera <strong><code>admin</code></strong>.</p>

<form method="post" id="adminForm">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">
    <input type="hidden" name="step" value="4">
    <div class="grid">
        <div class="form-group">
            <label>Nom</label>
            <input type="text" name="admin_nom" value="<?= htmlspecialchars($inst['admin']['nom'] ?? '') ?>" required>
        </div>
        <div class="form-group">
            <label>Prénom</label>
            <input type="text" name="admin_prenom" value="<?= htmlspecialchars($inst['admin']['prenom'] ?? '') ?>" required>
        </div>
    </div>
    <div class="form-group">
        <label>Email</label>
        <input type="email" name="admin_mail" value="<?= htmlspecialchars($inst['admin']['mail'] ?? '') ?>" required>
    </div>
    <div class="form-group pw-wrap">
        <label>Mot de passe</label>
        <input type="password" name="admin_password" id="pw" required minlength="12">
        <button type="button" class="pw-toggle" onclick="let f=document.getElementById('pw');f.type=f.type==='password'?'text':'password'">👁️</button>
        <div class="pw-bar-track"><div class="pw-bar" id="pwBar"></div></div>
        <button type="button" class="pw-gen" onclick="genPw()">🎲 Générer un mot de passe sécurisé</button>
        <div class="pw-reqs">
            <strong>Exigences :</strong>
            <div class="pw-req fail" id="r-len">✗ Au moins 12 caractères</div>
            <div class="pw-req fail" id="r-up">✗ Au moins une majuscule</div>
            <div class="pw-req fail" id="r-lo">✗ Au moins une minuscule</div>
            <div class="pw-req fail" id="r-num">✗ Au moins un chiffre</div>
            <div class="pw-req fail" id="r-sp">✗ Au moins un caractère spécial</div>
        </div>
    </div>
    <div class="actions">
        <a href="?step=3" class="btn btn-secondary">← Retour</a>
        <button type="submit" class="btn btn-primary" id="btnNext" disabled>Continuer →</button>
    </div>
</form>

<script>
function checkPw(v){
    const rules={len:v.length>=12,up:/[A-Z]/.test(v),lo:/[a-z]/.test(v),num:/[0-9]/.test(v),sp:/[^A-Za-z0-9]/.test(v)};
    let s=0;
    for(const[k,ok]of Object.entries(rules)){
        const el=document.getElementById('r-'+k);
        el.className='pw-req '+(ok?'ok':'fail');
        el.textContent=(ok?'✓':'✗')+el.textContent.slice(1);
        if(ok)s++;
    }
    const bar=document.getElementById('pwBar');
    const colors=['#e53e3e','#ed8936','#ecc94b','#48bb78','#38b2ac'];
    bar.style.width=['20%','40%','60%','80%','100%'][s-1]||'0%';
    bar.style.backgroundColor=colors[s-1]||'#edf2f7';
    document.getElementById('btnNext').disabled=s<5;
}
function genPw(){
    const S='ABCDEFGHIJKLMNOPQRSTUVWXYZ',s='abcdefghijklmnopqrstuvwxyz',n='0123456789',p='!@#$%^&*()-_=+';
    let r='';const a=S+s+n+p;
    r+=S[Math.random()*S.length|0]+s[Math.random()*s.length|0]+n[Math.random()*n.length|0]+p[Math.random()*p.length|0];
    for(let i=4;i<16;i++)r+=a[Math.random()*a.length|0];
    r=r.split('').sort(()=>Math.random()-.5).join('');
    const f=document.getElementById('pw');f.type='text';f.value=r;checkPw(r);
}
document.getElementById('pw').addEventListener('input',function(){checkPw(this.value)});
</script>
<?php endif; ?>


<?php // ═══════════════════════════════════════════════════════════════════════
      // ÉTAPE 5 : RÉCAPITULATIF / EXÉCUTION / RÉSULTAT
      // ═══════════════════════════════════════════════════════════════════════ ?>
<?php if ($currentStep === 5): ?>

    <?php if ($installed): ?>
        <?php $log = $inst['log'] ?? []; ?>
        <div class="result-card">
            <h2>🎉 Installation terminée !</h2>
            <p>Fronote est prêt à être utilisé.</p>
        </div>

        <h3 style="font-size:1em;margin-bottom:8px">Journal d'installation</h3>
        <ul class="log-list">
        <?php foreach ($log as [$type, $msg]): ?>
            <li class="log-<?= $type === 'ok' ? 'ok' : 'warn' ?>"><?= $type === 'ok' ? '✅' : '⚠️' ?> <?= $msg ?></li>
        <?php endforeach; ?>
        </ul>

        <div class="info-box">
            <h4>📋 Informations de connexion</h4>
            <p style="margin:8px 0"><strong>Identifiant :</strong> <code>admin</code></p>
            <p style="margin:8px 0"><strong>Mot de passe :</strong> celui que vous avez défini à l'étape 4</p>
            <p style="margin:8px 0"><strong>Type :</strong> <code>administrateur</code></p>
        </div>

        <div class="info-box">
            <h4>🔒 Fonctionnalités de sécurité installées</h4>
            <ul style="padding-left:20px;font-size:.9em;line-height:1.8;margin:8px 0">
                <li>AuthManager unifié (API\Auth\AuthManager)</li>
                <li>SessionGuard avec session_regenerate_id()</li>
                <li>RateLimiter IP-based en base de données</li>
                <li>Protection CSRF avec rotation de tokens</li>
                <li>Mots de passe hashés BCRYPT (cost 12)</li>
                <li>Audit log (Event Sourcing)</li>
                <li>Connexion centralisée via getPDO()</li>
            </ul>
        </div>

        <div style="text-align:center;margin-top:24px">
            <a href="login/index.php" class="btn btn-success" style="font-size:1.05em;padding:14px 32px;width:100%;justify-content:center">
                🔐 Se connecter maintenant
            </a>
        </div>

    <?php else: ?>
        <?php /* RÉCAPITULATIF avant exécution */ ?>
        <?php if (empty($inst['db']) || empty($inst['app']) || empty($inst['admin'])): ?>
            <div class="msg msg-error">❌ Données d'installation incomplètes — la session a expiré ou a été corrompue.</div>
            <div class="actions" style="justify-content:center">
                <a href="?reset" class="btn btn-primary">🔄 Recommencer l'installation</a>
            </div>
        <?php else: ?>
        <h2 class="section-title">Récapitulatif</h2>
        <p class="section-sub">Vérifiez les informations puis lancez l'installation.</p>

        <?php $db = $inst['db']; $ap = $inst['app']; $ad = $inst['admin']; ?>

        <div class="info-box">
            <h4>🗄️ Base de données</h4>
            <p style="margin:4px 0"><code><?= htmlspecialchars($db['dbUser']) ?>@<?= htmlspecialchars($db['dbHost']) ?>:<?= $db['dbPort'] ?></code> → base : <code><?= htmlspecialchars($db['dbName']) ?></code></p>
        </div>
        <div class="info-box">
            <h4>⚙️ Application</h4>
            <p style="margin:4px 0"><strong><?= htmlspecialchars($ap['appName']) ?></strong> — <?= htmlspecialchars($ap['appEnv']) ?> — <?= htmlspecialchars($ap['appUrl']) ?></p>
        </div>
        <div class="info-box">
            <h4>👤 Administrateur</h4>
            <p style="margin:4px 0"><?= htmlspecialchars($ad['prenom']) ?> <?= htmlspecialchars($ad['nom']) ?> — <?= htmlspecialchars($ad['mail']) ?> — identifiant : <code>admin</code></p>
        </div>
        <?php $sm = $inst['smtp'] ?? []; ?>
        <div class="info-box">
            <h4>📧 SMTP</h4>
            <?php if (!empty($sm['enabled']) && !empty($sm['host'])): ?>
            <p style="margin:4px 0"><code><?= htmlspecialchars($sm['host']) ?>:<?= $sm['port'] ?></code> (<?= htmlspecialchars($sm['encryption']) ?>) — <?= htmlspecialchars($sm['from_address']) ?></p>
            <?php else: ?>
            <p style="margin:4px 0;color:#999">Non configuré — configurable depuis l'administration</p>
            <?php endif; ?>
        </div>

        <div class="msg msg-warn">
            ⚠️ L'installation va <strong>supprimer et recréer</strong> la base <code><?= htmlspecialchars($db['dbName']) ?></code> si elle existe déjà.
        </div>

        <form method="post" id="execForm">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="step" value="5">
            <div class="actions">
                <a href="?step=4" class="btn btn-secondary">← Retour</a>
                <button type="submit" class="btn btn-primary" id="btnInstall">🚀 Lancer l'installation</button>
            </div>
        </form>
        <script>
        document.getElementById('execForm').addEventListener('submit',function(){
            var b=document.getElementById('btnInstall');
            b.disabled=true;b.textContent='⏳ Installation en cours…';
        });
        </script>
        <?php endif; /* fin else données complètes */ ?>
    <?php endif; ?>

<?php endif; ?>

</div><!-- .body -->
</div><!-- .card -->

<p style="text-align:center;color:rgba(255,255,255,.5);margin-top:16px;font-size:.8em">
    IP client : <?= htmlspecialchars($clientIP) ?> — PHP <?= PHP_VERSION ?>
</p>

</div><!-- .container -->
</body>
</html>
