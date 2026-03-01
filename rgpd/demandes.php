<?php
/**
 * M23 – Gestion des demandes RGPD (admin)
 */
$pageTitle = 'Demandes RGPD';
$activePage = 'demandes';
require_once __DIR__ . '/includes/header.php';

if (!isAdmin()) { redirect('/accueil/accueil.php'); }

$filtreStatut = $_GET['statut'] ?? '';
$demandes = $rgpdService->getDemandes($filtreStatut ? ['statut' => $filtreStatut] : []);
$typesDemande = AuditRgpdService::typesDemande();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken()) {
    $id = (int)$_POST['demande_id'];
    $statut = $_POST['statut'];
    $reponse = trim($_POST['reponse'] ?? '');
    $rgpdService->traiterDemande($id, $statut, $reponse, getUserId());
    $_SESSION['success_message'] = 'Demande mise à jour.';
    header('Location: demandes.php');
    exit;
}
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-file-contract"></i> Demandes RGPD</h1>
    </div>

    <?php if (!empty($_SESSION['success_message'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success_message'] ?></div><?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <div class="filter-bar">
        <a href="demandes.php" class="btn <?= !$filtreStatut ? 'btn-primary' : 'btn-outline' ?>">Toutes</a>
        <a href="demandes.php?statut=en_attente" class="btn <?= $filtreStatut === 'en_attente' ? 'btn-primary' : 'btn-outline' ?>">En attente</a>
        <a href="demandes.php?statut=en_cours" class="btn <?= $filtreStatut === 'en_cours' ? 'btn-primary' : 'btn-outline' ?>">En cours</a>
        <a href="demandes.php?statut=traitee" class="btn <?= $filtreStatut === 'traitee' ? 'btn-primary' : 'btn-outline' ?>">Traitées</a>
    </div>

    <?php if (empty($demandes)): ?>
        <div class="empty-state"><i class="fas fa-file-contract"></i><p>Aucune demande.</p></div>
    <?php else: ?>
    <?php foreach ($demandes as $d): ?>
    <div class="card demande-card">
        <div class="card-header">
            <div>
                <strong><?= htmlspecialchars($d['demandeur_nom'] ?? 'Utilisateur #' . $d['user_id']) ?></strong>
                <span class="text-muted">(<?= $d['user_type'] ?>)</span>
                — <?= $typesDemande[$d['type_demande']] ?? $d['type_demande'] ?>
            </div>
            <div>
                <?= AuditRgpdService::statutBadge($d['statut']) ?>
                <span class="text-muted"><?= formatDate($d['date_demande']) ?></span>
            </div>
        </div>
        <div class="card-body">
            <p><?= nl2br(htmlspecialchars($d['description'])) ?></p>
            <?php if ($d['statut'] !== 'traitee' && $d['statut'] !== 'refusee'): ?>
            <form method="post" class="demande-form">
                <?= csrfField() ?>
                <input type="hidden" name="demande_id" value="<?= $d['id'] ?>">
                <div class="form-row">
                    <select name="statut" class="form-control">
                        <option value="en_cours">En cours</option>
                        <option value="traitee">Traitée</option>
                        <option value="refusee">Refusée</option>
                    </select>
                    <textarea name="reponse" class="form-control" rows="2" placeholder="Réponse…"><?= htmlspecialchars($d['reponse'] ?? '') ?></textarea>
                    <button class="btn btn-primary"><i class="fas fa-check"></i></button>
                </div>
            </form>
            <?php elseif ($d['reponse']): ?>
            <div class="demande-reponse"><strong>Réponse :</strong> <?= nl2br(htmlspecialchars($d['reponse'])) ?></div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
