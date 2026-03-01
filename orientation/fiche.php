<?php
/**
 * M28 – Orientation — Fiche élève (création/édition)
 */
$pageTitle = 'Ma fiche d\'orientation';
$activePage = 'fiche';
require_once __DIR__ . '/includes/header.php';

if (!isEleve()) { redirect('/orientation/orientation.php'); }

$currentYear = date('Y') . '-' . (date('Y') + 1);
$fiche = $orientationService->getFicheEleve(getUserId());
$voeux = $fiche ? $orientationService->getVoeux($fiche['id']) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken()) {
    $data = [
        'annee_scolaire' => $currentYear,
        'projet_professionnel' => trim($_POST['projet_professionnel'] ?? ''),
        'centres_interet' => trim($_POST['centres_interet'] ?? ''),
        'competences_cles' => trim($_POST['competences_cles'] ?? ''),
        'avis_pp' => null,
        'avis_conseil' => null,
        'statut' => $_POST['action_submit'] === 'soumettre' ? 'soumise' : 'brouillon',
    ];

    if ($fiche) {
        $orientationService->modifierFiche($fiche['id'], $data);
        $ficheId = $fiche['id'];
    } else {
        $ficheId = $orientationService->creerFiche(getUserId(), $data);
    }

    // Voeux
    $voeux_data = [];
    foreach ($_POST['voeu_formation'] ?? [] as $i => $formation) {
        $voeux_data[] = [
            'formation' => $formation,
            'etablissement_vise' => $_POST['voeu_etablissement'][$i] ?? '',
            'motivation' => $_POST['voeu_motivation'][$i] ?? '',
        ];
    }
    $orientationService->sauvegarderVoeux($ficheId, $voeux_data);

    $_SESSION['success_message'] = $data['statut'] === 'soumise' ? 'Fiche soumise avec succès.' : 'Brouillon enregistré.';
    header('Location: fiche.php');
    exit;
}
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-compass"></i> Ma fiche d'orientation</h1>
    </div>

    <?php if (!empty($_SESSION['success_message'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success_message'] ?></div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if ($fiche && $fiche['statut'] === 'validee'): ?>
    <div class="alert alert-info">Votre fiche a été validée. Vous ne pouvez plus la modifier.</div>
    <?php endif; ?>

    <form method="post">
        <?= csrfField() ?>

        <div class="card form-section">
            <div class="card-header"><h2><i class="fas fa-bullseye"></i> Projet professionnel</h2></div>
            <div class="card-body">
                <div class="form-group">
                    <label>Décrivez votre projet professionnel ou l'idée que vous en avez</label>
                    <textarea name="projet_professionnel" class="form-control" rows="4" <?= ($fiche && $fiche['statut'] === 'validee') ? 'disabled' : '' ?>><?= htmlspecialchars($fiche['projet_professionnel'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <div class="card form-section">
            <div class="card-header"><h2><i class="fas fa-heart"></i> Centres d'intérêt</h2></div>
            <div class="card-body">
                <div class="form-group">
                    <label>Vos passions, activités, domaines qui vous intéressent</label>
                    <textarea name="centres_interet" class="form-control" rows="3" <?= ($fiche && $fiche['statut'] === 'validee') ? 'disabled' : '' ?>><?= htmlspecialchars($fiche['centres_interet'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <div class="card form-section">
            <div class="card-header"><h2><i class="fas fa-star"></i> Compétences clés</h2></div>
            <div class="card-body">
                <div class="form-group">
                    <label>Vos points forts, compétences, ce dans quoi vous excellez</label>
                    <textarea name="competences_cles" class="form-control" rows="3" <?= ($fiche && $fiche['statut'] === 'validee') ? 'disabled' : '' ?>><?= htmlspecialchars($fiche['competences_cles'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <!-- Voeux -->
        <div class="card form-section">
            <div class="card-header">
                <h2><i class="fas fa-list-ol"></i> Vœux d'orientation</h2>
            </div>
            <div class="card-body">
                <p class="form-help">Classez vos vœux par ordre de préférence.</p>
                <div id="voeux-container">
                    <?php if (empty($voeux)): ?>
                    <div class="voeu-row">
                        <span class="voeu-rang">1</span>
                        <input type="text" name="voeu_formation[]" class="form-control" placeholder="Formation / Filière">
                        <input type="text" name="voeu_etablissement[]" class="form-control" placeholder="Établissement visé">
                        <input type="text" name="voeu_motivation[]" class="form-control" placeholder="Motivation">
                    </div>
                    <?php else: ?>
                    <?php foreach ($voeux as $i => $v): ?>
                    <div class="voeu-row">
                        <span class="voeu-rang"><?= $i + 1 ?></span>
                        <input type="text" name="voeu_formation[]" class="form-control" placeholder="Formation" value="<?= htmlspecialchars($v['formation']) ?>" <?= ($fiche && $fiche['statut'] === 'validee') ? 'disabled' : '' ?>>
                        <input type="text" name="voeu_etablissement[]" class="form-control" placeholder="Établissement" value="<?= htmlspecialchars($v['etablissement_vise'] ?? '') ?>" <?= ($fiche && $fiche['statut'] === 'validee') ? 'disabled' : '' ?>>
                        <input type="text" name="voeu_motivation[]" class="form-control" placeholder="Motivation" value="<?= htmlspecialchars($v['motivation'] ?? '') ?>" <?= ($fiche && $fiche['statut'] === 'validee') ? 'disabled' : '' ?>>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <?php if (!$fiche || $fiche['statut'] !== 'validee'): ?>
                <button type="button" class="btn btn-sm btn-outline" onclick="ajouterVoeu()"><i class="fas fa-plus"></i> Ajouter un vœu</button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Avis PP/Conseil -->
        <?php if ($fiche && ($fiche['avis_pp'] || $fiche['avis_conseil'])): ?>
        <div class="card form-section">
            <div class="card-header"><h2><i class="fas fa-comment-dots"></i> Avis</h2></div>
            <div class="card-body">
                <?php if ($fiche['avis_pp']): ?>
                <div class="avis-block"><label>Avis du professeur principal</label><p><?= nl2br(htmlspecialchars($fiche['avis_pp'])) ?></p></div>
                <?php endif; ?>
                <?php if ($fiche['avis_conseil']): ?>
                <div class="avis-block"><label>Avis du conseil de classe</label><p><?= nl2br(htmlspecialchars($fiche['avis_conseil'])) ?></p></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!$fiche || $fiche['statut'] !== 'validee'): ?>
        <div class="form-actions">
            <button type="submit" name="action_submit" value="brouillon" class="btn btn-outline"><i class="fas fa-save"></i> Enregistrer brouillon</button>
            <button type="submit" name="action_submit" value="soumettre" class="btn btn-primary" onclick="return confirm('Soumettre définitivement ?')"><i class="fas fa-paper-plane"></i> Soumettre</button>
        </div>
        <?php endif; ?>
    </form>
</div>

<script>
let voeuCount = document.querySelectorAll('.voeu-row').length;
function ajouterVoeu() {
    voeuCount++;
    const container = document.getElementById('voeux-container');
    const row = document.createElement('div');
    row.className = 'voeu-row';
    row.innerHTML = `
        <span class="voeu-rang">${voeuCount}</span>
        <input type="text" name="voeu_formation[]" class="form-control" placeholder="Formation / Filière">
        <input type="text" name="voeu_etablissement[]" class="form-control" placeholder="Établissement visé">
        <input type="text" name="voeu_motivation[]" class="form-control" placeholder="Motivation">
        <button type="button" class="btn btn-sm btn-danger" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
    `;
    container.appendChild(row);
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
