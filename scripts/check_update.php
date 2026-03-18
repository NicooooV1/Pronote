<?php
/**
 * Script CRON de vérification des mises à jour Fronote.
 * Compare la version locale (version.json) avec la version distante sur GitHub.
 * Si une mise à jour est disponible, déclenche scripts/update.php.
 *
 * Usage CRON (Linux) :
 *   * * * * * php /var/www/fronote/scripts/check_update.php >> /var/log/fronote-update.log 2>&1
 *
 * Usage CLI :
 *   php scripts/check_update.php
 */
declare(strict_types=1);

define('FRONOTE_CHECK_UPDATE_SCRIPT', true);

$projectRoot = dirname(__DIR__);

function culog(string $msg): void
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
}

// ─── Parser .env manuellement ─────────────────────────────────────────────────
$env = [];
$envFile = $projectRoot . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if ($line === '' || $line[0] === '#') continue;
        $eqPos = strpos($line, '=');
        if ($eqPos === false) continue;
        $key = trim(substr($line, 0, $eqPos));
        $val = trim(substr($line, $eqPos + 1));
        if (strlen($val) >= 2 && ($val[0] === '"' || $val[0] === "'") && $val[0] === $val[-1]) {
            $val = substr($val, 1, -1);
        }
        $env[$key] = $val;
    }
}

$githubRepo   = $env['GITHUB_REPO']   ?? '';
$githubBranch = $env['GITHUB_BRANCH'] ?? 'main';

if (empty($githubRepo)) {
    culog('GITHUB_REPO non configuré dans .env — vérification ignorée.');
    exit(0);
}

// ─── Lire la version locale ───────────────────────────────────────────────────
$versionFile = $projectRoot . '/version.json';
if (!file_exists($versionFile)) {
    culog('version.json introuvable — vérification ignorée.');
    exit(0);
}

$localData    = json_decode((string) file_get_contents($versionFile), true);
$localVersion = $localData['version'] ?? '0.0.0';

// ─── Récupérer la version distante ───────────────────────────────────────────
$remoteUrl = 'https://raw.githubusercontent.com/'
    . rawurlencode($githubRepo) . '/'
    . rawurlencode($githubBranch) . '/version.json';

$ctx = stream_context_create([
    'http' => [
        'timeout'        => 10,
        'ignore_errors'  => true,
        'user_agent'     => 'Fronote-UpdateChecker/1.0',
    ],
    'ssl' => [
        'verify_peer'      => true,
        'verify_peer_name' => true,
    ],
]);

$remoteRaw = @file_get_contents($remoteUrl, false, $ctx);
if ($remoteRaw === false) {
    culog('Impossible de récupérer la version distante depuis ' . $remoteUrl);
    exit(0);
}

$remoteData    = json_decode($remoteRaw, true);
$remoteVersion = $remoteData['version'] ?? null;

if ($remoteVersion === null) {
    culog('version.json distant invalide ou mal formé.');
    exit(0);
}

culog('Version locale : ' . $localVersion . ' — Version distante : ' . $remoteVersion);

// ─── Comparer et déclencher la mise à jour si besoin ─────────────────────────
if (version_compare($remoteVersion, $localVersion, '>')) {
    culog('Mise à jour disponible (' . $localVersion . ' → ' . $remoteVersion . '). Déclenchement de update.php…');
    require $projectRoot . '/scripts/update.php';
} else {
    culog('Aucune mise à jour disponible.');
}
