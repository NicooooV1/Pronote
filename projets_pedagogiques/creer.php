<?php
$activePage = 'creer';
require_once __DIR__ . '/includes/header.php';

$user = $_SESSION['user'];
$role  = $user['type'] ?? 'eleve';
if (!in_array($role, ['admin', 'professeur'])) { header('Location: projets.php'); exit; }

$editId  = (int) ($_GET['id'] ?? 0);
$projet  = $editId ? $projetService->getProjet($editId) : null;
$types   = ProjetPedagogiqueService::typesLabels();
$statuts = ProjetPedagogiqueService::statutLabels();
$erreur  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'titre'          => trim($_POST['titre'] ?? ''),
        'description'    => trim($_POST['description'] ?? ''),
        'objectifs'      => trim($_POST['objectifs'] ?? ''),
        'type'           => $_POST['type'] ?? 'projet_classe',
        'responsable_id' => (int) ($_POST['responsable_id'] ?: $user['id']),
        'classes'        => trim($_POST['classes'] ?? ''),
        'matieres'       => trim($_POST['matieres'] ?? ''),
        'date_debut'     => $_POST['date_debut'] ?? date('Y-m-d'),
        'date_fin'       => $_POST['date_fin'] ?: null,
        'budget'         => $_POST['budget'] ?: null,
        'statut'         => $_POST['statut'] ?? 'brouillon',
        'bilan'          => trim($_POST['bilan'] ?? ''),
    ];
    if (!$data['titre']) { $erreur = 'Le titre est obligatoire.'; }
    if (!$erreur) {
        if ($editId) {
            $projetService->modifierProjet($editId, $data);
        } else {
            $editId = $projetService->creerProjet($data);
        }
        header("Location: detail.php?id=$editId");
        exit;
    }
}
$p = $projet ?? ['titre' => '', 'description' => '', 'objectifs' => '', 'type' => 'projet_classe', 'responsable_id' => $user['id'], 'classes' => '', 'matieres' => '', 'date_debut' => date('Y-m-d'), 'date_fin' => '', 'budget' => '', 'statut' => 'brouillon', 'bilan' => ''];
?>
<div class="container mt-4">
    <h2><i class="fas fa-<?= $editId ? 'edit' : 'plus' ?> me-2"></i><?= $editId ? 'Modifier le projet' : 'Nouveau projet pédagogique' ?></h2>

    <?php if ($erreur): ?><div class="alert alert-danger"><?= htmlspecialchars($erreur) ?></div><?php endif; ?>

    <form method="post" class="card mt-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">Titre *</label>
                    <input name="titre" class="form-control" value="<?= htmlspecialchars($p['titre']) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Type</label>
                    <select name="type" class="form-select">
                        <?php foreach ($types as $k => $v): ?>
                            <option value="<?= $k ?>" <?= ($p['type'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($p['description'] ?? '') ?></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Objectifs pédagogiques</label>
                    <textarea name="objectifs" class="form-control" rows="3"><?= htmlspecialchars($p['objectifs'] ?? '') ?></textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label">ID responsable</label>
                    <input name="responsable_id" type="number" class="form-control" value="<?= (int)$p['responsable_id'] ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Classes (ex: 3A, 4B)</label>
                    <input name="classes" class="form-control" value="<?= htmlspecialchars($p['classes'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Matières</label>
                    <input name="matieres" class="form-control" value="<?= htmlspecialchars($p['matieres'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date début *</label>
                    <input name="date_debut" type="date" class="form-control" value="<?= htmlspecialchars($p['date_debut']) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date fin</label>
                    <input name="date_fin" type="date" class="form-control" value="<?= htmlspecialchars($p['date_fin'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Budget (€)</label>
                    <input name="budget" type="number" step="0.01" class="form-control" value="<?= htmlspecialchars($p['budget'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Statut</label>
                    <select name="statut" class="form-select">
                        <?php foreach ($statuts as $k => $v): ?>
                            <option value="<?= $k ?>" <?= ($p['statut'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($editId): ?>
                <div class="col-12">
                    <label class="form-label">Bilan</label>
                    <textarea name="bilan" class="form-control" rows="3"><?= htmlspecialchars($p['bilan'] ?? '') ?></textarea>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-footer text-end">
            <a href="projets.php" class="btn btn-secondary me-2">Annuler</a>
            <button class="btn btn-primary"><i class="fas fa-save me-1"></i><?= $editId ? 'Enregistrer' : 'Créer le projet' ?></button>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
