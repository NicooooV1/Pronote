<?php
/**
 * M30 – Clubs — Détail + inscription
 */
$pageTitle = 'Détail club';
require_once __DIR__ . '/includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$club = $clubService->getClub($id);
if (!$club) { header('Location: clubs.php'); exit; }

$membres = $clubService->getMembres($id);
$demandes = $clubService->getDemandes($id);
$isGestionnaire = isAdmin() || isPersonnelVS() || (isProfesseur() && $club['responsable_id'] == getUserId());
$cats = ClubService::categories();

// Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'inscrire' && isEleve()) {
        try {
            $clubService->inscrire($id, getUserId());
            $_SESSION['success_message'] = 'Demande d\'inscription envoyée !';
        } catch (RuntimeException $e) {
            $_SESSION['error_message'] = $e->getMessage();
        }
    } elseif ($action === 'accepter' && $isGestionnaire) {
        $clubService->traiterDemande((int)$_POST['inscription_id'], 'accepte');
    } elseif ($action === 'refuser' && $isGestionnaire) {
        $clubService->traiterDemande((int)$_POST['inscription_id'], 'refuse');
    } elseif ($action === 'retirer' && $isGestionnaire) {
        $clubService->desinscrire((int)$_POST['inscription_id']);
    }
    header('Location: detail.php?id=' . $id);
    exit;
}
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-<?= ClubService::iconeCategorie($club['categorie']) ?>"></i> <?= htmlspecialchars($club['nom']) ?></h1>
        <a href="clubs.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>

    <?php if (!empty($_SESSION['success_message'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success_message'] ?></div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    <?php if (!empty($_SESSION['error_message'])): ?>
        <div class="alert alert-danger"><?= $_SESSION['error_message'] ?></div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <div class="club-detail-header">
        <div class="club-icon-lg cat-<?= $club['categorie'] ?>">
            <i class="fas fa-<?= ClubService::iconeCategorie($club['categorie']) ?>"></i>
        </div>
        <div>
            <span class="club-cat"><?= $cats[$club['categorie']] ?? '' ?></span>
            <?php if ($club['description']): ?><p><?= nl2br(htmlspecialchars($club['description'])) ?></p><?php endif; ?>
        </div>
    </div>

    <div class="info-grid">
        <?php if ($club['responsable_nom']): ?><div class="info-item"><i class="fas fa-user-tie"></i><span>Responsable: <?= htmlspecialchars($club['responsable_nom']) ?></span></div><?php endif; ?>
        <?php if ($club['horaires']): ?><div class="info-item"><i class="fas fa-clock"></i><span><?= htmlspecialchars($club['horaires']) ?></span></div><?php endif; ?>
        <?php if ($club['lieu']): ?><div class="info-item"><i class="fas fa-map-marker-alt"></i><span><?= htmlspecialchars($club['lieu']) ?></span></div><?php endif; ?>
        <div class="info-item"><i class="fas fa-users"></i><span><?= $club['nb_inscrits'] ?><?= $club['places_max'] ? ' / ' . $club['places_max'] . ' places' : ' membres' ?></span></div>
    </div>

    <?php if (isEleve()): ?>
    <div class="inscription-action">
        <form method="post">
            <?= csrfField() ?>
            <button name="action" value="inscrire" class="btn btn-primary btn-lg"><i class="fas fa-hand-point-up"></i> S'inscrire</button>
        </form>
    </div>
    <?php endif; ?>

    <!-- Demandes en attente -->
    <?php if ($isGestionnaire && !empty($demandes)): ?>
    <div class="card">
        <div class="card-header"><h2>Demandes en attente (<?= count($demandes) ?>)</h2></div>
        <div class="card-body">
            <?php foreach ($demandes as $d): ?>
            <div class="member-item">
                <span><?= htmlspecialchars($d['prenom'] . ' ' . $d['eleve_nom']) ?></span>
                <span class="text-muted"><?= formatDate($d['date_inscription']) ?></span>
                <form method="post" style="display:inline;">
                    <?= csrfField() ?>
                    <input type="hidden" name="inscription_id" value="<?= $d['id'] ?>">
                    <button name="action" value="accepter" class="btn btn-sm btn-success"><i class="fas fa-check"></i></button>
                    <button name="action" value="refuser" class="btn btn-sm btn-danger"><i class="fas fa-times"></i></button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Membres -->
    <div class="card">
        <div class="card-header"><h2>Membres (<?= count($membres) ?>)</h2></div>
        <div class="card-body">
            <?php if (empty($membres)): ?>
                <p class="text-muted">Aucun membre pour l'instant.</p>
            <?php else: ?>
            <div class="members-list">
                <?php foreach ($membres as $m): ?>
                <div class="member-item">
                    <div class="member-avatar"><?= strtoupper(substr($m['prenom'], 0, 1) . substr($m['eleve_nom'], 0, 1)) ?></div>
                    <div class="member-info">
                        <strong><?= htmlspecialchars($m['prenom'] . ' ' . $m['eleve_nom']) ?></strong>
                        <span><?= htmlspecialchars($m['classe_nom'] ?? '') ?></span>
                    </div>
                    <?php if ($isGestionnaire): ?>
                    <form method="post" style="display:inline;">
                        <?= csrfField() ?>
                        <input type="hidden" name="inscription_id" value="<?= $m['id'] ?>">
                        <button name="action" value="retirer" class="btn btn-sm btn-outline" onclick="return confirm('Retirer ?')"><i class="fas fa-user-minus"></i></button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
