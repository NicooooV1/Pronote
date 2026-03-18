<?php
/**
 * M30 – Clubs & Associations — Export CSV/PDF
 */
require_once __DIR__ . '/../API/bootstrap.php';
requireAuth();
if (!isAdmin() && !isVieScolaire()) { die('Accès refusé'); }

require_once __DIR__ . '/includes/ClubService.php';
$service = new ClubService(getPDO());
$exportService = new \API\Services\ExportService(getPDO());

$type = $_GET['type'] ?? 'clubs';
$format = $_GET['format'] ?? 'csv';

if ($type === 'membres' && !empty($_GET['club_id'])) {
    $data = $service->getMembresForExport((int)$_GET['club_id']);
    $headers = ['Club', 'Prénom', 'Nom', 'Classe', 'Date inscription', 'Statut'];
    $title = 'Membres du club';
    $filename = 'membres_club';
} else {
    $data = $service->getClubsForExport($_GET['categorie'] ?? null);
    $headers = ['Nom', 'Catégorie', 'Responsable', 'Horaires', 'Lieu', 'Places max', 'Inscrits', 'Début', 'Fin'];
    $title = 'Liste des clubs';
    $filename = 'clubs';
}

if ($format === 'pdf') {
    $exportService->pdf($title, $headers, $data, $filename);
} else {
    $exportService->csv($headers, $data, $filename);
}
