<?php
/**
 * M16 – Documents — Ajouter un document
 */
$pageTitle = 'Ajouter un document';
require_once __DIR__ . '/includes/header.php';

if (!isAdmin() && !isTeacher() && !isVieScolaire()) {
    redirect('../accueil/accueil.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRFToken();

    if (empty($_FILES['fichier']['name'])) {
        $error = 'Veuillez sélectionner un fichier.';
    } elseif (empty($_POST['titre'])) {
        $error = 'Le titre est obligatoire.';
    } else {
        $maxSize = 10 * 1024 * 1024; // 10 Mo
        if ($_FILES['fichier']['size'] > $maxSize) {
            $error = 'Le fichier ne doit pas dépasser 10 Mo.';
        } else {
            try {
                $data = [
                    'titre'       => trim($_POST['titre']),
                    'description' => trim($_POST['description'] ?? ''),
                    'categorie'   => $_POST['categorie'] ?? 'autre',
                    'visibilite'  => $_POST['visibilite'] ?? [],
                    'auteur_id'   => getUserId(),
                    'auteur_type' => getUserRole(),
                ];
                $docService->ajouter($data, $_FILES['fichier']);
                $success = 'Document ajouté avec succès.';
            } catch (Exception $e) {
                $error = 'Erreur lors de l\'ajout : ' . $e->getMessage();
            }
        }
    }
}

$categories = DocumentService::categories();
$roles = ['administrateur' => 'Administrateurs', 'professeur' => 'Professeurs', 'eleve' => 'Élèves', 'parent' => 'Parents', 'vie_scolaire' => 'Vie scolaire'];
?>

<div class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-upload"></i> Ajouter un document</h1>
        <a href="documents.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $success ?>
            <a href="documents.php">Voir les documents</a></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="post" enctype="multipart/form-data">
                <?= csrfField() ?>

                <div class="form-group">
                    <label class="form-label">Titre *</label>
                    <input type="text" name="titre" class="form-control" required value="<?= htmlspecialchars($_POST['titre'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label class="form-label">Catégorie</label>
                        <select name="categorie" class="form-select">
                            <?php foreach ($categories as $k => $v): ?>
                                <option value="<?= $k ?>"><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-md-6">
                        <label class="form-label">Fichier * <small>(max 10 Mo)</small></label>
                        <input type="file" name="fichier" class="form-control" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Visibilité <small>(laisser vide = visible par tous)</small></label>
                    <div class="checkbox-group">
                        <?php foreach ($roles as $k => $v): ?>
                            <label class="checkbox-label">
                                <input type="checkbox" name="visibilite[]" value="<?= $k ?>"> <?= $v ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Ajouter le document</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
