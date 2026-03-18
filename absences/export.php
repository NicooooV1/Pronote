<?php
/**
 * export.php — Export CSV/PDF du module Absences.
 * 
 * Paramètres GET :
 *   format : csv | pdf (défaut: csv)
 *   + tous les filtres d'absences (date_debut, date_fin, classe, justifie, type)
 */
require_once __DIR__ . '/../API/core.php';
require_once __DIR__ . '/includes/AbsenceRepository.php';
require_once __DIR__ . '/includes/AbsenceHelper.php';

requireAuth();

// Vérifier les droits — seuls admin/vie_scolaire/prof peuvent exporter
if (!canManageAbsences()) {
    $_SESSION['error_message'] = 'Vous n\'avez pas les droits pour exporter les absences.';
    header('Location: absences.php');
    exit;
}

$pdo    = getPDO();
$user   = getCurrentUser();
$role   = getUserRole();
$format = in_array($_GET['format'] ?? '', ['csv', 'pdf']) ? $_GET['format'] : 'csv';
$type   = in_array($_GET['type'] ?? '', ['absences', 'retards']) ? $_GET['type'] : 'absences';

$repo    = new AbsenceRepository($pdo);
$filters = AbsenceHelper::getFilters();

// Récupérer les données
if ($type === 'retards') {
    $data = $repo->getRetardsByRole($role, $user['id'], $filters);
} else {
    $data = $repo->getByRole($role, $user['id'], $filters);
}

// Service d'export
$export = new \API\Services\ExportService($pdo);

if ($type === 'retards') {
    $columns = [
        'eleve_nom'    => 'Élève',
        'classe'       => 'Classe',
        'date'         => 'Date',
        'heure_arrivee'=> 'Heure d\'arrivée',
        'duree'        => 'Durée (min)',
        'motif'        => 'Motif',
        'justifie'     => 'Justifié',
    ];
    $filename = 'retards_' . date('Y-m-d') . '.' . ($format === 'pdf' ? 'pdf' : 'csv');
    $title = 'Retards du ' . date('d/m/Y', strtotime($filters['date_debut'])) . ' au ' . date('d/m/Y', strtotime($filters['date_fin']));
} else {
    $columns = [
        'eleve_nom'    => 'Élève',
        'classe'       => 'Classe',
        'date_debut'   => 'Début',
        'date_fin'     => 'Fin',
        'motif'        => 'Motif',
        'statut'       => 'Statut',
        'justifie'     => 'Justifié',
    ];
    $filename = 'absences_' . date('Y-m-d') . '.' . ($format === 'pdf' ? 'pdf' : 'csv');
    $title = 'Absences du ' . date('d/m/Y', strtotime($filters['date_debut'])) . ' au ' . date('d/m/Y', strtotime($filters['date_fin']));
}

if ($format === 'pdf') {
    $tableHtml = $export->buildTable($data, $columns, $title);
    $export->pdf($tableHtml, $title, $filename);
} else {
    $export->csv($data, $columns, $filename);
}
