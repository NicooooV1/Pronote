<?php
/**
 * Administration — Gestionnaire de thèmes
 * Installer, prévisualiser, activer et supprimer des thèmes CSS.
 */
require_once __DIR__ . '/../../API/core.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAuth();
requireRole('administrateur');

$themeService = app('themes');
$message = '';
$messageType = '';

// ─── Actions POST ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['csrf_token'] ?? '') === ($_SESSION['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'upload') {
        $result = $themeService->uploadTheme(
            $_FILES['css_file'] ?? [],
            $_POST['theme_key'] ?? '',
            $_POST['theme_name'] ?? '',
            $_POST['theme_description'] ?? '',
            $_FILES['preview_file'] ?? null
        );
        $message = $result['message'] ?? $result['error'] ?? '';
        $messageType = $result['success'] ? 'success' : 'error';
        if ($result['success']) {
            logAudit('theme.uploaded', 'themes', null, null, ['key' => $_POST['theme_key']]);
        }
    }

    if ($action === 'set_default') {
        $key = $_POST['theme_key'] ?? 'classic';
        $themeService->setDefault($key);
        $message = 'Thème par défaut mis à jour.';
        $messageType = 'success';
        logAudit('theme.default_changed', 'themes', null, null, ['key' => $key]);
    }

    if ($action === 'delete') {
        $result = $themeService->delete($_POST['theme_key'] ?? '');
        $message = $result['message'] ?? $result['error'] ?? '';
        $messageType = $result['success'] ? 'success' : 'error';
    }

    if ($action === 'install_remote') {
        $marketplace = app('marketplace');
        $result = $marketplace->installTheme($_POST['theme_key'] ?? '');
        $message = $result['message'] ?? $result['error'] ?? '';
        $messageType = $result['success'] ? 'success' : 'error';
    }
}

// ─── Données ─────────────────────────────────────────────────────
$themes = $themeService->getAll();
$defaultTheme = $themeService->getDefault();
$tokens = $themeService->getTokens();
$remoteCatalog = [];
try {
    $remoteCatalog = app('marketplace')->getCatalog('theme');
} catch (\Throwable $e) {}

$pageTitle = 'Gestion des thèmes';
$currentPage = 'themes';
$extraCss = ['../../assets/css/admin.css'];

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

include __DIR__ . '/../includes/header.php';
?>

<style>
.th-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px}
.th-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;margin-bottom:32px}
.th-card{background:#fff;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;transition:.2s}
.th-card:hover{box-shadow:0 4px 12px rgba(0,0,0,.08)}
.th-card.active{border-color:#667eea;box-shadow:0 0 0 2px rgba(102,126,234,.3)}
.th-preview{height:120px;background:#f7fafc;display:flex;align-items:center;justify-content:center;color:#cbd5e0;font-size:2em;position:relative}
.th-preview img{width:100%;height:100%;object-fit:cover}
.th-default-badge{position:absolute;top:8px;right:8px;background:#667eea;color:#fff;font-size:.7em;padding:3px 10px;border-radius:12px;font-weight:600}
.th-body{padding:16px}
.th-name{font-weight:700;font-size:.95em;color:#2d3748}
.th-desc{font-size:.82em;color:#718096;margin-top:4px}
.th-meta{display:flex;justify-content:space-between;align-items:center;margin-top:8px;font-size:.78em;color:#a0aec0}
.th-actions{display:flex;gap:6px;margin-top:12px}
.th-btn{padding:5px 12px;border:none;border-radius:5px;cursor:pointer;font-size:.8em;font-weight:600}
.th-btn-primary{background:#667eea;color:#fff}
.th-btn-danger{background:#fc8181;color:#fff}
.th-btn-outline{background:transparent;border:1px solid #e2e8f0;color:#4a5568}
.th-section{margin-bottom:32px}
.th-section h3{font-size:1em;color:#4a5568;margin-bottom:14px;padding-bottom:8px;border-bottom:2px solid #e2e8f0}
.th-upload{background:#f7fafc;border:2px dashed #e2e8f0;border-radius:10px;padding:24px;margin-bottom:24px}
.th-upload label{display:block;font-size:.85em;color:#4a5568;margin-bottom:6px;font-weight:600}
.th-upload input[type=text],.th-upload textarea{width:100%;padding:8px 12px;border:1px solid #e2e8f0;border-radius:6px;font-size:.9em;margin-bottom:12px;box-sizing:border-box}
.th-upload input[type=file]{margin-bottom:12px}
.th-tokens{max-height:300px;overflow-y:auto;font-size:.82em}
.th-token-row{display:flex;align-items:center;gap:10px;padding:4px 0;border-bottom:1px solid #f7fafc}
.th-token-name{font-family:monospace;color:#667eea;min-width:220px}
.th-token-swatch{width:24px;height:24px;border-radius:4px;border:1px solid #e2e8f0;flex-shrink:0}
</style>

<?php if ($message): ?>
<div style="margin-bottom:16px;padding:12px 16px;border-radius:8px;background:<?= $messageType === 'success' ? '#f0fff4' : '#fff5f5' ?>;color:<?= $messageType === 'success' ? '#276749' : '#c53030' ?>;border:1px solid <?= $messageType === 'success' ? '#c6f6d5' : '#fed7d7' ?>">
    <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<div class="th-header">
    <h2><i class="fas fa-palette"></i> Gestion des thèmes</h2>
</div>

<!-- Thèmes installés -->
<div class="th-section">
    <h3><i class="fas fa-swatchbook"></i> Thèmes disponibles</h3>
    <div class="th-grid">
        <?php foreach ($themes as $theme): ?>
        <div class="th-card <?= $theme['key'] === $defaultTheme ? 'active' : '' ?>">
            <div class="th-preview">
                <?php if (!empty($theme['preview_image'])): ?>
                <img src="../../<?= htmlspecialchars($theme['preview_image']) ?>" alt="Preview">
                <?php else: ?>
                <i class="fas fa-palette"></i>
                <?php endif; ?>
                <?php if ($theme['key'] === $defaultTheme): ?>
                <span class="th-default-badge">Par défaut</span>
                <?php endif; ?>
            </div>
            <div class="th-body">
                <div class="th-name"><?= htmlspecialchars($theme['name'] ?? $theme['key']) ?></div>
                <div class="th-desc"><?= htmlspecialchars($theme['description'] ?? '') ?></div>
                <div class="th-meta">
                    <span><?= htmlspecialchars($theme['author'] ?? '') ?></span>
                    <span>v<?= htmlspecialchars($theme['version'] ?? '1.0') ?></span>
                </div>
                <div class="th-actions">
                    <?php if ($theme['key'] !== $defaultTheme): ?>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="action" value="set_default">
                        <input type="hidden" name="theme_key" value="<?= htmlspecialchars($theme['key']) ?>">
                        <button class="th-btn th-btn-primary" type="submit"><i class="fas fa-check"></i> Activer</button>
                    </form>
                    <?php endif; ?>
                    <?php if (empty($theme['is_builtin'])): ?>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer ce thème ?')">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="theme_key" value="<?= htmlspecialchars($theme['key']) ?>">
                        <button class="th-btn th-btn-danger" type="submit"><i class="fas fa-trash"></i></button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Upload custom theme -->
<div class="th-section">
    <h3><i class="fas fa-upload"></i> Installer un thème personnalisé</h3>
    <form class="th-upload" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
        <input type="hidden" name="action" value="upload">
        <label>Clé du thème (ex: modern-blue)</label>
        <input type="text" name="theme_key" placeholder="modern-blue" required pattern="[a-z0-9_-]{2,30}">
        <label>Nom d'affichage</label>
        <input type="text" name="theme_name" placeholder="Modern Blue" required>
        <label>Description (optionnel)</label>
        <textarea name="theme_description" rows="2" placeholder="Un thème moderne aux tons bleus..."></textarea>
        <label>Fichier CSS (max 500 Ko)</label>
        <input type="file" name="css_file" accept=".css" required>
        <label>Image de preview (optionnel, max 2 Mo)</label>
        <input type="file" name="preview_file" accept="image/png,image/jpeg,image/webp">
        <button class="th-btn th-btn-primary" type="submit" style="margin-top:8px"><i class="fas fa-upload"></i> Installer le thème</button>
    </form>
</div>

<!-- Marketplace de thèmes -->
<?php if (!empty($remoteCatalog)): ?>
<div class="th-section">
    <h3><i class="fas fa-store"></i> Thèmes de la marketplace</h3>
    <div class="th-grid">
        <?php foreach ($remoteCatalog as $remote): ?>
        <div class="th-card">
            <div class="th-preview"><i class="fas fa-cloud-download-alt"></i></div>
            <div class="th-body">
                <div class="th-name"><?= htmlspecialchars($remote['name'] ?? $remote['key']) ?></div>
                <div class="th-desc"><?= htmlspecialchars($remote['description'] ?? '') ?></div>
                <div class="th-actions">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="action" value="install_remote">
                        <input type="hidden" name="theme_key" value="<?= htmlspecialchars($remote['key'] ?? '') ?>">
                        <button class="th-btn th-btn-primary"><i class="fas fa-download"></i> Installer</button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Token editor preview -->
<?php if (!empty($tokens)): ?>
<div class="th-section">
    <h3><i class="fas fa-sliders-h"></i> Variables CSS (design tokens)</h3>
    <p style="font-size:.85em;color:#718096;margin-bottom:12px">Variables du fichier <code>tokens.css</code> utilisées par tous les thèmes.</p>
    <div class="th-tokens">
        <?php foreach (array_slice($tokens, 0, 50) as $token): ?>
        <div class="th-token-row">
            <span class="th-token-name"><?= htmlspecialchars($token['name']) ?></span>
            <span class="th-token-swatch" style="background:<?= htmlspecialchars($token['value']) ?>"></span>
            <span><?= htmlspecialchars($token['value']) ?></span>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
