<?php
/**
 * M30 – Infirmerie — Export CSV/PDF
 */
require_once __DIR__ . '/../API/bootstrap.php';
requireAuth();
if (!isAdmin() && !isVieScolaire()) { die('Accès refusé'); }

require_once __DIR__ . '/includes/InfirmerieService.php';
$service = new InfirmerieService(getPDO());
$exportService = new \API\Services\ExportService(getPDO());

$format = $_GET['format'] ?? 'csv';
$filtres = [];
if (!empty($_GET['date_debut'])) $filtres['date_debut'] = $_GET['date_debut'];
if (!empty($_GET['date_fin'])) $filtres['date_fin'] = $_GET['date_fin'];

$data = $service->getPassagesForExport($filtres);
$headers = ['Élève', 'Classe', 'Date', 'Motif', 'Symptômes', 'Soins', 'Orientation', 'Commentaire'];

if ($format === 'pdf') {
    $exportService->pdf('Passages infirmerie', $headers, $data, 'passages_infirmerie');
} else {
    $exportService->csv($headers, $data, 'passages_infirmerie');
}
