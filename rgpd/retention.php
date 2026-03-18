<?php
/**
 * M23 – RGPD — Politiques de rétention (admin)
 * Configuration des durées de conservation et purge automatique
 */
$pageTitle = 'Rétention des données';
$activePage = 'retention';
require_once __DIR__ . '/includes/header.php';

if (!isAdmin()) { redirect('/accueil/accueil.php'); }

$message = '';
$messageType = '';

// Sauvegarder les politiques
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_policies') {
        $policies = $_POST['policy'] ?? [];
        foreach ($policies as $table => $cfg) {
            $duree = max(7, (int)($cfg['duree'] ?? 90));
            $actif = isset($cfg['actif']);
            $rgpdService->sauvegarderRetentionPolicy($table, $duree, $actif);
        }
        $message = 'Politiques de rétention mises à jour.';
        $messageType = 'success';
    }

    if ($action === 'execute_purge') {
        $results = $rgpdService->executerPurge();
        $totalPurged = 0;
        foreach ($results as $r) {
            $totalPurged += $r['purged'] ?? 0;
        }
        $message = "Purge exécutée : {$totalPurged} enregistrement(s) supprimé(s).";
        $messageType = 'info';
    }
}

$policies = $rgpdService->getRetentionPolicies();
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-database"></i> Rétention des données</h1>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="rgpd-info-banner">
        <i class="fas fa-info-circle"></i>
        <p>
            Conformément au RGPD, les données personnelles ne doivent pas être conservées au-delà de la durée nécessaire.
            Configurez ci-dessous les durées de rétention et lancez la purge manuellement ou via un cron.
        </p>
    </div>

    <div class="card mb-4">
        <div class="card-header"><h2>Politiques de rétention</h2></div>
        <div class="card-body">
            <form method="post">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="save_policies">
                <table class="ds-table">
                    <thead>
                        <tr>
                            <th>Données</th>
                            <th>Durée (jours)</th>
                            <th>Actif</th>
                            <th>Dernière purge</th>
                            <th>Obligatoire</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($policies as $key => $p): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($p['label']) ?></strong>
                                <br><small class="text-muted"><?= htmlspecialchars($key) ?></small>
                            </td>
                            <td>
                                <input type="number" name="policy[<?= $key ?>][duree]" 
                                       value="<?= $p['duree'] ?>" min="7" max="3650" 
                                       class="form-control" style="width:100px"
                                       <?= $p['obligatoire'] ? 'readonly' : '' ?>>
                            </td>
                            <td>
                                <input type="checkbox" name="policy[<?= $key ?>][actif]" value="1" 
                                       <?= ($p['actif'] ?? true) ? 'checked' : '' ?>
                                       <?= $p['obligatoire'] ? 'disabled checked' : '' ?>>
                                <?php if ($p['obligatoire']): ?>
                                <input type="hidden" name="policy[<?= $key ?>][actif]" value="1">
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($p['derniere_purge']): ?>
                                    <span class="text-success"><i class="fas fa-check"></i> <?= formatDate($p['derniere_purge']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">Jamais</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($p['obligatoire']): ?>
                                    <span class="badge badge-info">Légal</span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="form-actions mt-3">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer les politiques</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2><i class="fas fa-broom"></i> Purge manuelle</h2></div>
        <div class="card-body">
            <p>La purge supprimera les enregistrements plus anciens que la durée de rétention configurée pour chaque catégorie active.</p>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> 
                <strong>Attention :</strong> Cette action est irréversible. Les données purgées ne pourront pas être récupérées.
            </div>
            <form method="post" onsubmit="return confirm('Êtes-vous sûr de vouloir exécuter la purge ? Cette action est irréversible.')">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="execute_purge">
                <button type="submit" class="btn btn-danger"><i class="fas fa-broom"></i> Exécuter la purge maintenant</button>
            </form>
            <p class="text-muted mt-2" style="font-size:.85rem">
                💡 Astuce : Vous pouvez automatiser la purge via un cron :<br>
                <code>0 3 * * 0 php /var/www/html/Pronote/rgpd/cron_purge.php</code>
            </p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
