<?php
/**
 * Téléchargement sécurisé des pièces jointes
 * Vérifie que l'utilisateur est participant à la conversation avant de servir le fichier.
 * Usage : download.php?id=<attachment_id>
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/auth.php';
require_once __DIR__ . '/core/validator.php';

// Authentification obligatoire
$user = checkAuth();
if (!$user) {
    http_response_code(401);
    die('Non authentifié');
}

// Valider l'ID de la pièce jointe
$attachmentId = Validator::id($_GET['id'] ?? null);
if (!$attachmentId) {
    http_response_code(400);
    die('ID de pièce jointe invalide');
}

try {
    // Récupérer les infos de la pièce jointe + vérifier que l'utilisateur est participant
    $stmt = $pdo->prepare("
        SELECT ma.file_name, ma.file_path, ma.message_id, m.conversation_id
        FROM message_attachments ma
        JOIN messages m ON ma.message_id = m.id
        JOIN conversation_participants cp ON m.conversation_id = cp.conversation_id
        WHERE ma.id = ?
          AND cp.user_id = ? AND cp.user_type = ?
          AND cp.is_deleted = 0
        LIMIT 1
    ");
    $stmt->execute([$attachmentId, $user['id'], $user['type']]);
    $attachment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$attachment) {
        http_response_code(403);
        die('Accès refusé : vous n\'êtes pas participant à cette conversation ou la pièce jointe n\'existe pas.');
    }

    // Servir le fichier via le service centralisé
    $uploader = new \API\Services\FileUploadService('messagerie');
    $uploader->serve($attachment['file_path'], $attachment['file_name']);

} catch (Exception $e) {
    error_log("Download error: " . $e->getMessage());
    http_response_code(500);
    die('Erreur serveur');
}
