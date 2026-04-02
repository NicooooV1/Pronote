<?php

declare(strict_types=1);

namespace API\Admin;

/**
 * Classe générique pour les pages CRUD admin.
 *
 * Reçoit une configuration déclarative et gère :
 *  - CSRF, messages flash, dispatch POST (create/edit/delete/toggle_*)
 *  - Rendu du tableau, des modals de création/édition
 *  - Inclusion automatique des headers/footers admin
 *
 * Usage :
 *   $page = new AdminCrudPage([...config...]);
 *   $page->handle();
 *   $page->render();
 */
class AdminCrudPage
{
    private array $config;
    private string $message = '';
    private string $error = '';
    private array $items = [];
    private string $csrfToken;

    public function __construct(array $config)
    {
        $defaults = [
            'title'       => 'Administration',
            'currentPage' => '',
            'service'     => null,
            'columns'     => [],
            'form_fields' => [],
            'actions'     => ['edit', 'delete'],
            'stats'       => null,
            'extraCss'    => [],
            'extraStyle'  => '',
            'idField'     => 'id',
            'entityName'  => 'Élément',
            'listMethod'  => 'getAll',
            'createLabel' => null,
        ];
        $this->config = array_merge($defaults, $config);

        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        $this->csrfToken = $_SESSION['csrf_token'];
    }

    /**
     * Gère les actions POST (create, edit, delete, toggle_*).
     */
    public function handle(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }
        if (($_POST['csrf_token'] ?? '') !== $this->csrfToken) {
            $this->error = 'Jeton CSRF invalide.';
            return;
        }

        $action  = $_POST['action'] ?? '';
        $service = $this->config['service'];
        $idField = $this->config['idField'];

        try {
            if ($action === 'create') {
                $data = $this->extractFormData();
                $service->create($data);
                $this->message = $this->config['entityName'] . ' créé(e).';
            } elseif ($action === 'edit') {
                $id   = (int) ($_POST[$idField] ?? 0);
                $data = $this->extractFormData(true);
                $service->update($id, $data);
                $this->message = $this->config['entityName'] . ' modifié(e).';
            } elseif ($action === 'delete') {
                $id = (int) ($_POST[$idField] ?? 0);
                $service->delete($id);
                $this->message = $this->config['entityName'] . ' supprimé(e).';
            } elseif (str_starts_with($action, 'toggle_')) {
                $id     = (int) ($_POST[$idField] ?? 0);
                $field  = substr($action, 7); // e.g. "active" from "toggle_active"
                $method = 'toggle' . ucfirst($field);
                if (method_exists($service, $method)) {
                    $service->$method($id);
                    $this->message = 'Statut modifié.';
                }
            }
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    /**
     * Charge les données et affiche la page complète (header + contenu + footer).
     */
    public function render(): void
    {
        $service    = $this->config['service'];
        $listMethod = $this->config['listMethod'];
        $this->items = $service->$listMethod();

        // Variables pour le header
        $pageTitle  = $this->config['title'];
        $currentPage = $this->config['currentPage'];
        $extraCss   = $this->config['extraCss'];

        // Extra style inline
        $extraHeadHtml = '';
        if (!empty($this->config['extraStyle'])) {
            $extraHeadHtml = '<style>' . $this->config['extraStyle'] . '</style>';
        }

        // Include header (sets up sidebar, topbar, etc.)
        include dirname(__DIR__, 2) . '/admin/includes/header.php';

        // Render page body
        $this->renderBody();

        // Include footer
        include dirname(__DIR__, 2) . '/admin/includes/footer.php';
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function renderBody(): void
    {
        $e = fn(?string $v): string => htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
        $columns    = $this->config['columns'];
        $actions    = $this->config['actions'];
        $formFields = $this->config['form_fields'];
        $idField    = $this->config['idField'];
        $csrf       = $this->csrfToken;
        $createLabel = $this->config['createLabel'] ?? ('Nouveau ' . mb_strtolower($this->config['entityName']));
        ?>
        <div style="max-width:960px;margin:0 auto">
            <?php if ($this->message): ?>
                <div class="alert alert-success"><?= $e($this->message) ?></div>
            <?php endif; ?>
            <?php if ($this->error): ?>
                <div class="alert alert-danger"><?= $e($this->error) ?></div>
            <?php endif; ?>

            <div class="top-bar">
                <?php if (in_array('create', array_merge($actions, ['create']), true)): ?>
                    <button class="btn btn-primary" onclick="document.getElementById('crudCreateModal').classList.add('active')">
                        <i class="fas fa-plus"></i> <?= $e($createLabel) ?>
                    </button>
                <?php endif; ?>
            </div>

            <table class="crud-table" style="width:100%;border-collapse:collapse;background:white;border-radius:10px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.06)">
                <thead>
                    <tr>
                        <?php foreach ($columns as $col): ?>
                            <th style="padding:10px 14px;text-align:left;background:#f7fafc;font-weight:600;color:#4a5568;font-size:12px;border-bottom:1px solid #f0f0f0">
                                <?= $e($col['label']) ?>
                            </th>
                        <?php endforeach; ?>
                        <?php if (!empty($actions)): ?>
                            <th style="padding:10px 14px;text-align:left;background:#f7fafc;font-weight:600;color:#4a5568;font-size:12px;border-bottom:1px solid #f0f0f0">Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($this->items as $row): ?>
                        <tr>
                            <?php foreach ($columns as $key => $col): ?>
                                <td style="padding:10px 14px;font-size:13px;border-bottom:1px solid #f0f0f0">
                                    <?php if (isset($col['render'])): ?>
                                        <?= ($col['render'])($row[$key] ?? null, $row) ?>
                                    <?php else: ?>
                                        <?= $e((string) ($row[$key] ?? '')) ?>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                            <?php if (!empty($actions)): ?>
                                <td style="padding:10px 14px;font-size:13px;border-bottom:1px solid #f0f0f0">
                                    <?php $this->renderRowActions($row, $csrf, $idField, $actions); ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php $this->renderCreateModal($csrf, $formFields); ?>
        <?php $this->renderEditModal($csrf, $formFields, $idField); ?>

        <script>
        function crudOpenEdit(data) {
            <?php foreach ($formFields as $key => $field): ?>
                <?php if (($field['type'] ?? 'text') === 'checkbox'): ?>
                    document.getElementById('crud_e_<?= $key ?>').checked = !!parseInt(data['<?= $key ?>']);
                <?php else: ?>
                    if (document.getElementById('crud_e_<?= $key ?>')) document.getElementById('crud_e_<?= $key ?>').value = data['<?= $key ?>'] ?? '<?= $field['default'] ?? '' ?>';
                <?php endif; ?>
            <?php endforeach; ?>
            document.getElementById('crud_e_id').value = data['<?= $idField ?>'];
            document.getElementById('crudEditModal').classList.add('active');
        }
        document.querySelectorAll('.modal-overlay').forEach(m => m.addEventListener('click', e => { if (e.target === m) m.classList.remove('active'); }));
        </script>
        <?php
    }

    private function renderRowActions(array $row, string $csrf, string $idField, array $actions): void
    {
        $id = $row[$idField] ?? 0;
        $jsonData = htmlspecialchars(json_encode($row, JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8');

        if (in_array('edit', $actions, true)) {
            echo "<button class=\"btn-xs primary\" onclick='crudOpenEdit({$jsonData})'><i class=\"fas fa-pen\"></i></button> ";
        }

        foreach ($actions as $action) {
            if (str_starts_with($action, 'toggle_')) {
                $field = substr($action, 7);
                $isActive = !empty($row[$field]);
                echo "<form method=\"post\" style=\"display:inline\">"
                    . "<input type=\"hidden\" name=\"csrf_token\" value=\"{$csrf}\">"
                    . "<input type=\"hidden\" name=\"action\" value=\"{$action}\">"
                    . "<input type=\"hidden\" name=\"{$idField}\" value=\"{$id}\">"
                    . "<button class=\"btn-xs " . ($isActive ? 'warning' : 'success') . "\" title=\"" . ($isActive ? 'Désactiver' : 'Activer') . "\">"
                    . "<i class=\"fas fa-" . ($isActive ? 'eye-slash' : 'eye') . "\"></i></button></form> ";
            }
        }

        if (in_array('delete', $actions, true)) {
            echo "<form method=\"post\" style=\"display:inline\" onsubmit=\"return confirm('Supprimer ?')\">"
                . "<input type=\"hidden\" name=\"csrf_token\" value=\"{$csrf}\">"
                . "<input type=\"hidden\" name=\"action\" value=\"delete\">"
                . "<input type=\"hidden\" name=\"{$idField}\" value=\"{$id}\">"
                . "<button class=\"btn-xs danger\"><i class=\"fas fa-trash\"></i></button></form>";
        }
    }

    private function renderCreateModal(string $csrf, array $formFields): void
    {
        ?>
        <div class="modal-overlay" id="crudCreateModal">
            <div class="modal-box">
                <h3><i class="fas fa-plus"></i> <?= htmlspecialchars($this->config['createLabel'] ?? 'Créer') ?></h3>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="action" value="create">
                    <?php foreach ($formFields as $key => $field): ?>
                        <?php if (!empty($field['edit_only'])) continue; ?>
                        <?php $this->renderFormField($key, $field, 'c'); ?>
                    <?php endforeach; ?>
                    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px">
                        <button type="button" class="btn btn-secondary" onclick="document.getElementById('crudCreateModal').classList.remove('active')">Annuler</button>
                        <button type="submit" class="btn btn-primary">Créer</button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    private function renderEditModal(string $csrf, array $formFields, string $idField): void
    {
        ?>
        <div class="modal-overlay" id="crudEditModal">
            <div class="modal-box">
                <h3><i class="fas fa-pen"></i> Modifier</h3>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="<?= $idField ?>" id="crud_e_id">
                    <?php foreach ($formFields as $key => $field): ?>
                        <?php $this->renderFormField($key, $field, 'e'); ?>
                    <?php endforeach; ?>
                    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px">
                        <button type="button" class="btn btn-secondary" onclick="document.getElementById('crudEditModal').classList.remove('active')">Annuler</button>
                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    private function renderFormField(string $key, array $field, string $prefix): void
    {
        $type     = $field['type'] ?? 'text';
        $label    = $field['label'] ?? $key;
        $required = !empty($field['required']) ? 'required' : '';
        $id       = "crud_{$prefix}_{$key}";
        $default  = $field['default'] ?? '';

        if ($type === 'checkbox') {
            echo "<div class=\"form-group\"><label><input type=\"checkbox\" name=\"{$key}\" id=\"{$id}\"> {$label}</label></div>";
            return;
        }

        $attrs = '';
        if (isset($field['maxlength'])) $attrs .= " maxlength=\"{$field['maxlength']}\"";
        if (isset($field['step']))      $attrs .= " step=\"{$field['step']}\"";
        if (isset($field['min']))       $attrs .= " min=\"{$field['min']}\"";
        if (isset($field['max']))       $attrs .= " max=\"{$field['max']}\"";
        if (isset($field['placeholder'])) $attrs .= " placeholder=\"" . htmlspecialchars($field['placeholder']) . "\"";

        if ($type === 'textarea') {
            echo "<div class=\"form-group\"><label>{$label}</label><textarea name=\"{$key}\" id=\"{$id}\" {$required}{$attrs}>{$default}</textarea></div>";
        } elseif ($type === 'select' && isset($field['options'])) {
            echo "<div class=\"form-group\"><label>{$label}</label><select name=\"{$key}\" id=\"{$id}\" {$required}>";
            foreach ($field['options'] as $val => $text) {
                $sel = ($val == $default) ? ' selected' : '';
                echo "<option value=\"" . htmlspecialchars((string) $val) . "\"{$sel}>" . htmlspecialchars($text) . "</option>";
            }
            echo "</select></div>";
        } else {
            $val = ($prefix === 'c' && $default !== '') ? " value=\"" . htmlspecialchars((string) $default) . "\"" : '';
            echo "<div class=\"form-group\"><label>{$label}</label><input type=\"{$type}\" name=\"{$key}\" id=\"{$id}\"{$val} {$required}{$attrs}></div>";
        }
    }

    private function extractFormData(bool $isEdit = false): array
    {
        $data = [];
        foreach ($this->config['form_fields'] as $key => $field) {
            if (!$isEdit && !empty($field['edit_only'])) {
                continue;
            }
            $type = $field['type'] ?? 'text';
            if ($type === 'checkbox') {
                $data[$key] = isset($_POST[$key]) ? 1 : 0;
            } else {
                $data[$key] = trim($_POST[$key] ?? '');
            }
        }
        return $data;
    }
}
