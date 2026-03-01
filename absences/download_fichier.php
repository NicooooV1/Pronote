<?php
/**
 * download_fichier.php — Téléchargement sécurisé des pièces jointes de justificatifs
 * Vérifie les droits d'accès avant de servir le fichier.
 */

require_once __DIR__ . '/../API/core.php';
require_once __DIR__ . '/includes/AbsenceRepository.php';

if (!isLoggedIn()) {
    http_response_code(403);
    exit('Accès refusé');
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(400);
    exit('Identifiant invalide');
}

$pdo  = getPDO();
$repo = new AbsenceRepository($pdo);

// Récupérer l'attachment
$attachment = $repo->getAttachmentById($id);
if (!$attachment) {
    http_response_code(404);
    exit('Fichier non trouvé');
}

// Vérifier l'accès : l'utilisateur doit être admin/vie_scolaire OU propriétaire du justificatif
$role   = getUserRole();
$userId = (int) getCurrentUser()['id'];

if (!in_array($role, ['admin', 'vie_scolaire'])) {
    // Récupérer le justificatif lié
    $justificatif = $repo->getJustificatifById((int) $attachment['id_justificatif']);
    if (!$justificatif) {
        http_response_code(404);
        exit('Fichier non trouvé');
    }

    $autorise = false;
    if ($role === 'eleve' && (int) $justificatif['id_eleve'] === $userId) {
        $autorise = true;
    } elseif ($role === 'parent') {
        $enfants = $repo->getChildrenForParent($userId);
        $autorise = in_array($justificatif['id_eleve'], $enfants);
    } elseif ($role === 'professeur') {
        // Les professeurs qui gèrent les absences ont accès
        $autorise = canManageAbsences();
    }

    if (!$autorise) {
        http_response_code(403);
        exit('Accès refusé');
    }
}

// Servir le fichier via le service centralisé
$uploader = new \API\Services\FileUploadService('justificatifs');
$uploader->serve($attachment['nom_serveur'], $attachment['nom_original']);
