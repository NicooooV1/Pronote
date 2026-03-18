<?php
/**
 * M28 – Examens — Export CSV/PDF
 */
require_once __DIR__ . '/../API/bootstrap.php';
requireAuth();
if (!isAdmin() && !isVieScolaire() && !isTeacher()) { die('Accès refusé'); }

require_once __DIR__ . '/includes/ExamenService.php';
$service = new ExamenService(getPDO());
$exportService = new \API\Services\ExportService(getPDO());

$type = $_GET['type'] ?? 'examens';
$format = $_GET['format'] ?? 'csv';

if ($type === 'convocations' && !empty($_GET['epreuve_id'])) {
    $epreuveId = (int)$_GET['epreuve_id'];
    $data = $service->getConvocationsForExport($epreuveId);
    $headers = ['Nom', 'Prénom', 'Classe', 'Place', 'Présent', 'Note'];
    $title = 'Convocations épreuve #' . $epreuveId;
    $filename = 'convocations_epreuve_' . $epreuveId;
} else {
    $statut = $_GET['statut'] ?? null;
    $data = $service->getExamensForExport($statut);
    $headers = ['Nom', 'Type', 'Date début', 'Date fin', 'Statut', 'Nb épreuves'];
    $title = 'Liste des examens';
    $filename = 'examens';
}

if ($format === 'pdf') {
    $exportService->pdf($title, $headers, $data, $filename);
} else {
    $exportService->csv($headers, $data, $filename);
}
