<?php
/**
 * Import / Export — Interface d'administration
 * Onglets : Export, Import, Historique
 */
require_once __DIR__ . '/../../API/core.php';
require_once __DIR__ . '/../includes/admin_functions.php';
require_once __DIR__ . '/../../API/Services/ImportExportService.php';

requireAuth();
requireRole('administrateur');

$pdo = getPDO();
$service = new ImportExportService($pdo);
$admin = getCurrentUser();

$message = '';
$messageType = '';
$importResult = null;
$previewData = null;
$passwordsList = null;

// ─── Traitement des actions POST ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';

    // --- EXPORT UTILISATEURS ---
    if ($action === 'export_users') {
        $type   = $_POST['user_type'] ?? 'tous';
        $format = $_POST['format'] ?? 'csv';
        $result = $service->exportUsers($type, $format);

        if ($result['success']) {
            // Envoyer le fichier en download
            $filePath = $result['file_path'];
            $fileName = $result['file_name'];
            $mime = ($format === 'json') ? 'application/json' : 'text/csv';
            header('Content-Type: ' . $mime . '; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Content-Length: ' . filesize($filePath));
            readfile($filePath);
            @unlink($filePath);
            exit;
        } else {
            $message = $result['message'];
            $messageType = 'error';
        }
    }

    // --- EXPORT CONFIGURATION ---
    if ($action === 'export_config') {
        $result = $service->exportConfig();
        if ($result['success']) {
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $result['file_name'] . '"');
            header('Content-Length: ' . filesize($result['file_path']));
            readfile($result['file_path']);
            @unlink($result['file_path']);
            exit;
        } else {
            $message = $result['message'];
            $messageType = 'error';
        }
    }

    // --- EXPORT SQL ---
    if ($action === 'export_sql') {
        $tables = $_POST['sql_tables'] ?? [];
        $includeStructure = isset($_POST['include_structure']);
        $result = $service->exportSQL($tables, $includeStructure);
        if ($result['success']) {
            header('Content-Type: application/sql; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $result['file_name'] . '"');
            header('Content-Length: ' . filesize($result['file_path']));
            readfile($result['file_path']);
            @unlink($result['file_path']);
            exit;
        } else {
            $message = $result['message'];
            $messageType = 'error';
        }
    }

    // --- PREVIEW IMPORT ---
    if ($action === 'preview_import' && !empty($_FILES['import_file']['tmp_name'])) {
        $tmpPath = $_FILES['import_file']['tmp_name'];
        $tmpName = $_FILES['import_file']['name'];
        $importType = $_POST['import_type'] ?? 'users';

        if ($importType === 'config') {
            // Preview JSON config
            $content = file_get_contents($tmpPath);
            $data = json_decode($content, true);
            if ($data && ($data['export_type'] ?? '') === 'configuration') {
                $previewData = [
                    'type'    => 'config',
                    'modules' => count($data['modules_config'] ?? []),
                    'perms'   => count($data['module_permissions'] ?? []),
                    'date'    => $data['exported_at'] ?? 'N/A',
                ];
                // Stocker le fichier temporairement
                $storedPath = sys_get_temp_dir() . '/fronote_import_' . session_id() . '.json';
                copy($tmpPath, $storedPath);
                $_SESSION['pending_import'] = [
                    'type' => 'config',
                    'path' => $storedPath,
                    'name' => $tmpName,
                ];
                $message = 'Apercu du fichier de configuration pret. Confirmez l\'import ci-dessous.';
                $messageType = 'info';
            } else {
                $message = 'Fichier JSON invalide. Le fichier doit etre un export de type "configuration".';
                $messageType = 'error';
            }
        } else {
            // Preview CSV users
            $preview = $service->previewCsv($tmpPath);
            if ($preview['success']) {
                $previewData = [
                    'type'        => 'users',
                    'headers'     => $preview['headers'],
                    'rows'        => $preview['rows'],
                    'total_lines' => $preview['total_lines'],
                    'user_type'   => $_POST['user_type_import'] ?? 'eleve',
                ];
                // Stocker le fichier temporairement
                $storedPath = sys_get_temp_dir() . '/fronote_import_' . session_id() . '.csv';
                copy($tmpPath, $storedPath);
                $_SESSION['pending_import'] = [
                    'type'      => 'users',
                    'user_type' => $_POST['user_type_import'] ?? 'eleve',
                    'path'      => $storedPath,
                    'name'      => $tmpName,
                ];
                $message = $preview['total_lines'] . ' ligne(s) detectee(s). Verifiez l\'apercu et confirmez.';
                $messageType = 'info';
            } else {
                $message = $preview['message'];
                $messageType = 'error';
            }
        }
    }

    // --- CONFIRMER IMPORT ---
    if ($action === 'confirm_import' && !empty($_SESSION['pending_import'])) {
        $pending = $_SESSION['pending_import'];

        if ($pending['type'] === 'users' && file_exists($pending['path'])) {
            $fakeFile = [
                'tmp_name' => $pending['path'],
                'name'     => $pending['name'],
            ];
            $importResult = $service->importUsers($pending['user_type'], $fakeFile, [
                'generate_passwords' => isset($_POST['generate_passwords']),
            ]);
            if (!empty($importResult['passwords'])) {
                $passwordsList = $importResult['passwords'];
            }
            $message = $importResult['message'];
            $messageType = ($importResult['nb_erreurs'] > 0 && $importResult['nb_importes'] === 0) ? 'error' : 'success';
        } elseif ($pending['type'] === 'config' && file_exists($pending['path'])) {
            $fakeFile = [
                'tmp_name' => $pending['path'],
                'name'     => $pending['name'],
            ];
            $importResult = $service->importConfig($fakeFile);
            $message = $importResult['message'];
            $messageType = $importResult['success'] ? 'success' : 'error';
        } else {
            $message = 'Session d\'import expiree. Veuillez re-uploader le fichier.';
            $messageType = 'error';
        }

        // Nettoyage
        if (file_exists($pending['path'] ?? '')) {
            @unlink($pending['path']);
        }
        unset($_SESSION['pending_import']);
    }

    // --- ANNULER IMPORT ---
    if ($action === 'cancel_import' && !empty($_SESSION['pending_import'])) {
        if (file_exists($_SESSION['pending_import']['path'] ?? '')) {
            @unlink($_SESSION['pending_import']['path']);
        }
        unset($_SESSION['pending_import']);
        $message = 'Import annule.';
        $messageType = 'info';
    }
}

// ─── Donnees pour la page ─────────────────────────────────────────────
$activeTab    = $_GET['tab'] ?? 'export';
$validTabs    = ['export', 'import', 'historique'];
if (!in_array($activeTab, $validTabs)) $activeTab = 'export';

$userTypes    = $service->getValidUserTypes();
$history      = $service->getImportHistory(50);
$historyCount = $service->getHistoryCount();
$allTables    = $service->getExportableTables();

// Statistiques rapides
$exportCount = 0;
$importCount = 0;
try {
    $exportCount = (int)$pdo->query("SELECT COUNT(*) FROM import_export_logs WHERE type = 'export'")->fetchColumn();
    $importCount = (int)$pdo->query("SELECT COUNT(*) FROM import_export_logs WHERE type = 'import'")->fetchColumn();
} catch (PDOException $e) {}

$hasPending = !empty($_SESSION['pending_import']);

$pageTitle  = 'Import / Export';
$currentPage = 'import_export';
$extraCss   = ['../../assets/css/admin.css'];

ob_start();
?>
<style>
    .ie-container { max-width: 1200px; margin: 0 auto; }

    /* Tabs */
    .ie-tabs { display: flex; gap: 0; margin-bottom: 20px; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,0.05); }
    .ie-tab { flex: 1; text-align: center; padding: 12px 20px; font-size: 13px; font-weight: 600; color: #4a5568; text-decoration: none; border-bottom: 3px solid transparent; transition: all 0.2s; }
    .ie-tab:hover { background: #f7fafc; color: #2d3748; }
    .ie-tab.active { color: #4f46e5; border-bottom-color: #4f46e5; background: #f0f0ff; }
    .ie-tab i { margin-right: 6px; }

    /* Stats bar */
    .stats-bar { display: flex; gap: 15px; margin-bottom: 20px; }
    .stat-card { flex: 1; background: white; padding: 15px 20px; border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,0.05); text-align: center; }
    .stat-card .val { font-size: 24px; font-weight: 700; color: #2d3748; }
    .stat-card .lbl { font-size: 11px; color: #718096; margin-top: 4px; }

    /* Cards */
    .ie-card { background: white; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); padding: 20px 24px; margin-bottom: 20px; }
    .ie-card h3 { font-size: 15px; font-weight: 700; color: #2d3748; margin: 0 0 15px; display: flex; align-items: center; gap: 8px; }
    .ie-card h3 i { color: #4f46e5; }

    /* Forms */
    .form-row { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; margin-bottom: 12px; }
    .form-group { display: flex; flex-direction: column; gap: 4px; }
    .form-group label { font-size: 11px; font-weight: 600; color: #4a5568; }
    .form-group select, .form-group input[type="file"] { padding: 7px 10px; border: 1px solid #d2d6dc; border-radius: 6px; font-size: 12px; min-width: 180px; }
    .form-group input[type="file"] { padding: 5px; }

    /* Buttons */
    .btn-export { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border: none; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
    .btn-export.primary { background: #4f46e5; color: white; }
    .btn-export.primary:hover { background: #4338ca; }
    .btn-export.success { background: #059669; color: white; }
    .btn-export.success:hover { background: #047857; }
    .btn-export.danger { background: #dc2626; color: white; }
    .btn-export.danger:hover { background: #b91c1c; }
    .btn-export.warning { background: #d97706; color: white; }
    .btn-export.warning:hover { background: #b45309; }
    .btn-export.secondary { background: #6b7280; color: white; }
    .btn-export.secondary:hover { background: #4b5563; }

    /* Messages */
    .ie-alert { padding: 12px 16px; border-radius: 8px; font-size: 13px; margin-bottom: 15px; display: flex; align-items: center; gap: 8px; }
    .ie-alert.success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
    .ie-alert.error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    .ie-alert.info { background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }

    /* Preview table */
    .preview-table { width: 100%; border-collapse: collapse; font-size: 12px; margin: 12px 0; }
    .preview-table th, .preview-table td { padding: 6px 10px; text-align: left; border-bottom: 1px solid #f0f0f0; }
    .preview-table th { background: #f7fafc; font-weight: 600; color: #4a5568; font-size: 11px; }
    .preview-table tr:hover { background: #fafafa; }

    /* History table */
    .history-table { width: 100%; border-collapse: collapse; }
    .history-table th, .history-table td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #f0f0f0; font-size: 12px; }
    .history-table th { background: #f7fafc; font-weight: 600; color: #4a5568; }
    .badge-type { display: inline-block; padding: 2px 8px; border-radius: 6px; font-size: 10px; font-weight: 600; }
    .badge-export { background: #dbeafe; color: #1e40af; }
    .badge-import { background: #fef3c7; color: #92400e; }
    .badge-status { display: inline-block; padding: 2px 8px; border-radius: 6px; font-size: 10px; font-weight: 600; }
    .badge-termine { background: #d1fae5; color: #065f46; }
    .badge-erreur { background: #fee2e2; color: #991b1b; }
    .badge-en_cours { background: #fef3c7; color: #92400e; }

    /* Checkbox row */
    .checkbox-row { display: flex; align-items: center; gap: 6px; margin: 6px 0; }
    .checkbox-row input[type="checkbox"] { accent-color: #4f46e5; }
    .checkbox-row label { font-size: 12px; color: #4a5568; cursor: pointer; }

    /* SQL tables selector */
    .tables-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 4px; max-height: 250px; overflow-y: auto; padding: 10px; background: #f9fafb; border-radius: 6px; border: 1px solid #e5e7eb; margin-bottom: 12px; }
    .tables-grid label { font-size: 11px; display: flex; align-items: center; gap: 4px; padding: 3px 0; }

    /* Passwords list */
    .passwords-card { background: #fffbeb; border: 1px solid #fcd34d; border-radius: 8px; padding: 15px; margin-top: 15px; }
    .passwords-card h4 { font-size: 13px; color: #92400e; margin: 0 0 10px; }
    .passwords-table { width: 100%; border-collapse: collapse; font-size: 11px; }
    .passwords-table th, .passwords-table td { padding: 4px 8px; text-align: left; border-bottom: 1px solid #fde68a; }
    .passwords-table th { color: #92400e; font-weight: 600; }

    /* Errors list */
    .errors-list { max-height: 200px; overflow-y: auto; background: #fef2f2; border: 1px solid #fecaca; border-radius: 6px; padding: 10px; margin-top: 10px; }
    .errors-list li { font-size: 11px; color: #991b1b; padding: 2px 0; }

    /* Separator */
    .ie-separator { border: 0; border-top: 1px solid #e5e7eb; margin: 20px 0; }

    /* Select all / none */
    .select-actions { display: flex; gap: 8px; margin-bottom: 6px; }
    .select-actions a { font-size: 11px; color: #4f46e5; cursor: pointer; text-decoration: none; }
    .select-actions a:hover { text-decoration: underline; }
</style>
<?php
$extraHeadHtml = ob_get_clean();
include __DIR__ . '/../includes/sub_header.php';
?>

<div class="ie-container">
    <!-- Stats -->
    <div class="stats-bar">
        <div class="stat-card"><div class="val"><?= $exportCount ?></div><div class="lbl">Exports realises</div></div>
        <div class="stat-card"><div class="val"><?= $importCount ?></div><div class="lbl">Imports realises</div></div>
        <div class="stat-card"><div class="val"><?= $historyCount ?></div><div class="lbl">Total operations</div></div>
    </div>

    <!-- Tabs -->
    <div class="ie-tabs">
        <a href="?tab=export" class="ie-tab <?= $activeTab === 'export' ? 'active' : '' ?>"><i class="fas fa-download"></i> Export</a>
        <a href="?tab=import" class="ie-tab <?= $activeTab === 'import' ? 'active' : '' ?>"><i class="fas fa-upload"></i> Import</a>
        <a href="?tab=historique" class="ie-tab <?= $activeTab === 'historique' ? 'active' : '' ?>"><i class="fas fa-history"></i> Historique</a>
    </div>

    <!-- Message flash -->
    <?php if (!empty($message)): ?>
        <div class="ie-alert <?= $messageType ?>">
            <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : ($messageType === 'error' ? 'exclamation-circle' : 'info-circle') ?>"></i>
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- ================= ONGLET EXPORT ================= -->
    <?php if ($activeTab === 'export'): ?>

        <!-- Export utilisateurs -->
        <div class="ie-card">
            <h3><i class="fas fa-users"></i> Exporter des utilisateurs</h3>
            <form method="post" action="?tab=export">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="action" value="export_users">
                <div class="form-row">
                    <div class="form-group">
                        <label>Type d'utilisateur</label>
                        <select name="user_type">
                            <option value="tous">Tous les utilisateurs</option>
                            <?php foreach ($userTypes as $key => $label): ?>
                                <option value="<?= $key ?>"><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Format</label>
                        <select name="format">
                            <option value="csv">CSV (Excel)</option>
                            <option value="json">JSON</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-export primary"><i class="fas fa-download"></i> Telecharger</button>
                </div>
            </form>
        </div>

        <!-- Export configuration -->
        <div class="ie-card">
            <h3><i class="fas fa-cogs"></i> Exporter la configuration</h3>
            <p style="font-size:12px;color:#718096;margin:0 0 12px">Exporte les modules, permissions et parametres du systeme au format JSON.</p>
            <form method="post" action="?tab=export">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="action" value="export_config">
                <button type="submit" class="btn-export success"><i class="fas fa-download"></i> Exporter la configuration</button>
            </form>
        </div>

        <!-- Export SQL -->
        <div class="ie-card">
            <h3><i class="fas fa-database"></i> Sauvegarde SQL</h3>
            <p style="font-size:12px;color:#718096;margin:0 0 12px">Genere un dump SQL des tables selectionnees (structure + donnees).</p>
            <form method="post" action="?tab=export">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="action" value="export_sql">

                <div class="select-actions">
                    <a onclick="document.querySelectorAll('.sql-table-cb').forEach(c=>c.checked=true)">Tout selectionner</a>
                    <a onclick="document.querySelectorAll('.sql-table-cb').forEach(c=>c.checked=false)">Tout deselectionner</a>
                </div>
                <div class="tables-grid">
                    <?php foreach ($allTables as $t): ?>
                        <label>
                            <input type="checkbox" name="sql_tables[]" value="<?= htmlspecialchars($t) ?>" class="sql-table-cb"
                                <?= in_array($t, ['eleves','professeurs','parents','vie_scolaire','administrateurs','classes','matieres','periodes','etablissement_info','modules_config','module_permissions']) ? 'checked' : '' ?>>
                            <?= htmlspecialchars($t) ?>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="checkbox-row">
                    <input type="checkbox" name="include_structure" id="include_structure" checked>
                    <label for="include_structure">Inclure la structure des tables (CREATE TABLE)</label>
                </div>

                <button type="submit" class="btn-export warning" style="margin-top:8px"><i class="fas fa-database"></i> Generer le backup SQL</button>
            </form>
        </div>

    <?php endif; ?>

    <!-- ================= ONGLET IMPORT ================= -->
    <?php if ($activeTab === 'import'): ?>

        <?php if ($hasPending && !$importResult): ?>
            <!-- Apercu en attente de confirmation -->
            <?php $pending = $_SESSION['pending_import']; ?>

            <div class="ie-card">
                <h3><i class="fas fa-eye"></i> Apercu de l'import</h3>

                <?php if ($previewData && $previewData['type'] === 'users'): ?>
                    <p style="font-size:12px;color:#4a5568;margin-bottom:10px">
                        <strong>Fichier :</strong> <?= htmlspecialchars($pending['name']) ?> |
                        <strong>Type :</strong> <?= htmlspecialchars($userTypes[$previewData['user_type']] ?? $previewData['user_type']) ?> |
                        <strong>Lignes :</strong> <?= $previewData['total_lines'] ?>
                    </p>
                    <div style="overflow-x:auto">
                        <table class="preview-table">
                            <thead>
                                <tr>
                                    <?php foreach ($previewData['headers'] as $h): ?>
                                        <th><?= htmlspecialchars($h) ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($previewData['rows'] as $row): ?>
                                    <tr>
                                        <?php foreach ($previewData['headers'] as $h): ?>
                                            <td><?= htmlspecialchars($row[$h] ?? '') ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($previewData['total_lines'] > 5): ?>
                                    <tr><td colspan="<?= count($previewData['headers']) ?>" style="text-align:center;color:#999;font-style:italic">... et <?= $previewData['total_lines'] - 5 ?> autre(s) ligne(s)</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php elseif ($previewData && $previewData['type'] === 'config'): ?>
                    <p style="font-size:12px;color:#4a5568">
                        <strong>Fichier :</strong> <?= htmlspecialchars($pending['name']) ?><br>
                        <strong>Date d'export :</strong> <?= htmlspecialchars($previewData['date']) ?><br>
                        <strong>Modules :</strong> <?= $previewData['modules'] ?> |
                        <strong>Permissions :</strong> <?= $previewData['perms'] ?>
                    </p>
                <?php endif; ?>

                <hr class="ie-separator">
                <div style="display:flex;gap:10px;align-items:center">
                    <form method="post" action="?tab=import" style="display:inline">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                        <input type="hidden" name="action" value="confirm_import">
                        <?php if (($pending['type'] ?? '') === 'users'): ?>
                            <div class="checkbox-row" style="margin-bottom:8px">
                                <input type="checkbox" name="generate_passwords" id="gen_pwd" checked>
                                <label for="gen_pwd">Generer les mots de passe automatiquement</label>
                            </div>
                        <?php endif; ?>
                        <button type="submit" class="btn-export success"><i class="fas fa-check"></i> Confirmer l'import</button>
                    </form>
                    <form method="post" action="?tab=import" style="display:inline">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                        <input type="hidden" name="action" value="cancel_import">
                        <button type="submit" class="btn-export danger"><i class="fas fa-times"></i> Annuler</button>
                    </form>
                </div>
            </div>

        <?php elseif ($importResult): ?>
            <!-- Resultat de l'import -->
            <div class="ie-card">
                <h3><i class="fas fa-clipboard-check"></i> Resultat de l'import</h3>
                <div style="display:flex;gap:20px;margin-bottom:15px">
                    <?php if (isset($importResult['nb_total'])): ?>
                        <div style="text-align:center">
                            <div style="font-size:20px;font-weight:700;color:#2d3748"><?= $importResult['nb_total'] ?></div>
                            <div style="font-size:11px;color:#718096">Total</div>
                        </div>
                    <?php endif; ?>
                    <?php if (isset($importResult['nb_importes'])): ?>
                        <div style="text-align:center">
                            <div style="font-size:20px;font-weight:700;color:#059669"><?= $importResult['nb_importes'] ?></div>
                            <div style="font-size:11px;color:#718096">Importes</div>
                        </div>
                    <?php endif; ?>
                    <?php if (isset($importResult['nb_doublons']) && $importResult['nb_doublons'] > 0): ?>
                        <div style="text-align:center">
                            <div style="font-size:20px;font-weight:700;color:#d97706"><?= $importResult['nb_doublons'] ?></div>
                            <div style="font-size:11px;color:#718096">Doublons</div>
                        </div>
                    <?php endif; ?>
                    <?php if (isset($importResult['nb_erreurs']) && $importResult['nb_erreurs'] > 0): ?>
                        <div style="text-align:center">
                            <div style="font-size:20px;font-weight:700;color:#dc2626"><?= $importResult['nb_erreurs'] ?></div>
                            <div style="font-size:11px;color:#718096">Erreurs</div>
                        </div>
                    <?php endif; ?>
                    <?php if (isset($importResult['nb_traites'])): ?>
                        <div style="text-align:center">
                            <div style="font-size:20px;font-weight:700;color:#059669"><?= $importResult['nb_traites'] ?></div>
                            <div style="font-size:11px;color:#718096">Traites</div>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($importResult['erreurs'])): ?>
                    <details>
                        <summary style="font-size:12px;cursor:pointer;color:#991b1b;font-weight:600">Voir les erreurs (<?= count($importResult['erreurs']) ?>)</summary>
                        <ul class="errors-list">
                            <?php foreach ($importResult['erreurs'] as $err): ?>
                                <li><?= htmlspecialchars($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </details>
                <?php endif; ?>

                <?php if (!empty($passwordsList)): ?>
                    <div class="passwords-card">
                        <h4><i class="fas fa-key"></i> Mots de passe generes (a communiquer aux utilisateurs)</h4>
                        <table class="passwords-table">
                            <thead><tr><th>Identifiant</th><th>Nom</th><th>Prenom</th><th>Type</th><th>Mot de passe</th></tr></thead>
                            <tbody>
                                <?php foreach ($passwordsList as $p): ?>
                                    <tr>
                                        <td style="font-family:monospace"><?= htmlspecialchars($p['identifiant']) ?></td>
                                        <td><?= htmlspecialchars($p['nom']) ?></td>
                                        <td><?= htmlspecialchars($p['prenom']) ?></td>
                                        <td><?= htmlspecialchars($p['type']) ?></td>
                                        <td style="font-family:monospace;font-weight:600"><?= htmlspecialchars($p['password']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <div style="margin-top:15px">
                    <a href="?tab=import" class="btn-export secondary"><i class="fas fa-redo"></i> Nouvel import</a>
                </div>
            </div>

        <?php else: ?>
            <!-- Formulaire d'upload -->
            <div class="ie-card">
                <h3><i class="fas fa-file-upload"></i> Importer des utilisateurs (CSV)</h3>
                <p style="font-size:12px;color:#718096;margin:0 0 10px">
                    Le fichier CSV doit contenir les colonnes : <code>nom</code>, <code>prenom</code>, <code>mail</code>, <code>identifiant</code> (optionnel).
                    Separateur : point-virgule (;). Encodage : UTF-8.
                </p>
                <form method="post" action="?tab=import" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="action" value="preview_import">
                    <input type="hidden" name="import_type" value="users">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Type d'utilisateur cible</label>
                            <select name="user_type_import">
                                <?php foreach ($userTypes as $key => $label): ?>
                                    <option value="<?= $key ?>"><?= htmlspecialchars($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Fichier CSV</label>
                            <input type="file" name="import_file" accept=".csv,.txt" required>
                        </div>
                        <button type="submit" class="btn-export primary"><i class="fas fa-eye"></i> Previsualiser</button>
                    </div>
                </form>
            </div>

            <div class="ie-card">
                <h3><i class="fas fa-file-import"></i> Importer une configuration (JSON)</h3>
                <p style="font-size:12px;color:#718096;margin:0 0 10px">
                    Importez un fichier JSON prealablement exporte depuis l'onglet Export.
                    Cela mettra a jour les modules et permissions existants.
                </p>
                <form method="post" action="?tab=import" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="action" value="preview_import">
                    <input type="hidden" name="import_type" value="config">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Fichier JSON</label>
                            <input type="file" name="import_file" accept=".json" required>
                        </div>
                        <button type="submit" class="btn-export primary"><i class="fas fa-eye"></i> Previsualiser</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

    <?php endif; ?>

    <!-- ================= ONGLET HISTORIQUE ================= -->
    <?php if ($activeTab === 'historique'): ?>

        <div class="ie-card">
            <h3><i class="fas fa-history"></i> Historique des operations (<?= $historyCount ?> entrees)</h3>

            <?php if (empty($history)): ?>
                <div style="text-align:center;padding:30px;color:#999">
                    <i class="fas fa-inbox" style="font-size:32px;margin-bottom:10px;display:block"></i>
                    Aucune operation enregistree.
                </div>
            <?php else: ?>
                <div style="overflow-x:auto">
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Cible</th>
                                <th>Format</th>
                                <th>Fichier</th>
                                <th>Lignes</th>
                                <th>Traitees</th>
                                <th>Erreurs</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history as $h): ?>
                                <tr>
                                    <td style="color:#888"><?= $h['id'] ?></td>
                                    <td style="font-size:11px;white-space:nowrap"><?= date('d/m/Y H:i', strtotime($h['created_at'])) ?></td>
                                    <td>
                                        <span class="badge-type badge-<?= $h['type'] ?>">
                                            <?= $h['type'] === 'export' ? 'Export' : 'Import' ?>
                                        </span>
                                    </td>
                                    <td style="font-size:11px"><?= htmlspecialchars($h['cible'] ?? '-') ?></td>
                                    <td><code style="font-size:10px"><?= htmlspecialchars($h['format'] ?? '-') ?></code></td>
                                    <td style="font-size:11px;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= htmlspecialchars($h['fichier_nom'] ?? '') ?>"><?= htmlspecialchars($h['fichier_nom'] ?? '-') ?></td>
                                    <td style="text-align:center"><?= $h['nb_lignes_total'] ?? 0 ?></td>
                                    <td style="text-align:center;color:#059669;font-weight:600"><?= $h['nb_lignes_traitees'] ?? 0 ?></td>
                                    <td style="text-align:center">
                                        <?php if (($h['nb_erreurs'] ?? 0) > 0): ?>
                                            <span style="color:#dc2626;font-weight:600"><?= $h['nb_erreurs'] ?></span>
                                        <?php else: ?>
                                            <span style="color:#999">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge-status badge-<?= $h['statut'] ?? 'termine' ?>">
                                            <?php
                                            $statusLabels = ['termine' => 'Termine', 'erreur' => 'Erreur', 'en_cours' => 'En cours', 'annule' => 'Annule'];
                                            echo $statusLabels[$h['statut'] ?? 'termine'] ?? $h['statut'];
                                            ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/sub_footer.php'; ?>
