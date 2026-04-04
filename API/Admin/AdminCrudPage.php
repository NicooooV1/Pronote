<?php

declare(strict_types=1);

namespace API\Admin;

/**
 * Classe générique pour les pages CRUD admin.
 *
 * Reçoit une configuration déclarative et gère :
 *  - CSRF, messages flash, dispatch POST (create/edit/delete/toggle_*)
 *  - Rendu du tableau avec pagination, tri, filtres, stats, onglets
 *  - Modals de création/édition
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
    private int $totalItems = 0;
    private int $currentPage = 1;
    private int $perPage = 30;
    private string $csrfToken;
    private string $sortBy = '';
    private string $sortDir = 'asc';
    private string $activeTab = '';

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
            'filters'     => [],
            'tabs'        => [],
            'extraCss'    => [],
            'extraStyle'  => '',
            'idField'     => 'id',
            'entityName'  => 'Élément',
            'listMethod'  => 'getAll',
            'createLabel' => null,
            'perPage'     => 30,
            'paginated'   => false,
            'sortable'    => false,
            'on_action'   => null,
            'emptyMessage'=> 'Aucun élément trouvé.',
        ];
        $this->config = array_merge($defaults, $config);
        $this->perPage = $this->config['perPage'];

        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        $this->csrfToken = $_SESSION['csrf_token'];

        // Parse request params
        $this->currentPage = max(1, (int) ($_GET['page'] ?? 1));
        $this->sortBy = $_GET['sort'] ?? '';
        $this->sortDir = ($_GET['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $this->activeTab = $_GET['tab'] ?? '';

        // Default to first tab if tabs are defined
        if (!empty($this->config['tabs']) && empty($this->activeTab)) {
            $this->activeTab = array_key_first($this->config['tabs']);
        }
    }

    /**
     * Gère les actions POST (create, edit, delete, toggle_*, custom).
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
                $field  = substr($action, 7);
                $method = 'toggle' . ucfirst($field);
                if (method_exists($service, $method)) {
                    $service->$method($id);
                    $this->message = 'Statut modifié.';
                }
            } elseif (is_callable($this->config['on_action'])) {
                $result = ($this->config['on_action'])($action, $service, $idField);
                if (is_string($result)) {
                    $this->message = $result;
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
        $this->loadItems();

        // Variables pour le header
        $pageTitle   = $this->config['title'];
        $currentPage = $this->config['currentPage'];
        $extraCss    = $this->config['extraCss'];

        $extraHeadHtml = '';
        if (!empty($this->config['extraStyle'])) {
            $extraHeadHtml = '<style>' . $this->config['extraStyle'] . '</style>';
        }

        include dirname(__DIR__, 2) . '/admin/includes/header.php';
        $this->renderBody();
        include dirname(__DIR__, 2) . '/admin/includes/footer.php';
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function loadItems(): void
    {
        $service    = $this->config['service'];
        $listMethod = $this->config['listMethod'];

        if ($this->config['paginated']) {
            $filters = $this->getActiveFilters();
            $result = $service->$listMethod($filters, $this->currentPage, $this->perPage);
            $this->items = $result['data'] ?? [];
            $this->totalItems = $result['total'] ?? count($this->items);
        } else {
            $allItems = $service->$listMethod();
            $this->items = $this->applyClientSideFiltersAndSort($allItems);
            $this->totalItems = count($this->items);

            // Client-side pagination
            if ($this->totalItems > $this->perPage) {
                $offset = ($this->currentPage - 1) * $this->perPage;
                $this->items = array_slice($this->items, $offset, $this->perPage);
            }
        }
    }

    private function getActiveFilters(): array
    {
        $filters = [];
        foreach ($this->config['filters'] as $key => $def) {
            $val = $_GET[$key] ?? '';
            if ($val !== '') {
                $filters[$key] = $val;
            }
        }
        if ($this->activeTab) {
            $filters['tab'] = $this->activeTab;
        }
        return $filters;
    }

    private function applyClientSideFiltersAndSort(array $items): array
    {
        // Apply filters
        foreach ($this->config['filters'] as $key => $def) {
            $val = $_GET[$key] ?? '';
            if ($val === '') continue;
            $items = array_filter($items, function ($row) use ($key, $val) {
                return isset($row[$key]) && (string) $row[$key] === $val;
            });
        }

        // Apply sort
        if ($this->sortBy && isset($this->config['columns'][$this->sortBy])) {
            $col = $this->config['columns'][$this->sortBy];
            if (!empty($col['sortable'])) {
                $dir = $this->sortDir === 'desc' ? -1 : 1;
                $sortKey = $this->sortBy;
                usort($items, function ($a, $b) use ($sortKey, $dir) {
                    $va = $a[$sortKey] ?? '';
                    $vb = $b[$sortKey] ?? '';
                    if (is_numeric($va) && is_numeric($vb)) {
                        return ($va - $vb) * $dir;
                    }
                    return strcmp((string) $va, (string) $vb) * $dir;
                });
            }
        }

        return array_values($items);
    }

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
        <div style="max-width:1100px;margin:0 auto">
            <?php if ($this->message): ?>
                <div class="alert alert-success"><?= $e($this->message) ?></div>
            <?php endif; ?>
            <?php if ($this->error): ?>
                <div class="alert alert-danger"><?= $e($this->error) ?></div>
            <?php endif; ?>

            <?php $this->renderStats(); ?>
            <?php $this->renderTabs(); ?>
            <?php $this->renderFilters(); ?>

            <div class="top-bar" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
                <div>
                    <?php if (in_array('create', array_merge($actions, ['create']), true)): ?>
                        <button class="btn btn-primary" onclick="document.getElementById('crudCreateModal').classList.add('active')">
                            <i class="fas fa-plus"></i> <?= $e($createLabel) ?>
                        </button>
                    <?php endif; ?>
                </div>
                <div style="font-size:13px;color:#6b7280">
                    <?= $this->totalItems ?> résultat<?= $this->totalItems > 1 ? 's' : '' ?>
                </div>
            </div>

            <?php if (empty($this->items)): ?>
                <div style="text-align:center;padding:40px;color:#999;background:white;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,0.06)">
                    <p><?= $e($this->config['emptyMessage']) ?></p>
                </div>
            <?php else: ?>
            <table class="crud-table" style="width:100%;border-collapse:collapse;background:white;border-radius:10px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.06)">
                <thead>
                    <tr>
                        <?php foreach ($columns as $key => $col): ?>
                            <th style="padding:10px 14px;text-align:left;background:#f7fafc;font-weight:600;color:#4a5568;font-size:12px;border-bottom:1px solid #f0f0f0">
                                <?php if (!empty($col['sortable'])): ?>
                                    <?php
                                    $newDir = ($this->sortBy === $key && $this->sortDir === 'asc') ? 'desc' : 'asc';
                                    $sortUrl = '?' . http_build_query(array_merge($_GET, ['sort' => $key, 'dir' => $newDir, 'page' => 1]));
                                    $icon = '';
                                    if ($this->sortBy === $key) {
                                        $icon = $this->sortDir === 'asc' ? ' <i class="fas fa-sort-up"></i>' : ' <i class="fas fa-sort-down"></i>';
                                    }
                                    ?>
                                    <a href="<?= $e($sortUrl) ?>" style="color:inherit;text-decoration:none"><?= $e($col['label']) ?><?= $icon ?></a>
                                <?php else: ?>
                                    <?= $e($col['label']) ?>
                                <?php endif; ?>
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
            <?php endif; ?>

            <?php $this->renderPagination(); ?>
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

    private function renderStats(): void
    {
        if (!is_callable($this->config['stats'])) return;

        $stats = ($this->config['stats'])();
        if (empty($stats)) return;

        echo '<div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:15px">';
        foreach ($stats as $stat) {
            $icon  = $stat['icon'] ?? 'fas fa-chart-bar';
            $color = $stat['color'] ?? '#0f4c81';
            $value = htmlspecialchars((string) ($stat['value'] ?? '0'), ENT_QUOTES, 'UTF-8');
            $label = htmlspecialchars((string) ($stat['label'] ?? ''), ENT_QUOTES, 'UTF-8');
            echo '<div style="display:inline-flex;align-items:center;gap:6px;background:white;border-radius:8px;padding:8px 14px;box-shadow:0 1px 4px rgba(0,0,0,0.06);font-size:14px">'
                . '<i class="' . htmlspecialchars($icon) . '" style="color:' . htmlspecialchars($color) . '"></i> '
                . '<strong style="font-size:18px;font-weight:700">' . $value . '</strong> ' . $label
                . '</div>';
        }
        echo '</div>';
    }

    private function renderTabs(): void
    {
        if (empty($this->config['tabs'])) return;

        echo '<div style="display:flex;gap:0;margin-bottom:15px;border-bottom:2px solid #e5e7eb">';
        foreach ($this->config['tabs'] as $key => $tab) {
            $label = htmlspecialchars($tab['label'] ?? $key, ENT_QUOTES, 'UTF-8');
            $isActive = ($this->activeTab === $key);
            $url = '?' . http_build_query(array_merge($_GET, ['tab' => $key, 'page' => 1]));
            $style = $isActive
                ? 'padding:10px 20px;font-size:14px;font-weight:600;color:#0f4c81;border-bottom:2px solid #0f4c81;margin-bottom:-2px;text-decoration:none;background:transparent;border:none;cursor:pointer'
                : 'padding:10px 20px;font-size:14px;color:#6b7280;text-decoration:none;border-bottom:2px solid transparent;margin-bottom:-2px;background:transparent;border:none;cursor:pointer';
            $icon = isset($tab['icon']) ? '<i class="' . htmlspecialchars($tab['icon']) . '"></i> ' : '';
            $badge = isset($tab['badge']) ? ' <span style="background:#ef4444;color:white;font-size:11px;padding:1px 6px;border-radius:10px;margin-left:4px">' . (int) $tab['badge'] . '</span>' : '';
            echo '<a href="' . htmlspecialchars($url) . '" style="' . $style . '">' . $icon . $label . $badge . '</a>';
        }
        echo '</div>';
    }

    private function renderFilters(): void
    {
        if (empty($this->config['filters'])) return;

        echo '<form method="get" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:15px;align-items:flex-end">';
        // Preserve tab and sort params
        if ($this->activeTab) {
            echo '<input type="hidden" name="tab" value="' . htmlspecialchars($this->activeTab) . '">';
        }

        foreach ($this->config['filters'] as $key => $def) {
            $type  = $def['type'] ?? 'select';
            $label = $def['label'] ?? $key;
            $value = $_GET[$key] ?? '';

            if ($type === 'select' && isset($def['options'])) {
                $placeholder = $def['placeholder'] ?? ('Tous / ' . $label);
                echo '<select name="' . htmlspecialchars($key) . '" style="padding:7px 10px;border:1px solid #d2d6dc;border-radius:6px;font-size:13px">';
                echo '<option value="">' . htmlspecialchars($placeholder) . '</option>';
                foreach ($def['options'] as $optVal => $optLabel) {
                    $sel = ((string) $optVal === $value) ? ' selected' : '';
                    echo '<option value="' . htmlspecialchars((string) $optVal) . '"' . $sel . '>' . htmlspecialchars($optLabel) . '</option>';
                }
                echo '</select>';
            } elseif ($type === 'text') {
                $placeholder = $def['placeholder'] ?? $label . '…';
                echo '<input type="text" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars($value) . '" placeholder="' . htmlspecialchars($placeholder) . '" style="padding:7px 10px;border:1px solid #d2d6dc;border-radius:6px;font-size:13px">';
            } elseif ($type === 'date') {
                echo '<input type="date" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars($value) . '" style="padding:7px 10px;border:1px solid #d2d6dc;border-radius:6px;font-size:13px">';
            }
        }

        echo '<button type="submit" class="btn btn-primary" style="height:35px"><i class="fas fa-filter"></i></button>';

        // Check if any filter is active
        $hasFilters = false;
        foreach ($this->config['filters'] as $key => $def) {
            if (!empty($_GET[$key])) { $hasFilters = true; break; }
        }
        if ($hasFilters) {
            $resetUrl = strtok($_SERVER['REQUEST_URI'], '?');
            if ($this->activeTab) {
                $resetUrl .= '?tab=' . urlencode($this->activeTab);
            }
            echo ' <a href="' . htmlspecialchars($resetUrl) . '" class="btn btn-secondary" style="height:35px;line-height:35px;text-decoration:none">Reset</a>';
        }

        echo '</form>';
    }

    private function renderPagination(): void
    {
        $totalPages = max(1, (int) ceil($this->totalItems / $this->perPage));
        if ($totalPages <= 1) return;

        $params = $_GET;
        echo '<div style="display:flex;justify-content:center;gap:4px;margin-top:15px;padding:10px 0">';

        // Previous
        if ($this->currentPage > 1) {
            $params['page'] = $this->currentPage - 1;
            echo '<a href="?' . http_build_query($params) . '" style="padding:6px 12px;border:1px solid #d2d6dc;border-radius:6px;text-decoration:none;font-size:13px;color:#4a5568">&laquo;</a>';
        }

        // Page numbers
        $start = max(1, $this->currentPage - 2);
        $end = min($totalPages, $this->currentPage + 2);

        if ($start > 1) {
            $params['page'] = 1;
            echo '<a href="?' . http_build_query($params) . '" style="padding:6px 12px;border:1px solid #d2d6dc;border-radius:6px;text-decoration:none;font-size:13px;color:#4a5568">1</a>';
            if ($start > 2) echo '<span style="padding:6px 4px;color:#999">…</span>';
        }

        for ($i = $start; $i <= $end; $i++) {
            $params['page'] = $i;
            $isCurrent = ($i === $this->currentPage);
            $style = $isCurrent
                ? 'padding:6px 12px;border:1px solid #0f4c81;border-radius:6px;background:#0f4c81;color:white;font-size:13px;text-decoration:none;font-weight:600'
                : 'padding:6px 12px;border:1px solid #d2d6dc;border-radius:6px;text-decoration:none;font-size:13px;color:#4a5568';
            echo '<a href="?' . http_build_query($params) . '" style="' . $style . '">' . $i . '</a>';
        }

        if ($end < $totalPages) {
            if ($end < $totalPages - 1) echo '<span style="padding:6px 4px;color:#999">…</span>';
            $params['page'] = $totalPages;
            echo '<a href="?' . http_build_query($params) . '" style="padding:6px 12px;border:1px solid #d2d6dc;border-radius:6px;text-decoration:none;font-size:13px;color:#4a5568">' . $totalPages . '</a>';
        }

        // Next
        if ($this->currentPage < $totalPages) {
            $params['page'] = $this->currentPage + 1;
            echo '<a href="?' . http_build_query($params) . '" style="padding:6px 12px;border:1px solid #d2d6dc;border-radius:6px;text-decoration:none;font-size:13px;color:#4a5568">&raquo;</a>';
        }

        echo '</div>';
    }

    private function renderRowActions(array $row, string $csrf, string $idField, array $actions): void
    {
        $id = $row[$idField] ?? 0;
        $jsonData = htmlspecialchars(json_encode($row, JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8');

        if (in_array('edit', $actions, true)) {
            echo "<button class=\"btn-xs primary\" onclick='crudOpenEdit({$jsonData})'><i class=\"fas fa-pen\"></i></button> ";
        }

        foreach ($actions as $action) {
            if (is_array($action)) {
                // Custom action: ['action' => 'name', 'render' => fn($row, $csrf, $idField) => 'html']
                if (isset($action['render'])) {
                    echo ($action['render'])($row, $csrf, $idField) . ' ';
                }
            } elseif (is_string($action) && str_starts_with($action, 'toggle_')) {
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
        if (isset($field['maxlength']))   $attrs .= " maxlength=\"{$field['maxlength']}\"";
        if (isset($field['step']))        $attrs .= " step=\"{$field['step']}\"";
        if (isset($field['min']))         $attrs .= " min=\"{$field['min']}\"";
        if (isset($field['max']))         $attrs .= " max=\"{$field['max']}\"";
        if (isset($field['placeholder'])) $attrs .= " placeholder=\"" . htmlspecialchars($field['placeholder']) . "\"";
        if (isset($field['rows']))        $attrs .= " rows=\"{$field['rows']}\"";

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
