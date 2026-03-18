<?php
/**
 * Script de mise à jour automatique Fronote.
 * Déclenché par webhook_update.php ou scripts/check_update.php.
 *
 * Usage CLI : php scripts/update.php
 *
 * Processus :
 *   1. Vérifier l'absence de update.lock (anti-concurrence)
 *   2. Créer update.lock
 *   3. Sauvegarder .env → temp/backup.env
 *   4. git pull origin main
 *   5. Restaurer .env si disparu
 *   6. Tester le bootstrap PHP
 *   7. Logger la nouvelle version
 *   8. Supprimer update.lock (finally)
 */
declare(strict_types=1);

define('FRONOTE_UPDATE_SCRIPT', true);

$projectRoot = dirname(__DIR__);
$lockFile    = $projectRoot . '/temp/update.lock';
$logFile     = $projectRoot . '/temp/update.log';

// S'assurer que le répertoire temp existe
if (!is_dir(dirname($lockFile))) {
    mkdir(dirname($lockFile), 0750, true);
}

function ulog(string $msg): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
    echo $line . PHP_EOL;
}

function urun(string $cmd, int &$code = 0): string
{
    $output = [];
    exec($cmd . ' 2>&1', $output, $code);
    return implode(' | ', $output);
}

// ─── Anti-concurrence ─────────────────────────────────────────────────────────
if (file_exists($lockFile)) {
    $age = time() - (int) filemtime($lockFile);
    if ($age < 300) { // lock valide 5 min max
        ulog('Update already in progress (lock file exists, age=' . $age . 's). Aborting.');
        exit(1);
    }
    ulog('Stale lock file detected (' . $age . 's old). Removing and continuing.');
    unlink($lockFile);
}

file_put_contents($lockFile, date('c') . ' pid=' . getmypid() . PHP_EOL);

// ─── Mise à jour ──────────────────────────────────────────────────────────────
$exitCode = 0;
try {
    ulog('=== Démarrage de la mise à jour ===');

    // 1. Backup .env
    $envFile    = $projectRoot . '/.env';
    $envBackup  = $projectRoot . '/temp/backup.env';
    if (file_exists($envFile)) {
        copy($envFile, $envBackup);
        ulog('Backup .env → temp/backup.env');
    }

    // 2. git pull
    $phpBin = PHP_BINARY;
    $gitCmd = 'git -C ' . escapeshellarg($projectRoot) . ' pull origin main';
    $gitOut = urun($gitCmd, $gitCode);
    ulog('git pull: ' . $gitOut);

    if ($gitCode !== 0) {
        throw new RuntimeException('git pull a échoué (code ' . $gitCode . ')');
    }

    // 3. Restaurer .env si disparu après pull
    if (!file_exists($envFile) && file_exists($envBackup)) {
        copy($envBackup, $envFile);
        ulog('Restauré .env depuis backup (git pull l\'avait supprimé)');
    }

    // 4. Composer install --no-dev (si dispo)
    $composerBin = trim((string)(shell_exec('which composer 2>/dev/null') ?: shell_exec('where composer 2>NUL') ?: ''));
    if (!empty($composerBin) && file_exists($projectRoot . '/composer.json')) {
        $compOut = urun($composerBin . ' install --no-dev --optimize-autoloader -d ' . escapeshellarg($projectRoot), $compCode);
        ulog('Composer: ' . ($compOut ?: '(ok)'));
    } else {
        ulog('Composer non disponible — étape ignorée');
    }

    // 5. Test du bootstrap
    $bootstrapTest = $phpBin . ' -r "error_reporting(E_ALL); require ' . escapeshellarg($projectRoot . '/API/bootstrap.php') . ';"';
    $bootOut = urun($bootstrapTest, $bootCode);
    if ($bootCode !== 0) {
        throw new RuntimeException('Bootstrap test échoué: ' . $bootOut);
    }
    ulog('Bootstrap test: OK');

    // 6. Lire la nouvelle version
    $versionFile = $projectRoot . '/version.json';
    $newVersion  = 'unknown';
    if (file_exists($versionFile)) {
        $vData      = json_decode((string) file_get_contents($versionFile), true);
        $newVersion = $vData['version'] ?? 'unknown';
    }
    ulog('=== Mise à jour terminée — version ' . $newVersion . ' ===');

} catch (Throwable $e) {
    ulog('ERREUR: ' . $e->getMessage());
    $exitCode = 1;
} finally {
    if (file_exists($lockFile)) {
        unlink($lockFile);
    }
}

exit($exitCode);
