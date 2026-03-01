<?php
/**
 * M44 – Téléchargement fichier diplôme
 */
require_once __DIR__ . '/includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$diplome = $diplService->getDiplome($id);
if (!$diplome || !$diplome['fichier_path']) { redirect('/diplomes/diplomes.php'); }

// Accès: admin/VS toujours, eleve si c'est le sien, parent si enfant
if (!isAdmin() && !isPersonnelVS()) {
    if (isEleve() && $diplome['eleve_id'] != getUserId()) { redirect('/diplomes/diplomes.php'); }
    if (isParent()) {
        $check = $pdo->prepare("SELECT 1 FROM parent_eleve WHERE parent_id = ? AND eleve_id = ?");
        $check->execute([getUserId(), $diplome['eleve_id']]);
        if (!$check->fetchColumn()) { redirect('/diplomes/diplomes.php'); }
    }
}

$file = __DIR__ . '/uploads/' . $diplome['fichier_path'];
if (!file_exists($file)) { redirect('/diplomes/diplomes.php'); }

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($diplome['fichier_path']) . '"');
header('Content-Length: ' . filesize($file));
readfile($file);
exit;
