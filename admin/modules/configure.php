<?php
/**
 * Administration — Configuration d'un module
 * Permet de modifier le label, la description, l'icône,
 * et les paramètres JSON spécifiques au module.
 */
require_once __DIR__ . '/../../API/core.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAuth();
requireRole('administrateur');

$pdo = getPDO();
$moduleService = app('modules');
$message = '';
$messageType = '';

$moduleKey = $_GET['module'] ?? '';
$module = $moduleService->get($moduleKey);

if (!$module) {
    header('Location: index.php');
    exit;
}

// ─── Configuration spécifique par module ─────────────────────────────────────
// Chaque module peut avoir des champs de configuration spécifiques.
// On définit ici la structure des champs pour chaque module connu.
$configFields = getModuleConfigFields($moduleKey);

// ─── Traitement POST ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['csrf_token'] ?? '') === ($_SESSION['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_info') {
        $moduleService->updateInfo($moduleKey, [
            'label' => trim($_POST['label'] ?? $module['label']),
            'description' => trim($_POST['description'] ?? ''),
            'icon' => trim($_POST['icon'] ?? $module['icon']),
            'sort_order' => (int)($_POST['sort_order'] ?? $module['sort_order']),
        ]);
        logAudit('module.info_updated', 'modules_config', $module['id'], null, ['module' => $moduleKey]);
        $message = 'Informations du module mises à jour.';
        $messageType = 'success';
        $module = $moduleService->get($moduleKey); // Refresh
    }

    if ($action === 'update_roles') {
        $allRoles = ['administrateur', 'professeur', 'eleve', 'parent', 'personnel', 'vie_scolaire'];
        $selectedRoles = [];
        foreach ($allRoles as $r) {
            if (!empty($_POST['role_' . $r])) {
                $selectedRoles[] = $r;
            }
        }
        // null = tous les rôles (pas de restriction)
        $moduleService->updateRolesAutorises($moduleKey, count($selectedRoles) ? $selectedRoles : null);
        logAudit('module.roles_updated', 'modules_config', $module['id'], null, ['module' => $moduleKey, 'roles' => $selectedRoles]);
        $message = 'Rôles autorisés mis à jour.';
        $messageType = 'success';
        $module = $moduleService->get($moduleKey);
    }

    if ($action === 'update_config') {
        $config = [];
        foreach ($configFields as $field) {
            $key = $field['key'];
            if ($field['type'] === 'checkbox') {
                $config[$key] = !empty($_POST['cfg_' . $key]);
            } elseif ($field['type'] === 'number') {
                $config[$key] = (int)($_POST['cfg_' . $key] ?? $field['default'] ?? 0);
            } else {
                $config[$key] = trim($_POST['cfg_' . $key] ?? '');
            }
        }
        $moduleService->updateConfig($moduleKey, $config);
        logAudit('module.config_updated', 'modules_config', $module['id'], null, ['module' => $moduleKey, 'config' => $config]);
        $message = 'Configuration du module enregistrée.';
        $messageType = 'success';
        $module = $moduleService->get($moduleKey); // Refresh
    }
}

$currentConfig = $module['config'] ?? [];

$pageTitle = 'Configuration : ' . $module['label'];
$currentPage = 'modules';
$extraCss = ['../../assets/css/admin.css'];

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

include __DIR__ . '/../includes/sub_header.php';
?>

<style>
.config-card{background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:24px;margin-bottom:20px}
.config-card h3{font-size:1.05em;color:#2d3748;margin:0 0 16px;padding-bottom:10px;border-bottom:1px solid #edf2f7;display:flex;align-items:center;gap:8px}
.config-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
@media(max-width:640px){.config-grid{grid-template-columns:1fr}}
.field{margin-bottom:14px}
.field label{display:block;font-size:.88em;font-weight:600;color:#4a5568;margin-bottom:4px}
.field input,.field select,.field textarea{width:100%;padding:9px 12px;border:1px solid #e2e8f0;border-radius:6px;font-size:.92em}
.field input:focus,.field select:focus,.field textarea:focus{outline:none;border-color:#667eea;box-shadow:0 0 0 3px rgba(102,126,234,.12)}
.field .hint{font-size:.78em;color:#a0aec0;margin-top:3px}
.field textarea{min-height:80px;resize:vertical}
.mod-preview{display:flex;align-items:center;gap:14px;padding:16px;background:#f8f9fa;border-radius:8px;margin-bottom:20px}
.mod-preview-icon{width:50px;height:50px;border-radius:10px;background:#f0f4ff;display:flex;align-items:center;justify-content:center;font-size:1.3em;color:#667eea}
.mod-preview h4{margin:0;font-size:1em;color:#2d3748}
.mod-preview p{margin:2px 0 0;font-size:.85em;color:#a0aec0}
.btn-bar{display:flex;gap:10px;margin-top:16px}
.btn-save{padding:10px 24px;background:#667eea;color:#fff;border:none;border-radius:6px;font-weight:600;cursor:pointer;font-size:.92em}
.btn-save:hover{background:#5a67d8}
.btn-back{padding:10px 24px;background:#edf2f7;color:#4a5568;border:none;border-radius:6px;font-weight:600;cursor:pointer;text-decoration:none;font-size:.92em}
.btn-back:hover{background:#e2e8f0}
.msg{padding:12px 16px;border-radius:6px;margin-bottom:16px;font-size:.92em}
.msg-success{background:#f0fff4;border:1px solid #9ae6b4;color:#276749}
.msg-error{background:#fff5f5;border:1px solid #feb2b2;color:#c53030}
.no-config{color:#a0aec0;font-style:italic;padding:20px;text-align:center}
</style>

<div class="mod-preview">
    <div class="mod-preview-icon"><i class="<?= htmlspecialchars($module['icon']) ?>"></i></div>
    <div>
        <h4><?= htmlspecialchars($module['label']) ?></h4>
        <p><?= htmlspecialchars($module['description'] ?? '') ?></p>
        <div style="margin-top:4px">
            <?php if (!empty($module['is_core'])): ?>
                <span style="font-size:.75em;padding:2px 8px;border-radius:10px;background:#ebf4ff;color:#3182ce;font-weight:600">Système</span>
            <?php endif; ?>
            <span style="font-size:.75em;padding:2px 8px;border-radius:10px;background:<?= $module['enabled'] ? '#c6f6d5' : '#fed7d7' ?>;color:<?= $module['enabled'] ? '#276749' : '#9b2c2c' ?>;font-weight:600">
                <?= $module['enabled'] ? 'Activé' : 'Désactivé' ?>
            </span>
        </div>
    </div>
</div>

<?php if ($message): ?>
<div class="msg msg-<?= $messageType ?>"><?= $messageType === 'success' ? '✅' : '❌' ?> <?= $message ?></div>
<?php endif; ?>

<!-- Informations du module -->
<div class="config-card">
    <h3><i class="fas fa-info-circle"></i> Informations du module</h3>
    <form method="post">
        <input type="hidden" name="action" value="update_info">
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
        <div class="config-grid">
            <div class="field">
                <label>Nom affiché</label>
                <input type="text" name="label" value="<?= htmlspecialchars($module['label']) ?>" required>
            </div>
            <div class="field">
                <label>Icône (classe FontAwesome)</label>
                <input type="text" name="icon" value="<?= htmlspecialchars($module['icon']) ?>" placeholder="fas fa-puzzle-piece">
                <div class="hint">Ex: fas fa-book, fas fa-chart-bar, fas fa-users</div>
            </div>
            <div class="field" style="grid-column:1/-1">
                <label>Description</label>
                <textarea name="description" rows="2"><?= htmlspecialchars($module['description'] ?? '') ?></textarea>
            </div>
            <div class="field">
                <label>Ordre d'affichage</label>
                <input type="number" name="sort_order" value="<?= (int)$module['sort_order'] ?>" min="0" max="999">
                <div class="hint">Plus petit = affiché en premier dans la sidebar</div>
            </div>
        </div>
        <div class="btn-bar">
            <button type="submit" class="btn-save"><i class="fas fa-save"></i> Enregistrer</button>
        </div>
    </form>
</div>

<!-- Configuration spécifique -->
<div class="config-card">
    <h3><i class="fas fa-sliders-h"></i> Configuration spécifique</h3>
    <?php if (empty($configFields)): ?>
        <div class="no-config">Ce module n'a pas de paramètres spécifiques configurables.</div>
    <?php else: ?>
        <form method="post">
            <input type="hidden" name="action" value="update_config">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <div class="config-grid">
                <?php foreach ($configFields as $field): ?>
                <div class="field" style="<?= ($field['type'] === 'textarea') ? 'grid-column:1/-1' : '' ?>">
                    <label><?= htmlspecialchars($field['label']) ?></label>
                    <?php
                    $val = $currentConfig[$field['key']] ?? ($field['default'] ?? '');
                    $name = 'cfg_' . $field['key'];
                    ?>
                    <?php if ($field['type'] === 'text'): ?>
                        <input type="text" name="<?= $name ?>" value="<?= htmlspecialchars((string)$val) ?>" placeholder="<?= htmlspecialchars($field['placeholder'] ?? '') ?>">
                    <?php elseif ($field['type'] === 'number'): ?>
                        <input type="number" name="<?= $name ?>" value="<?= (int)$val ?>" min="<?= $field['min'] ?? 0 ?>" max="<?= $field['max'] ?? 9999 ?>">
                    <?php elseif ($field['type'] === 'checkbox'): ?>
                        <label style="font-weight:400;display:flex;align-items:center;gap:8px;cursor:pointer">
                            <input type="checkbox" name="<?= $name ?>" value="1" <?= !empty($val) ? 'checked' : '' ?>>
                            <?= htmlspecialchars($field['checkbox_label'] ?? 'Activer') ?>
                        </label>
                    <?php elseif ($field['type'] === 'select'): ?>
                        <select name="<?= $name ?>">
                            <?php foreach ($field['options'] as $optVal => $optLabel): ?>
                            <option value="<?= htmlspecialchars($optVal) ?>" <?= (string)$val === (string)$optVal ? 'selected' : '' ?>>
                                <?= htmlspecialchars($optLabel) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    <?php elseif ($field['type'] === 'textarea'): ?>
                        <textarea name="<?= $name ?>" rows="3"><?= htmlspecialchars((string)$val) ?></textarea>
                    <?php endif; ?>
                    <?php if (!empty($field['hint'])): ?>
                        <div class="hint"><?= htmlspecialchars($field['hint']) ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="btn-bar">
                <button type="submit" class="btn-save"><i class="fas fa-save"></i> Enregistrer la configuration</button>
            </div>
        </form>
    <?php endif; ?>
</div>

<!-- Rôles autorisés -->
<div class="config-card">
    <h3><i class="fas fa-users-cog"></i> Visibilité par rôle</h3>
    <p style="font-size:.88em;color:#718096;margin:0 0 16px">
        Définissez quels rôles peuvent voir ce module dans la sidebar. Laissez tout décoché pour autoriser tous les rôles (comportement par défaut).
    </p>
    <form method="post">
        <input type="hidden" name="action" value="update_roles">
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
        <?php
        $allRoles = [
            'administrateur' => 'Administrateur',
            'professeur'     => 'Professeur',
            'eleve'          => 'Élève',
            'parent'         => 'Parent',
            'personnel'      => 'Personnel',
            'vie_scolaire'   => 'Vie scolaire',
        ];
        $currentRoles = $module['roles_autorises'] ?? null;
        ?>
        <div style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:16px">
        <?php foreach ($allRoles as $roleKey => $roleLabel): ?>
            <label style="display:flex;align-items:center;gap:6px;font-size:.92em;cursor:pointer;padding:8px 14px;border:1px solid #e2e8f0;border-radius:6px;background:#f8f9fa">
                <input type="checkbox" name="role_<?= $roleKey ?>" value="1"
                    <?= (is_array($currentRoles) && in_array($roleKey, $currentRoles)) ? 'checked' : '' ?>>
                <?= htmlspecialchars($roleLabel) ?>
            </label>
        <?php endforeach; ?>
        </div>
        <?php if (empty($currentRoles)): ?>
            <p style="font-size:.82em;color:#48bb78;margin:0 0 12px"><i class="fas fa-check-circle"></i> Actuellement visible par tous les rôles.</p>
        <?php else: ?>
            <p style="font-size:.82em;color:#ed8936;margin:0 0 12px"><i class="fas fa-filter"></i> Restreint aux rôles cochés.</p>
        <?php endif; ?>
        <div class="btn-bar">
            <button type="submit" class="btn-save"><i class="fas fa-save"></i> Enregistrer les rôles</button>
        </div>
    </form>
</div>

<div class="btn-bar" style="margin-top:20px">
    <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Retour aux modules</a>
</div>

<?php include __DIR__ . '/../includes/sub_footer.php'; ?>

<?php
/**
 * Définit les champs de configuration pour chaque module.
 * Retourne un tableau de champs pour le module donné.
 */
function getModuleConfigFields(string $moduleKey): array {
    $fields = [
        'notes' => [
            ['key' => 'note_max', 'label' => 'Note maximale par défaut', 'type' => 'number', 'default' => 20, 'min' => 1, 'max' => 100],
            ['key' => 'show_class_average', 'label' => 'Moyenne de classe', 'type' => 'checkbox', 'default' => true, 'checkbox_label' => 'Afficher la moyenne de classe'],
            ['key' => 'show_rank', 'label' => 'Classement', 'type' => 'checkbox', 'default' => false, 'checkbox_label' => 'Afficher le classement'],
            ['key' => 'decimal_places', 'label' => 'Décimales affichées', 'type' => 'number', 'default' => 2, 'min' => 0, 'max' => 4],
        ],
        'absences' => [
            ['key' => 'auto_notify_parents', 'label' => 'Notification parents', 'type' => 'checkbox', 'default' => true, 'checkbox_label' => 'Notifier les parents automatiquement par email'],
            ['key' => 'justification_delay_days', 'label' => 'Délai de justification (jours)', 'type' => 'number', 'default' => 15, 'min' => 1, 'max' => 90],
            ['key' => 'allowed_file_types', 'label' => 'Types de fichiers acceptés', 'type' => 'text', 'default' => 'pdf,jpg,png', 'hint' => 'Extensions séparées par des virgules'],
        ],
        'bulletins' => [
            ['key' => 'show_absences', 'label' => 'Absences sur bulletin', 'type' => 'checkbox', 'default' => true, 'checkbox_label' => 'Afficher le nombre d\'absences'],
            ['key' => 'show_retards', 'label' => 'Retards sur bulletin', 'type' => 'checkbox', 'default' => true, 'checkbox_label' => 'Afficher le nombre de retards'],
            ['key' => 'appreciation_max_length', 'label' => 'Longueur max appréciation', 'type' => 'number', 'default' => 500, 'min' => 100, 'max' => 2000],
            ['key' => 'pdf_template_type', 'label' => 'Template PDF', 'type' => 'select', 'default' => 'standard', 'options' => ['standard' => 'Standard', 'minimal' => 'Minimaliste', 'detailed' => 'Détaillé']],
        ],
        'messagerie' => [
            ['key' => 'max_message_length', 'label' => 'Longueur max message', 'type' => 'number', 'default' => 5000, 'min' => 500, 'max' => 50000],
            ['key' => 'allow_attachments', 'label' => 'Pièces jointes', 'type' => 'checkbox', 'default' => true, 'checkbox_label' => 'Autoriser les pièces jointes'],
            ['key' => 'max_attachment_size_mb', 'label' => 'Taille max pièce jointe (Mo)', 'type' => 'number', 'default' => 5, 'min' => 1, 'max' => 50],
            ['key' => 'email_notification', 'label' => 'Notification email', 'type' => 'checkbox', 'default' => false, 'checkbox_label' => 'Envoyer un email pour chaque nouveau message'],
        ],
        'emploi_du_temps' => [
            ['key' => 'start_hour', 'label' => 'Heure de début', 'type' => 'text', 'default' => '08:00', 'hint' => 'Format HH:MM'],
            ['key' => 'end_hour', 'label' => 'Heure de fin', 'type' => 'text', 'default' => '18:00', 'hint' => 'Format HH:MM'],
            ['key' => 'slot_duration_minutes', 'label' => 'Durée d\'un créneau (min)', 'type' => 'number', 'default' => 60, 'min' => 15, 'max' => 120],
            ['key' => 'show_weekends', 'label' => 'Week-ends', 'type' => 'checkbox', 'default' => false, 'checkbox_label' => 'Afficher samedi et dimanche'],
        ],
        'devoirs' => [
            ['key' => 'max_file_size_mb', 'label' => 'Taille max rendu (Mo)', 'type' => 'number', 'default' => 10, 'min' => 1, 'max' => 100],
            ['key' => 'allowed_extensions', 'label' => 'Extensions autorisées', 'type' => 'text', 'default' => 'pdf,doc,docx,odt,jpg,png', 'hint' => 'Extensions séparées par des virgules'],
            ['key' => 'late_submission', 'label' => 'Rendus en retard', 'type' => 'checkbox', 'default' => false, 'checkbox_label' => 'Autoriser les rendus après la date limite'],
        ],
        'reunions' => [
            ['key' => 'slot_duration_minutes', 'label' => 'Durée créneau par défaut (min)', 'type' => 'number', 'default' => 15, 'min' => 5, 'max' => 60],
            ['key' => 'max_slots_per_parent', 'label' => 'Max créneaux par parent', 'type' => 'number', 'default' => 5, 'min' => 1, 'max' => 20],
            ['key' => 'send_confirmation_email', 'label' => 'Email de confirmation', 'type' => 'checkbox', 'default' => true, 'checkbox_label' => 'Envoyer un email de confirmation aux parents'],
        ],
        'discipline' => [
            ['key' => 'auto_notify_parents', 'label' => 'Notification parents', 'type' => 'checkbox', 'default' => true, 'checkbox_label' => 'Notifier les parents des incidents'],
            ['key' => 'penalty_levels', 'label' => 'Niveaux de sanction', 'type' => 'textarea', 'default' => "Avertissement\nBlâme\nExclusion temporaire\nExclusion définitive", 'hint' => 'Un niveau par ligne'],
        ],
        'inscriptions' => [
            ['key' => 'open_period', 'label' => 'Période d\'inscription ouverte', 'type' => 'checkbox', 'default' => false, 'checkbox_label' => 'Les inscriptions en ligne sont ouvertes'],
            ['key' => 'require_documents', 'label' => 'Documents obligatoires', 'type' => 'text', 'default' => 'Carte identité,Justificatif domicile,Photo', 'hint' => 'Séparés par des virgules'],
        ],
        'periscolaire' => [
            ['key' => 'cantine_enabled', 'label' => 'Cantine', 'type' => 'checkbox', 'default' => true, 'checkbox_label' => 'Activer le module cantine'],
            ['key' => 'garderie_enabled', 'label' => 'Garderie', 'type' => 'checkbox', 'default' => true, 'checkbox_label' => 'Activer la garderie'],
            ['key' => 'tarif_cantine', 'label' => 'Tarif cantine par défaut (€)', 'type' => 'number', 'default' => 3, 'min' => 0, 'max' => 50],
        ],
        'facturation' => [
            ['key' => 'currency', 'label' => 'Devise', 'type' => 'select', 'default' => 'EUR', 'options' => ['EUR' => 'Euro (€)', 'USD' => 'Dollar ($)', 'GBP' => 'Livre (£)', 'CHF' => 'Franc suisse (CHF)']],
            ['key' => 'tva_rate', 'label' => 'Taux TVA par défaut (%)', 'type' => 'number', 'default' => 0, 'min' => 0, 'max' => 30],
            ['key' => 'payment_reminder_days', 'label' => 'Rappel paiement (jours)', 'type' => 'number', 'default' => 30, 'min' => 7, 'max' => 90],
        ],
    ];

    return $fields[$moduleKey] ?? [];
}
?>
