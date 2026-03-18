<?php
/**
 * Administration — Gestion des modules
 * Active/désactive les modules, configure individuellement chacun.
 */
require_once __DIR__ . '/../../API/core.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAuth();
requireRole('administrateur');

$pdo = getPDO();
$moduleService = app('modules');
$message = '';
$messageType = '';

// ─── Traitement des actions POST ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['csrf_token'] ?? '') === ($_SESSION['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';

    // Toggle un module
    if ($action === 'toggle') {
        $key = $_POST['module_key'] ?? '';
        $enabled = !empty($_POST['enabled']);
        if ($moduleService->isCore($key)) {
            $message = 'Les modules système ne peuvent pas être désactivés.';
            $messageType = 'error';
        } elseif ($moduleService->setEnabled($key, $enabled)) {
            $state = $enabled ? 'activé' : 'désactivé';
            $module = $moduleService->get($key);
            logAudit('module.' . ($enabled ? 'enabled' : 'disabled'), 'modules_config', null, null, ['module' => $key]);
            $message = 'Module « ' . htmlspecialchars($module['label'] ?? $key) . ' » ' . $state . ' avec succès.';
            $messageType = 'success';
        } else {
            $message = 'Erreur lors de la modification du module.';
            $messageType = 'error';
        }
    }

    // Batch toggle
    if ($action === 'batch') {
        $enabledModules = $_POST['modules'] ?? [];
        $all = $moduleService->getAll();
        $count = 0;
        foreach ($all as $key => $mod) {
            if ($mod['is_core']) continue;
            $shouldEnable = in_array($key, $enabledModules);
            if ((bool)$mod['enabled'] !== $shouldEnable) {
                $moduleService->setEnabled($key, $shouldEnable);
                $count++;
            }
        }
        if ($count > 0) {
            logAudit('module.batch_update', 'modules_config', null, null, ['changed' => $count]);
            $message = "{$count} module(s) mis à jour.";
            $messageType = 'success';
        } else {
            $message = 'Aucune modification.';
            $messageType = 'info';
        }
    }
}

// ─── Données ─────────────────────────────────────────────────────────────────
$categories = $moduleService->getByCategory();
$categoryLabels = \API\Services\ModuleService::categoryLabels();
$stats = $moduleService->getStats();

$pageTitle = 'Gestion des modules';
$currentPage = 'modules';
$extraCss = ['../../assets/css/admin.css'];

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

include __DIR__ . '/../includes/sub_header.php';
?>

<style>
.modules-stats{display:flex;gap:16px;margin-bottom:24px}
.mod-stat{background:#f8f9fa;border-radius:8px;padding:14px 20px;flex:1;text-align:center}
.mod-stat-value{font-size:1.8em;font-weight:700;color:#333}
.mod-stat-label{font-size:.85em;color:#718096;margin-top:2px}
.mod-category{margin-bottom:28px}
.mod-category-title{font-size:1.1em;font-weight:700;color:#4a5568;margin-bottom:12px;padding-bottom:8px;border-bottom:2px solid #e2e8f0;display:flex;align-items:center;gap:8px}
.mod-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:14px}
.mod-card{background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:16px;display:flex;align-items:flex-start;gap:14px;transition:.2s}
.mod-card:hover{box-shadow:0 2px 8px rgba(0,0,0,.06)}
.mod-card.disabled{opacity:.55;background:#fafafa}
.mod-card.core{border-left:3px solid #667eea}
.mod-icon{width:42px;height:42px;border-radius:8px;background:#f0f4ff;display:flex;align-items:center;justify-content:center;font-size:1.1em;color:#667eea;flex-shrink:0}
.mod-info{flex:1;min-width:0}
.mod-name{font-weight:600;color:#2d3748;font-size:.95em}
.mod-desc{font-size:.82em;color:#a0aec0;margin-top:2px;line-height:1.4}
.mod-badges{display:flex;gap:6px;margin-top:6px;flex-wrap:wrap}
.mod-badge{font-size:.72em;padding:2px 8px;border-radius:10px;font-weight:600}
.mod-badge-core{background:#ebf4ff;color:#3182ce}
.mod-badge-on{background:#c6f6d5;color:#276749}
.mod-badge-off{background:#fed7d7;color:#9b2c2c}
.mod-actions{flex-shrink:0;display:flex;flex-direction:column;align-items:center;gap:6px}
.toggle-switch{position:relative;width:44px;height:24px}
.toggle-switch input{opacity:0;width:0;height:0}
.toggle-slider{position:absolute;inset:0;background:#cbd5e0;border-radius:12px;cursor:pointer;transition:.2s}
.toggle-slider::before{content:'';position:absolute;width:18px;height:18px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.2s}
.toggle-switch input:checked+.toggle-slider{background:#48bb78}
.toggle-switch input:checked+.toggle-slider::before{transform:translateX(20px)}
.toggle-switch input:disabled+.toggle-slider{opacity:.5;cursor:not-allowed}
.mod-config-btn{font-size:.78em;color:#667eea;text-decoration:none;display:flex;align-items:center;gap:4px}
.mod-config-btn:hover{text-decoration:underline}
.batch-bar{position:sticky;bottom:0;background:#fff;border-top:2px solid #e2e8f0;padding:14px 20px;display:none;align-items:center;justify-content:space-between;box-shadow:0 -2px 8px rgba(0,0,0,.05);z-index:100}
.batch-bar.visible{display:flex}
</style>

<!-- Statistiques -->
<div class="modules-stats">
    <div class="mod-stat">
        <div class="mod-stat-value"><?= $stats['total'] ?></div>
        <div class="mod-stat-label">Modules total</div>
    </div>
    <div class="mod-stat">
        <div class="mod-stat-value" style="color:#48bb78"><?= $stats['enabled'] ?></div>
        <div class="mod-stat-label">Activés</div>
    </div>
    <div class="mod-stat">
        <div class="mod-stat-value" style="color:#e53e3e"><?= $stats['total'] - $stats['enabled'] ?></div>
        <div class="mod-stat-label">Désactivés</div>
    </div>
    <div class="mod-stat">
        <div class="mod-stat-value" style="color:#667eea"><?= $stats['core'] ?></div>
        <div class="mod-stat-label">Système (verrouillés)</div>
    </div>
</div>

<?php if ($message): ?>
<div class="msg msg-<?= $messageType === 'error' ? 'error' : ($messageType === 'success' ? 'success' : 'warn') ?>" style="padding:12px 16px;border-radius:6px;margin-bottom:16px;font-size:.92em">
    <?= $messageType === 'success' ? '✅' : ($messageType === 'error' ? '❌' : 'ℹ️') ?> <?= $message ?>
</div>
<?php endif; ?>

<!-- Modules par catégorie -->
<?php foreach ($categories as $catKey => $modules): ?>
<div class="mod-category">
    <div class="mod-category-title">
        <span><?= htmlspecialchars($categoryLabels[$catKey] ?? ucfirst($catKey)) ?></span>
        <span style="font-size:.78em;font-weight:400;color:#a0aec0">(<?= count($modules) ?>)</span>
    </div>
    <div class="mod-grid">
        <?php foreach ($modules as $mod): ?>
        <div class="mod-card <?= empty($mod['enabled']) ? 'disabled' : '' ?> <?= !empty($mod['is_core']) ? 'core' : '' ?>">
            <div class="mod-icon"><i class="<?= htmlspecialchars($mod['icon']) ?>"></i></div>
            <div class="mod-info">
                <div class="mod-name"><?= htmlspecialchars($mod['label']) ?></div>
                <div class="mod-desc"><?= htmlspecialchars($mod['description'] ?? '') ?></div>
                <div class="mod-badges">
                    <?php if (!empty($mod['is_core'])): ?>
                        <span class="mod-badge mod-badge-core">Système</span>
                    <?php endif; ?>
                    <?php if (!empty($mod['enabled'])): ?>
                        <span class="mod-badge mod-badge-on">Activé</span>
                    <?php else: ?>
                        <span class="mod-badge mod-badge-off">Désactivé</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="mod-actions">
                <form method="post" style="margin:0">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="module_key" value="<?= htmlspecialchars($mod['module_key']) ?>">
                    <input type="hidden" name="enabled" value="<?= empty($mod['enabled']) ? '1' : '0' ?>">
                    <label class="toggle-switch" title="<?= !empty($mod['is_core']) ? 'Module système — ne peut pas être désactivé' : 'Activer/désactiver' ?>">
                        <input type="checkbox" <?= !empty($mod['enabled']) ? 'checked' : '' ?> <?= !empty($mod['is_core']) ? 'disabled' : '' ?>
                               onchange="this.form.submit()">
                        <span class="toggle-slider"></span>
                    </label>
                </form>
                <a href="configure.php?module=<?= urlencode($mod['module_key']) ?>" class="mod-config-btn">
                    <i class="fas fa-cog"></i> Config
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>

<?php include __DIR__ . '/../includes/sub_footer.php'; ?>
