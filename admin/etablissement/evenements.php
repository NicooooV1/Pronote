<?php
/**
 * Gestion des événements — vue admin, CRUD
 * Converti en AdminCrudPage déclaratif.
 */
require_once __DIR__ . '/../../API/core.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAuth();
requireRole('administrateur');

$eventService = app('evenements');

// Load distinct types for filter options
$types = [];
try {
    $typesRaw = $eventService->getDistinctTypes();
    foreach ($typesRaw as $t) {
        $types[$t] = $t;
    }
} catch (\Throwable $e) {}

$page = new \API\Admin\AdminCrudPage([
    'title'       => 'Événements',
    'currentPage' => 'etab_evenements',
    'service'     => $eventService,
    'entityName'  => 'Événement',
    'createLabel' => 'Nouvel événement',
    'idField'     => 'id',
    'listMethod'  => 'getFiltered',
    'paginated'   => true,
    'perPage'     => 30,
    'extraCss'    => ['../../assets/css/admin.css'],
    'filters' => [
        'type' => [
            'type' => 'select',
            'label' => 'Type',
            'placeholder' => 'Tous types',
            'options' => $types,
        ],
        'status' => [
            'type' => 'select',
            'label' => 'Statut',
            'placeholder' => 'Tout statut',
            'options' => ['actif' => 'Actif', 'annule' => 'Annulé'],
        ],
    ],
    'columns' => [
        'titre' => ['label' => 'Titre', 'sortable' => true,
            'render' => fn($v) => '<strong>' . htmlspecialchars($v ?? '') . '</strong>'],
        'type_evenement' => ['label' => 'Type',
            'render' => fn($v) => '<span style="font-size:12px">' . htmlspecialchars($v ?? '') . '</span>'],
        'date_debut' => ['label' => 'Date début', 'sortable' => true,
            'render' => fn($v) => $v ? '<span style="font-size:12px">' . date('d/m/Y H:i', strtotime($v)) . '</span>' : '-'],
        'date_fin' => ['label' => 'Date fin',
            'render' => fn($v) => $v ? '<span style="font-size:12px">' . date('d/m/Y H:i', strtotime($v)) . '</span>' : '-'],
        'lieu' => ['label' => 'Lieu',
            'render' => fn($v) => '<span style="font-size:12px">' . htmlspecialchars($v ?? '-') . '</span>'],
        'statut' => ['label' => 'Statut',
            'render' => fn($v) => '<span style="display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;'
                . ($v === 'actif' ? 'background:#d1fae5;color:#065f46' : 'background:#fee2e2;color:#991b1b')
                . '">' . htmlspecialchars($v ?? '') . '</span>'],
        'createur' => ['label' => 'Créateur',
            'render' => fn($v) => '<span style="font-size:12px">' . htmlspecialchars($v ?? '') . '</span>'],
    ],
    'form_fields' => [
        'titre'           => ['type' => 'text', 'label' => 'Titre', 'required' => true],
        'description'     => ['type' => 'textarea', 'label' => 'Description', 'rows' => 3],
        'date_debut'      => ['type' => 'datetime-local', 'label' => 'Début', 'required' => true],
        'date_fin'        => ['type' => 'datetime-local', 'label' => 'Fin', 'required' => true],
        'type_evenement'  => ['type' => 'text', 'label' => 'Type'],
        'lieu'            => ['type' => 'text', 'label' => 'Lieu'],
    ],
    'actions' => [
        'edit',
        [
            'action' => 'toggle_status',
            'render' => function ($row, $csrf, $idField) {
                $id = $row[$idField] ?? 0;
                $isActif = ($row['statut'] ?? '') === 'actif';
                return '<form method="post" style="display:inline">'
                    . '<input type="hidden" name="csrf_token" value="' . $csrf . '">'
                    . '<input type="hidden" name="action" value="toggle_status">'
                    . '<input type="hidden" name="' . $idField . '" value="' . $id . '">'
                    . '<button class="btn-xs ' . ($isActif ? 'warning' : 'success') . '" title="' . ($isActif ? 'Annuler' : 'Activer') . '">'
                    . '<i class="fas fa-' . ($isActif ? 'ban' : 'check') . '"></i></button></form>';
            },
        ],
        'delete',
    ],
    'on_action' => function (string $action, $service, string $idField) {
        if ($action === 'toggle_status') {
            $id = (int) ($_POST[$idField] ?? 0);
            $service->toggleStatus($id);
            return 'Statut mis à jour.';
        }
        return null;
    },
]);
$page->handle();
$page->render();
