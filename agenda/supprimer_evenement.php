<?php
ob_start();

include 'includes/db.php';
include 'includes/auth.php';

if (!isLoggedIn()) {
    header('Location: ../login/public/login.php');
    exit;
}

$user = getCurrentUser();
$user_fullname = getUserFullName();
$user_role = getUserRole();
$user_initials = strtoupper(substr($user['prenom'], 0, 1) . substr($user['nom'], 0, 1));

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: agenda.php');
    exit;
}

$id = $_GET['id'];

try {
    $stmt = $pdo->prepare('SELECT * FROM evenements WHERE id = ?');
    $stmt->execute([$id]);
    $evenement = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$evenement) {
        header('Location: agenda.php');
        exit;
    }

    $can_delete = false;

    if (isAdmin() || isVieScolaire()) {
        $can_delete = true;
    } 
    elseif (isTeacher() && $evenement['createur'] === $user_fullname) {
        $can_delete = true;
    }

    if (!$can_delete) {
        header('Location: details_evenement.php?id=' . $id);
        exit;
    }

    $date_debut = new DateTime($evenement['date_debut']);
    $date_fin = new DateTime($evenement['date_fin']);
    $format_date = 'd/m/Y';
    $format_heure = 'H:i';

    // Types d'événements
    $types_evenements = [
        'cours' => ['nom' => 'Cours', 'icone' => 'book', 'couleur' => '#00843d'],
        'devoirs' => ['nom' => 'Devoirs', 'icone' => 'pencil', 'couleur' => '#4285f4'],
        'reunion' => ['nom' => 'Réunion', 'icone' => 'users', 'couleur' => '#ff9800'],
        'examen' => ['nom' => 'Examen', 'icone' => 'file-text', 'couleur' => '#f44336'],
        'sortie' => ['nom' => 'Sortie scolaire', 'icone' => 'map-pin', 'couleur' => '#00c853'],
        'autre' => ['nom' => 'Autre', 'icone' => 'calendar', 'couleur' => '#9e9e9e']
    ];
    
    // Récupérer les informations du type d'événement
    $type_info = isset($types_evenements[$evenement['type_evenement']]) 
              ? $types_evenements[$evenement['type_evenement']] 
              : $types_evenements['autre'];
              
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération de l'événement ID=$id: " . $e->getMessage());
    $_SESSION['error_message'] = "Une erreur est survenue lors de l'accès à l'événement";
    header('Location: agenda.php?error=database_error');
    exit;
}

$pageTitle = "Supprimer l'événement";
include 'includes/header.php';
?>

<div class="calendar-navigation">
    <a href="details_evenement.php?id=<?= htmlspecialchars($id) ?>" class="back-button">
        <span class="back-icon">
            <i class="fas fa-arrow-left"></i>
        </span>
        Retour aux détails
    </a>
</div>

<div class="event-delete-container">
    <div class="event-delete-header">
        <h1>Supprimer l'événement</h1>
    </div>
    
    <div class="event-delete-body">
        <?php if (!$deleted): ?>
            <div class="event-summary">
                <h2><?= htmlspecialchars($evenement['titre']) ?></h2>
                <div class="event-summary-detail">
                    <div class="detail-content">
                        <span class="event-type-badge" style="background-color: <?= $type_info['couleur'] ?>;">
                            <i class="fas fa-<?= $type_info['icone'] ?>"></i>
                            <?= $type_info['nom'] ?>
                        </span>
                    </div>
                </div>
                
                <div class="event-summary-detail">
                    <div class="detail-icon">
                        <i class="far fa-calendar-alt"></i>
                    </div>
                    <div class="detail-content">
                        <?php if ($date_debut->format('Y-m-d') === $date_fin->format('Y-m-d')): ?>
                            Le <?= $date_debut->format($format_date) ?> de <?= $date_debut->format($format_heure) ?> à <?= $date_fin->format($format_heure) ?>
                        <?php else: ?>
                            Du <?= $date_debut->format($format_date) ?> à <?= $date_debut->format($format_heure) ?> 
                            au <?= $date_fin->format($format_date) ?> à <?= $date_fin->format($format_heure) ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (!empty($evenement['lieu'])): ?>
                <div class="event-summary-detail">
                    <div class="detail-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <div class="detail-content">
                        <?= htmlspecialchars($evenement['lieu']) ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="event-delete-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <p>Attention : cette action est irréversible. L'événement sera définitivement supprimé.</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert-message alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>
            
            <form method="post" class="delete-form">
                <input type="hidden" name="csrf_token" value="<?= $token ?>">
                <input type="hidden" name="confirmer" value="1">
                
                <div class="form-actions">
                    <a href="details_evenement.php?id=<?= htmlspecialchars($id) ?>" class="btn btn-secondary">Annuler</a>
                    <button type="submit" class="btn btn-danger">Confirmer la suppression</button>
                </div>
            </form>
        <?php else: ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                <p>L'événement a été supprimé avec succès.</p>
                <a href="agenda.php" class="btn btn-primary">Retour à l'agenda</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
include 'includes/footer.php';
ob_end_flush();
?>