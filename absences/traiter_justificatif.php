<?php
// Démarrer la mise en mémoire tampon
ob_start();

// Inclusion de l'API centralisée
require_once __DIR__ . '/../API/core.php';
$pdo = getPDO();
require_once __DIR__ . '/includes/functions.php';

// Vérifier que l'utilisateur est connecté et autorisé
if (!isLoggedIn() || !canManageAbsences()) {
    header('Location: ' . LOGIN_URL);
    exit;
}

// Récupérer les informations de l'utilisateur connecté
$user = getCurrentUser();
$user_fullname = getUserFullName();
$user_role = getUserRole();
$user_initials = getUserInitials();

// Récupérer l'ID du justificatif avec validation
$id_justificatif = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id_justificatif) {
    $_SESSION['error_message'] = "Identifiant de justificatif invalide";
    header('Location: justificatifs.php');
    exit;
}

// Récupérer les détails du justificatif
$justificatif = null;
try {
    $stmt = $pdo->prepare("
        SELECT j.*, e.nom, e.prenom, e.classe 
        FROM justificatifs j
        JOIN eleves e ON j.id_eleve = e.id
        WHERE j.id = ?
    ");
    $stmt->execute([$id_justificatif]);
    $justificatif = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération du justificatif: " . $e->getMessage());
    $_SESSION['error_message'] = "Une erreur est survenue lors de la récupération des données.";
    header('Location: justificatifs.php');
    exit;
}

if (!$justificatif) {
    $_SESSION['error_message'] = "Justificatif non trouvé";
    header('Location: justificatifs.php');
    exit;
}

// Vérifier si le justificatif est déjà traité
if ($justificatif['traite']) {
    $_SESSION['error_message'] = "Ce justificatif a déjà été traité";
    header('Location: details_justificatif.php?id=' . $id_justificatif);
    exit;
}

// Configuration de la page
$pageTitle = 'Traiter le justificatif';
$currentPage = 'justificatifs';
$showBackButton = true;
$backLink = 'details_justificatif.php?id=' . $id_justificatif;

// Ajouter un jeton CSRF pour protéger le formulaire
$csrf_token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;

// Message de succès ou d'erreur
$message = '';
$erreur = '';

// Récupérer les absences associées à la période du justificatif
$absences = [];
try {
    $stmt = $pdo->prepare("
        SELECT a.*, e.nom, e.prenom, e.classe
        FROM absences a
        JOIN eleves e ON a.id_eleve = e.id
        WHERE a.id_eleve = ? 
        AND (
            (a.date_debut BETWEEN ? AND ?) OR
            (a.date_fin BETWEEN ? AND ?) OR
            (a.date_debut <= ? AND a.date_fin >= ?)
        )
        ORDER BY a.date_debut DESC
    ");
    $stmt->execute([
        $justificatif['id_eleve'],
        $justificatif['date_debut_absence'], $justificatif['date_fin_absence'],
        $justificatif['date_debut_absence'], $justificatif['date_fin_absence'],
        $justificatif['date_debut_absence'], $justificatif['date_fin_absence']
    ]);
    $absences = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des absences: " . $e->getMessage());
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérification du jeton CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $erreur = "Erreur de sécurité. Veuillez réessayer.";
    } else {
        $approuve = isset($_POST['approuve']) ? 1 : 0;
        $id_absence = filter_input(INPUT_POST, 'id_absence', FILTER_VALIDATE_INT);
        $commentaire = filter_input(INPUT_POST, 'commentaire', FILTER_SANITIZE_STRING);
        
        try {
            // Mettre à jour le justificatif
            $stmt = $pdo->prepare("
                UPDATE justificatifs
                SET traite = 1,
                    approuve = ?,
                    id_absence = ?,
                    commentaire_admin = ?,
                    date_traitement = NOW(),
                    traite_par = ?
                WHERE id = ?
            ");
            
            $stmt->execute([$approuve, $id_absence ?: null, $commentaire, $user_fullname, $id_justificatif]);
            
            // Si approuvé et une absence est sélectionnée, mettre à jour l'absence
            if ($approuve && $id_absence) {
                $stmt = $pdo->prepare("UPDATE absences SET justifie = 1 WHERE id = ?");
                $stmt->execute([$id_absence]);
            }
            
            $_SESSION['success_message'] = "Le justificatif a été traité avec succès.";
            header('Location: details_justificatif.php?id=' . $id_justificatif);
            exit;
            
        } catch (PDOException $e) {
            error_log("Erreur lors du traitement du justificatif: " . $e->getMessage());
            $erreur = "Une erreur est survenue lors du traitement du justificatif.";
        }
    }
}

// Inclure l'en-tête
include 'includes/header.php';
?>

<!-- Message de succès ou d'erreur -->
<?php if (!empty($message)): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <span><?= $message ?></span>
        <button class="alert-close"><i class="fas fa-times"></i></button>
    </div>
<?php endif; ?>

<?php if (!empty($erreur)): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i>
        <span><?= $erreur ?></span>
        <button class="alert-close"><i class="fas fa-times"></i></button>
    </div>
<?php endif; ?>

<div class="content-container">
    <div class="content-header">
        <h2>Traiter le justificatif #<?= $id_justificatif ?></h2>
    </div>

    <div class="content-body">
        <!-- Détails du justificatif -->
        <div class="form-container mb-4">
            <h3>Informations sur le justificatif</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label>Élève</label>
                    <div class="form-value">
                        <?= htmlspecialchars($justificatif['prenom'] . ' ' . $justificatif['nom']) ?> (<?= htmlspecialchars($justificatif['classe']) ?>)
                    </div>
                </div>
                <div class="form-group">
                    <label>Date de soumission</label>
                    <div class="form-value">
                        <?= isset($justificatif['date_soumission']) ? date('d/m/Y', strtotime($justificatif['date_soumission'])) : 'N/A' ?>
                    </div>
                </div>
                <div class="form-group form-full">
                    <label>Période d'absence</label>
                    <div class="form-value">
                        Du <?= date('d/m/Y', strtotime($justificatif['date_debut_absence'])) ?>
                        au <?= date('d/m/Y', strtotime($justificatif['date_fin_absence'])) ?>
                    </div>
                </div>
                <div class="form-group">
                    <label>Motif</label>
                    <div class="form-value"><?= htmlspecialchars($justificatif['motif'] ?? 'Non spécifié') ?></div>
                </div>
                <div class="form-group form-full">
                    <label>Description</label>
                    <div class="form-value"><?= nl2br(htmlspecialchars($justificatif['description'] ?? 'Aucune description fournie')) ?></div>
                </div>
                
                <?php if (!empty($justificatif['fichier_path'])): ?>
                <div class="form-group form-full">
                    <label>Document justificatif</label>
                    <div class="form-value">
                        <a href="<?= htmlspecialchars($justificatif['fichier_path']) ?>" class="btn btn-outline" target="_blank">
                            <i class="fas fa-file-download"></i> Télécharger le justificatif
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Formulaire de traitement -->
        <form method="post" action="traiter_justificatif.php?id=<?= $id_justificatif ?>">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <div class="form-container">
                <h3>Traitement du justificatif</h3>
                <div class="form-grid">
                    <div class="form-group form-full">
                        <div class="checkbox-group">
                            <input type="checkbox" name="approuve" id="approuve" checked>
                            <label for="approuve">Approuver ce justificatif</label>
                        </div>
                        <p class="text-small text-muted">
                            Si approuvé, l'absence sélectionnée ci-dessous sera automatiquement marquée comme justifiée.
                        </p>
                    </div>
                    
                    <div class="form-group form-full">
                        <label for="id_absence">Absence à justifier</label>
                        <select name="id_absence" id="id_absence">
                            <option value="">-- Sélectionner une absence --</option>
                            <?php foreach ($absences as $absence): ?>
                                <?php
                                $dateDebut = new DateTime($absence['date_debut']);
                                $dateFin = new DateTime($absence['date_fin']);
                                ?>
                                <option value="<?= $absence['id'] ?>" <?= $absence['id'] === $justificatif['id_absence'] ? 'selected' : '' ?>>
                                    <?= $dateDebut->format('d/m/Y H:i') ?> - <?= $dateFin->format('d/m/Y H:i') ?> 
                                    (<?= $absence['type_absence'] ?>) 
                                    <?= $absence['justifie'] ? '[Déjà justifiée]' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group form-full">
                        <label for="commentaire">Commentaire administratif</label>
                        <textarea name="commentaire" id="commentaire" rows="4"><?= htmlspecialchars($justificatif['commentaire_admin'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="form-actions form-full">
                        <a href="details_justificatif.php?id=<?= $id_justificatif ?>" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Annuler
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check"></i> Valider le traitement
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<?php
// Inclure le pied de page
include 'includes/footer.php';
ob_end_flush();
?>
