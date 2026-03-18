<?php
/**
 * Bulletins — Page principale
 * Vue différente selon le rôle : élève voit ses bulletins, prof/admin gère par classe
 */
require_once __DIR__ . '/includes/BulletinService.php';
$currentPage = 'liste';
$pageTitle = 'Bulletins scolaires';
require_once __DIR__ . '/includes/header.php';
requireAuth();

$pdo = getPDO();
$service = new BulletinService($pdo);
$periodes = $service->getPeriodes();
$selectedPeriode = (int)($_GET['periode'] ?? 0);

if (!$selectedPeriode && !empty($periodes)) {
    $courante = $service->getPeriodeCourante();
    $selectedPeriode = $courante ? $courante['id'] : $periodes[0]['id'];
}
?>

<div class="page-header">
    <h1><i class="fas fa-file-alt"></i> Bulletins scolaires</h1>
    <div class="header-actions">
        <div class="periode-selector">
            <?php foreach ($periodes as $p): ?>
                <a href="?periode=<?= $p['id'] ?>" class="btn btn-sm <?= $selectedPeriode == $p['id'] ? 'btn-primary' : 'btn-outline' ?>"><?= htmlspecialchars($p['nom']) ?></a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php if ($user_role === 'eleve'): ?>
    <?php
    $bulletin = $service->getBulletinEleve($user['id'], $selectedPeriode);
    if ($bulletin && in_array($bulletin['statut'], ['publie', 'valide'])):
        $lignes = $service->getLignesMatieres($bulletin['id']);
    ?>
    <div class="bulletin-view">
        <div class="bulletin-header-card">
            <div class="bulletin-meta">
                <h2><?= htmlspecialchars($user_fullname) ?></h2>
                <span class="badge badge-info"><?= htmlspecialchars($user['classe'] ?? '') ?></span>
                <span class="text-muted"><?= htmlspecialchars($periodes[array_search($selectedPeriode, array_column($periodes, 'id'))]['nom'] ?? '') ?></span>
            </div>
            <div class="bulletin-summary">
                <div class="summary-item primary"><span class="value"><?= $bulletin['moyenne_generale'] !== null ? number_format($bulletin['moyenne_generale'], 2) : '-' ?></span><span class="label">Moyenne générale</span></div>
                <div class="summary-item info"><span class="value"><?= $bulletin['rang'] ?? '-' ?></span><span class="label">Rang</span></div>
                <div class="summary-item warning"><span class="value"><?= $bulletin['nb_absences'] ?></span><span class="label">Absences</span></div>
                <div class="summary-item danger"><span class="value"><?= $bulletin['nb_retards'] ?></span><span class="label">Retards</span></div>
            </div>
        </div>

        <?php if ($bulletin['avis_conseil'] && $bulletin['avis_conseil'] !== 'aucun'): ?>
            <div class="avis-conseil">
                <i class="fas fa-award"></i>
                <?= htmlspecialchars(BulletinService::avisLabels()[$bulletin['avis_conseil']] ?? $bulletin['avis_conseil']) ?>
            </div>
        <?php endif; ?>

        <table class="bulletin-table">
            <thead>
                <tr>
                    <th>Matière</th>
                    <th>Professeur</th>
                    <th class="text-center">Moyenne</th>
                    <th class="text-center">Classe</th>
                    <th class="text-center">Min</th>
                    <th class="text-center">Max</th>
                    <th>Appréciation</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lignes as $l): ?>
                <tr>
                    <td><span class="matiere-dot" style="background:<?= htmlspecialchars($l['couleur'] ?? '#3498db') ?>"></span><?= htmlspecialchars($l['matiere_nom']) ?></td>
                    <td class="text-muted"><?= htmlspecialchars($l['professeur_nom']) ?></td>
                    <td class="text-center fw-bold"><?= $l['moyenne_eleve'] !== null ? number_format($l['moyenne_eleve'], 2) : '-' ?></td>
                    <td class="text-center"><?= $l['moyenne_classe'] !== null ? number_format($l['moyenne_classe'], 2) : '-' ?></td>
                    <td class="text-center text-muted"><?= $l['moyenne_min'] !== null ? number_format($l['moyenne_min'], 2) : '-' ?></td>
                    <td class="text-center text-muted"><?= $l['moyenne_max'] !== null ? number_format($l['moyenne_max'], 2) : '-' ?></td>
                    <td class="appreciation-cell"><?= htmlspecialchars($l['appreciation'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if (!empty($bulletin['appreciation_generale'])): ?>
        <div class="appreciation-generale">
            <h4>Appréciation générale</h4>
            <p><?= nl2br(htmlspecialchars($bulletin['appreciation_generale'])) ?></p>
        </div>
        <?php endif; ?>
    </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-file-alt"></i>
            <p>Aucun bulletin disponible pour cette période.</p>
        </div>
    <?php endif; ?>

<?php elseif ($user_role === 'parent'): ?>
    <?php
    $stmt = $pdo->prepare("SELECT e.* FROM parent_eleve pe JOIN eleves e ON pe.id_eleve = e.id WHERE pe.id_parent = ?");
    $stmt->execute([$user['id']]);
    $enfants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $selectedEnfant = (int)($_GET['eleve'] ?? ($enfants[0]['id'] ?? 0));
    ?>
    <div class="enfant-tabs">
        <?php foreach ($enfants as $enf): ?>
            <a href="?periode=<?= $selectedPeriode ?>&eleve=<?= $enf['id'] ?>" class="btn btn-sm <?= $selectedEnfant == $enf['id'] ? 'btn-primary' : 'btn-outline' ?>">
                <?= htmlspecialchars($enf['prenom'] . ' ' . $enf['nom']) ?>
            </a>
        <?php endforeach; ?>
    </div>
    <?php
    if ($selectedEnfant) {
        $bulletin = $service->getBulletinEleve($selectedEnfant, $selectedPeriode);
        if ($bulletin && in_array($bulletin['statut'], ['publie'])):
            $lignes = $service->getLignesMatieres($bulletin['id']);
    ?>
    <div class="bulletin-view">
        <table class="bulletin-table">
            <thead>
                <tr><th>Matière</th><th>Professeur</th><th class="text-center">Moyenne</th><th class="text-center">Classe</th><th>Appréciation</th></tr>
            </thead>
            <tbody>
                <?php foreach ($lignes as $l): ?>
                <tr>
                    <td><span class="matiere-dot" style="background:<?= htmlspecialchars($l['couleur'] ?? '#3498db') ?>"></span><?= htmlspecialchars($l['matiere_nom']) ?></td>
                    <td class="text-muted"><?= htmlspecialchars($l['professeur_nom']) ?></td>
                    <td class="text-center fw-bold"><?= $l['moyenne_eleve'] !== null ? number_format($l['moyenne_eleve'], 2) : '-' ?></td>
                    <td class="text-center"><?= $l['moyenne_classe'] !== null ? number_format($l['moyenne_classe'], 2) : '-' ?></td>
                    <td><?= htmlspecialchars($l['appreciation'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (!empty($bulletin['appreciation_generale'])): ?>
        <div class="appreciation-generale"><h4>Appréciation générale</h4><p><?= nl2br(htmlspecialchars($bulletin['appreciation_generale'])) ?></p></div>
        <?php endif; ?>
    </div>
    <?php else: ?>
        <div class="empty-state"><i class="fas fa-file-alt"></i><p>Aucun bulletin publié pour cette période.</p></div>
    <?php endif;
    }
    ?>

<?php else: /* admin / prof / vie_scolaire */ ?>
    <?php
    $classes = $service->getClasses();
    $filterClasse = (int)($_GET['classe'] ?? ($classes[0]['id'] ?? 0));

    // DataTable paginé pour la vue admin
    $dt = new \API\Core\DataTable($pdo, 'bulletins');
    $dt->setSelect('bulletins.*, e.nom AS eleve_nom, e.prenom AS eleve_prenom');
    $dt->setColumns(['statut', 'avis_conseil']);
    $dt->setSearchable(['e.nom', 'e.prenom']);
    $dt->setSortable(['eleve_nom', 'moyenne_generale', 'rang', 'nb_absences', 'statut']);
    $dt->setJoins('JOIN eleves e ON bulletins.eleve_id = e.id');
    if ($filterClasse) {
        $dt->addWhere('bulletins.classe_id = ?', [$filterClasse]);
    }
    if ($selectedPeriode) {
        $dt->addWhere('bulletins.periode_id = ?', [$selectedPeriode]);
    }
    // Filtre statut optionnel
    $filterStatut = $_GET['statut'] ?? '';
    if ($filterStatut && in_array($filterStatut, ['brouillon', 'valide', 'publie'])) {
        $dt->addWhere('bulletins.statut = ?', [$filterStatut]);
    }
    $dt->setDefaultPerPage(30);
    $dtResult = $dt->fetch($_GET);
    $bulletins = $dtResult['data'];
    $stats = $filterClasse ? $service->getStatsClasse($filterClasse, $selectedPeriode) : [];
    ?>
    <div class="filter-bar">
        <select id="classeSelect" onchange="window.location='?periode=<?= $selectedPeriode ?>&classe='+this.value" class="form-select">
            <?php foreach ($classes as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $filterClasse == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nom']) ?></option>
            <?php endforeach; ?>
        </select>
        <select onchange="window.location='?periode=<?= $selectedPeriode ?>&classe=<?= $filterClasse ?>&statut='+this.value" class="form-select" style="width:auto;">
            <option value="">Tous statuts</option>
            <option value="brouillon" <?= $filterStatut === 'brouillon' ? 'selected' : '' ?>>Brouillons</option>
            <option value="valide" <?= $filterStatut === 'valide' ? 'selected' : '' ?>>Validés</option>
            <option value="publie" <?= $filterStatut === 'publie' ? 'selected' : '' ?>>Publiés</option>
        </select>
        <?php if (isAdmin() || isVieScolaire()): ?>
        <a href="generer.php?classe=<?= $filterClasse ?>&periode=<?= $selectedPeriode ?>" class="btn btn-primary"><i class="fas fa-sync"></i> Générer</a>
        <?php endif; ?>
    </div>

    <?php if (!empty($stats) && $stats['total'] > 0): ?>
    <div class="stats-row">
        <div class="stat-card"><span class="stat-value"><?= $stats['total'] ?></span><span class="stat-label">Bulletins</span></div>
        <div class="stat-card primary"><span class="stat-value"><?= $stats['moy_classe'] ? number_format($stats['moy_classe'], 2) : '-' ?></span><span class="stat-label">Moyenne classe</span></div>
        <div class="stat-card success"><span class="stat-value"><?= $stats['publies'] ?></span><span class="stat-label">Publiés</span></div>
        <div class="stat-card warning"><span class="stat-value"><?= $stats['brouillons'] ?></span><span class="stat-label">Brouillons</span></div>
    </div>
    <?php endif; ?>

    <div class="data-table-container">
        <div class="dt-toolbar">
            <?= \API\Core\DataTable::renderSearchBar($dtResult, 'Rechercher un élève…') ?>
            <?= \API\Core\DataTable::renderPerPageSelector($dtResult, [20, 30, 50]) ?>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th><?= \API\Core\DataTable::renderSortHeader('Élève', 'eleve_nom', $dtResult) ?></th>
                    <th class="text-center"><?= \API\Core\DataTable::renderSortHeader('Moyenne', 'moyenne_generale', $dtResult) ?></th>
                    <th class="text-center"><?= \API\Core\DataTable::renderSortHeader('Rang', 'rang', $dtResult) ?></th>
                    <th class="text-center"><?= \API\Core\DataTable::renderSortHeader('Absences', 'nb_absences', $dtResult) ?></th>
                    <th class="text-center">Avis</th>
                    <th class="text-center"><?= \API\Core\DataTable::renderSortHeader('Statut', 'statut', $dtResult) ?></th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($bulletins)): ?>
                    <tr><td colspan="7" class="text-center text-muted">Aucun bulletin généré. <a href="generer.php?classe=<?= $filterClasse ?>&periode=<?= $selectedPeriode ?>">Générer les bulletins</a></td></tr>
                <?php else: ?>
                    <?php foreach ($bulletins as $b): ?>
                    <tr>
                        <td class="fw-500"><?= htmlspecialchars($b['eleve_prenom'] . ' ' . $b['eleve_nom']) ?></td>
                        <td class="text-center fw-bold"><?= $b['moyenne_generale'] !== null ? number_format($b['moyenne_generale'], 2) : '-' ?></td>
                        <td class="text-center"><?= $b['rang'] ?? '-' ?></td>
                        <td class="text-center"><?= $b['nb_absences'] ?></td>
                        <td class="text-center"><?= $b['avis_conseil'] !== 'aucun' ? htmlspecialchars(BulletinService::avisLabels()[$b['avis_conseil']] ?? '') : '-' ?></td>
                        <td class="text-center"><?= BulletinService::statutBadge($b['statut']) ?></td>
                        <td>
                            <a href="detail_bulletin.php?id=<?= $b['id'] ?>" class="btn btn-sm btn-outline" title="Voir"><i class="fas fa-eye"></i></a>
                            <a href="editer_bulletin.php?id=<?= $b['id'] ?>" class="btn btn-sm btn-outline" title="Éditer"><i class="fas fa-edit"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?= \API\Core\DataTable::renderPagination($dtResult) ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
