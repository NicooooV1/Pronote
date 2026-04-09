<?php
/**
 * Administration — A propos de Fronote
 * Affiche version, credits, informations systeme.
 */
require_once __DIR__ . '/../API/core.php';
require_once __DIR__ . '/includes/admin_functions.php';

requireAuth();
requireRole('administrateur');

$versionFile = BASE_PATH . '/version.json';
$versionData = file_exists($versionFile) ? json_decode(file_get_contents($versionFile), true) : [];
$version = $versionData['version'] ?? '?';
$codename = $versionData['codename'] ?? '';

// Load all modules with credits
$pdo = getPDO();
$modules = [];
try {
    $stmt = $pdo->query("SELECT module_key, label, author, author_url, license, is_core, enabled FROM modules_config ORDER BY label");
    $modules = $stmt->fetchAll(\PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
    // Columns might not exist yet (migration pending)
}

// Fallback: read from module.json files
if (empty($modules) || !isset($modules[0]['author'])) {
    $modules = [];
    $dirs = glob(BASE_PATH . '/*/module.json');
    foreach ($dirs as $file) {
        $data = json_decode(file_get_contents($file), true);
        if (!$data) continue;
        $modules[] = [
            'module_key' => $data['key'] ?? basename(dirname($file)),
            'label'      => is_array($data['name'] ?? null) ? ($data['name']['fr'] ?? $data['key']) : ($data['name'] ?? $data['key']),
            'author'     => $data['author'] ?? 'Fronote Team',
            'author_url' => $data['author_url'] ?? '',
            'license'    => $data['license'] ?? 'MIT',
            'is_core'    => $data['core'] ?? false,
            'enabled'    => 1,
        ];
    }
    usort($modules, fn($a, $b) => strcasecmp($a['label'], $b['label']));
}

$pageTitle = 'A propos';
$activePage = 'systeme';
require_once __DIR__ . '/../templates/shared_header.php';
?>

<div class="topbar">
    <div class="topbar-left">
        <h1 class="page-title"><i class="fas fa-info-circle"></i> A propos de Fronote</h1>
    </div>
</div>

<div class="content-body p-lg">

    <div class="d-flex gap-lg flex-wrap mb-lg">
        <?= ui_stat_card('Version', $version . ($codename ? " ($codename)" : ''), ['icon' => 'fas fa-tag', 'color' => 'primary']) ?>
        <?= ui_stat_card('Modules', (string)count($modules), ['icon' => 'fas fa-puzzle-piece', 'color' => 'success']) ?>
        <?= ui_stat_card('PHP', PHP_VERSION, ['icon' => 'fab fa-php', 'color' => 'warning']) ?>
        <?= ui_stat_card('Environnement', strtoupper(getenv('APP_ENV') ?: 'production'), ['icon' => 'fas fa-server', 'color' => 'danger']) ?>
    </div>

    <?php
    $columns = [
        ['label' => 'Module', 'width' => '30%'],
        'Auteur',
        'Licence',
        ['label' => 'Type', 'width' => '100px'],
    ];
    $rows = [];
    foreach ($modules as $m) {
        $authorHtml = e($m['author'] ?? 'Fronote Team');
        if (!empty($m['author_url'])) {
            $authorHtml = '<a href="' . e($m['author_url']) . '" target="_blank" rel="noopener" class="text-primary">' . $authorHtml . '</a>';
        }
        $rows[] = [
            '<strong>' . e($m['label']) . '</strong> <span class="text-muted fs-xs">' . e($m['module_key']) . '</span>',
            $authorHtml,
            ui_badge($m['license'] ?? 'MIT', 'default'),
            ($m['is_core'] ?? false) ? ui_badge('Core', 'primary') : ui_badge('Module', 'success'),
        ];
    }
    echo ui_card('Credits des modules', ui_table($columns, $rows, ['empty_message' => 'Aucun module trouve.']), ['icon' => 'fas fa-users']);
    ?>

    <?= ui_card('Systeme', '
        <div class="d-flex flex-col gap-sm">
            <div><strong>Serveur :</strong> ' . e(php_uname('s') . ' ' . php_uname('r')) . '</div>
            <div><strong>PHP :</strong> ' . e(PHP_VERSION) . '</div>
            <div><strong>Extensions :</strong> ' . e(implode(', ', ['pdo', 'pdo_mysql', 'json', 'mbstring', 'openssl', 'curl', 'zip'])) . '</div>
            <div><strong>Memoire max :</strong> ' . e(ini_get('memory_limit')) . '</div>
            <div><strong>Chemin :</strong> ' . e(BASE_PATH) . '</div>
        </div>
    ', ['icon' => 'fas fa-microchip']) ?>

</div>

<?php require_once __DIR__ . '/../templates/shared_footer.php'; ?>
