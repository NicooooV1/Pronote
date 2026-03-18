<?php
/**
 * M33 – Facturation : Export CSV/PDF
 */
require_once __DIR__ . '/includes/header.php';

if (!isAdmin() && !isPersonnelVS()) {
    http_response_code(403);
    exit('Accès refusé');
}

$exportService = new \API\Services\ExportService(getPDO());
$format = $_GET['format'] ?? 'csv';
$filters = [];
if (!empty($_GET['statut'])) $filters['statut'] = $_GET['statut'];

$data = $factService->getFacturesForExport($filters);
$columns = ['Numéro', 'Parent', 'Type', 'Montant HT', 'TVA', 'Montant TTC', 'Payé', 'Reste', 'Statut', 'Échéance', 'Date création'];

if ($format === 'pdf') {
    $exportService->pdf($data, $columns, 'Facturation');
} else {
    $exportService->csv($data, $columns, 'facturation');
}
