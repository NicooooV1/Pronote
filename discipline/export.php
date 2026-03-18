<?php
/**
 * export.php — Export CSV/PDF du module Discipline.
 */
require_once __DIR__ . '/../API/core.php';
require_once __DIR__ . '/includes/DisciplineService.php';

requireAuth();

if (!isAdmin() && !isVieScolaire()) {
    $_SESSION['error_message'] = 'Accès non autorisé.';
    header('Location: incidents.php');
    exit;
}

$pdo     = getPDO();
$service = new DisciplineService($pdo);
$export  = new \API\Services\ExportService($pdo);

$format = in_array($_GET['format'] ?? '', ['csv', 'pdf']) ? $_GET['format'] : 'csv';
$type   = in_array($_GET['type'] ?? '', ['incidents', 'sanctions']) ? $_GET['type'] : 'incidents';

$filters = [];
if (!empty($_GET['statut']))     $filters['statut']     = $_GET['statut'];
if (!empty($_GET['gravite']))    $filters['gravite']    = $_GET['gravite'];
if (!empty($_GET['classe']))     $filters['classe']     = $_GET['classe'];
if (!empty($_GET['date_debut'])) $filters['date_debut'] = $_GET['date_debut'];
if (!empty($_GET['date_fin']))   $filters['date_fin']   = $_GET['date_fin'];

if ($type === 'sanctions') {
    $data    = $service->getSanctionsForExport($filters);
    $columns = [
        'date'   => 'Date',
        'eleve'  => 'Élève',
        'classe' => 'Classe',
        'type'   => 'Type de sanction',
        'motif'  => 'Motif',
        'duree'  => 'Durée',
    ];
    $title    = 'Sanctions disciplinaires';
    $filename = 'sanctions_' . date('Y-m-d');
} else {
    $data    = $service->getIncidentsForExport($filters);
    $columns = [
        'date'    => 'Date',
        'eleve'   => 'Élève',
        'classe'  => 'Classe',
        'type'    => 'Type',
        'gravite' => 'Gravité',
        'statut'  => 'Statut',
        'lieu'    => 'Lieu',
    ];
    $title    = 'Incidents disciplinaires';
    $filename = 'incidents_' . date('Y-m-d');
}

if ($format === 'pdf') {
    $tableHtml = $export->buildTable($data, $columns, $title);
    $export->pdf($tableHtml, $title, $filename . '.pdf');
} else {
    $export->csv($data, $columns, $filename . '.csv');
}
