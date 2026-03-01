<?php
/**
 * M28 – Orientation — Voir une fiche (profs/admin/parent)
 */
$pageTitle = 'Fiche orientation';
require_once __DIR__ . '/includes/header.php';

$id = (int)($_GET['id'] ?? 0);
$fiche = $orientationService->getFiche($id);
if (!$fiche) { header('Location: orientation.php'); exit; }

$voeux = $orientationService->getVoeux($id);
$canEdit = isAdmin() || isProfesseur() || isPersonnelVS();

// Avis prof/admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken() && $canEdit) {
    $data = [
        'projet_professionnel' => $fiche['projet_professionnel'],
        'centres_interet' => $fiche['centres_interet'],
        'competences_cles' => $fiche['competences_cles'],
        'avis_pp' => trim($_POST['avis_pp'] ?? $fiche['avis_pp']),
        'avis_conseil' => trim($_POST['avis_conseil'] ?? $fiche['avis_conseil']),
        'statut' => $_POST['statut'] ?? $fiche['statut'],
    ];
    $orientationService->modifierFiche($id, $data);

    // Avis voeux
    if (!empty($_POST['voeu_avis_pp'])) {
        $updatedVoeux = [];
        foreach ($voeux as $i => $v) {
            $v['avis_pp'] = $_POST['voeu_avis_pp'][$i] ?? $v['avis_pp'];
            $v['avis_conseil'] = $_POST['voeu_avis_conseil'][$i] ?? $v['avis_conseil'];
            $updatedVoeux[] = $v;
        }
        $orientationService->sauvegarderVoeux($id, $updatedVoeux);
    }

    header('Location: voir.php?id=' . $id);
    exit;
}
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-compass"></i> Fiche de <?= htmlspecialchars($fiche['prenom'] . ' ' . $fiche['eleve_nom']) ?></h1>
        <a href="orientation.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>

    <div class="detail-status">
        <?= OrientationService::statutBadge($fiche['statut']) ?>
        <span class="text-muted"><?= htmlspecialchars($fiche['classe_nom'] ?? '') ?> — <?= $fiche['annee_scolaire'] ?></span>
    </div>

    <form method="post">
        <?= csrfField() ?>

        <div class="card">
            <div class="card-header"><h2>Projet professionnel</h2></div>
            <div class="card-body"><p><?= nl2br(htmlspecialchars($fiche['projet_professionnel'] ?: '—')) ?></p></div>
        </div>

        <div class="card">
            <div class="card-header"><h2>Centres d'intérêt</h2></div>
            <div class="card-body"><p><?= nl2br(htmlspecialchars($fiche['centres_interet'] ?: '—')) ?></p></div>
        </div>

        <div class="card">
            <div class="card-header"><h2>Compétences clés</h2></div>
            <div class="card-body"><p><?= nl2br(htmlspecialchars($fiche['competences_cles'] ?: '—')) ?></p></div>
        </div>

        <!-- Voeux -->
        <div class="card">
            <div class="card-header"><h2>Vœux (<?= count($voeux) ?>)</h2></div>
            <div class="card-body">
                <?php if (empty($voeux)): ?>
                    <p class="text-muted">Aucun vœu.</p>
                <?php else: ?>
                <div class="voeux-table">
                    <table>
                        <thead>
                            <tr><th>Rang</th><th>Formation</th><th>Établissement</th><th>Motivation</th>
                            <?php if ($canEdit): ?><th>Avis PP</th><th>Avis Conseil</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($voeux as $i => $v): ?>
                            <tr>
                                <td><strong><?= $v['rang'] ?></strong></td>
                                <td><?= htmlspecialchars($v['formation']) ?></td>
                                <td><?= htmlspecialchars($v['etablissement_vise'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($v['motivation'] ?? '—') ?></td>
                                <?php if ($canEdit): ?>
                                <td><input type="text" name="voeu_avis_pp[<?= $i ?>]" class="form-control form-control-sm" value="<?= htmlspecialchars($v['avis_pp'] ?? '') ?>"></td>
                                <td><input type="text" name="voeu_avis_conseil[<?= $i ?>]" class="form-control form-control-sm" value="<?= htmlspecialchars($v['avis_conseil'] ?? '') ?>"></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Avis profs -->
        <?php if ($canEdit): ?>
        <div class="card">
            <div class="card-header"><h2>Avis pédagogique</h2></div>
            <div class="card-body">
                <div class="form-grid-2">
                    <div class="form-group">
                        <label>Avis du professeur principal</label>
                        <textarea name="avis_pp" class="form-control" rows="3"><?= htmlspecialchars($fiche['avis_pp'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Avis du conseil de classe</label>
                        <textarea name="avis_conseil" class="form-control" rows="3"><?= htmlspecialchars($fiche['avis_conseil'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="form-group">
                    <label>Statut</label>
                    <select name="statut" class="form-control" style="max-width:200px;">
                        <option value="soumise" <?= $fiche['statut'] === 'soumise' ? 'selected' : '' ?>>Soumise</option>
                        <option value="validee" <?= $fiche['statut'] === 'validee' ? 'selected' : '' ?>>Validée</option>
                        <option value="refusee" <?= $fiche['statut'] === 'refusee' ? 'selected' : '' ?>>Refusée</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer les avis</button>
            </div>
        </div>
        <?php else: ?>
        <?php if ($fiche['avis_pp'] || $fiche['avis_conseil']): ?>
        <div class="card">
            <div class="card-header"><h2>Avis</h2></div>
            <div class="card-body">
                <?php if ($fiche['avis_pp']): ?><div class="avis-block"><label>Professeur principal</label><p><?= nl2br(htmlspecialchars($fiche['avis_pp'])) ?></p></div><?php endif; ?>
                <?php if ($fiche['avis_conseil']): ?><div class="avis-block"><label>Conseil de classe</label><p><?= nl2br(htmlspecialchars($fiche['avis_conseil'])) ?></p></div><?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
