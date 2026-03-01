<?php
/**
 * M30 – Clubs — Créer (profs/admin)
 */
$pageTitle = 'Créer un club';
$activePage = 'creer';
require_once __DIR__ . '/includes/header.php';

if (!isAdmin() && !isPersonnelVS() && !isProfesseur()) { redirect('/clubs/clubs.php'); }

$cats = ClubService::categories();
$profs = $clubService->getProfesseurs();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken()) {
    $data = [
        'nom' => trim($_POST['nom'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'categorie' => $_POST['categorie'] ?? 'autre',
        'responsable_id' => $_POST['responsable_id'] ?: (isProfesseur() ? getUserId() : null),
        'horaires' => trim($_POST['horaires'] ?? ''),
        'lieu' => trim($_POST['lieu'] ?? ''),
        'places_max' => $_POST['places_max'] ?: null,
        'date_debut' => $_POST['date_debut'] ?: null,
        'date_fin' => $_POST['date_fin'] ?: null,
    ];
    if (empty($data['nom'])) {
        $error = 'Le nom du club est obligatoire.';
    } else {
        $id = $clubService->creerClub($data);
        header('Location: detail.php?id=' . $id);
        exit;
    }
}
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-plus-circle"></i> Créer un club</h1>
        <a href="clubs.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>

    <?php if (!empty($error)): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="post">
                <?= csrfField() ?>
                <div class="form-grid-2">
                    <div class="form-group"><label>Nom *</label><input type="text" name="nom" class="form-control" required value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>"></div>
                    <div class="form-group">
                        <label>Catégorie</label>
                        <select name="categorie" class="form-control">
                            <?php foreach ($cats as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Responsable</label>
                        <select name="responsable_id" class="form-control">
                            <option value="">— Sélectionner —</option>
                            <?php foreach ($profs as $p): ?><option value="<?= $p['id'] ?>" <?= (isProfesseur() && $p['id'] == getUserId()) ? 'selected' : '' ?>><?= htmlspecialchars($p['prenom'] . ' ' . $p['nom']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Horaires</label><input type="text" name="horaires" class="form-control" placeholder="ex: Mercredi 14h-16h"></div>
                    <div class="form-group"><label>Lieu</label><input type="text" name="lieu" class="form-control" placeholder="ex: Salle polyvalente"></div>
                    <div class="form-group"><label>Places max</label><input type="number" name="places_max" class="form-control" min="1"></div>
                    <div class="form-group"><label>Date début</label><input type="date" name="date_debut" class="form-control"></div>
                    <div class="form-group"><label>Date fin</label><input type="date" name="date_fin" class="form-control"></div>
                    <div class="form-group full-width"><label>Description</label><textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea></div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Créer</button>
                    <a href="clubs.php" class="btn btn-outline">Annuler</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
