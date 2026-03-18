<?php
/**
 * export.php — Export CSV/PDF du module Notes.
 */
require_once __DIR__ . '/../API/core.php';
require_once __DIR__ . '/includes/NoteService.php';

requireAuth();

if (!isAdmin() && !isVieScolaire() && !isTeacher()) {
    $_SESSION['error_message'] = 'Accès non autorisé.';
    header('Location: notes.php');
    exit;
}

$pdo         = getPDO();
$noteService = new NoteService($pdo);
$export      = new \API\Services\ExportService($pdo);

$format    = in_array($_GET['format'] ?? '', ['csv', 'pdf']) ? $_GET['format'] : 'csv';
$trimestre = max(1, min(3, (int) ($_GET['trimestre'] ?? NoteService::getTrimestreCourant())));

$filters = [];
if (!empty($_GET['classe']))  $filters['classe']  = $_GET['classe'];
if (!empty($_GET['matiere'])) $filters['matiere'] = $_GET['matiere'];

$data = $noteService->getNotesForExport($trimestre, $filters);

$columns = [
    'eleve'       => 'Élève',
    'classe'      => 'Classe',
    'matiere'     => 'Matière',
    'note'        => 'Note',
    'coefficient' => 'Coeff.',
    'type'        => 'Type',
    'date'        => 'Date',
    'commentaire' => 'Commentaire',
];

$title    = 'Notes — Trimestre ' . $trimestre;
$filename = 'notes_T' . $trimestre . '_' . date('Y-m-d');

if ($format === 'pdf') {
    $tableHtml = $export->buildTable($data, $columns, $title);
    $export->pdf($tableHtml, $title, $filename . '.pdf');
} else {
    $export->csv($data, $columns, $filename . '.csv');
}
