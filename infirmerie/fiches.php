<?php
/**
 * M31 – Fiches santé — Liste et édition
 */
$pageTitle = 'Fiches santé';
$activePage = 'fiches';
require_once __DIR__ . '/includes/header.php';

if (!isAdmin() && !isPersonnelVS()) { redirect('/infirmerie/infirmerie.php'); }

$recherche = $_GET['q'] ?? '';
$fiches = $infirmerieService->getFiches($recherche ?: null);
$groupes = InfirmerieService::groupesSanguins();
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-folder-open"></i> Fiches santé</h1>
    </div>

    <form class="filter-bar" method="get">
        <input type="text" name="q" class="form-control" placeholder="Rechercher un élève…" value="<?= htmlspecialchars($recherche) ?>">
        <button class="btn btn-primary"><i class="fas fa-search"></i></button>
    </form>

    <?php if (empty($fiches)): ?>
        <div class="empty-state"><i class="fas fa-notes-medical"></i><p>Aucune fiche santé trouvée.</p></div>
    <?php else: ?>
    <div class="fiches-list">
        <?php foreach ($fiches as $f): ?>
        <div class="fiche-item">
            <div class="fiche-avatar"><?= strtoupper(substr($f['prenom'], 0, 1) . substr($f['eleve_nom'], 0, 1)) ?></div>
            <div class="fiche-info">
                <strong><?= htmlspecialchars($f['prenom'] . ' ' . $f['eleve_nom']) ?></strong>
                <span class="text-muted"><?= htmlspecialchars($f['classe_nom'] ?? '') ?></span>
            </div>
            <div class="fiche-tags">
                <?php if ($f['allergies']): ?><span class="badge badge-danger"><i class="fas fa-exclamation-triangle"></i> Allergies</span><?php endif; ?>
                <?php if ($f['pai']): ?><span class="badge badge-warning">PAI</span><?php endif; ?>
                <?php if ($f['groupe_sanguin']): ?><span class="badge badge-info"><?= htmlspecialchars($f['groupe_sanguin']) ?></span><?php endif; ?>
            </div>
            <a href="fiche_sante.php?eleve=<?= $f['eleve_id'] ?>" class="btn btn-sm btn-outline"><i class="fas fa-eye"></i> Voir</a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
