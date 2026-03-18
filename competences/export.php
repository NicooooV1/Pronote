<?php
/**
 * M38 – Compétences & Évaluations — Export CSV/PDF
 */
require_once __DIR__ . '/../API/bootstrap.php';
requireAuth();
if (!isAdmin() && !isVieScolaire() && !isProfesseur()) { die('Accès refusé'); }

require_once __DIR__ . '/includes/CompetenceService.php';
$service = new CompetenceService(getPDO());
$exportService = new \API\Services\ExportService(getPDO());

$type = $_GET['type'] ?? 'evaluations';
$format = $_GET['format'] ?? 'csv';
$classeId = (int)($_GET['classe_id'] ?? 0);
$periodeId = !empty($_GET['periode_id']) ? (int)$_GET['periode_id'] : null;

if (!$classeId) { die('Paramètre classe_id requis'); }

if ($type === 'bilan') {
    $data = $service->getBilanForExport($classeId, $periodeId);
    $headers = ['Nom', 'Prénom', 'Domaine', 'Niveau moyen', 'Nb évaluations'];
    $title = 'Bilan compétences par domaine';
    $filename = 'bilan_competences';
} else {
    $data = $service->getEvaluationsForExport($classeId, $periodeId);
    $headers = ['Nom', 'Prénom', 'Domaine', 'Code', 'Compétence', 'Niveau', 'Matière', 'Professeur', 'Date'];
    $title = 'Évaluations de compétences';
    $filename = 'evaluations_competences';
}

if ($format === 'pdf') {
    $exportService->pdf($title, $headers, $data, $filename);
} else {
    $exportService->csv($headers, $data, $filename);
}
