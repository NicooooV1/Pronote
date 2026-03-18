<?php
/**
 * M19 – Internat — Export CSV/PDF
 */
require_once __DIR__ . '/../API/bootstrap.php';
requireAuth();
if (!isAdmin() && !isVieScolaire()) { die('Accès refusé'); }

require_once __DIR__ . '/includes/InternatService.php';
$service = new InternatService(getPDO());
$exportService = new \API\Services\ExportService(getPDO());

$type = $_GET['type'] ?? 'affectations';
$format = $_GET['format'] ?? 'csv';

if ($type === 'incidents') {
    $dateDebut = $_GET['date_debut'] ?? null;
    $dateFin = $_GET['date_fin'] ?? null;
    $data = $service->getIncidentsForExport($dateDebut, $dateFin);
    $headers = ['Date', 'Chambre', 'Élève', 'Type', 'Description', 'Gravité', 'Traité', 'Suite donnée'];
    $title = 'Incidents internat';
    $filename = 'incidents_internat';
} else {
    $data = $service->getAffectationsForExport($_GET['annee'] ?? null);
    $headers = ['Élève', 'Classe', 'Chambre', 'Bâtiment', 'Année scolaire', 'Début', 'Fin', 'Statut'];
    $title = 'Affectations internat';
    $filename = 'affectations_internat';
}

if ($format === 'pdf') {
    $exportService->pdf($title, $headers, $data, $filename);
} else {
    $exportService->csv($headers, $data, $filename);
}
