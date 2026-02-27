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

// Configuration de la page
$pageTitle = 'Détails du justificatif';
$currentPage = 'justificatifs';
$showBackButton = true;
$backLink = 'justificatifs.php';

// Récupérer l'absence associée si elle existe
$absence = null;
if (!empty($justificatif['id_absence'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT a.*, e.nom, e.prenom, e.classe 
            FROM absences a
            JOIN eleves e ON a.id_eleve = e.id
            WHERE a.id = ?
        ");
        $stmt->execute([$justificatif['id_absence']]);
        $absence = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la récupération de l'absence: " . $e->getMessage());
    }
}

// Inclure l'en-tête
include 'includes/header.php';
?>

<div class="content-container">
    <div class="content-header">
        <h2>
            Justificatif #<?= $id_justificatif ?>
        </h2>
        <div class="content-actions">
            <?php if (!$justificatif['traite']): ?>
            <a href="traiter_justificatif.php?id=<?= $id_justificatif ?>" class="btn btn-primary">
                <i class="fas fa-check-circle"></i> Traiter
            </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="content-body">
        <!-- Statut du justificatif -->
        <div class="alert <?= $justificatif['traite'] ? ($justificatif['approuve'] ? 'alert-success' : 'alert-error') : 'alert-warning' ?>">
            <i class="fas <?= $justificatif['traite'] ? ($justificatif['approuve'] ? 'fa-check-circle' : 'fa-times-circle') : 'fa-clock' ?>"></i>
            <div>
                <strong>Statut: </strong>
                <?php if ($justificatif['traite']): ?>
                    <?= $justificatif['approuve'] ? 'Approuvé' : 'Rejeté' ?>
                    <?php if (!empty($justificatif['traite_par'])): ?>
                        par <?= htmlspecialchars($justificatif['traite_par']) ?>
                    <?php endif; ?>
                    <?php if (!empty($justificatif['date_traitement'])): ?>
                        le <?= date('d/m/Y à H:i', strtotime($justificatif['date_traitement'])) ?>
                    <?php endif; ?>
                <?php else: ?>
                    En attente de traitement
                <?php endif; ?>
            </div>
        </div>

        <!-- Informations sur l'élève -->
        <div class="form-container">
            <h3>Informations sur l'élève</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label>Nom</label>
                    <div class="form-value"><?= htmlspecialchars($justificatif['nom']) ?></div>
                </div>
                <div class="form-group">
                    <label>Prénom</label>
                    <div class="form-value"><?= htmlspecialchars($justificatif['prenom']) ?></div>
                </div>
                <div class="form-group">
                    <label>Classe</label>
                    <div class="form-value"><?= htmlspecialchars($justificatif['classe']) ?></div>
                </div>
            </div>
        </div>

        <!-- Détails du justificatif -->
        <div class="form-container mt-4">
            <h3>Détails du justificatif</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label>Date de soumission</label>
                    <div class="form-value">
                        <?= isset($justificatif['date_soumission']) ? date('d/m/Y', strtotime($justificatif['date_soumission'])) : 'N/A' ?>
                    </div>
                </div>
                <div class="form-group">
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
                
                <?php if ($justificatif['traite'] && !empty($justificatif['commentaire_admin'])): ?>
                <div class="form-group form-full">
                    <label>Commentaire administratif</label>
                    <div class="form-value"><?= nl2br(htmlspecialchars($justificatif['commentaire_admin'])) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Informations sur l'absence associée -->
        <?php if ($absence): ?>
        <div class="form-container mt-4">
            <h3>Absence associée</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label>Date de début</label>
                    <div class="form-value"><?= date('d/m/Y à H:i', strtotime($absence['date_debut'])) ?></div>
                </div>
                <div class="form-group">
                    <label>Date de fin</label>
                    <div class="form-value"><?= date('d/m/Y à H:i', strtotime($absence['date_fin'])) ?></div>
                </div>
                <div class="form-group">
                    <label>Type</label>
                    <div class="form-value">
                        <span class="badge badge-<?= $absence['type_absence'] ?>">
                            <?php
                            switch ($absence['type_absence']) {
                                case 'cours': echo 'Cours'; break;
                                case 'demi-journee': echo 'Demi-journée'; break;
                                case 'journee': echo 'Journée'; break;
                                default: echo htmlspecialchars($absence['type_absence']);
                            }
                            ?>
                        </span>
                    </div>
                </div>
                <div class="form-group">
                    <label>Statut</label>
                    <div class="form-value">
                        <?php if ($absence['justifie']): ?>
                            <span class="badge badge-success">Justifiée</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Non justifiée</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="form-group form-full">
                    <label>Actions</label>
                    <div class="form-value">
                        <a href="details_absence.php?id=<?= $absence['id'] ?>" class="btn btn-outline">
                            <i class="fas fa-eye"></i> Voir les détails de l'absence
                        </a>
                        <?php if (canManageAbsences()): ?>
                        <a href="modifier_absence.php?id=<?= $absence['id'] ?>" class="btn btn-outline ml-2">
                            <i class="fas fa-edit"></i> Modifier l'absence
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="alert alert-info mt-4">
            <i class="fas fa-info-circle"></i>
            <span>Aucune absence spécifique n'est associée à ce justificatif.</span>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="form-actions">
        <a href="justificatifs.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Retour à la liste
        </a>
        <?php if (!$justificatif['traite']): ?>
        <a href="traiter_justificatif.php?id=<?= $id_justificatif ?>" class="btn btn-primary">
            <i class="fas fa-check-circle"></i> Traiter ce justificatif
        </a>
        <?php endif; ?>
    </div>
</div>

<?php
// Inclure le pied de page
include 'includes/footer.php';
ob_end_flush();
?>
