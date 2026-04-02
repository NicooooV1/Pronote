<?php
/**
 * Gestion des matières — CRUD via AdminCrudPage
 */
require_once __DIR__ . '/../../API/module_boot.php';
requireRole('administrateur');

$page = new \API\Admin\AdminCrudPage([
    'title'       => 'Matières',
    'currentPage' => 'etab_matieres',
    'service'     => app('matieres'),
    'entityName'  => 'Matière',
    'createLabel' => 'Nouvelle matière',
    'columns' => [
        'nom'         => ['label' => 'Matière', 'render' => fn($v, $row) => '<span class="color-dot" style="background:' . e($row['couleur']) . '"></span><strong>' . e($v) . '</strong>'],
        'code'        => ['label' => 'Code', 'render' => fn($v) => '<code>' . e($v) . '</code>'],
        'coefficient' => ['label' => 'Coef'],
        'note_count'  => ['label' => 'Notes'],
        'actif'       => ['label' => 'Statut', 'render' => fn($v) => $v ? '<span style="color:#059669">Active</span>' : '<span style="color:#dc2626">Inactive</span>'],
    ],
    'form_fields' => [
        'nom'         => ['type' => 'text', 'label' => 'Nom', 'required' => true, 'placeholder' => 'Mathématiques'],
        'code'        => ['type' => 'text', 'label' => 'Code', 'required' => true, 'maxlength' => 10, 'placeholder' => 'MATH'],
        'coefficient' => ['type' => 'number', 'label' => 'Coefficient', 'default' => 1, 'step' => 0.01, 'min' => 0.01],
        'couleur'     => ['type' => 'color', 'label' => 'Couleur', 'default' => '#3498db'],
        'actif'       => ['type' => 'checkbox', 'label' => 'Active', 'edit_only' => true],
    ],
    'actions'     => ['edit', 'toggle_actif', 'delete'],
    'extraStyle'  => '.color-dot{display:inline-block;width:14px;height:14px;border-radius:50%;vertical-align:middle;margin-right:6px}',
]);

$page->handle();
$page->render();
