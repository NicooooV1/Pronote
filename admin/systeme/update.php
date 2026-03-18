<?php
/**
 * Gestion des mises à jour — statut, journal, configuration, aide
 */
require_once __DIR__ . '/../../API/core.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAuth();
requireRole('administrateur');

$projectRoot = dirname(__DIR__, 2);

// ─── Réponse AJAX journal (avant tout output HTML) ─────────────────────────
$logFile_early = $projectRoot . '/temp/update.log';
if (isset($_GET['_ajax']) && $_GET['_ajax'] === 'log') {
    header('Content-Type: text/plain; charset=utf-8');
    $all   = file_exists($logFile_early) ? file($logFile_early, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
    $lines = array_slice($all, -50);
    echo empty($lines) ? '(aucune entrée dans le journal)' : implode("\n", $lines);
    exit;
}
$envPath     = $projectRoot . '/.env';
$lockFile    = $projectRoot . '/temp/update.lock';
$logFile     = $projectRoot . '/temp/update.log';
$versionFile = $projectRoot . '/version.json';

// ─── CSRF ──────────────────────────────────────────────────────────────────
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// ─── Helper : met à jour des clés dans .env ────────────────────────────────
function updateEnvValues(array $updates, string $envPath): void
{
    $lines = file_exists($envPath) ? file($envPath, FILE_IGNORE_NEW_LINES) : [];
    $touched = array_fill_keys(array_keys($updates), false);

    foreach ($lines as &$line) {
        if (preg_match('/^\s*([A-Z0-9_]+)\s*=/', $line, $m)) {
            $key = $m[1];
            if (array_key_exists($key, $updates)) {
                $val = $updates[$key];
                $line = $key . '=' . (str_contains($val, ' ') ? '"' . addslashes($val) . '"' : $val);
                $touched[$key] = true;
            }
        }
    }
    unset($line);

    foreach ($touched as $key => $done) {
        if (!$done) {
            $val = $updates[$key];
            $lines[] = $key . '=' . (str_contains($val, ' ') ? '"' . addslashes($val) . '"' : $val);
        }
    }

    file_put_contents($envPath, implode(PHP_EOL, $lines) . PHP_EOL, LOCK_EX);
}

// ─── Actions POST ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    // Vérification CSRF
    if (($_POST['csrf_token'] ?? '') !== $csrfToken) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Token CSRF invalide.']);
        exit;
    }

    // --- Déclencher une mise à jour ---
    if ($postAction === 'trigger') {
        header('Content-Type: application/json');
        if (file_exists($lockFile)) {
            $age = time() - (int) filemtime($lockFile);
            if ($age < 300) {
                echo json_encode(['success' => false, 'message' => 'Une mise à jour est déjà en cours (verrou actif, âge ' . $age . 's).']);
                exit;
            }
        }
        $phpBin      = PHP_BINARY;
        $updateScript = escapeshellarg($projectRoot . '/scripts/update.php');
        $cmd = $phpBin . ' ' . $updateScript . ' >> ' . escapeshellarg($logFile) . ' 2>&1';
        if (PHP_OS_FAMILY === 'Windows') {
            pclose(popen('start /B ' . $cmd, 'r'));
        } else {
            $desc = [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']];
            $proc = proc_open($cmd . ' &', $desc, $pipes);
            if (is_resource($proc)) {
                proc_close($proc);
            }
        }
        echo json_encode(['success' => true, 'message' => 'Mise à jour lancée en arrière-plan. Consultez le journal dans quelques instants.']);
        exit;
    }

    // --- Sauvegarder la configuration ---
    if ($postAction === 'save_config') {
        $allowed = ['GITHUB_REPO', 'GITHUB_BRANCH', 'GITHUB_WEBHOOK_SECRET', 'UPDATE_AUTO_CHECK'];
        $updates = [];
        foreach ($allowed as $key) {
            if (isset($_POST[$key])) {
                $updates[$key] = trim($_POST[$key]);
            }
        }
        // Checkbox → 'true'/'false'
        $updates['UPDATE_AUTO_CHECK'] = isset($_POST['UPDATE_AUTO_CHECK']) ? 'true' : 'false';

        updateEnvValues($updates, $envPath);

        // Recharger les variables d'environnement pour affichage immédiat
        foreach ($updates as $k => $v) {
            putenv($k . '=' . $v);
            $_ENV[$k] = $v;
        }

        $successMsg = 'Configuration enregistrée.';
    }
}

// ─── Données pour l'affichage ──────────────────────────────────────────────
$versionData = [];
if (file_exists($versionFile)) {
    $versionData = json_decode((string) file_get_contents($versionFile), true) ?? [];
}
$currentVersion = $versionData['version'] ?? 'inconnue';
$buildDate      = $versionData['build']   ?? '-';

$lockActive = false;
$lockAge    = 0;
if (file_exists($lockFile)) {
    $lockAge    = time() - (int) filemtime($lockFile);
    $lockActive = $lockAge < 300;
}

$logLines = [];
if (file_exists($logFile)) {
    $all      = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $logLines = array_slice($all, -50);
}

$pageTitle   = 'Mises à jour';
$currentPage = 'update';
$extraCss    = ['../../assets/css/admin.css'];

ob_start();
?>
<style>
    .update-container { max-width: 900px; margin: 0 auto; }
    .tab-nav { display: flex; gap: 0; border-bottom: 2px solid #e2e8f0; margin-bottom: 20px; }
    .tab-btn { padding: 10px 20px; background: none; border: none; border-bottom: 2px solid transparent; margin-bottom: -2px; cursor: pointer; font-size: 14px; font-weight: 500; color: #718096; transition: color .15s, border-color .15s; }
    .tab-btn.active { color: #0f4c81; border-bottom-color: #0f4c81; }
    .tab-pane { display: none; }
    .tab-pane.active { display: block; }
    .status-card { background: #fff; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,.06); padding: 20px 24px; margin-bottom: 16px; }
    .status-row { display: flex; align-items: center; gap: 12px; margin-bottom: 8px; }
    .badge-ok  { background: #d1fae5; color: #065f46; padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
    .badge-warn{ background: #fef3c7; color: #92400e; padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
    .badge-err { background: #fee2e2; color: #991b1b; padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
    .log-box   { background: #1a202c; color: #e2e8f0; border-radius: 8px; padding: 16px; font-family: monospace; font-size: 12px; line-height: 1.6; max-height: 420px; overflow-y: auto; white-space: pre-wrap; word-break: break-all; }
    .config-form label { display: block; font-size: 13px; font-weight: 600; color: #4a5568; margin-bottom: 4px; }
    .config-form input[type=text], .config-form input[type=password] { width: 100%; padding: 8px 10px; border: 1px solid #d2d6dc; border-radius: 6px; font-size: 13px; box-sizing: border-box; }
    .config-form .field { margin-bottom: 16px; }
    .help-block { background: #f7fafc; border-left: 4px solid #0f4c81; border-radius: 6px; padding: 14px 18px; margin-bottom: 16px; font-size: 13px; line-height: 1.7; }
    .help-block code { background: #e2e8f0; padding: 2px 5px; border-radius: 4px; font-size: 12px; }
    #trigger-result { margin-top: 12px; font-size: 13px; }
</style>
<?php
$extraHeadHtml = ob_get_clean();
include __DIR__ . '/../includes/sub_header.php';
?>

<div class="update-container">

<?php if (!empty($successMsg)): ?>
    <div class="admin-alert-item" style="background:#d1fae5;color:#065f46;border-radius:8px;padding:10px 16px;margin-bottom:16px">
        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($successMsg) ?>
    </div>
<?php endif; ?>

    <!-- Onglets -->
    <div class="tab-nav">
        <button class="tab-btn active" onclick="switchTab('statut',this)"><i class="fas fa-heartbeat"></i> Statut</button>
        <button class="tab-btn" onclick="switchTab('journal',this)"><i class="fas fa-scroll"></i> Journal</button>
        <button class="tab-btn" onclick="switchTab('config',this)"><i class="fas fa-sliders-h"></i> Configuration</button>
        <button class="tab-btn" onclick="switchTab('aide',this)"><i class="fas fa-question-circle"></i> Aide</button>
    </div>

    <!-- ═══ STATUT ═══ -->
    <div id="tab-statut" class="tab-pane active">
        <div class="status-card">
            <div class="status-row">
                <strong style="font-size:16px">Version actuelle :</strong>
                <span class="badge-ok"><?= htmlspecialchars($currentVersion) ?></span>
                <span style="color:#718096;font-size:13px">Build : <?= htmlspecialchars($buildDate) ?></span>
            </div>
            <div class="status-row" style="margin-top:8px">
                <strong>Verrou de mise à jour :</strong>
                <?php if ($lockActive): ?>
                    <span class="badge-warn"><i class="fas fa-lock"></i> En cours (<?= $lockAge ?>s)</span>
                <?php elseif (file_exists($lockFile)): ?>
                    <span class="badge-err"><i class="fas fa-lock-open"></i> Verrou périmé (<?= $lockAge ?>s)</span>
                <?php else: ?>
                    <span class="badge-ok"><i class="fas fa-lock-open"></i> Libre</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="status-card">
            <p style="font-size:13px;color:#4a5568;margin-bottom:14px">
                Lance <code>scripts/update.php</code> en arrière-plan (git pull, composer, bootstrap test).
            </p>
            <button id="btn-trigger" class="btn btn-primary" onclick="triggerUpdate()">
                <i class="fas fa-sync-alt"></i> Déclencher une mise à jour
            </button>
            <div id="trigger-result"></div>
        </div>
    </div>

    <!-- ═══ JOURNAL ═══ -->
    <div id="tab-journal" class="tab-pane">
        <div class="status-card">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
                <strong>temp/update.log</strong>
                <button class="btn btn-secondary" style="font-size:12px" onclick="refreshLog()"><i class="fas fa-redo"></i> Actualiser</button>
            </div>
            <div id="log-content" class="log-box"><?php
                if (empty($logLines)) {
                    echo '(aucune entrée dans le journal)';
                } else {
                    echo htmlspecialchars(implode("\n", $logLines));
                }
            ?></div>
        </div>
    </div>

    <!-- ═══ CONFIGURATION ═══ -->
    <div id="tab-config" class="tab-pane">
        <div class="status-card">
            <form method="post" class="config-form">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="save_config">

                <div class="field">
                    <label for="GITHUB_REPO">Dépôt GitHub <span style="font-weight:400;color:#718096">(user/repo)</span></label>
                    <input type="text" id="GITHUB_REPO" name="GITHUB_REPO"
                           value="<?= htmlspecialchars(getenv('GITHUB_REPO') ?: '') ?>"
                           placeholder="monuser/monrepo">
                </div>

                <div class="field">
                    <label for="GITHUB_BRANCH">Branche</label>
                    <input type="text" id="GITHUB_BRANCH" name="GITHUB_BRANCH"
                           value="<?= htmlspecialchars(getenv('GITHUB_BRANCH') ?: 'main') ?>"
                           placeholder="main">
                </div>

                <div class="field">
                    <label for="GITHUB_WEBHOOK_SECRET">Webhook secret</label>
                    <input type="password" id="GITHUB_WEBHOOK_SECRET" name="GITHUB_WEBHOOK_SECRET"
                           value="<?= htmlspecialchars(getenv('GITHUB_WEBHOOK_SECRET') ?: '') ?>"
                           placeholder="••••••••">
                </div>

                <div class="field" style="display:flex;align-items:center;gap:10px">
                    <input type="checkbox" id="UPDATE_AUTO_CHECK" name="UPDATE_AUTO_CHECK"
                           <?= (getenv('UPDATE_AUTO_CHECK') === 'true') ? 'checked' : '' ?>>
                    <label for="UPDATE_AUTO_CHECK" style="margin:0;font-weight:400">Vérification automatique des mises à jour</label>
                </div>

                <button type="submit" class="btn btn-primary" style="margin-top:8px">
                    <i class="fas fa-save"></i> Enregistrer
                </button>
            </form>
        </div>
    </div>

    <!-- ═══ AIDE ═══ -->
    <div id="tab-aide" class="tab-pane">
        <div class="help-block">
            <strong><i class="fas fa-webhook"></i> Webhook GitHub</strong><br>
            Ajoutez un webhook dans les paramètres de votre dépôt :<br>
            URL : <code>https://votre-domaine/webhook_update.php</code><br>
            Content type : <code>application/json</code><br>
            Secret : valeur de <code>GITHUB_WEBHOOK_SECRET</code> dans le .env<br>
            Événement : <strong>Push</strong>
        </div>
        <div class="help-block">
            <strong><i class="fas fa-clock"></i> Cron de vérification automatique</strong><br>
            Ajoutez cette ligne à votre crontab pour vérifier les mises à jour toutes les heures :<br>
            <code>0 * * * * php <?= htmlspecialchars($projectRoot) ?>/scripts/check_update.php >> <?= htmlspecialchars($projectRoot) ?>/temp/check_update.log 2>&1</code>
        </div>
        <div class="help-block">
            <strong><i class="fas fa-terminal"></i> Mise à jour manuelle</strong><br>
            Vous pouvez lancer la mise à jour directement depuis un terminal :<br>
            <code>php <?= htmlspecialchars($projectRoot) ?>/scripts/update.php</code>
        </div>
        <div class="help-block">
            <strong><i class="fas fa-file-alt"></i> Fichiers clés</strong><br>
            Journal de mise à jour : <code>temp/update.log</code><br>
            Verrou actif : <code>temp/update.lock</code><br>
            Backup .env : <code>temp/backup.env</code><br>
            Version courante : <code>version.json</code>
        </div>
    </div>
</div>

<script>
function switchTab(name, btn) {
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    btn.classList.add('active');
}

function triggerUpdate() {
    const btn = document.getElementById('btn-trigger');
    const res = document.getElementById('trigger-result');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Lancement…';
    res.innerHTML = '';

    fetch(window.location.href, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=trigger&csrf_token=<?= urlencode($csrfToken) ?>'
    })
    .then(r => r.json())
    .then(data => {
        res.innerHTML = '<span style="color:' + (data.success ? '#065f46' : '#991b1b') + '">'
            + '<i class="fas fa-' + (data.success ? 'check' : 'times') + '-circle"></i> '
            + data.message + '</span>';
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-sync-alt"></i> Déclencher une mise à jour';
    })
    .catch(() => {
        res.innerHTML = '<span style="color:#991b1b"><i class="fas fa-times-circle"></i> Erreur réseau.</span>';
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-sync-alt"></i> Déclencher une mise à jour';
    });
}

function refreshLog() {
    fetch('?_ajax=log')
        .then(r => r.text())
        .then(txt => { document.getElementById('log-content').textContent = txt; });
}
</script>

<?php include __DIR__ . '/../includes/sub_footer.php'; ?>
