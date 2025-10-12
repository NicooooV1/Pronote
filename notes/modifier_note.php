<?php
// Démarrer la mise en mémoire tampon
ob_start();

// Configuration sécurisée des sessions
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => isset($_SERVER['HTTPS']),
        'use_strict_mode' => true
    ]);
}

// Inclure les fichiers nécessaires avec gestion d'erreurs
try {
    require_once __DIR__ . '/../API/auth_central.php';
    require_once 'includes/db.php';
    require_once 'includes/functions.php';
} catch (Exception $e) {
    logError("Erreur inclusion fichiers: " . $e->getMessage());
    die("Erreur de configuration système");
}

// Vérification stricte des permissions
requireAuth();
if (!canManageNotes()) {
    redirectTo('notes.php', 'Accès non autorisé');
}

// Récupération sécurisée des données utilisateur
$user = getCurrentUser();
$user_fullname = getUserFullName();
$user_initials = getUserInitials();
$user_role = getUserRole();

// Validation de l'ID de la note
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id <= 0) {
    setFlashMessage('error', "Identifiant de note invalide.");
    redirectTo('notes.php');
}

// Variables d'état
$note = null;
$errors = [];
$success = false;

try {
    // Récupération de la note avec vérification des permissions
    $query = "SELECT n.*, e.nom as nom_eleve, e.prenom as prenom_eleve, 
              m.nom as nom_matiere, p.nom as nom_professeur, p.prenom as prenom_professeur
              FROM notes n 
              LEFT JOIN eleves e ON n.id_eleve = e.id 
              LEFT JOIN matieres m ON n.id_matiere = m.id 
              LEFT JOIN professeurs p ON n.id_professeur = p.id 
              WHERE n.id = ?";
    
    // Ajout de restriction pour les enseignants
    if (isTeacher() && !isAdmin() && !isVieScolaire()) {
        $query .= " AND n.id_professeur = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$id, $user['id']]);
    } else {
        $stmt = $pdo->prepare($query);
        $stmt->execute([$id]);
    }
    
    $note = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$note) {
        setFlashMessage('error', "Note non trouvée ou accès non autorisé.");
        redirectTo('notes.php');
    }
    
    // Traitement du formulaire de modification
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        validateCSRFToken($_POST['csrf_token'] ?? '');
        
        // Validation des données
        $nouvelle_note = filter_input(INPUT_POST, 'note', FILTER_VALIDATE_FLOAT);
        $coefficient = filter_input(INPUT_POST, 'coefficient', FILTER_VALIDATE_FLOAT);
        $commentaire = filter_input(INPUT_POST, 'commentaire', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $date_evaluation = filter_input(INPUT_POST, 'date_evaluation', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $trimestre = filter_input(INPUT_POST, 'trimestre', FILTER_VALIDATE_INT);
        
        // Validations métier
        if ($nouvelle_note === false || $nouvelle_note < 0 || $nouvelle_note > 20) {
            $errors[] = "La note doit être comprise entre 0 et 20.";
        }
        
        if ($coefficient === false || $coefficient <= 0 || $coefficient > 10) {
            $errors[] = "Le coefficient doit être compris entre 0.1 et 10.";
        }
        
        if ($trimestre === false || $trimestre < 1 || $trimestre > 3) {
            $errors[] = "Le trimestre doit être compris entre 1 et 3.";
        }
        
        if ($date_evaluation && !validateDate($date_evaluation)) {
            $errors[] = "Date d'évaluation invalide.";
        }
        
        // Si pas d'erreurs, mise à jour
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                
                $updateQuery = "UPDATE notes SET 
                    note = ?, 
                    coefficient = ?, 
                    commentaire = ?, 
                    date_evaluation = ?, 
                    trimestre = ?,
                    date_modification = NOW()
                    WHERE id = ?";
                
                $stmt = $pdo->prepare($updateQuery);
                $result = $stmt->execute([
                    $nouvelle_note,
                    $coefficient,
                    $commentaire,
                    $date_evaluation ?: null,
                    $trimestre,
                    $id
                ]);
                
                if ($result) {
                    // Log de sécurité
                    logSecurityEvent('note_modified', [
                        'note_id' => $id,
                        'modified_by' => $user['id'],
                        'old_value' => $note['note'],
                        'new_value' => $nouvelle_note
                    ]);
                    
                    $pdo->commit();
                    setFlashMessage('success', "Note modifiée avec succès.");
                    redirectTo('notes.php');
                } else {
                    throw new Exception("Erreur lors de la mise à jour");
                }
                
            } catch (Exception $e) {
                $pdo->rollBack();
                logError("Erreur modification note: " . $e->getMessage());
                $errors[] = "Erreur lors de la modification de la note.";
            }
        }
    }
    
} catch (Exception $e) {
    logError("Erreur dans modifier_note.php: " . $e->getMessage());
    setFlashMessage('error', "Une erreur système est survenue.");
    redirectTo('notes.php');
}

// Charger les données de référence
$matieres = [];
$classes = [];

try {
    // Récupération des matières
    $stmt = $pdo->query("SELECT id, nom FROM matieres WHERE actif = 1 ORDER BY nom");
    $matieres = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupération des classes
    $stmt = $pdo->query("SELECT DISTINCT nom FROM classes WHERE actif = 1 ORDER BY nom");
    $classes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (Exception $e) {
    logError("Erreur chargement données référence: " . $e->getMessage());
}

// Génération du token CSRF
$csrf_token = generateCSRFToken();

// Charger les données depuis le fichier JSON de façon sécurisée
$etablissement_data = [];
$json_file = dirname(__DIR__) . '/login/data/etablissement.json';
if (file_exists($json_file) && is_readable($json_file)) {
    $json_content = file_get_contents($json_file);
    $etablissement_data = json_decode($json_content, true) ?: [];
}

include 'includes/header.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <h2>Modification de la note</h2>
        <a href="notes.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Retour
        </a>
    </div>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <div class="note-form">
        <form method="post" action="" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
            
            <div class="form-group">
                <label>Élève :</label>
                <input type="text" value="<?= htmlspecialchars(($note['prenom_eleve'] ?? '') . ' ' . ($note['nom_eleve'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" readonly class="form-control">
            </div>
            
            <div class="form-group">
                <label>Matière :</label>
                <input type="text" value="<?= htmlspecialchars($note['nom_matiere'] ?? '', ENT_QUOTES, 'UTF-8') ?>" readonly class="form-control">
            </div>
            
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="note">Note * :</label>
                    <input type="number" id="note" name="note" step="0.25" min="0" max="20" 
                           value="<?= htmlspecialchars($note['note'] ?? '', ENT_QUOTES, 'UTF-8') ?>" 
                           required class="form-control">
                </div>
                
                <div class="form-group col-md-6">
                    <label for="coefficient">Coefficient * :</label>
                    <input type="number" id="coefficient" name="coefficient" step="0.25" min="0.25" max="10" 
                           value="<?= htmlspecialchars($note['coefficient'] ?? '1', ENT_QUOTES, 'UTF-8') ?>" 
                           required class="form-control">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="date_evaluation">Date d'évaluation :</label>
                    <input type="date" id="date_evaluation" name="date_evaluation" 
                           value="<?= htmlspecialchars($note['date_evaluation'] ?? '', ENT_QUOTES, 'UTF-8') ?>" 
                           max="<?= date('Y-m-d') ?>" class="form-control">
                </div>
                
                <div class="form-group col-md-6">
                    <label for="trimestre">Trimestre * :</label>
                    <select id="trimestre" name="trimestre" required class="form-control">
                        <option value="1" <?= ($note['trimestre'] ?? '') == '1' ? 'selected' : '' ?>>1er Trimestre</option>
                        <option value="2" <?= ($note['trimestre'] ?? '') == '2' ? 'selected' : '' ?>>2ème Trimestre</option>
                        <option value="3" <?= ($note['trimestre'] ?? '') == '3' ? 'selected' : '' ?>>3ème Trimestre</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label for="commentaire">Commentaire :</label>
                <textarea id="commentaire" name="commentaire" rows="3" maxlength="500" class="form-control"><?= htmlspecialchars($note['commentaire'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                <small class="form-text text-muted">Maximum 500 caractères</small>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Enregistrer les modifications
                </button>
                <a href="notes.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Annuler
                </a>
            </div>
        </form>
    </div>
</div>

<script>
// Validation côté client
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    const noteInput = document.getElementById('note');
    const coefficientInput = document.getElementById('coefficient');
    
    form.addEventListener('submit', function(e) {
        let isValid = true;
        
        // Validation de la note
        const note = parseFloat(noteInput.value);
        if (isNaN(note) || note < 0 || note > 20) {
            isValid = false;
            noteInput.classList.add('is-invalid');
        } else {
            noteInput.classList.remove('is-invalid');
        }
        
        // Validation du coefficient
        const coefficient = parseFloat(coefficientInput.value);
        if (isNaN(coefficient) || coefficient <= 0 || coefficient > 10) {
            isValid = false;
            coefficientInput.classList.add('is-invalid');
        } else {
            coefficientInput.classList.remove('is-invalid');
        }
        
        if (!isValid) {
            e.preventDefault();
            alert('Veuillez corriger les erreurs dans le formulaire.');
        }
    });
});
</script>

<?php
include 'includes/footer.php';
ob_end_flush();
?>