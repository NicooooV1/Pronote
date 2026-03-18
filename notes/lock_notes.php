<?php
/**
 * lock_notes.php — Verrouillage en lot des notes d'une classe/matière/trimestre.
 */
require_once __DIR__ . '/../API/core.php';
require_once __DIR__ . '/includes/NoteService.php';

requireAuth();

if (!isAdmin()) {
    $_SESSION['error_message'] = 'Seuls les administrateurs peuvent verrouiller les notes.';
    header('Location: notes.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validateCSRFToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['error_message'] = 'Requête invalide.';
    header('Location: notes.php');
    exit;
}

$classe    = trim($_POST['classe'] ?? '');
$matiere   = (int) ($_POST['matiere'] ?? 0);
$trimestre = max(1, min(3, (int) ($_POST['trimestre'] ?? 1)));

if (empty($classe) || !$matiere) {
    $_SESSION['error_message'] = 'Classe et matière requises.';
    header('Location: notes.php');
    exit;
}

$pdo         = getPDO();
$noteService = new NoteService($pdo);
$user        = getCurrentUser();

$count = $noteService->bulkLockNotes($matiere, $classe, $trimestre, $user['id']);

$_SESSION['success_message'] = "$count note(s) verrouillée(s) pour $classe, trimestre $trimestre.";
header("Location: notes.php?trimestre=$trimestre&classe=" . urlencode($classe) . "&matiere=$matiere");
exit;
