<?php
/**
 * Administration — Gestion des Feature Flags
 * Toggle, creation, configuration des fonctionnalites.
 */
require_once __DIR__ . '/../../API/core.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAuth();
requireRole('administrateur');

$features = app('features');
$csrf = app('csrf');
$message = '';
$messageType = '';

// Handle AJAX toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    if (!$csrf->validate($_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        echo json_encode(['success' => false, 'error' => 'CSRF invalid']);
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'toggle') {
        $key = $_POST['flag_key'] ?? '';
        $enabled = (int)($_POST['enabled'] ?? 0);
        $ok = $features->setEnabled($key, (bool)$enabled);
        echo json_encode(['success' => $ok]);
        exit;
    }

    if ($action === 'create') {
        $key = trim($_POST['flag_key'] ?? '');
        $label = trim($_POST['label'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $enabled = !empty($_POST['enabled']);
        $types = !empty($_POST['establishment_types']) ? array_map('trim', explode(',', $_POST['establishment_types'])) : null;

        if (!$key || !$label) {
            echo json_encode(['success' => false, 'error' => 'Cle et libelle requis']);
            exit;
        }

        // Use create method - description column maps to label in this schema
        try {
            $pdo = getPDO();
            $stmt = $pdo->prepare("INSERT INTO feature_flags (flag_key, label, description, enabled, establishment_types) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$key, $label, $desc, (int)$enabled, $types ? json_encode($types) : null]);
            $features->clearCache();
            echo json_encode(['success' => true]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'delete') {
        $key = $_POST['flag_key'] ?? '';
        $ok = $features->delete($key);
        echo json_encode(['success' => $ok]);
        exit;
    }

    if ($action === 'batch_toggle') {
        $states = json_decode($_POST['states'] ?? '{}', true) ?: [];
        $count = $features->batchSetEnabled($states);
        echo json_encode(['success' => true, 'updated' => $count]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action']);
    exit;
}

// Load data
$allFlags = $features->getAll();
$grouped = $features->getGroupedByModule();
$enabledCount = count(array_filter($allFlags, fn($f) => !empty($f['enabled'])));
$totalCount = count($allFlags);
$csrfToken = $csrf->generate();

$pageTitle = 'Feature Flags';
$activePage = 'systeme';

require_once __DIR__ . '/../../templates/shared_header.php';
?>

<div class="topbar">
    <div class="topbar-left">
        <h1 class="page-title"><i class="fas fa-toggle-on"></i> Feature Flags</h1>
    </div>
    <div class="topbar-right">
        <button type="button" class="ui-btn ui-btn--primary ui-btn--sm" onclick="openCreateModal()">
            <i class="fas fa-plus"></i> Nouveau flag
        </button>
    </div>
</div>

<div class="content-body p-lg">

    <div class="d-flex gap-lg flex-wrap mb-lg">
        <?= ui_stat_card('Total flags', (string)$totalCount, ['icon' => 'fas fa-flag', 'color' => 'primary']) ?>
        <?= ui_stat_card('Actifs', (string)$enabledCount, ['icon' => 'fas fa-check-circle', 'color' => 'success']) ?>
        <?= ui_stat_card('Inactifs', (string)($totalCount - $enabledCount), ['icon' => 'fas fa-times-circle', 'color' => 'danger']) ?>
        <?= ui_stat_card('Modules', (string)count($grouped), ['icon' => 'fas fa-cubes', 'color' => 'warning']) ?>
    </div>

    <!-- Filter bar -->
    <div class="d-flex gap-md mb-md flex-wrap" style="align-items:center;">
        <input type="text" id="flagSearch" class="form-control" placeholder="Rechercher un flag..." style="max-width:300px;" oninput="filterFlags()">
        <select id="flagFilter" class="form-control" style="max-width:200px;" onchange="filterFlags()">
            <option value="all">Tous</option>
            <option value="enabled">Actifs uniquement</option>
            <option value="disabled">Inactifs uniquement</option>
        </select>
        <div style="margin-left:auto;">
            <button type="button" class="ui-btn ui-btn--ghost ui-btn--sm" onclick="expandAll()"><i class="fas fa-expand-arrows-alt"></i> Tout ouvrir</button>
            <button type="button" class="ui-btn ui-btn--ghost ui-btn--sm" onclick="collapseAll()"><i class="fas fa-compress-arrows-alt"></i> Tout fermer</button>
        </div>
    </div>

    <!-- Flags grouped by module -->
    <?php foreach ($grouped as $module => $flags): ?>
    <?php
        $activeInModule = count(array_filter($flags, fn($f) => !empty($f['enabled'])));
        $totalInModule = count($flags);
    ?>
    <div class="flag-module-group mb-md" data-module="<?= e($module) ?>">
        <div class="flag-module-header" onclick="this.parentElement.classList.toggle('collapsed')">
            <div class="d-flex gap-md" style="align-items:center;">
                <i class="fas fa-chevron-down flag-chevron"></i>
                <strong class="fs-md"><?= e(ucfirst($module)) ?></strong>
                <span class="text-muted fs-xs">(<?= $activeInModule ?>/<?= $totalInModule ?> actifs)</span>
            </div>
            <div>
                <?= ui_badge($activeInModule . '/' . $totalInModule, $activeInModule === $totalInModule ? 'success' : ($activeInModule > 0 ? 'warning' : 'danger')) ?>
            </div>
        </div>
        <div class="flag-module-body">
            <?php foreach ($flags as $flag): ?>
            <div class="flag-row" data-key="<?= e($flag['flag_key']) ?>" data-enabled="<?= $flag['enabled'] ? '1' : '0' ?>">
                <div class="flag-info">
                    <div class="flag-key"><?= e($flag['flag_key']) ?></div>
                    <div class="flag-label"><?= e($flag['label'] ?? '') ?></div>
                    <?php if (!empty($flag['description'])): ?>
                    <div class="flag-desc text-muted fs-xs"><?= e($flag['description']) ?></div>
                    <?php endif; ?>
                    <?php if ($flag['establishment_types']): ?>
                    <div class="mt-xs">
                        <?php foreach ($flag['establishment_types'] as $type): ?>
                        <?= ui_badge($type, 'primary') ?>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="flag-actions d-flex gap-sm" style="align-items:center;">
                    <?php if ($flag['config']): ?>
                    <span class="text-muted fs-xs" title="<?= e(json_encode($flag['config'])) ?>"><i class="fas fa-cog"></i></span>
                    <?php endif; ?>
                    <label class="flag-toggle">
                        <input type="checkbox" <?= $flag['enabled'] ? 'checked' : '' ?> onchange="toggleFlag('<?= e($flag['flag_key']) ?>', this.checked)">
                        <span class="flag-toggle-slider"></span>
                    </label>
                    <button type="button" class="ui-btn ui-btn--ghost ui-btn--sm" onclick="deleteFlag('<?= e($flag['flag_key']) ?>')" title="Supprimer">
                        <i class="fas fa-trash-alt text-danger"></i>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>

</div>

<!-- Create modal -->
<?= ui_modal('createFlagModal', 'Nouveau Feature Flag', '
    <form id="createFlagForm" onsubmit="return createFlag(event)">
        ' . ui_form_group('Cle (module.feature)', '<input type="text" name="flag_key" class="form-control" placeholder="module.feature_name" required pattern="[a-z0-9_.]+">') . '
        ' . ui_form_group('Libelle', '<input type="text" name="label" class="form-control" placeholder="Nom affiche" required>') . '
        ' . ui_form_group('Description', '<textarea name="description" class="form-control" rows="2" placeholder="Description optionnelle"></textarea>') . '
        ' . ui_form_group('Types etablissement', '<input type="text" name="establishment_types" class="form-control" placeholder="Vide = tous, ou: college,lycee,superieur">') . '
        ' . ui_form_group('', '<label class="d-flex gap-sm" style="align-items:center;cursor:pointer;"><input type="checkbox" name="enabled" value="1"> Actif par defaut</label>') . '
        <div class="d-flex gap-md mt-md" style="justify-content:flex-end;">
            <button type="button" class="ui-btn ui-btn--ghost" onclick="FronoteModal.close(\'createFlagModal\')">Annuler</button>
            <button type="submit" class="ui-btn ui-btn--primary">Creer</button>
        </div>
    </form>
') ?>

<style nonce="<?= $_hdr_nonce ?>">
.flag-module-header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 12px 16px; background: var(--bg-light); border-radius: 8px;
    cursor: pointer; user-select: none; transition: background 0.15s;
}
.flag-module-header:hover { background: var(--primary-bg); }
.flag-chevron { transition: transform 0.2s; font-size: 12px; color: var(--text-muted); }
.collapsed .flag-chevron { transform: rotate(-90deg); }
.collapsed .flag-module-body { display: none; }
.flag-module-body { padding: 0 8px; }
.flag-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 10px 12px; border-bottom: 1px solid var(--border-color);
    transition: background 0.1s;
}
.flag-row:hover { background: rgba(102,126,234,0.03); }
.flag-row:last-child { border-bottom: none; }
.flag-key { font-family: monospace; font-size: 13px; color: var(--primary); font-weight: 600; }
.flag-label { font-size: 13px; color: var(--text); }
.flag-desc { margin-top: 2px; }
.flag-toggle { position: relative; display: inline-block; width: 44px; height: 24px; cursor: pointer; }
.flag-toggle input { opacity: 0; width: 0; height: 0; }
.flag-toggle-slider {
    position: absolute; inset: 0; background: #ccc; border-radius: 24px;
    transition: background 0.2s;
}
.flag-toggle-slider::before {
    content: ''; position: absolute; left: 3px; top: 3px;
    width: 18px; height: 18px; background: #fff; border-radius: 50%;
    transition: transform 0.2s;
}
.flag-toggle input:checked + .flag-toggle-slider { background: var(--primary); }
.flag-toggle input:checked + .flag-toggle-slider::before { transform: translateX(20px); }
.flag-row[data-enabled="0"] .flag-info { opacity: 0.5; }
</style>

<script nonce="<?= $_hdr_nonce ?>">
var csrfToken = '<?= $csrfToken ?>';

function toggleFlag(key, enabled) {
    var row = document.querySelector('.flag-row[data-key="' + key + '"]');
    if (row) row.setAttribute('data-enabled', enabled ? '1' : '0');

    var fd = new FormData();
    fd.append('action', 'toggle');
    fd.append('flag_key', key);
    fd.append('enabled', enabled ? '1' : '0');
    fd.append('_token', csrfToken);

    fetch(window.location.href, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success && typeof FronoteToast !== 'undefined') {
            FronoteToast.success(key + ' ' + (enabled ? 'active' : 'desactive'));
        }
    });
}

function deleteFlag(key) {
    if (!confirm('Supprimer le flag "' + key + '" ?')) return;
    var fd = new FormData();
    fd.append('action', 'delete');
    fd.append('flag_key', key);
    fd.append('_token', csrfToken);

    fetch(window.location.href, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            var row = document.querySelector('.flag-row[data-key="' + key + '"]');
            if (row) row.remove();
            if (typeof FronoteToast !== 'undefined') FronoteToast.success('Flag supprime');
        }
    });
}

function openCreateModal() {
    FronoteModal.open('createFlagModal');
}

function createFlag(e) {
    e.preventDefault();
    var form = document.getElementById('createFlagForm');
    var fd = new FormData(form);
    fd.append('action', 'create');
    fd.append('_token', csrfToken);

    fetch(window.location.href, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            FronoteModal.close('createFlagModal');
            window.location.reload();
        } else {
            alert(data.error || 'Erreur');
        }
    });
    return false;
}

function filterFlags() {
    var search = document.getElementById('flagSearch').value.toLowerCase();
    var filter = document.getElementById('flagFilter').value;

    document.querySelectorAll('.flag-row').forEach(function(row) {
        var key = row.getAttribute('data-key').toLowerCase();
        var enabled = row.getAttribute('data-enabled') === '1';
        var label = (row.querySelector('.flag-label') || {}).textContent || '';
        var matchSearch = !search || key.indexOf(search) !== -1 || label.toLowerCase().indexOf(search) !== -1;
        var matchFilter = filter === 'all' || (filter === 'enabled' && enabled) || (filter === 'disabled' && !enabled);
        row.style.display = (matchSearch && matchFilter) ? '' : 'none';
    });

    // Hide empty groups
    document.querySelectorAll('.flag-module-group').forEach(function(group) {
        var visible = group.querySelectorAll('.flag-row:not([style*="display: none"])').length;
        group.style.display = visible > 0 ? '' : 'none';
    });
}

function expandAll() {
    document.querySelectorAll('.flag-module-group.collapsed').forEach(function(g) { g.classList.remove('collapsed'); });
}
function collapseAll() {
    document.querySelectorAll('.flag-module-group:not(.collapsed)').forEach(function(g) { g.classList.add('collapsed'); });
}
</script>

<?php require_once __DIR__ . '/../../templates/shared_footer.php'; ?>
