<?php
/**
 * Récepteur de webhook GitHub pour la mise à jour automatique.
 * POST /API/endpoints/webhook_update.php
 *
 * Vérifie la signature HMAC-SHA256, filtre les push sur main,
 * puis déclenche scripts/update.php en arrière-plan.
 *
 * Configuration GitHub :
 *   - Payload URL : https://monsite.fr/API/endpoints/webhook_update.php
 *   - Content type : application/json
 *   - Secret : valeur de GITHUB_WEBHOOK_SECRET dans .env
 *   - Events : Just the push event
 */

// Ne pas charger le bootstrap complet pour rester léger et sans side-effects
define('FRONOTE_WEBHOOK_ENDPOINT', true);

header('Content-Type: application/json');

$rawPayload = (string) file_get_contents('php://input');
$signature  = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
$event      = $_SERVER['HTTP_X_GITHUB_EVENT']      ?? '';

$projectRoot = dirname(dirname(__DIR__));
$envFile     = $projectRoot . '/.env';

// ─── Lire le secret depuis .env (sans bootstrap) ──────────────────────────────
$secret = '';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if ($line === '' || $line[0] === '#') continue;
        $eqPos = strpos($line, '=');
        if ($eqPos === false) continue;
        $key = trim(substr($line, 0, $eqPos));
        $val = trim(substr($line, $eqPos + 1));
        // Supprimer les guillemets optionnels autour de la valeur
        if (strlen($val) >= 2 && ($val[0] === '"' || $val[0] === "'") && $val[0] === $val[-1]) {
            $val = substr($val, 1, -1);
        }
        if ($key === 'GITHUB_WEBHOOK_SECRET') {
            $secret = $val;
            break;
        }
    }
}

// ─── Vérification de la signature ────────────────────────────────────────────
if (empty($secret)) {
    http_response_code(500);
    echo json_encode(['error' => 'GITHUB_WEBHOOK_SECRET not configured']);
    exit;
}

if (empty($signature)) {
    http_response_code(401);
    echo json_encode(['error' => 'Missing signature']);
    exit;
}

$expected = 'sha256=' . hash_hmac('sha256', $rawPayload, $secret);
if (!hash_equals($expected, $signature)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid signature']);
    exit;
}

// ─── Filtrer : push sur main uniquement ──────────────────────────────────────
if ($event !== 'push') {
    echo json_encode(['status' => 'ignored', 'reason' => 'event is not push']);
    exit;
}

$payload = json_decode($rawPayload, true);
$ref     = $payload['ref'] ?? '';

if ($ref !== 'refs/heads/main') {
    echo json_encode(['status' => 'ignored', 'reason' => 'not a push to main', 'ref' => $ref]);
    exit;
}

// ─── Déclencher la mise à jour en arrière-plan ───────────────────────────────
$scriptPath = $projectRoot . '/scripts/update.php';
$logFile    = $projectRoot . '/temp/update.log';

// S'assurer que le répertoire temp existe
if (!is_dir(dirname($logFile))) {
    @mkdir(dirname($logFile), 0750, true);
}

if (!file_exists($scriptPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'scripts/update.php not found']);
    exit;
}

$phpBin = PHP_BINARY;
$cmd    = $phpBin . ' ' . escapeshellarg($scriptPath);
$log    = escapeshellarg($logFile);

if (PHP_OS_FAMILY === 'Windows') {
    pclose(popen('start /B ' . $cmd . ' >> ' . $logFile . ' 2>&1', 'r'));
} else {
    exec('nohup ' . $cmd . ' >> ' . $log . ' 2>&1 &');
}

$commit = substr($payload['after'] ?? '', 0, 8);
echo json_encode([
    'status' => 'update_triggered',
    'commit' => $commit,
    'ref'    => $ref,
]);
