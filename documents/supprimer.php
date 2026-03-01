<?php
/**
 * M16 – Documents — Supprimer un document
 */
require_once __DIR__ . '/../API/bootstrap.php';
requireAuth();

if (!isAdmin()) {
    redirect('../accueil/accueil.php');
}

require_once __DIR__ . '/includes/DocumentService.php';
$docService = new DocumentService(getPDO());

$id = (int)($_GET['id'] ?? 0);

if ($docService->supprimer($id)) {
    $_SESSION['success_message'] = 'Document supprimé.';
} else {
    $_SESSION['error_message'] = 'Erreur lors de la suppression.';
}

header('Location: documents.php');
exit;
