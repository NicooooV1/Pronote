<?php
/**
 * M17 – Stages & Alternance — Export CSV/PDF
 */
require_once __DIR__ . '/../API/bootstrap.php';
requireAuth();
if (!isAdmin() && !isVieScolaire() && !isProfesseur()) { die('Accès refusé'); }

require_once __DIR__ . '/includes/StageService.php';
$service = new StageService(getPDO());
$exportService = new \API\Services\ExportService(getPDO());

$format = $_GET['format'] ?? 'csv';
$filters = array_filter([
    'type' => $_GET['type_stage'] ?? null,
    'statut' => $_GET['statut'] ?? null,
    'prof_referent_id' => $_GET['prof_referent_id'] ?? null,
]);

$data = $service->getStagesForExport($filters);
$headers = ['Élève', 'Classe', 'Type', 'Entreprise', 'Tuteur', 'Prof référent', 'Début', 'Fin', 'Statut'];
$title = 'Liste des stages';
$filename = 'stages';

if ($format === 'pdf') {
    $exportService->pdf($title, $headers, $data, $filename);
} else {
    $exportService->csv($headers, $data, $filename);
}
