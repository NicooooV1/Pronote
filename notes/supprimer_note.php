<?php
// Démarrer la mise en mémoire tampon
ob_start();

// Inclure les fichiers nécessaires de manière sécurisée
require_once __DIR__ . '/../API/auth_central.php';
require_once 'includes/db.php';

// Vérifier les permissions pour gérer les notes avec validation stricte
requireAuth();
if (!canManageNotes()) {
    redirectTo('notes.php', 'Accès non autorisé');
}

// Récupérer les informations de l'utilisateur connecté via l'API centralisée
$user = getCurrentUser();
$user_fullname = getUserFullName();
$user_initials = getUserInitials();
$user_role = getUserRole();

// Validation stricte et sécurisée de l'ID
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id <= 0) {
    setFlashMessage('error', "Identifiant de note invalide.");
    redirectTo('notes.php');
}

// Vérification des autorisations spécifiques selon le rôle avec requête préparée
try {
    if (isTeacher() && !isAdmin() && !isVieScolaire()) {
        // Vérifier que l'enseignant ne peut supprimer que ses propres notes
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notes WHERE id = ? AND id_professeur = ?");
        $stmt->execute([$id, $user['id']]);
        
        if ($stmt->fetchColumn() == 0) {
            setFlashMessage('error', "Vous ne pouvez supprimer que vos propres notes.");
            redirectTo('notes.php');
        }
    }
    
    // Récupérer les détails de la note pour vérification
    $stmt = $pdo->prepare("SELECT * FROM notes WHERE id = ?");
    $stmt->execute([$id]);
    $note = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$note) {
        setFlashMessage('error', "Note non trouvée.");
        redirectTo('notes.php');
    }
    
    // Traitement de la suppression avec confirmation CSRF
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        validateCSRFToken($_POST['csrf_token'] ?? '');
        
        // Transaction pour garantir l'intégrité
        $pdo->beginTransaction();
        
        try {
            $stmt = $pdo->prepare("DELETE FROM notes WHERE id = ?");
            $result = $stmt->execute([$id]);
            
            if ($result) {
                // Log de sécurité
                logSecurityEvent('note_deleted', [
                    'note_id' => $id,
                    'deleted_by' => $user['id'],
                    'user_role' => $user_role
                ]);
                
                $pdo->commit();
                setFlashMessage('success', "Note supprimée avec succès.");
            } else {
                throw new Exception("Erreur lors de la suppression");
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            logError("Erreur suppression note: " . $e->getMessage());
            setFlashMessage('error', "Erreur lors de la suppression.");
        }
        
        redirectTo('notes.php');
    }
    
} catch (Exception $e) {
    logError("Erreur dans supprimer_note.php: " . $e->getMessage());
    setFlashMessage('error', "Une erreur est survenue.");
    redirectTo('notes.php');
}

// Génération du token CSRF pour le formulaire
$csrf_token = generateCSRFToken();

// Inclure l'en-tête avec protection XSS
include 'includes/header.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <h2>Suppression de la note</h2>
    </div>
    
    <div class="confirmation-form">
        <div class="alert alert-warning">
            <strong>Attention !</strong> Cette action est irréversible.
        </div>
        
        <div class="note-details">
            <h4>Détails de la note à supprimer :</h4>
            <ul>
                <li><strong>Élève :</strong> <?= htmlspecialchars($note['nom_eleve'] ?? '', ENT_QUOTES, 'UTF-8') ?></li>
                <li><strong>Note :</strong> <?= htmlspecialchars($note['note'] ?? '', ENT_QUOTES, 'UTF-8') ?>/20</li>
                <li><strong>Matière :</strong> <?= htmlspecialchars($note['matiere'] ?? '', ENT_QUOTES, 'UTF-8') ?></li>
                <li><strong>Date :</strong> <?= htmlspecialchars(formatDate($note['date_ajout'] ?? ''), ENT_QUOTES, 'UTF-8') ?></li>
            </ul>
        </div>
        
        <form method="post" action="" class="deletion-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
            
            <div class="form-actions">
                <button type="submit" class="btn btn-danger" onclick="return confirm('Êtes-vous certain de vouloir supprimer cette note ?')">
                    <i class="fas fa-trash"></i> Confirmer la suppression
                </button>
                <a href="notes.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Annuler
                </a>
            </div>
        </form>
    </div>
</div>

<?php
include 'includes/footer.php';
ob_end_flush();
?>