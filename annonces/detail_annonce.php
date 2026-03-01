<?php
/**
 * M11 – Annonces : Détail d'une annonce (+ sondage / vote)
 */

require_once __DIR__ . '/includes/AnnonceService.php';

$pageTitle = 'Détail annonce';
$currentPage = 'annonces';
require_once __DIR__ . '/includes/header.php';
requireAuth();

$pdo = getPDO();
$service = new AnnonceService($pdo);
$user = getCurrentUser();
$role = getUserRole();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    echo '<div class="alert alert-danger">Aucune annonce spécifiée.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$annonce = $service->getAnnonce($id);
if (!$annonce) {
    echo '<div class="alert alert-danger">Annonce introuvable.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Marquer comme lue
$service->marquerLue($id, $user['id'], $role);

// Traitement du vote
$success = '';
$error = '';
$sondage = ($annonce['type'] === 'sondage') ? $service->getSondage($id) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'voter' && $sondage) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Session expirée.';
    } else {
        // Vérifier que le sondage est ouvert
        if ($sondage['date_fin'] && strtotime($sondage['date_fin']) <= time()) {
            $error = 'Ce sondage est terminé.';
        } else {
            try {
                if ($sondage['type_reponse'] === 'texte_libre') {
                    $service->voter($sondage['id'], $user['id'], $role, null, $_POST['texte_libre'] ?? '');
                } elseif ($sondage['type_reponse'] === 'choix_multiple') {
                    // Supprimer anciens votes et recréer
                    $del = $pdo->prepare("DELETE FROM sondage_votes WHERE sondage_id = ? AND user_id = ? AND user_type = ?");
                    $del->execute([$sondage['id'], $user['id'], $role]);
                    foreach ($_POST['options'] ?? [] as $optId) {
                        $service->voter($sondage['id'], $user['id'], $role, (int)$optId);
                    }
                } else {
                    $service->voter($sondage['id'], $user['id'], $role, (int)($_POST['option_id'] ?? 0));
                }
                $success = 'Vote enregistré !';
                // Recharger le sondage
                $sondage = $service->getSondage($id);
            } catch (Exception $e) {
                $error = 'Erreur : ' . $e->getMessage();
            }
        }
    }
}

$aVote = $sondage ? $service->aVote($sondage['id'], $user['id'], $role) : false;
$types = AnnonceService::getTypes();
?>

<a href="annonces.php" class="btn btn-sm btn-secondary" style="margin-bottom:1rem;">
    <i class="fas fa-arrow-left"></i> Retour aux annonces
</a>

<?php if ($success): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><i class="fas fa-times-circle"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<article class="annonce-detail card">
    <div class="annonce-detail-header">
        <span class="badge <?= AnnonceService::getTypeBadgeClass($annonce['type']) ?>">
            <?= htmlspecialchars($types[$annonce['type']] ?? $annonce['type']) ?>
        </span>
        <?php if ($annonce['epingle']): ?>
        <span class="badge badge-pin"><i class="fas fa-thumbtack"></i> Épinglée</span>
        <?php endif; ?>
        <span class="annonce-detail-date">
            Publiée le <?= date('d/m/Y à H:i', strtotime($annonce['date_publication'])) ?>
        </span>
    </div>

    <h1 class="annonce-detail-titre"><?= htmlspecialchars($annonce['titre']) ?></h1>

    <div class="annonce-detail-contenu">
        <?= nl2br(htmlspecialchars($annonce['contenu'])) ?>
    </div>

    <?php if ($annonce['date_expiration']): ?>
    <div class="annonce-detail-expiration">
        <i class="fas fa-hourglass-half"></i> 
        Expire le <?= date('d/m/Y à H:i', strtotime($annonce['date_expiration'])) ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($annonce['cible_roles'])): ?>
    <div class="annonce-detail-cible">
        <i class="fas fa-crosshairs"></i> Ciblée pour : 
        <?= implode(', ', array_map('ucfirst', $annonce['cible_roles'])) ?>
    </div>
    <?php endif; ?>
</article>

<!-- Sondage -->
<?php if ($sondage): ?>
<div class="card sondage-card">
    <h2 class="sondage-titre"><i class="fas fa-poll"></i> <?= htmlspecialchars($sondage['question']) ?></h2>
    
    <div class="sondage-info">
        <span><i class="fas fa-users"></i> <?= $sondage['total_votants'] ?> votant(s)</span>
        <?php if ($sondage['date_fin']): ?>
        <span><i class="fas fa-clock"></i> 
            <?php if (strtotime($sondage['date_fin']) > time()): ?>
                Ouvert jusqu'au <?= date('d/m/Y à H:i', strtotime($sondage['date_fin'])) ?>
            <?php else: ?>
                <strong>Sondage terminé</strong>
            <?php endif; ?>
        </span>
        <?php endif; ?>
        <?php if ($sondage['anonyme']): ?>
        <span><i class="fas fa-user-secret"></i> Anonyme</span>
        <?php endif; ?>
    </div>

    <?php 
    $sondageOuvert = !$sondage['date_fin'] || strtotime($sondage['date_fin']) > time();
    $showResults = $aVote || !$sondageOuvert || isAdmin();
    ?>

    <?php if (!$aVote && $sondageOuvert): ?>
    <!-- Formulaire de vote -->
    <form method="POST" class="sondage-vote-form">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="voter">

        <?php if ($sondage['type_reponse'] === 'texte_libre'): ?>
        <div class="form-group">
            <textarea name="texte_libre" class="form-control" rows="3" placeholder="Votre réponse..." required></textarea>
        </div>
        <?php else: ?>
        <div class="sondage-options">
            <?php foreach ($sondage['options'] as $opt): ?>
            <label class="sondage-option-label">
                <input type="<?= $sondage['type_reponse'] === 'choix_multiple' ? 'checkbox' : 'radio' ?>"
                       name="<?= $sondage['type_reponse'] === 'choix_multiple' ? 'options[]' : 'option_id' ?>"
                       value="<?= $opt['id'] ?>" required>
                <span class="sondage-option-text"><?= htmlspecialchars($opt['label']) ?></span>
            </label>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <button type="submit" class="btn btn-primary"><i class="fas fa-vote-yea"></i> Voter</button>
    </form>
    <?php endif; ?>

    <?php if ($showResults && $sondage['type_reponse'] !== 'texte_libre'): ?>
    <!-- Résultats -->
    <div class="sondage-resultats">
        <h3>Résultats</h3>
        <?php foreach ($sondage['options'] as $opt): 
            $pct = $sondage['total_votants'] > 0 ? round(($opt['nb_votes'] / max(1, $sondage['total_votants'])) * 100, 1) : 0;
        ?>
        <div class="sondage-result-row">
            <div class="sondage-result-label">
                <?= htmlspecialchars($opt['label']) ?>
                <span class="sondage-result-count"><?= $opt['nb_votes'] ?> vote(s) — <?= $pct ?>%</span>
            </div>
            <div class="sondage-result-bar">
                <div class="sondage-result-fill" style="width: <?= $pct ?>%;"></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Actions admin -->
<?php if (isAdmin() || ($annonce['auteur_id'] == $user['id'] && $annonce['auteur_type'] === $role)): ?>
<div class="form-actions">
    <a href="modifier_annonce.php?id=<?= $id ?>" class="btn btn-secondary"><i class="fas fa-edit"></i> Modifier</a>
    <form method="POST" action="supprimer_annonce.php" style="display:inline;" 
          onsubmit="return confirm('Supprimer cette annonce ?');">
        <?= csrfField() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <button type="submit" class="btn btn-danger"><i class="fas fa-trash"></i> Supprimer</button>
    </form>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
