<?php
/**
 * M34 – Support & Aide — Export CSV/PDF
 */
require_once __DIR__ . '/../API/bootstrap.php';
requireAuth();
if (!isAdmin()) { die('Accès refusé'); }

require_once __DIR__ . '/includes/SupportService.php';
$service = new SupportService(getPDO());
$exportService = new \API\Services\ExportService(getPDO());

$type = $_GET['type'] ?? 'tickets';
$format = $_GET['format'] ?? 'csv';

if ($type === 'faq') {
    $data = $service->getFaqForExport($_GET['categorie'] ?? null);
    $headers = ['ID', 'Catégorie', 'Question', 'Réponse (extrait)', 'Vues', 'Utile oui/non', 'Ordre'];
    $title = 'FAQ — Articles';
    $filename = 'faq_articles';
} else {
    $filters = array_filter([
        'statut' => $_GET['statut'] ?? null,
        'categorie' => $_GET['categorie'] ?? null,
        'priorite' => $_GET['priorite'] ?? null,
    ]);
    $data = $service->getTicketsForExport($filters);
    $headers = ['ID', 'Date', 'Utilisateur', 'Type', 'Sujet', 'Catégorie', 'Priorité', 'Statut', 'Date réponse'];
    $title = 'Tickets support';
    $filename = 'tickets_support';
}

if ($format === 'pdf') {
    $exportService->pdf($title, $headers, $data, $filename);
} else {
    $exportService->csv($headers, $data, $filename);
}
