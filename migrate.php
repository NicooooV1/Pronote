<?php
/**
 * migrate.php — CLI migration runner for Pronote.
 *
 * Usage:
 *   php migrate.php          Run pending migrations
 *   php migrate.php status   Show migration status
 *   php migrate.php pending  List pending migrations
 *
 * Can also be accessed via browser by admin users.
 */

// CLI mode detection
$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {
    require_once __DIR__ . '/API/core.php';
    requireAuth();
    if (!isAdmin()) {
        http_response_code(403);
        die('Accès réservé aux administrateurs.');
    }
    header('Content-Type: text/plain; charset=utf-8');
}

// Database connection
if ($isCli) {
    // Load .env manually
    $envFile = __DIR__ . '/.env';
    if (!file_exists($envFile)) {
        die("Fichier .env introuvable. Lancez d'abord l'installation.\n");
    }
    $env = [];
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        [$key, $val] = array_pad(explode('=', $line, 2), 2, '');
        $env[trim($key)] = trim($val);
    }

    try {
        $dsn = "mysql:host={$env['DB_HOST']};port={$env['DB_PORT']};dbname={$env['DB_NAME']};charset=utf8mb4";
        $pdo = new PDO($dsn, $env['DB_USER'], $env['DB_PASS'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e) {
        die("Erreur de connexion : " . $e->getMessage() . "\n");
    }
} else {
    $pdo = getPDO();
}

require_once __DIR__ . '/API/Database/Migrator.php';
$migrator = new \API\Database\Migrator($pdo, __DIR__ . '/migrations');

$command = $isCli ? ($argv[1] ?? 'run') : ($_GET['action'] ?? 'run');

switch ($command) {
    case 'status':
        echo "=== Migration Status ===\n";
        $status = $migrator->status();
        if (empty($status)) {
            echo "Aucune migration enregistrée.\n";
        } else {
            echo str_pad('Migration', 50) . str_pad('Batch', 8) . "Date\n";
            echo str_repeat('-', 80) . "\n";
            foreach ($status as $s) {
                echo str_pad($s['migration'], 50) . str_pad($s['batch'], 8) . $s['created_at'] . "\n";
            }
        }
        break;

    case 'pending':
        $pending = $migrator->getPending();
        if (empty($pending)) {
            echo "Aucune migration en attente.\n";
        } else {
            echo "Migrations en attente :\n";
            foreach ($pending as $p) {
                echo "  - $p\n";
            }
        }
        break;

    case 'run':
    default:
        $pending = $migrator->getPending();
        if (empty($pending)) {
            echo "Rien à migrer. Base de données à jour.\n";
        } else {
            echo "Exécution de " . count($pending) . " migration(s)...\n";
            $count = $migrator->run();
            echo "$count migration(s) exécutée(s) avec succès.\n";
        }
        break;
}

if (!$isCli) {
    echo "\n\n[Terminé]";
}
