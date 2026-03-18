<?php
/**
 * Cahier de textes — Export CSV/PDF
 */
require_once __DIR__ . '/../API/core.php';
require_once __DIR__ . '/includes/DevoirService.php';

requireAuth();

if (!isAdmin() && !isVieScolaire() && !isTeacher()) {
    http_response_code(403);
    exit('Accès refusé');
}

$pdo = getPDO();
$service = new DevoirService($pdo);
$exportService = new \API\Services\ExportService($pdo);

$user = getCurrentUser();
$role = getUserRole();
$fullname = getUserFullName();

$format = $_GET['format'] ?? 'csv';
$filters = [];
if (!empty($_GET['classe']))  $filters['classe']  = $_GET['classe'];
if (!empty($_GET['matiere'])) $filters['matiere'] = $_GET['matiere'];

$data = $service->getDevoirsForExport($role, $user['id'], $fullname, $filters);
$columns = ['Matière', 'Classe', 'Titre', 'Description', 'Date rendu', 'Statut', 'Professeur', 'Type'];

if ($format === 'pdf') {
    $exportService->pdf($data, $columns, 'Cahier de textes');
} else {
    $exportService->csv($data, $columns, 'cahier_de_textes');
}
