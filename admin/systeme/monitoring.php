<?php
/**
 * Administration — Dashboard de monitoring
 * Sante systeme, performances, connexions, erreurs.
 */
require_once __DIR__ . '/../../API/core.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAuth();
requireRole('administrateur');

// Health check
$health = app('health');
$checks = $health->runAll();
$allHealthy = !empty($checks['healthy']);

// System info
$phpVersion = phpversion();
$serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
$uptime = '';
if (file_exists(BASE_PATH . '/install.lock')) {
    $installDate = file_get_contents(BASE_PATH . '/install.lock');
    $days = (int)((time() - strtotime(trim($installDate))) / 86400);
    $uptime = $days . ' jours';
}

// DB stats
try {
    $pdo = getPDO();
    $dbSize = $pdo->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb FROM information_schema.TABLES WHERE table_schema = DATABASE()")->fetchColumn();
    $tableCount = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE table_schema = DATABASE()")->fetchColumn();
    $activeSessions = $pdo->query("SELECT COUNT(*) FROM sessions WHERE last_activity > " . (time() - 1800))->fetchColumn();
} catch (\Throwable $e) {
    $dbSize = '?';
    $tableCount = '?';
    $activeSessions = '?';
}

// Module count
try {
    $moduleCount = $pdo->query("SELECT COUNT(*) FROM modules_config WHERE enabled = 1")->fetchColumn();
} catch (\Throwable $e) {
    $moduleCount = '?';
}

// Feature flags stats
try {
    $flagsTotal = $pdo->query("SELECT COUNT(*) FROM feature_flags")->fetchColumn();
    $flagsEnabled = $pdo->query("SELECT COUNT(*) FROM feature_flags WHERE enabled = 1")->fetchColumn();
} catch (\Throwable $e) {
    $flagsTotal = '?';
    $flagsEnabled = '?';
}

// Environment
$env = app('environment');
$envName = 'production';
if ($env->isDev()) $envName = 'development';
elseif ($env->isStaging()) $envName = 'staging';

// Disk
$diskFree = round(disk_free_space(BASE_PATH) / 1024 / 1024 / 1024, 2);
$diskTotal = round(disk_total_space(BASE_PATH) / 1024 / 1024 / 1024, 2);
$diskPct = $diskTotal > 0 ? round(($diskTotal - $diskFree) / $diskTotal * 100) : 0;

$csrfToken = app('csrf')->generate();
$pageTitle = 'Monitoring';
$activePage = 'systeme';

require_once __DIR__ . '/../../templates/shared_header.php';
?>

<div class="topbar">
    <div class="topbar-left">
        <h1 class="page-title"><i class="fas fa-heartbeat"></i> Monitoring systeme</h1>
    </div>
    <div class="topbar-right">
        <span class="fs-xs text-muted">Derniere verification: <?= date('H:i:s') ?></span>
        <a href="" class="ui-btn ui-btn--ghost ui-btn--sm"><i class="fas fa-sync-alt"></i> Rafraichir</a>
    </div>
</div>

<div class="content-body p-lg">

    <!-- Global health status -->
    <div class="d-flex gap-lg flex-wrap mb-lg">
        <?= ui_stat_card('Sante globale', $allHealthy ? 'OK' : 'Degradee', ['icon' => 'fas fa-heart', 'color' => $allHealthy ? 'success' : 'danger']) ?>
        <?= ui_stat_card('Environnement', ucfirst($envName), ['icon' => 'fas fa-server', 'color' => $envName === 'production' ? 'primary' : 'warning']) ?>
        <?= ui_stat_card('Sessions actives', (string)$activeSessions, ['icon' => 'fas fa-users', 'color' => 'primary']) ?>
        <?= ui_stat_card('Modules actifs', (string)$moduleCount, ['icon' => 'fas fa-puzzle-piece', 'color' => 'success']) ?>
    </div>

    <div class="d-flex gap-lg flex-wrap mb-lg">
        <?= ui_stat_card('PHP', $phpVersion, ['icon' => 'fab fa-php', 'color' => 'primary']) ?>
        <?= ui_stat_card('Base de donnees', $dbSize . ' MB', ['icon' => 'fas fa-database', 'color' => 'primary']) ?>
        <?= ui_stat_card('Tables', (string)$tableCount, ['icon' => 'fas fa-table', 'color' => 'primary']) ?>
        <?= ui_stat_card('Uptime', $uptime ?: 'N/A', ['icon' => 'fas fa-clock', 'color' => 'primary']) ?>
    </div>

    <!-- Health checks detail -->
    <?php
    $healthRows = [];
    foreach ($checks as $name => $check) {
        if ($name === 'healthy') continue;
        if (!is_array($check)) continue;
        $status = !empty($check['ok']) ? ui_badge('OK', 'success') : ui_badge('ERREUR', 'danger');
        $details = '';
        if (isset($check['latency_ms'])) $details .= 'Latence: ' . $check['latency_ms'] . 'ms ';
        if (isset($check['free_gb'])) $details .= 'Libre: ' . $check['free_gb'] . ' GB ';
        if (isset($check['percent_used'])) $details .= '(' . $check['percent_used'] . '% utilise) ';
        if (isset($check['version'])) $details .= 'v' . $check['version'] . ' ';
        if (isset($check['error'])) $details .= '<span class="text-danger">' . e($check['error']) . '</span>';
        $healthRows[] = ['<strong>' . e(ucfirst($name)) . '</strong>', $status, $details ?: '-'];
    }
    echo ui_card('Verifications de sante', ui_table(
        [['label' => 'Service', 'width' => '20%'], ['label' => 'Statut', 'width' => '15%', 'align' => 'center'], ['label' => 'Details']],
        $healthRows
    ), ['icon' => 'fas fa-stethoscope']);
    ?>

    <div class="d-flex gap-lg flex-wrap mt-lg">
        <!-- Disk usage -->
        <div style="flex:1;min-width:300px;">
        <?= ui_card('Espace disque', '
            <div class="d-flex gap-md mb-md" style="align-items:center;">
                <div style="flex:1;">
                    <div style="background:var(--bg-light);border-radius:8px;height:20px;overflow:hidden;">
                        <div style="width:' . $diskPct . '%;height:100%;background:' . ($diskPct > 90 ? 'var(--danger)' : ($diskPct > 70 ? 'var(--warning)' : 'var(--primary)')) . ';border-radius:8px;transition:width 0.5s;"></div>
                    </div>
                </div>
                <span class="fw-bold">' . $diskPct . '%</span>
            </div>
            <div class="text-muted fs-xs">Utilise: ' . ($diskTotal - $diskFree) . ' GB / ' . $diskTotal . ' GB (libre: ' . $diskFree . ' GB)</div>
        ', ['icon' => 'fas fa-hdd']) ?>
        </div>

        <!-- Feature flags -->
        <div style="flex:1;min-width:300px;">
        <?= ui_card('Feature Flags', '
            <div class="d-flex gap-md mb-md" style="align-items:center;">
                <div style="flex:1;">
                    <div style="background:var(--bg-light);border-radius:8px;height:20px;overflow:hidden;">
                        <div style="width:' . ($flagsTotal > 0 ? round($flagsEnabled / $flagsTotal * 100) : 0) . '%;height:100%;background:var(--success);border-radius:8px;"></div>
                    </div>
                </div>
                <span class="fw-bold">' . $flagsEnabled . '/' . $flagsTotal . '</span>
            </div>
            <div class="text-muted fs-xs">' . $flagsEnabled . ' actifs sur ' . $flagsTotal . ' flags configures</div>
            <a href="feature_flags.php" class="ui-btn ui-btn--ghost ui-btn--sm mt-sm">Gerer les flags</a>
        ', ['icon' => 'fas fa-toggle-on']) ?>
        </div>
    </div>

    <!-- PHP info -->
    <?php
    $extensions = ['pdo_mysql', 'mbstring', 'json', 'openssl', 'intl', 'gd', 'curl', 'fileinfo', 'zip'];
    $extRows = [];
    foreach ($extensions as $ext) {
        $loaded = extension_loaded($ext);
        $extRows[] = [e($ext), $loaded ? ui_badge('Charge', 'success') : ui_badge('Manquant', 'danger')];
    }
    echo '<div class="mt-lg">';
    echo ui_card('Extensions PHP', ui_table(
        [['label' => 'Extension', 'width' => '50%'], ['label' => 'Statut', 'align' => 'center']],
        $extRows
    ), ['icon' => 'fab fa-php']);
    echo '</div>';
    ?>

</div>

<?php require_once __DIR__ . '/../../templates/shared_footer.php'; ?>
