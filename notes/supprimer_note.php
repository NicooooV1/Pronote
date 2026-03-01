<?php
/**
 * Module Notes — Suppression d'une note avec confirmation.
 * Utilise NoteService pour les opérations SQL.
 */
ob_start();

require_once __DIR__ . '/../API/core.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/NoteService.php';

requireAuth();
if (!canManageNotes()) {
    header('Location: notes.php');
    exit;
}

$user          = getCurrentUser();
$user_fullname = getUserFullName();
$user_initials = getUserInitials();
$user_role     = getUserRole();
$pdo           = getPDO();
$noteService   = new NoteService($pdo);

// Validation de l'ID
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id <= 0) {
    setFlashMessage('error', "Identifiant de note invalide.");
    header('Location: notes.php');
    exit;
}

// Récupérer la note (restreint au professeur si applicable)
$profId = (isTeacher() && !isAdmin() && !isVieScolaire()) ? $user['id'] : null;
$note = $noteService->getNoteById($id, $profId);

if (!$note) {
    setFlashMessage('error', "Note non trouvée ou accès non autorisé.");
    header('Location: notes.php');
    exit;
}

// Traitement de la suppression
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRFToken($_POST['csrf_token'] ?? '');

    try {
        $noteService->deleteNote($id);

        if (function_exists('logSecurityEvent')) {
            logSecurityEvent('note_deleted', [
                'note_id'    => $id,
                'deleted_by' => $user['id'],
                'user_role'  => $user_role,
            ]);
        }

        setFlashMessage('success', "Note supprimée avec succès.");
    } catch (Exception $e) {
        error_log("Erreur suppression note: " . $e->getMessage());
        setFlashMessage('error', "Erreur lors de la suppression.");
    }

    header('Location: notes.php');
    exit;
}

$csrf_token = generateCSRFToken();
$pageTitle = 'Supprimer une note';
include 'includes/header.php';
?>

                <div style="background:white; border-radius:10px; padding:30px; box-shadow:0 2px 8px rgba(0,0,0,0.06); max-width:600px;">
                    <h2 style="font-size:1.1em; color:#2d3748; margin-bottom:20px;">Suppression de la note</h2>

                    <div class="alert alert-warning" style="margin-bottom:20px;">
                        <strong>Attention !</strong> Cette action est irréversible.
                    </div>

                    <div style="background:#f7fafc; border-radius:8px; padding:15px; margin-bottom:20px;">
                        <p style="margin:5px 0; font-size:14px;"><strong>Élève :</strong> <?= htmlspecialchars(($note['prenom_eleve'] ?? '') . ' ' . ($note['nom_eleve'] ?? '')) ?></p>
                        <p style="margin:5px 0; font-size:14px;"><strong>Matière :</strong> <?= htmlspecialchars($note['nom_matiere'] ?? '') ?></p>
                        <p style="margin:5px 0; font-size:14px;"><strong>Note :</strong> <?= htmlspecialchars($note['note'] ?? '') ?>/<?= $note['note_sur'] ?? 20 ?></p>
                        <p style="margin:5px 0; font-size:14px;"><strong>Date :</strong> <?= !empty($note['date_note']) ? date('d/m/Y', strtotime($note['date_note'])) : '-' ?></p>
                    </div>

                    <form method="post" action="">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <div style="display:flex; gap:10px; justify-content:flex-end;">
                            <a href="notes.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Annuler</a>
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Confirmer la suppression ?');">
                                <i class="fas fa-trash"></i> Supprimer
                            </button>
                        </div>
                    </form>
                </div>

<?php
include 'includes/footer.php';
ob_end_flush();
?>