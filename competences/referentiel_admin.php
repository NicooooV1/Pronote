<?php
/**
 * M38 – Compétences — Admin: CRUD référentiel de compétences.
 */
$pageTitle = 'Gestion du référentiel';
require_once __DIR__ . '/includes/header.php';

if (!isAdmin()) {
    header('Location: competences.php');
    exit;
}

$pdo = getPDO();
$errors = [];
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $data = [
            'code'           => trim($_POST['code'] ?? ''),
            'nom'            => trim($_POST['nom'] ?? ''),
            'description'    => trim($_POST['description'] ?? ''),
            'domaine'        => trim($_POST['domaine'] ?? ''),
            'parent_id'      => (int) ($_POST['parent_id'] ?? 0),
            'ordre'          => (int) ($_POST['ordre'] ?? 0),
            'niveau_attendu' => $_POST['niveau_attendu'] ?? 'acquis',
        ];
        if (empty($data['code']) || empty($data['nom'])) {
            $errors[] = 'Le code et le nom sont requis.';
        } else {
            $compService->createCompetence($data);
            $success = 'Compétence créée.';
        }
    } elseif ($action === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        $data = [
            'code'           => trim($_POST['code'] ?? ''),
            'nom'            => trim($_POST['nom'] ?? ''),
            'description'    => trim($_POST['description'] ?? ''),
            'domaine'        => trim($_POST['domaine'] ?? ''),
            'parent_id'      => (int) ($_POST['parent_id'] ?? 0),
            'ordre'          => (int) ($_POST['ordre'] ?? 0),
            'niveau_attendu' => $_POST['niveau_attendu'] ?? 'acquis',
        ];
        if ($id && !empty($data['code']) && !empty($data['nom'])) {
            $compService->updateCompetence($id, $data);
            $success = 'Compétence mise à jour.';
        } else {
            $errors[] = 'Données invalides.';
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id && $compService->deleteCompetence($id)) {
            $success = 'Compétence supprimée.';
        } else {
            $errors[] = 'Impossible de supprimer cette compétence.';
        }
    }
}

$arbre = $compService->getArbreCompetences();
$allFlat = $compService->getCompetencesFlat();
$niveauxLabels = CompetenceService::niveauxLabels();
$csrf = generateCSRFToken();

// Editing mode
$editComp = null;
if (isset($_GET['edit'])) {
    $editComp = $compService->getCompetenceById((int) $_GET['edit']);
}
?>

<div class="main-content">
    <div class="page-header" style="display:flex;justify-content:space-between;align-items:center;">
        <div>
            <h1><i class="fas fa-cogs"></i> Gestion du référentiel</h1>
            <p class="page-subtitle">Ajouter, modifier ou supprimer des compétences du socle commun</p>
        </div>
        <a href="competences.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>

    <?php if ($success): ?>
    <div class="alert alert-success" style="margin-bottom:16px;"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger" style="margin-bottom:16px;">
        <ul style="margin:0;padding-left:20px;"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
        <!-- Formulaire ajout/édition -->
        <div style="background:white;border-radius:12px;padding:24px;box-shadow:0 2px 8px rgba(0,0,0,0.06);">
            <h3 style="font-size:15px;font-weight:600;color:#2d3748;margin-bottom:16px;">
                <?= $editComp ? 'Modifier la compétence' : 'Nouvelle compétence' ?>
            </h3>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="action" value="<?= $editComp ? 'update' : 'create' ?>">
                <?php if ($editComp): ?><input type="hidden" name="id" value="<?= $editComp['id'] ?>"><?php endif; ?>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:#4a5568;margin-bottom:4px;">Code *</label>
                        <input type="text" name="code" class="form-control" required value="<?= htmlspecialchars($editComp['code'] ?? '') ?>" placeholder="Ex: D1.1">
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:#4a5568;margin-bottom:4px;">Domaine</label>
                        <input type="text" name="domaine" class="form-control" value="<?= htmlspecialchars($editComp['domaine'] ?? '') ?>" placeholder="Ex: Langages">
                    </div>
                </div>

                <div style="margin-bottom:12px;">
                    <label style="display:block;font-size:12px;font-weight:600;color:#4a5568;margin-bottom:4px;">Nom *</label>
                    <input type="text" name="nom" class="form-control" required value="<?= htmlspecialchars($editComp['nom'] ?? '') ?>">
                </div>

                <div style="margin-bottom:12px;">
                    <label style="display:block;font-size:12px;font-weight:600;color:#4a5568;margin-bottom:4px;">Description</label>
                    <textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($editComp['description'] ?? '') ?></textarea>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:16px;">
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:#4a5568;margin-bottom:4px;">Parent</label>
                        <select name="parent_id" class="form-control">
                            <option value="0">— Racine —</option>
                            <?php foreach ($allFlat as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= ($editComp['parent_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['code'] . ' — ' . $c['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:#4a5568;margin-bottom:4px;">Ordre</label>
                        <input type="number" name="ordre" class="form-control" value="<?= $editComp['ordre'] ?? 0 ?>" min="0">
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;font-weight:600;color:#4a5568;margin-bottom:4px;">Niveau attendu</label>
                        <select name="niveau_attendu" class="form-control">
                            <?php foreach (['acquis','depasse','en_cours'] as $nv): ?>
                            <option value="<?= $nv ?>" <?= ($editComp['niveau_attendu'] ?? 'acquis') === $nv ? 'selected' : '' ?>><?= $niveauxLabels[$nv] ?? $nv ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="display:flex;gap:8px;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?= $editComp ? 'Mettre à jour' : 'Créer' ?></button>
                    <?php if ($editComp): ?>
                    <a href="referentiel_admin.php" class="btn btn-secondary">Annuler</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Liste des compétences -->
        <div style="background:white;border-radius:12px;padding:24px;box-shadow:0 2px 8px rgba(0,0,0,0.06);max-height:600px;overflow-y:auto;">
            <h3 style="font-size:15px;font-weight:600;color:#2d3748;margin-bottom:16px;">
                Référentiel actuel (<?= count($allFlat) ?> compétences)
            </h3>
            <?php foreach ($arbre as $domaine): ?>
            <div style="margin-bottom:16px;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                    <span style="font-weight:700;color:#0f4c81;font-size:13px;"><?= htmlspecialchars($domaine['code']) ?></span>
                    <span style="font-weight:600;font-size:13px;color:#2d3748;"><?= htmlspecialchars($domaine['nom']) ?></span>
                    <div style="margin-left:auto;display:flex;gap:4px;">
                        <a href="?edit=<?= $domaine['id'] ?>" class="btn btn-sm btn-secondary" title="Modifier"><i class="fas fa-edit"></i></a>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Supprimer ce domaine et toutes ses sous-compétences ?');">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $domaine['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                </div>
                <?php if (!empty($domaine['children'])): ?>
                <?php foreach ($domaine['children'] as $child): ?>
                <div style="display:flex;align-items:center;gap:8px;padding:4px 0 4px 20px;font-size:13px;border-bottom:1px solid #f7fafc;">
                    <span style="color:#718096;font-weight:500;"><?= htmlspecialchars($child['code']) ?></span>
                    <span style="color:#4a5568;"><?= htmlspecialchars($child['nom']) ?></span>
                    <div style="margin-left:auto;display:flex;gap:4px;">
                        <a href="?edit=<?= $child['id'] ?>" class="btn btn-sm btn-secondary" style="padding:2px 6px;font-size:11px;"><i class="fas fa-edit"></i></a>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Supprimer cette compétence ?');">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $child['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger" style="padding:2px 6px;font-size:11px;"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <?php if (empty($arbre)): ?>
            <div class="empty-state"><p>Aucune compétence définie.</p></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
