<?php
/**
 * Administration — Marketplace de modules
 * Catalogue, installation et désinstallation de modules depuis le registre distant.
 */
require_once __DIR__ . '/../../API/core.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAuth();
requireRole('administrateur');

$marketplace = app('marketplace');
$moduleService = app('modules');
$message = '';
$messageType = '';

// ─── Actions POST ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['csrf_token'] ?? '') === ($_SESSION['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    $key = $_POST['module_key'] ?? '';

    if ($action === 'install' && $key) {
        $result = $marketplace->installModule($key);
        $message = $result['message'] ?? $result['error'] ?? '';
        $messageType = $result['success'] ? 'success' : 'error';
        if ($result['success']) {
            logAudit('marketplace.install', 'marketplace_installs', null, null, ['module' => $key]);
        }
    }

    if ($action === 'uninstall' && $key) {
        $result = $marketplace->uninstallModule($key);
        $message = $result['message'] ?? $result['error'] ?? '';
        $messageType = $result['success'] ? 'success' : 'error';
        if ($result['success']) {
            logAudit('marketplace.uninstall', 'marketplace_installs', null, null, ['module' => $key]);
        }
    }
}

// ─── Données ─────────────────────────────────────────────────────
$search = trim($_GET['q'] ?? '');
$catalog = $search ? $marketplace->search($search) : $marketplace->getCatalog('module');
$installed = $marketplace->getInstalled();
$installedKeys = array_column($installed, 'item_key');
$updates = $marketplace->checkUpdates();

$pageTitle = 'Marketplace';
$currentPage = 'marketplace';
$extraCss = ['../../assets/css/admin.css'];

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

include __DIR__ . '/../includes/header.php';
?>

<style>
.mp-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px}
.mp-search{display:flex;gap:8px}
.mp-search input{padding:8px 14px;border:1px solid #e2e8f0;border-radius:6px;font-size:.9em;width:280px}
.mp-search button{padding:8px 16px;background:#667eea;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:.9em}
.mp-tabs{display:flex;gap:8px;margin-bottom:20px}
.mp-tab{padding:6px 16px;border-radius:20px;background:#f7fafc;border:1px solid #e2e8f0;cursor:pointer;font-size:.85em;text-decoration:none;color:#4a5568}
.mp-tab.active{background:#667eea;color:#fff;border-color:#667eea}
.mp-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:16px}
.mp-card{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:20px;transition:.2s}
.mp-card:hover{box-shadow:0 4px 12px rgba(0,0,0,.08)}
.mp-card-header{display:flex;gap:12px;align-items:flex-start;margin-bottom:12px}
.mp-card-icon{width:48px;height:48px;border-radius:10px;background:#f0f4ff;display:flex;align-items:center;justify-content:center;font-size:1.3em;color:#667eea;flex-shrink:0}
.mp-card-info{flex:1}
.mp-card-name{font-weight:700;font-size:1em;color:#2d3748}
.mp-card-author{font-size:.8em;color:#a0aec0;margin-top:2px}
.mp-card-desc{font-size:.85em;color:#718096;line-height:1.5;margin-bottom:12px}
.mp-card-footer{display:flex;justify-content:space-between;align-items:center}
.mp-card-version{font-size:.78em;color:#a0aec0}
.mp-card-tags{display:flex;gap:4px;flex-wrap:wrap}
.mp-tag{font-size:.7em;padding:2px 8px;border-radius:10px;background:#edf2f7;color:#718096}
.mp-btn{padding:6px 14px;border:none;border-radius:6px;cursor:pointer;font-size:.82em;font-weight:600}
.mp-btn-install{background:#48bb78;color:#fff}
.mp-btn-installed{background:#edf2f7;color:#a0aec0;cursor:default}
.mp-btn-uninstall{background:#fc8181;color:#fff}
.mp-empty{text-align:center;padding:60px 20px;color:#a0aec0}
.mp-empty i{font-size:3em;margin-bottom:12px;display:block}
.mp-updates{background:#fffff0;border:1px solid #fefcbf;border-radius:8px;padding:14px 18px;margin-bottom:20px}
.mp-updates strong{color:#d69e2e}
</style>

<?php if ($message): ?>
<div class="alert alert-<?= $messageType ?>" style="margin-bottom:16px;padding:12px 16px;border-radius:8px;background:<?= $messageType === 'success' ? '#f0fff4' : '#fff5f5' ?>;color:<?= $messageType === 'success' ? '#276749' : '#c53030' ?>;border:1px solid <?= $messageType === 'success' ? '#c6f6d5' : '#fed7d7' ?>">
    <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<div class="mp-header">
    <h2><i class="fas fa-store"></i> Marketplace</h2>
    <form class="mp-search" method="GET">
        <input type="text" name="q" placeholder="Rechercher un module..." value="<?= htmlspecialchars($search) ?>">
        <button type="submit"><i class="fas fa-search"></i> Rechercher</button>
    </form>
</div>

<?php if (!empty($updates)): ?>
<div class="mp-updates">
    <strong><i class="fas fa-arrow-circle-up"></i> <?= count($updates) ?> mise(s) à jour disponible(s)</strong>
    <?php foreach ($updates as $upd): ?>
    — <?= htmlspecialchars($upd['key']) ?> : <?= htmlspecialchars($upd['current_version']) ?> → <?= htmlspecialchars($upd['new_version']) ?>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="mp-tabs">
    <a href="index.php" class="mp-tab">Modules installés</a>
    <a href="marketplace.php" class="mp-tab active">Marketplace</a>
    <a href="permissions.php" class="mp-tab">Permissions</a>
</div>

<?php if (empty($catalog)): ?>
<div class="mp-empty">
    <i class="fas fa-cloud-download-alt"></i>
    <p><?= $search ? 'Aucun résultat pour « ' . htmlspecialchars($search) . ' ».' : 'Le catalogue est vide ou inaccessible.' ?></p>
    <p style="font-size:.85em">Vérifiez votre connexion internet ou configurez <code>MARKETPLACE_REGISTRY_URL</code> dans le fichier <code>.env</code>.</p>
</div>
<?php else: ?>
<div class="mp-grid">
    <?php foreach ($catalog as $item): ?>
    <?php $isInstalled = in_array($item['key'] ?? '', $installedKeys, true); ?>
    <div class="mp-card">
        <div class="mp-card-header">
            <div class="mp-card-icon"><i class="<?= htmlspecialchars($item['icon'] ?? 'fas fa-puzzle-piece') ?>"></i></div>
            <div class="mp-card-info">
                <div class="mp-card-name"><?= htmlspecialchars($item['name'] ?? $item['key'] ?? '') ?></div>
                <div class="mp-card-author">par <?= htmlspecialchars($item['author'] ?? 'Inconnu') ?></div>
            </div>
        </div>
        <div class="mp-card-desc"><?= htmlspecialchars($item['description'] ?? '') ?></div>
        <div class="mp-card-footer">
            <div>
                <span class="mp-card-version">v<?= htmlspecialchars($item['version'] ?? '1.0') ?></span>
                <div class="mp-card-tags">
                    <?php foreach (($item['tags'] ?? []) as $tag): ?>
                    <span class="mp-tag"><?= htmlspecialchars($tag) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php if ($isInstalled): ?>
            <form method="POST" style="display:inline" onsubmit="return confirm('Désinstaller ce module ?')">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="action" value="uninstall">
                <input type="hidden" name="module_key" value="<?= htmlspecialchars($item['key'] ?? '') ?>">
                <button class="mp-btn mp-btn-uninstall" type="submit"><i class="fas fa-trash"></i> Désinstaller</button>
            </form>
            <?php else: ?>
            <form method="POST" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="action" value="install">
                <input type="hidden" name="module_key" value="<?= htmlspecialchars($item['key'] ?? '') ?>">
                <button class="mp-btn mp-btn-install" type="submit"><i class="fas fa-download"></i> Installer</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
