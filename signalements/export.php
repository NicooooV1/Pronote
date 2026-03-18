<?php
/**
 * M45 – Signalements / Anti-harcèlement — Export CSV/PDF
 * Note: Les données sensibles (description tronquée, pas de noms de victimes)
 */
require_once __DIR__ . '/../API/bootstrap.php';
requireAuth();
if (!isAdmin() && !isVieScolaire()) { die('Accès refusé'); }

require_once __DIR__ . '/includes/SignalementService.php';
$service = new SignalementService(getPDO());
$exportService = new \API\Services\ExportService(getPDO());

$format = $_GET['format'] ?? 'csv';
$filters = array_filter([
    'statut' => $_GET['statut'] ?? null,
    'type' => $_GET['type_signalement'] ?? null,
    'urgence' => $_GET['urgence'] ?? null,
]);

$data = $service->getSignalementsForExport($filters);
$headers = ['ID', 'Date', 'Type', 'Urgence', 'Statut', 'Anonyme', 'Lieu', 'Description (extrait)', 'Date traitement'];
$title = 'Signalements';
$filename = 'signalements';

if ($format === 'pdf') {
    $exportService->pdf($title, $headers, $data, $filename);
} else {
    $exportService->csv($headers, $data, $filename);
}
