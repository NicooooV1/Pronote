<?php
/**
 * M35 – Archivage — Télécharger
 */
require_once __DIR__ . '/includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$path = $archiveService->getCheminFichier($id);

if (!$path) {
    header('Location: archivage.php');
    exit;
}

$archive = $archiveService->getArchive($id);
$filename = basename($path);

header('Content-Type: application/json');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
