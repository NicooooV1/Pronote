<?php
/**
 * Emploi du temps — Détection et gestion des conflits
 * Accessible uniquement par admin et vie scolaire.
 */
// Boot standardisé
$pageTitle  = 'Conflits EDT';
$activePage = 'emploi_du_temps';
require_once __DIR__ . '/../API/module_boot.php';

require_once __DIR__ . '/includes/EdtService.php';

if (!isAdmin() && !isVieScolaire()) {
    echo '<div class="alert alert-danger">Accès non autorisé.</div>';
    exit;
}

$service = new EdtService($pdo);
$conflits = $service->scanAllConflits();
$currentPage = 'conflits';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-exclamation-triangle"></i> Conflits d'emploi du temps</h1>
    <div class="header-actions">
        <a href="emploi_du_temps.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>
</div>

<?php if (empty($conflits)): ?>
    <div class="ds-alert ds-alert-success">
        <i class="fas fa-check-circle"></i>
        <strong>Aucun conflit détecté.</strong> L'emploi du temps est cohérent.
    </div>
<?php else: ?>
    <div class="ds-alert ds-alert-warning">
        <i class="fas fa-exclamation-triangle"></i>
        <strong><?= count($conflits) ?> conflit(s) détecté(s).</strong> Les cours suivants se chevauchent.
    </div>

    <div class="ds-card">
        <table class="ds-table">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Jour</th>
                    <th>Créneau</th>
                    <th>Description</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($conflits as $c): ?>
                <tr>
                    <td>
                        <span class="badge <?= $c['type'] === 'professeur' ? 'badge-warning' : 'badge-danger' ?>">
                            <i class="fas <?= $c['type'] === 'professeur' ? 'fa-user' : 'fa-door-open' ?>"></i>
                            <?= ucfirst($c['type']) ?>
                        </span>
                    </td>
                    <td><?= ucfirst($c['jour']) ?></td>
                    <td><?= htmlspecialchars($c['creneau']) ?></td>
                    <td><?= htmlspecialchars($c['description']) ?></td>
                    <td>
                        <a href="gerer_cours.php?id=<?= $c['cours1_id'] ?>" class="btn btn-sm btn-outline" title="Modifier cours 1"><i class="fas fa-edit"></i> #<?= $c['cours1_id'] ?></a>
                        <a href="gerer_cours.php?id=<?= $c['cours2_id'] ?>" class="btn btn-sm btn-outline" title="Modifier cours 2"><i class="fas fa-edit"></i> #<?= $c['cours2_id'] ?></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
