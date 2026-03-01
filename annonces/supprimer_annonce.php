<?php
/**
 * M11 – Annonces : Supprimer une annonce
 */

require_once __DIR__ . '/includes/AnnonceService.php';
require_once __DIR__ . '/../API/core.php';
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: annonces.php');
    exit;
}

if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    header('Location: annonces.php');
    exit;
}

$pdo = getPDO();
$service = new AnnonceService($pdo);
$user = getCurrentUser();
$role = getUserRole();

$id = (int)($_POST['id'] ?? 0);
if (!$id) {
    header('Location: annonces.php');
    exit;
}

$annonce = $service->getAnnonce($id);
if (!$annonce) {
    header('Location: annonces.php');
    exit;
}

// Vérifier les permissions
if (!isAdmin() && !($annonce['auteur_id'] == $user['id'] && $annonce['auteur_type'] === $role)) {
    header('Location: annonces.php');
    exit;
}

$service->deleteAnnonce($id);
header('Location: annonces.php?deleted=1');
exit;
