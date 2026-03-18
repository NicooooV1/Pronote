<?php
/**
 * M32 – Transports — Export CSV/PDF
 */
require_once __DIR__ . '/../API/bootstrap.php';
requireAuth();
if (!isAdmin() && !isVieScolaire()) { die('Accès refusé'); }

require_once __DIR__ . '/includes/TransportInternatService.php';
$service = new TransportInternatService(getPDO());
$exportService = new \API\Services\ExportService(getPDO());

$type = $_GET['type'] ?? 'lignes';
$format = $_GET['format'] ?? 'csv';

if ($type === 'inscrits' && !empty($_GET['ligne_id'])) {
    $data = $service->getInscritsForExport((int)$_GET['ligne_id']);
    $headers = ['Ligne', 'Élève', 'Classe', 'Arrêt'];
    $title = 'Inscrits transport';
    $filename = 'inscrits_transport';
} else {
    $data = $service->getLignesForExport($_GET['type_transport'] ?? null);
    $headers = ['Nom', 'Type', 'Itinéraire', 'Horaires', 'Capacité', 'Inscrits'];
    $title = 'Lignes de transport';
    $filename = 'lignes_transport';
}

if ($format === 'pdf') {
    $exportService->pdf($title, $headers, $data, $filename);
} else {
    $exportService->csv($headers, $data, $filename);
}
