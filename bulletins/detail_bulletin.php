<?php
/**
 * Bulletins — Détail d'un bulletin
 */
require_once __DIR__ . '/includes/BulletinService.php';
$currentPage = 'detail';
$pageTitle = 'Détail du bulletin';
require_once __DIR__ . '/includes/header.php';
requireAuth();

$pdo = getPDO();
$service = new BulletinService($pdo);
$id = (int)($_GET['id'] ?? 0);
$bulletin = $service->getBulletin($id);

if (!$bulletin) {
    echo '<div class="alert alert-error">Bulletin introuvable.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Vérifier accès : élève = son propre bulletin publié, parent = enfant publié, admin/prof = tout
if ($user_role === 'eleve' && ($bulletin['eleve_id'] != $user['id'] || !in_array($bulletin['statut'], ['publie','valide']))) {
    echo '<div class="alert alert-error">Accès refusé.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$lignes = $service->getLignesMatieres($id);
$avisLabels = BulletinService::avisLabels();
?>

<div class="page-header">
    <h1><i class="fas fa-file-alt"></i> Bulletin — <?= htmlspecialchars($bulletin['eleve_prenom'] . ' ' . $bulletin['eleve_nom']) ?></h1>
    <div class="header-actions">
        <?= BulletinService::statutBadge($bulletin['statut']) ?>
        <a href="bulletins.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Retour</a>
        <?php if (isAdmin() || isVieScolaire()): ?>
            <a href="editer_bulletin.php?id=<?= $id ?>" class="btn btn-primary"><i class="fas fa-edit"></i> Éditer</a>
        <?php endif; ?>
    </div>
</div>

<div class="bulletin-detail">
    <div class="bulletin-header-card">
        <div class="bulletin-meta">
            <h2><?= htmlspecialchars($bulletin['eleve_prenom'] . ' ' . $bulletin['eleve_nom']) ?></h2>
            <span class="badge badge-info"><?= htmlspecialchars($bulletin['classe_nom']) ?></span>
            <span class="text-muted"><?= htmlspecialchars($bulletin['periode_nom']) ?></span>
        </div>
        <div class="bulletin-summary">
            <div class="summary-item primary"><span class="value"><?= $bulletin['moyenne_generale'] !== null ? number_format($bulletin['moyenne_generale'], 2) : '-' ?>/20</span><span class="label">Moyenne</span></div>
            <div class="summary-item info"><span class="value"><?= $bulletin['rang'] ?? '-' ?></span><span class="label">Rang</span></div>
            <div class="summary-item warning"><span class="value"><?= $bulletin['nb_absences'] ?></span><span class="label">Absences</span></div>
            <div class="summary-item danger"><span class="value"><?= $bulletin['nb_retards'] ?></span><span class="label">Retards</span></div>
        </div>
    </div>

    <?php if ($bulletin['avis_conseil'] && $bulletin['avis_conseil'] !== 'aucun'): ?>
    <div class="avis-conseil avis-<?= $bulletin['avis_conseil'] ?>">
        <i class="fas fa-award"></i> <?= htmlspecialchars($avisLabels[$bulletin['avis_conseil']] ?? $bulletin['avis_conseil']) ?>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header"><h3>Résultats par matière</h3></div>
        <div class="card-body p-0">
            <table class="bulletin-table">
                <thead>
                    <tr>
                        <th>Matière</th>
                        <th>Professeur</th>
                        <th class="text-center">Élève</th>
                        <th class="text-center">Classe</th>
                        <th class="text-center">Min</th>
                        <th class="text-center">Max</th>
                        <th class="text-center">Coeff.</th>
                        <th>Appréciation</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lignes as $l): ?>
                    <tr>
                        <td>
                            <span class="matiere-dot" style="background:<?= htmlspecialchars($l['couleur'] ?? '#3498db') ?>"></span>
                            <?= htmlspecialchars($l['matiere_nom']) ?>
                        </td>
                        <td class="text-muted"><?= htmlspecialchars($l['professeur_nom']) ?></td>
                        <td class="text-center fw-bold <?= ($l['moyenne_eleve'] ?? 0) < 10 ? 'text-danger' : 'text-success' ?>"><?= $l['moyenne_eleve'] !== null ? number_format($l['moyenne_eleve'], 2) : '-' ?></td>
                        <td class="text-center"><?= $l['moyenne_classe'] !== null ? number_format($l['moyenne_classe'], 2) : '-' ?></td>
                        <td class="text-center text-muted"><?= $l['moyenne_min'] !== null ? number_format($l['moyenne_min'], 2) : '-' ?></td>
                        <td class="text-center text-muted"><?= $l['moyenne_max'] !== null ? number_format($l['moyenne_max'], 2) : '-' ?></td>
                        <td class="text-center"><?= $l['coefficient'] ?></td>
                        <td class="appreciation-cell"><?= htmlspecialchars($l['appreciation'] ?? '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if (!empty($bulletin['appreciation_generale'])): ?>
    <div class="appreciation-generale">
        <h4><i class="fas fa-comment-alt"></i> Appréciation générale</h4>
        <p><?= nl2br(htmlspecialchars($bulletin['appreciation_generale'])) ?></p>
    </div>
    <?php endif; ?>

    <?php if (!empty($bulletin['appreciation_vie_scolaire'])): ?>
    <div class="appreciation-generale">
        <h4><i class="fas fa-user-shield"></i> Vie scolaire</h4>
        <p><?= nl2br(htmlspecialchars($bulletin['appreciation_vie_scolaire'])) ?></p>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
