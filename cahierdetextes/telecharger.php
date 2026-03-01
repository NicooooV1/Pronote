<?php
/**
 * telecharger.php — Téléchargement sécurisé de pièces jointes (PJ-3)
 *
 * Les fichiers sont servis via ce script (pas d'accès direct à uploads/).
 * Vérifie l'authentification avant de servir le fichier.
 */
require_once __DIR__ . '/../API/core.php';
require_once __DIR__ . '/includes/DevoirService.php';

requireAuth();

$pdo     = getPDO();
$service = new DevoirService($pdo);

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    http_response_code(400);
    exit('Paramètre manquant.');
}

$fichier = $service->getFichierById($id);
if (!$fichier) {
    http_response_code(404);
    exit('Fichier introuvable.');
}

// Servir le fichier via le service centralisé
$uploader = new \API\Services\FileUploadService('devoirs');
$uploader->serve($fichier['nom_stockage'], $fichier['nom_original']);
