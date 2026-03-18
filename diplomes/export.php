<?php
/**
 * M44 – Diplômes & Relevés — Export CSV/PDF
 */
require_once __DIR__ . '/../API/bootstrap.php';
requireAuth();
if (!isAdmin() && !isVieScolaire()) { die('Accès refusé'); }

require_once __DIR__ . '/includes/DiplomeService.php';
$service = new DiplomeService(getPDO());
$exportService = new \API\Services\ExportService(getPDO());

$format = $_GET['format'] ?? 'csv';
$filters = array_filter([
    'type' => $_GET['type_diplome'] ?? null,
    'mention' => $_GET['mention'] ?? null,
    'annee' => $_GET['annee'] ?? null,
]);

$data = $service->getDiplomesForExport($filters);
$headers = ['Numéro', 'Élève', 'Classe', 'Intitulé', 'Type', 'Mention', 'Date obtention'];
$title = 'Liste des diplômes';
$filename = 'diplomes';

if ($format === 'pdf') {
    $exportService->pdf($title, $headers, $data, $filename);
} else {
    $exportService->csv($headers, $data, $filename);
}
