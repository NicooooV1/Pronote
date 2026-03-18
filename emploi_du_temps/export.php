<?php
/**
 * Emploi du temps — Export CSV/PDF
 */
require_once __DIR__ . '/../API/core.php';
require_once __DIR__ . '/includes/EdtService.php';

requireAuth();

if (!isAdmin() && !isVieScolaire() && !isTeacher()) {
    http_response_code(403);
    exit('Accès refusé');
}

$pdo = getPDO();
$service = new EdtService($pdo);
$exportService = new \API\Services\ExportService($pdo);

$classeId = (int)($_GET['classe'] ?? 0);
if (!$classeId) {
    http_response_code(400);
    exit('Paramètre classe manquant');
}

$format = $_GET['format'] ?? 'csv';
$data = $service->getEdtForExport($classeId);
$columns = ['Jour', 'Créneau', 'Matière', 'Professeur', 'Salle', 'Type'];

// Récupérer le nom de la classe pour le titre
$classes = $service->getClasses();
$classeNom = 'Classe';
foreach ($classes as $c) {
    if ($c['id'] == $classeId) { $classeNom = $c['nom']; break; }
}

if ($format === 'pdf') {
    $exportService->pdf($data, $columns, "EDT - {$classeNom}");
} else {
    $exportService->csv($data, $columns, "edt_{$classeNom}");
}
