<?php
/**
 * Gestion des périodes scolaires (trimestres/semestres)
 * Converti en AdminCrudPage déclaratif.
 */
require_once __DIR__ . '/../../API/core.php';
require_once __DIR__ . '/../includes/admin_functions.php';

requireAuth();
requireRole('administrateur');

$periodeService = app('periodes');

// Detect overlaps for stats display
$overlaps = [];
try {
    $overlaps = $periodeService->detectOverlaps();
} catch (\Throwable $e) {}

$page = new \API\Admin\AdminCrudPage([
    'title'       => 'Périodes scolaires',
    'currentPage' => 'etab_periodes',
    'service'     => $periodeService,
    'entityName'  => 'Période',
    'createLabel' => 'Nouvelle période',
    'idField'     => 'id',
    'listMethod'  => 'getAll',
    'extraCss'    => ['../../assets/css/admin.css'],
    'columns' => [
        'numero' => ['label' => '#', 'sortable' => true,
            'render' => fn($v, $row) => '<span style="display:inline-flex;width:32px;height:32px;border-radius:50%;background:#0f4c81;color:white;align-items:center;justify-content:center;font-weight:700">' . (int) $v . '</span>'],
        'nom' => ['label' => 'Nom', 'sortable' => true,
            'render' => function ($v, $row) {
                $today = date('Y-m-d');
                $isCurrent = ($today >= ($row['date_debut'] ?? '') && $today <= ($row['date_fin'] ?? ''));
                $badge = $isCurrent ? ' <span style="background:#d1fae5;color:#065f46;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600">En cours</span>' : '';
                return '<strong>' . htmlspecialchars($v ?? '') . '</strong>' . $badge;
            }],
        'type' => ['label' => 'Type',
            'render' => fn($v) => '<span style="display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;background:#e2e8f0;color:#4a5568">' . htmlspecialchars($v ?? '') . '</span>'],
        'date_debut' => ['label' => 'Début', 'sortable' => true,
            'render' => fn($v) => $v ? '<span style="background:#eff6ff;padding:4px 10px;border-radius:6px;color:#1e40af;font-weight:500;font-size:13px">' . date('d/m/Y', strtotime($v)) . '</span>' : '-'],
        'date_fin' => ['label' => 'Fin', 'sortable' => true,
            'render' => fn($v) => $v ? '<span style="background:#eff6ff;padding:4px 10px;border-radius:6px;color:#1e40af;font-weight:500;font-size:13px">' . date('d/m/Y', strtotime($v)) . '</span>' : '-'],
    ],
    'form_fields' => [
        'nom'        => ['type' => 'text', 'label' => 'Nom', 'required' => true, 'placeholder' => 'Trimestre 1'],
        'numero'     => ['type' => 'number', 'label' => 'Numéro', 'default' => 1, 'min' => 1, 'max' => 6],
        'type'       => ['type' => 'select', 'label' => 'Type', 'options' => ['trimestre' => 'Trimestre', 'semestre' => 'Semestre', 'annuel' => 'Annuel']],
        'date_debut' => ['type' => 'date', 'label' => 'Date début', 'required' => true],
        'date_fin'   => ['type' => 'date', 'label' => 'Date fin', 'required' => true],
    ],
    'actions' => ['edit', 'delete'],
    'stats' => function () use ($overlaps, $periodeService) {
        $stats = [];
        try {
            $all = $periodeService->getAll();
            $stats[] = ['icon' => 'fas fa-calendar-alt', 'color' => '#0f4c81', 'value' => count($all), 'label' => 'période(s)'];
            $current = $periodeService->getCurrent();
            if ($current) {
                $stats[] = ['icon' => 'fas fa-clock', 'color' => '#059669', 'value' => $current['nom'], 'label' => '(en cours)'];
            }
        } catch (\Throwable $e) {}
        if (!empty($overlaps)) {
            $stats[] = ['icon' => 'fas fa-exclamation-triangle', 'color' => '#f59e0b', 'value' => count($overlaps), 'label' => 'chevauchement(s)'];
        }
        return $stats;
    },
]);
$page->handle();
$page->render();
