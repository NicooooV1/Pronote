<?php
/**
 * Administration des devoirs — liste, filtres, modifier, supprimer
 * Converti en AdminCrudPage déclaratif.
 */
require_once __DIR__ . '/../../API/core.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAuth();
requireRole('administrateur');

$pdo = getPDO();
$devoirService = app('devoirs');

// Load reference lists for filters
$classesList = $pdo->query("SELECT DISTINCT classe FROM devoirs ORDER BY classe")->fetchAll(\PDO::FETCH_COLUMN);
$matieresList = $pdo->query("SELECT DISTINCT nom_matiere FROM devoirs ORDER BY nom_matiere")->fetchAll(\PDO::FETCH_COLUMN);

$classesOptions = [];
foreach ($classesList as $c) { $classesOptions[$c] = $c; }
$matieresOptions = [];
foreach ($matieresList as $m) { $matieresOptions[$m] = $m; }

$page = new \API\Admin\AdminCrudPage([
    'title'       => 'Devoirs',
    'currentPage' => 'devoirs',
    'service'     => $devoirService,
    'entityName'  => 'Devoir',
    'createLabel' => 'Nouveau devoir',
    'idField'     => 'id',
    'listMethod'  => 'getFiltered',
    'paginated'   => true,
    'perPage'     => 40,
    'extraCss'    => ['../../assets/css/admin.css'],
    'stats' => function () use ($devoirService) {
        $upcoming = $devoirService->getUpcomingCount();
        $overdue  = $devoirService->getOverdueCount();
        return [
            ['icon' => 'fas fa-book', 'color' => '#0f4c81', 'value' => $upcoming + $overdue, 'label' => 'devoirs'],
            ['icon' => 'fas fa-calendar-check', 'color' => '#059669', 'value' => $upcoming, 'label' => 'à venir'],
            ['icon' => 'fas fa-calendar-times', 'color' => '#dc2626', 'value' => $overdue, 'label' => 'passés'],
        ];
    },
    'filters' => [
        'classe' => [
            'type' => 'select',
            'label' => 'Classe',
            'placeholder' => 'Toutes classes',
            'options' => $classesOptions,
        ],
        'matiere' => [
            'type' => 'select',
            'label' => 'Matière',
            'placeholder' => 'Toutes matières',
            'options' => $matieresOptions,
        ],
        'professeur' => [
            'type' => 'text',
            'label' => 'Professeur',
            'placeholder' => 'Professeur…',
        ],
    ],
    'columns' => [
        'titre' => ['label' => 'Titre', 'sortable' => true,
            'render' => function ($v, $row) {
                $html = '<strong>' . htmlspecialchars($v ?? '') . '</strong>';
                if (!empty($row['description'])) {
                    $html .= '<br><small style="color:#888">' . htmlspecialchars(mb_substr($row['description'], 0, 60)) . '…</small>';
                }
                return $html;
            }],
        'classe' => ['label' => 'Classe', 'sortable' => true],
        'nom_matiere' => ['label' => 'Matière', 'sortable' => true],
        'nom_professeur' => ['label' => 'Professeur',
            'render' => fn($v) => '<span style="font-size:12px">' . htmlspecialchars($v ?? '') . '</span>'],
        'date_ajout' => ['label' => 'Date ajout',
            'render' => fn($v) => $v ? '<span style="font-size:12px">' . date('d/m/Y', strtotime($v)) . '</span>' : '-'],
        'date_rendu' => ['label' => 'Date rendu', 'sortable' => true,
            'render' => function ($v) {
                if (!$v) return '-';
                $today = date('Y-m-d');
                $class = $v < $today ? 'background:#fee2e2;color:#991b1b' : ($v === $today ? 'background:#d1fae5;color:#065f46' : 'background:#dbeafe;color:#1e40af');
                return '<span style="font-size:12px;padding:2px 8px;border-radius:10px;' . $class . '">' . date('d/m/Y', strtotime($v)) . '</span>';
            }],
    ],
    'form_fields' => [
        'titre'       => ['type' => 'text', 'label' => 'Titre', 'required' => true],
        'description' => ['type' => 'textarea', 'label' => 'Description', 'rows' => 3],
        'date_rendu'  => ['type' => 'date', 'label' => 'Date de rendu'],
    ],
    'actions' => ['edit', 'delete'],
]);
$page->handle();
$page->render();
