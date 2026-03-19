<?php
/**
 * Administration — Permissions CRUD par module et par role
 * Gere une matrice module x role avec des checkboxes pour chaque permission.
 */
require_once __DIR__ . '/../../API/core.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAuth();
requireRole('administrateur');

$pdo = getPDO();
$moduleService = app('modules');
$message = '';
$messageType = '';

// ─── Roles ──────────────────────────────────────────────────────────────────
$roles = [
    'administrateur' => 'Administrateur',
    'professeur'     => 'Professeur',
    'vie_scolaire'   => 'Vie scolaire',
    'eleve'          => 'Eleve',
    'parent'         => 'Parent',
];

// ─── Permissions standard & custom par module ────────────────────────────────
$standardPerms = [
    'can_view'   => ['label' => 'Voir',      'icon' => 'fas fa-eye'],
    'can_create' => ['label' => 'Creer',     'icon' => 'fas fa-plus'],
    'can_edit'   => ['label' => 'Modifier',   'icon' => 'fas fa-pencil-alt'],
    'can_delete' => ['label' => 'Supprimer',  'icon' => 'fas fa-trash-alt'],
    'can_export' => ['label' => 'Exporter',   'icon' => 'fas fa-file-export'],
    'can_import' => ['label' => 'Importer',   'icon' => 'fas fa-file-import'],
];

$customPermsByModule = [
    'messagerie' => [
        'can_send'     => ['label' => 'Envoyer',  'icon' => 'fas fa-paper-plane'],
        'can_moderate' => ['label' => 'Moderer',  'icon' => 'fas fa-gavel'],
        'can_broadcast'=> ['label' => 'Diffuser', 'icon' => 'fas fa-bullhorn'],
    ],
];

// ─── Permissions par defaut ──────────────────────────────────────────────────
function getDefaultPermissions(): array {
    return [
        'administrateur' => [
            'can_view' => 1, 'can_create' => 1, 'can_edit' => 1,
            'can_delete' => 1, 'can_export' => 1, 'can_import' => 1,
            'custom' => ['can_send' => true, 'can_moderate' => true, 'can_broadcast' => true],
        ],
        'professeur' => [
            'can_view' => 1, 'can_create' => 1, 'can_edit' => 1,
            'can_delete' => 0, 'can_export' => 1, 'can_import' => 0,
            'custom' => ['can_send' => true, 'can_moderate' => false, 'can_broadcast' => false],
        ],
        'vie_scolaire' => [
            'can_view' => 1, 'can_create' => 1, 'can_edit' => 1,
            'can_delete' => 0, 'can_export' => 1, 'can_import' => 1,
            'custom' => ['can_send' => true, 'can_moderate' => true, 'can_broadcast' => true],
        ],
        'eleve' => [
            'can_view' => 1, 'can_create' => 0, 'can_edit' => 0,
            'can_delete' => 0, 'can_export' => 0, 'can_import' => 0,
            'custom' => ['can_send' => true, 'can_moderate' => false, 'can_broadcast' => false],
        ],
        'parent' => [
            'can_view' => 1, 'can_create' => 0, 'can_edit' => 0,
            'can_delete' => 0, 'can_export' => 0, 'can_import' => 0,
            'custom' => ['can_send' => true, 'can_moderate' => false, 'can_broadcast' => false],
        ],
    ];
}

// ─── CSRF ────────────────────────────────────────────────────────────────────
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// ─── Traitement POST ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['csrf_token'] ?? '') === $csrf_token) {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_permissions') {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO module_permissions
                    (module_key, role, can_view, can_create, can_edit, can_delete, can_export, can_import, custom_permissions)
                VALUES
                    (:module_key, :role, :can_view, :can_create, :can_edit, :can_delete, :can_export, :can_import, :custom_permissions)
                ON DUPLICATE KEY UPDATE
                    can_view = VALUES(can_view),
                    can_create = VALUES(can_create),
                    can_edit = VALUES(can_edit),
                    can_delete = VALUES(can_delete),
                    can_export = VALUES(can_export),
                    can_import = VALUES(can_import),
                    custom_permissions = VALUES(custom_permissions)
            ");

            $allModules = $moduleService->getAll();
            $roleKeys = array_keys($GLOBALS['roles'] ?? $roles);

            foreach ($allModules as $moduleKey => $mod) {
                foreach ($roleKeys as $role) {
                    $prefix = "perm_{$moduleKey}_{$role}_";
                    $canView   = !empty($_POST[$prefix . 'can_view'])   ? 1 : 0;
                    $canCreate = !empty($_POST[$prefix . 'can_create']) ? 1 : 0;
                    $canEdit   = !empty($_POST[$prefix . 'can_edit'])   ? 1 : 0;
                    $canDelete = !empty($_POST[$prefix . 'can_delete']) ? 1 : 0;
                    $canExport = !empty($_POST[$prefix . 'can_export']) ? 1 : 0;
                    $canImport = !empty($_POST[$prefix . 'can_import']) ? 1 : 0;

                    // Custom permissions
                    $customPerms = null;
                    if (isset($customPermsByModule[$moduleKey])) {
                        $cp = [];
                        foreach ($customPermsByModule[$moduleKey] as $cpKey => $cpMeta) {
                            $cp[$cpKey] = !empty($_POST[$prefix . $cpKey]);
                        }
                        $customPerms = json_encode($cp);
                    }

                    $stmt->execute([
                        ':module_key'         => $moduleKey,
                        ':role'               => $role,
                        ':can_view'           => $canView,
                        ':can_create'         => $canCreate,
                        ':can_edit'           => $canEdit,
                        ':can_delete'         => $canDelete,
                        ':can_export'         => $canExport,
                        ':can_import'         => $canImport,
                        ':custom_permissions' => $customPerms,
                    ]);
                }
            }

            $pdo->commit();
            logAudit('permissions.updated', 'module_permissions', null, null, ['action' => 'bulk_save']);
            $message = 'Permissions enregistrees avec succes.';
            $messageType = 'success';
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Permissions save error: " . $e->getMessage());
            $message = 'Erreur lors de l\'enregistrement des permissions.';
            $messageType = 'error';
        }
    }

    if ($action === 'reset_defaults') {
        try {
            $pdo->beginTransaction();

            $pdo->exec("DELETE FROM module_permissions");

            $defaults = getDefaultPermissions();
            $allModules = $moduleService->getAll();
            $roleKeys = array_keys($roles);

            $stmt = $pdo->prepare("
                INSERT INTO module_permissions
                    (module_key, role, can_view, can_create, can_edit, can_delete, can_export, can_import, custom_permissions)
                VALUES
                    (:module_key, :role, :can_view, :can_create, :can_edit, :can_delete, :can_export, :can_import, :custom_permissions)
            ");

            foreach ($allModules as $moduleKey => $mod) {
                foreach ($roleKeys as $role) {
                    $def = $defaults[$role] ?? $defaults['eleve'];
                    $customPerms = null;
                    if (isset($customPermsByModule[$moduleKey]) && isset($def['custom'])) {
                        $customPerms = json_encode($def['custom']);
                    }

                    $stmt->execute([
                        ':module_key'         => $moduleKey,
                        ':role'               => $role,
                        ':can_view'           => $def['can_view'],
                        ':can_create'         => $def['can_create'],
                        ':can_edit'           => $def['can_edit'],
                        ':can_delete'         => $def['can_delete'],
                        ':can_export'         => $def['can_export'],
                        ':can_import'         => $def['can_import'],
                        ':custom_permissions' => $customPerms,
                    ]);
                }
            }

            $pdo->commit();
            logAudit('permissions.reset', 'module_permissions', null, null, ['action' => 'reset_defaults']);
            $message = 'Permissions reintialisees aux valeurs par defaut.';
            $messageType = 'success';
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Permissions reset error: " . $e->getMessage());
            $message = 'Erreur lors de la reinitialisation des permissions.';
            $messageType = 'error';
        }
    }
}

// ─── Chargement des donnees ──────────────────────────────────────────────────
$categories = $moduleService->getByCategory();
$categoryLabels = \API\Services\ModuleService::categoryLabels();

// Charger toutes les permissions existantes
$permStmt = $pdo->query("SELECT * FROM module_permissions");
$permRows = $permStmt->fetchAll(PDO::FETCH_ASSOC);

$permissions = [];
foreach ($permRows as $row) {
    $mk = $row['module_key'];
    $rl = $row['role'];
    $permissions[$mk][$rl] = $row;
    if (!empty($row['custom_permissions'])) {
        $permissions[$mk][$rl]['custom'] = json_decode($row['custom_permissions'], true);
    }
}

/**
 * Helper: verifier si une permission est activee
 */
function permChecked(array $permissions, string $moduleKey, string $role, string $perm): bool {
    if (isset($permissions[$moduleKey][$role])) {
        $row = $permissions[$moduleKey][$role];
        // Standard permission columns
        if (isset($row[$perm])) {
            return (bool)$row[$perm];
        }
        // Custom permissions from JSON
        if (isset($row['custom'][$perm])) {
            return (bool)$row['custom'][$perm];
        }
    }
    return false;
}

// ─── Page ────────────────────────────────────────────────────────────────────
$pageTitle = 'Permissions des modules';
$currentPage = 'modules';
$extraCss = ['../../assets/css/admin.css'];

include __DIR__ . '/../includes/sub_header.php';
?>

<style>
/* ─── Permissions page styles ─── */
.perm-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px}
.perm-header h2{margin:0;font-size:1.2em;color:#2d3748;display:flex;align-items:center;gap:8px}
.perm-header-actions{display:flex;gap:10px;flex-wrap:wrap}

.perm-category{margin-bottom:32px}
.perm-category-title{font-size:1.05em;font-weight:700;color:#4a5568;margin-bottom:14px;padding-bottom:8px;border-bottom:2px solid #e2e8f0;display:flex;align-items:center;gap:8px}
.perm-category-title i{color:#667eea}

.perm-table-wrap{overflow-x:auto;border:1px solid #e2e8f0;border-radius:8px;background:#fff;margin-bottom:16px}
.perm-table{width:100%;border-collapse:collapse;font-size:.88em}
.perm-table thead{background:#f7fafc;position:sticky;top:0;z-index:10}
.perm-table thead th{padding:12px 10px;text-align:center;font-weight:600;color:#4a5568;border-bottom:2px solid #e2e8f0;white-space:nowrap}
.perm-table thead th:first-child{text-align:left;min-width:180px;padding-left:16px}
.perm-table thead th.role-col{min-width:120px}
.perm-table tbody tr{border-bottom:1px solid #edf2f7;transition:background .15s}
.perm-table tbody tr:hover{background:#f8faff}
.perm-table tbody tr:last-child{border-bottom:none}
.perm-table tbody td{padding:10px;text-align:center;vertical-align:top}
.perm-table tbody td:first-child{text-align:left;padding-left:16px;font-weight:500;color:#2d3748}

.module-cell{display:flex;align-items:center;gap:10px}
.module-cell-icon{width:32px;height:32px;border-radius:6px;background:#f0f4ff;display:flex;align-items:center;justify-content:center;font-size:.9em;color:#667eea;flex-shrink:0}
.module-cell-info{display:flex;flex-direction:column}
.module-cell-name{font-weight:600;font-size:.92em;color:#2d3748}
.module-cell-key{font-size:.75em;color:#a0aec0;font-family:monospace}

.perm-checks{display:flex;flex-direction:column;gap:3px;align-items:flex-start}
.perm-check{display:flex;align-items:center;gap:5px;cursor:pointer;font-size:.82em;color:#4a5568;padding:2px 4px;border-radius:4px;transition:background .1s;white-space:nowrap}
.perm-check:hover{background:#edf2f7}
.perm-check input[type="checkbox"]{margin:0;cursor:pointer;accent-color:#667eea;width:14px;height:14px}
.perm-check i{font-size:.75em;color:#a0aec0;width:14px;text-align:center}
.perm-check.custom-perm{color:#667eea;font-weight:500}
.perm-check.custom-perm i{color:#667eea}

.perm-divider{border-top:1px dashed #e2e8f0;margin:3px 0;width:100%}

.role-header{display:flex;flex-direction:column;align-items:center;gap:2px}
.role-header-name{font-weight:700;font-size:.9em}
.role-header-badge{font-size:.7em;padding:1px 8px;border-radius:8px;font-weight:500}
.role-badge-administrateur{background:#ebf4ff;color:#3182ce}
.role-badge-professeur{background:#fefcbf;color:#975a16}
.role-badge-vie_scolaire{background:#e9d8fd;color:#6b46c1}
.role-badge-eleve{background:#c6f6d5;color:#276749}
.role-badge-parent{background:#fed7d7;color:#9b2c2c}

.btn-save-perms{padding:10px 24px;background:#667eea;color:#fff;border:none;border-radius:6px;font-weight:600;cursor:pointer;font-size:.92em;display:flex;align-items:center;gap:8px;transition:background .2s}
.btn-save-perms:hover{background:#5a67d8}
.btn-reset{padding:10px 20px;background:#fff;color:#e53e3e;border:1px solid #feb2b2;border-radius:6px;font-weight:600;cursor:pointer;font-size:.92em;display:flex;align-items:center;gap:8px;transition:all .2s}
.btn-reset:hover{background:#fff5f5;border-color:#e53e3e}
.btn-back{padding:10px 24px;background:#edf2f7;color:#4a5568;border:none;border-radius:6px;font-weight:600;cursor:pointer;text-decoration:none;font-size:.92em;display:inline-flex;align-items:center;gap:8px}
.btn-back:hover{background:#e2e8f0}

.msg{padding:12px 16px;border-radius:6px;margin-bottom:16px;font-size:.92em;display:flex;align-items:center;gap:8px}
.msg-success{background:#f0fff4;border:1px solid #9ae6b4;color:#276749}
.msg-error{background:#fff5f5;border:1px solid #feb2b2;color:#c53030}

.select-all-bar{display:flex;gap:8px;align-items:center;margin-bottom:8px;flex-wrap:wrap}
.select-all-btn{font-size:.78em;padding:4px 10px;border:1px solid #e2e8f0;border-radius:4px;background:#f8f9fa;color:#4a5568;cursor:pointer;transition:all .15s}
.select-all-btn:hover{background:#edf2f7;border-color:#cbd5e0}

.sticky-save{position:sticky;bottom:0;background:#fff;border-top:2px solid #e2e8f0;padding:14px 20px;display:flex;align-items:center;justify-content:space-between;gap:12px;box-shadow:0 -2px 8px rgba(0,0,0,.05);z-index:100;margin-top:20px;border-radius:0 0 8px 8px}

@media(max-width:900px){
    .perm-table{font-size:.8em}
    .perm-table thead th,.perm-table tbody td{padding:8px 6px}
    .module-cell-icon{display:none}
    .perm-header{flex-direction:column;align-items:flex-start}
}
</style>

<?php if ($message): ?>
<div class="msg msg-<?= $messageType ?>">
    <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
    <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<div class="perm-header">
    <h2><i class="fas fa-shield-alt" style="color:#667eea"></i> Matrice des permissions</h2>
    <div class="perm-header-actions">
        <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Modules</a>
    </div>
</div>

<form method="post" id="permissionsForm">
    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
    <input type="hidden" name="action" value="save_permissions">

    <?php foreach ($categories as $catKey => $modules): ?>
    <div class="perm-category">
        <div class="perm-category-title">
            <i class="fas fa-folder"></i>
            <?= htmlspecialchars($categoryLabels[$catKey] ?? ucfirst($catKey)) ?>
            <span style="font-size:.78em;font-weight:400;color:#a0aec0">(<?= count($modules) ?> modules)</span>
        </div>

        <div class="perm-table-wrap">
            <table class="perm-table">
                <thead>
                    <tr>
                        <th>Module</th>
                        <?php foreach ($roles as $roleKey => $roleLabel): ?>
                        <th class="role-col">
                            <div class="role-header">
                                <span class="role-header-name"><?= htmlspecialchars($roleLabel) ?></span>
                                <span class="role-header-badge role-badge-<?= $roleKey ?>"><?= $roleKey ?></span>
                            </div>
                        </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($modules as $mod):
                        $mk = $mod['module_key'];
                        $hasCustom = isset($customPermsByModule[$mk]);
                    ?>
                    <tr>
                        <td>
                            <div class="module-cell">
                                <div class="module-cell-icon">
                                    <i class="<?= htmlspecialchars($mod['icon']) ?>"></i>
                                </div>
                                <div class="module-cell-info">
                                    <span class="module-cell-name"><?= htmlspecialchars($mod['label']) ?></span>
                                    <span class="module-cell-key"><?= htmlspecialchars($mk) ?></span>
                                </div>
                            </div>
                        </td>
                        <?php foreach ($roles as $roleKey => $roleLabel):
                            $prefix = "perm_{$mk}_{$roleKey}_";
                        ?>
                        <td>
                            <div class="perm-checks">
                                <?php foreach ($standardPerms as $permKey => $permMeta): ?>
                                <label class="perm-check">
                                    <input type="checkbox"
                                           name="<?= $prefix . $permKey ?>"
                                           value="1"
                                           <?= permChecked($permissions, $mk, $roleKey, $permKey) ? 'checked' : '' ?>>
                                    <i class="<?= $permMeta['icon'] ?>"></i>
                                    <?= $permMeta['label'] ?>
                                </label>
                                <?php endforeach; ?>

                                <?php if ($hasCustom): ?>
                                <div class="perm-divider"></div>
                                <?php foreach ($customPermsByModule[$mk] as $cpKey => $cpMeta): ?>
                                <label class="perm-check custom-perm">
                                    <input type="checkbox"
                                           name="<?= $prefix . $cpKey ?>"
                                           value="1"
                                           <?= permChecked($permissions, $mk, $roleKey, $cpKey) ? 'checked' : '' ?>>
                                    <i class="<?= $cpMeta['icon'] ?>"></i>
                                    <?= $cpMeta['label'] ?>
                                </label>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="sticky-save">
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <button type="button" class="select-all-btn" onclick="toggleAllPerms(true)">
                <i class="fas fa-check-double"></i> Tout cocher
            </button>
            <button type="button" class="select-all-btn" onclick="toggleAllPerms(false)">
                <i class="fas fa-times"></i> Tout decocher
            </button>
            <button type="button" class="select-all-btn" onclick="toggleColumnPerms('can_view', true)">
                <i class="fas fa-eye"></i> Tout voir
            </button>
        </div>
        <div style="display:flex;gap:10px;align-items:center">
            <button type="submit" class="btn-save-perms">
                <i class="fas fa-save"></i> Enregistrer les permissions
            </button>
        </div>
    </div>
</form>

<!-- Reset form (separate to avoid accidental save) -->
<div style="margin-top:20px;padding:20px;background:#fff;border:1px solid #e2e8f0;border-radius:8px">
    <h3 style="margin:0 0 12px;font-size:1em;color:#2d3748;display:flex;align-items:center;gap:8px">
        <i class="fas fa-undo" style="color:#e53e3e"></i> Reinitialiser les permissions
    </h3>
    <p style="font-size:.88em;color:#718096;margin:0 0 14px">
        Cette action remplacera toutes les permissions actuelles par les valeurs par defaut.
        Les administrateurs auront tous les droits, les professeurs et la vie scolaire des droits intermediaires,
        les eleves et parents des droits en lecture seule.
    </p>
    <form method="post" onsubmit="return confirm('Etes-vous sur de vouloir reinitialiser toutes les permissions aux valeurs par defaut ? Cette action est irreversible.')">
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
        <input type="hidden" name="action" value="reset_defaults">
        <button type="submit" class="btn-reset">
            <i class="fas fa-undo"></i> Reinitialiser les permissions par defaut
        </button>
    </form>
</div>

<div style="margin-top:20px">
    <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Retour aux modules</a>
</div>

<script>
function toggleAllPerms(checked) {
    document.querySelectorAll('#permissionsForm input[type="checkbox"]').forEach(function(cb) {
        cb.checked = checked;
    });
}

function toggleColumnPerms(permName, checked) {
    document.querySelectorAll('#permissionsForm input[type="checkbox"]').forEach(function(cb) {
        if (cb.name.endsWith('_' + permName)) {
            cb.checked = checked;
        }
    });
}
</script>

<?php include __DIR__ . '/../includes/sub_footer.php'; ?>
