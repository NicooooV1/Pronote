<?php
/**
 * M16 – Documents — Télécharger un document
 */
require_once __DIR__ . '/../API/bootstrap.php';
requireAuth();

require_once __DIR__ . '/includes/DocumentService.php';
$docService = new DocumentService(getPDO());

$id = (int)($_GET['id'] ?? 0);
$doc = $docService->getDocument($id);

if (!$doc) {
    http_response_code(404);
    die('Document introuvable.');
}

$filepath = __DIR__ . '/' . $doc['fichier_chemin'];
if (!file_exists($filepath)) {
    http_response_code(404);
    die('Fichier introuvable sur le serveur.');
}

$docService->incrementerTelechargements($id);

header('Content-Description: File Transfer');
header('Content-Type: ' . ($doc['fichier_type'] ?: 'application/octet-stream'));
header('Content-Disposition: attachment; filename="' . basename($doc['fichier_nom']) . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: no-cache, must-revalidate');
readfile($filepath);
exit;
