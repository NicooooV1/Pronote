<?php
/**
 * M28 – Orientation — Export CSV/PDF
 */
require_once __DIR__ . '/../API/bootstrap.php';
requireAuth();
if (!isAdmin() && !isVieScolaire() && !isProfesseur()) { die('Accès refusé'); }

require_once __DIR__ . '/includes/OrientationService.php';
$service = new OrientationService(getPDO());
$exportService = new \API\Services\ExportService(getPDO());

$type = $_GET['type'] ?? 'fiches';
$format = $_GET['format'] ?? 'csv';
$filters = array_filter([
    'classe_id' => $_GET['classe_id'] ?? null,
    'statut' => $_GET['statut'] ?? null,
]);

if ($type === 'voeux') {
    $data = $service->getVoeuxForExport($filters);
    $headers = ['Nom', 'Prénom', 'Classe', 'Rang', 'Formation', 'Établissement visé', 'Avis PP', 'Avis conseil'];
    $title = 'Vœux d\'orientation';
    $filename = 'voeux_orientation';
} else {
    $data = $service->getFichesForExport($filters);
    $headers = ['Nom', 'Prénom', 'Classe', 'Année', 'Projet professionnel', 'Statut', 'Avis PP', 'Avis conseil'];
    $title = 'Fiches d\'orientation';
    $filename = 'fiches_orientation';
}

if ($format === 'pdf') {
    $exportService->pdf($title, $headers, $data, $filename);
} else {
    $exportService->csv($headers, $data, $filename);
}
