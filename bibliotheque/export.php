<?php
/**
 * M29 – Bibliothèque — Export CSV/PDF
 */
require_once __DIR__ . '/../API/bootstrap.php';
requireAuth();
if (!isAdmin() && !isVieScolaire()) { die('Accès refusé'); }

require_once __DIR__ . '/includes/BibliothequeService.php';
$service = new BibliothequeService(getPDO());
$exportService = new \API\Services\ExportService(getPDO());

$type = $_GET['type'] ?? 'livres';
$format = $_GET['format'] ?? 'csv';

if ($type === 'emprunts') {
    $data = $service->getEmpruntsForExport($_GET);
    $headers = ['Livre', 'Emprunteur', 'Type', 'Date emprunt', 'Retour prévu', 'Retour effectif', 'Statut'];
    $title = 'Emprunts bibliothèque';
    $filename = 'emprunts_bibliotheque';
} else {
    $data = $service->getLivresForExport($_GET);
    $headers = ['ISBN', 'Titre', 'Auteur', 'Catégorie', 'Éditeur', 'Année', 'Exemplaires', 'Disponibles'];
    $title = 'Catalogue bibliothèque';
    $filename = 'catalogue_bibliotheque';
}

if ($format === 'pdf') {
    $exportService->pdf($title, $headers, $data, $filename);
} else {
    $exportService->csv($headers, $data, $filename);
}
