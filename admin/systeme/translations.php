<?php
/**
 * Administration — Gestion des traductions
 * Vue, modification, ajout de langues.
 */
require_once __DIR__ . '/../../API/core.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAuth();
requireRole('administrateur');

$translator = app('translator');
$locales = $translator->getSupportedLocales();
$localeNames = \API\Services\TranslationService::getLocaleNames();
$langPath = BASE_PATH . '/lang';

// Handle AJAX save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $csrf = app('csrf');
    if (!$csrf->validate($_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        echo json_encode(['success' => false, 'error' => 'CSRF invalid']);
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'save_translation') {
        $locale = $_POST['locale'] ?? '';
        $domain = $_POST['domain'] ?? '';
        $key = $_POST['key'] ?? '';
        $value = $_POST['value'] ?? '';

        if (!in_array($locale, $locales) || !$domain || !$key) {
            echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
            exit;
        }

        // Determine file path
        $filePath = $langPath . '/' . $locale . '/' . $domain . '.json';
        if (strpos($domain, 'modules/') === 0) {
            $filePath = $langPath . '/' . $locale . '/' . $domain . '.json';
        }

        $translations = [];
        if (file_exists($filePath)) {
            $translations = json_decode(file_get_contents($filePath), true) ?: [];
        }
        $translations[$key] = $value;

        $dir = dirname($filePath);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        file_put_contents($filePath, json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");

        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action']);
    exit;
}

// Build coverage matrix
$coverage = [];
$frPath = $langPath . '/fr';
$domains = ['common', 'auth', 'admin'];

// Add module domains
$moduleFiles = glob($frPath . '/modules/*.json');
foreach ($moduleFiles as $f) {
    $domains[] = 'modules/' . basename($f, '.json');
}

foreach ($domains as $domain) {
    $frFile = $frPath . '/' . $domain . '.json';
    $frKeys = file_exists($frFile) ? array_keys(json_decode(file_get_contents($frFile), true) ?: []) : [];
    $frCount = count($frKeys);

    $coverage[$domain] = ['fr_count' => $frCount, 'locales' => []];
    foreach ($locales as $locale) {
        $file = $langPath . '/' . $locale . '/' . $domain . '.json';
        $keys = file_exists($file) ? array_keys(json_decode(file_get_contents($file), true) ?: []) : [];
        $translated = count(array_intersect($keys, $frKeys));
        $pct = $frCount > 0 ? round($translated / $frCount * 100) : 0;
        $coverage[$domain]['locales'][$locale] = ['count' => $translated, 'pct' => $pct];
    }
}

$csrfToken = app('csrf')->generate();
$pageTitle = 'Traductions';
$activePage = 'systeme';

require_once __DIR__ . '/../../templates/shared_header.php';
?>

<div class="topbar">
    <div class="topbar-left">
        <h1 class="page-title"><i class="fas fa-language"></i> Gestion des traductions</h1>
    </div>
</div>

<div class="content-body p-lg">

    <div class="d-flex gap-lg flex-wrap mb-lg">
        <?= ui_stat_card('Langues', (string)count($locales), ['icon' => 'fas fa-globe', 'color' => 'primary']) ?>
        <?= ui_stat_card('Domaines', (string)count($domains), ['icon' => 'fas fa-folder', 'color' => 'success']) ?>
    </div>

    <?php
    // Coverage table
    $cols = [['label' => 'Domaine', 'width' => '25%']];
    foreach ($locales as $l) {
        $cols[] = ['label' => strtoupper($l) . ' ' . ($localeNames[$l] ?? ''), 'align' => 'center'];
    }

    $rows = [];
    foreach ($coverage as $domain => $data) {
        $row = ['<strong>' . e($domain) . '</strong> <span class="text-muted fs-xs">(' . $data['fr_count'] . ' cles)</span>'];
        foreach ($locales as $l) {
            $pct = $data['locales'][$l]['pct'] ?? 0;
            $variant = $pct === 100 ? 'success' : ($pct >= 50 ? 'warning' : 'danger');
            $row[] = ui_badge($pct . '%', $variant);
        }
        $rows[] = $row;
    }

    echo ui_card('Couverture des traductions', ui_table($cols, $rows), ['icon' => 'fas fa-chart-bar']);
    ?>

    <div class="mt-lg">
    <?= ui_card('Editeur de traductions', '
        <div class="d-flex gap-md mb-md flex-wrap">
            <select id="editLocale" class="form-control" style="max-width:200px;">
                ' . implode('', array_map(fn($l) => '<option value="' . e($l) . '">' . strtoupper($l) . ' - ' . e($localeNames[$l] ?? $l) . '</option>', $locales)) . '
            </select>
            <select id="editDomain" class="form-control" style="max-width:300px;">
                ' . implode('', array_map(fn($d) => '<option value="' . e($d) . '">' . e($d) . '</option>', $domains)) . '
            </select>
            <button type="button" class="ui-btn ui-btn--primary ui-btn--sm" onclick="loadTranslations()">Charger</button>
        </div>
        <div id="translationEditor"></div>
    ', ['icon' => 'fas fa-edit']) ?>
    </div>
</div>

<script nonce="<?= $_hdr_nonce ?>">
function loadTranslations() {
    var locale = document.getElementById('editLocale').value;
    var domain = document.getElementById('editDomain').value;
    var container = document.getElementById('translationEditor');
    container.innerHTML = '<div class="text-muted p-md">Chargement...</div>';

    // Load FR reference + selected locale
    Promise.all([
        fetch('<?= $rootPrefix ?>lang/fr/' + domain + '.json').then(r => r.ok ? r.json() : {}),
        fetch('<?= $rootPrefix ?>lang/' + locale + '/' + domain + '.json').then(r => r.ok ? r.json() : {})
    ]).then(function(data) {
        var frKeys = data[0], localKeys = data[1];
        var html = '<table class="ui-table ui-table--striped"><thead><tr><th>Cle</th><th>FR (reference)</th><th>' + locale.toUpperCase() + '</th><th></th></tr></thead><tbody>';
        Object.keys(frKeys).forEach(function(key) {
            var val = localKeys[key] || '';
            var missing = !val ? ' style="background:rgba(239,68,68,0.05)"' : '';
            html += '<tr' + missing + '><td class="text-muted fs-xs">' + key + '</td><td class="fs-sm">' + (frKeys[key] || '') + '</td>';
            html += '<td><input type="text" class="form-control" data-key="' + key + '" value="' + val.replace(/"/g, '&quot;') + '" style="font-size:13px;"></td>';
            html += '<td><button type="button" class="ui-btn ui-btn--ghost ui-btn--sm" onclick="saveKey(\'' + locale + '\',\'' + domain + '\',\'' + key + '\',this)">Sauver</button></td></tr>';
        });
        html += '</tbody></table>';
        container.innerHTML = html;
    });
}

function saveKey(locale, domain, key, btn) {
    var input = btn.closest('tr').querySelector('input[data-key="' + key + '"]');
    var value = input.value;
    var formData = new FormData();
    formData.append('action', 'save_translation');
    formData.append('locale', locale);
    formData.append('domain', domain);
    formData.append('key', key);
    formData.append('value', value);
    formData.append('_token', '<?= $csrfToken ?>');

    fetch(window.location.href, { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            btn.textContent = '✓';
            btn.style.color = '#22c55e';
            setTimeout(function() { btn.textContent = 'Sauver'; btn.style.color = ''; }, 1500);
        }
    });
}
</script>

<?php require_once __DIR__ . '/../../templates/shared_footer.php'; ?>
