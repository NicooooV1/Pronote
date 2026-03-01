<?php
/**
 * M26 – Inscriptions — Formulaire parent
 */
$pageTitle = 'Inscrire un enfant';
$activePage = 'formulaire';
require_once __DIR__ . '/includes/header.php';

if (!isParent()) { redirect('/inscriptions/inscriptions.php'); }

$classes = $inscriptionService->getClasses();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken()) {
    $required = ['nom_eleve', 'prenom_eleve', 'date_naissance', 'sexe', 'adresse', 'telephone', 'email_contact'];
    $errors = [];
    foreach ($required as $f) {
        if (empty(trim($_POST[$f] ?? ''))) $errors[] = "Le champ $f est requis.";
    }

    if (empty($errors)) {
        $data = [
            'parent_id' => getUserId(),
            'nom_eleve' => trim($_POST['nom_eleve']),
            'prenom_eleve' => trim($_POST['prenom_eleve']),
            'date_naissance' => $_POST['date_naissance'],
            'sexe' => $_POST['sexe'],
            'classe_demandee' => $_POST['classe_demandee'] ?: null,
            'adresse' => trim($_POST['adresse']),
            'telephone' => trim($_POST['telephone']),
            'email_contact' => trim($_POST['email_contact']),
            'etablissement_precedent' => trim($_POST['etablissement_precedent'] ?? ''),
            'observations' => trim($_POST['observations'] ?? ''),
        ];
        $inscId = $inscriptionService->creerInscription($data);

        // Upload documents
        if (!empty($_FILES['documents'])) {
            $typesDoc = $_POST['type_document'] ?? [];
            foreach ($_FILES['documents']['name'] as $i => $name) {
                if ($_FILES['documents']['error'][$i] === UPLOAD_ERR_OK) {
                    $fichier = [
                        'name' => $name,
                        'tmp_name' => $_FILES['documents']['tmp_name'][$i],
                    ];
                    $typeDoc = $typesDoc[$i] ?? 'autre';
                    $inscriptionService->ajouterDocument($inscId, $typeDoc, $fichier);
                }
            }
        }

        $_SESSION['success_message'] = 'Inscription soumise avec succès. Vous serez notifié de la décision.';
        header('Location: inscriptions.php');
        exit;
    }
}
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-user-plus"></i> Inscrire un enfant</h1>
        <a href="inscriptions.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul><?php foreach ($errors as $e): ?><li><?= $e ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <?= csrfField() ?>

        <!-- Étape 1: Infos élève -->
        <div class="card form-section">
            <div class="card-header"><h2><i class="fas fa-child"></i> Informations de l'élève</h2></div>
            <div class="card-body">
                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="nom_eleve">Nom *</label>
                        <input type="text" name="nom_eleve" id="nom_eleve" class="form-control" required value="<?= htmlspecialchars($_POST['nom_eleve'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="prenom_eleve">Prénom *</label>
                        <input type="text" name="prenom_eleve" id="prenom_eleve" class="form-control" required value="<?= htmlspecialchars($_POST['prenom_eleve'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="date_naissance">Date de naissance *</label>
                        <input type="date" name="date_naissance" id="date_naissance" class="form-control" required value="<?= $_POST['date_naissance'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label for="sexe">Sexe *</label>
                        <select name="sexe" id="sexe" class="form-control" required>
                            <option value="M" <?= ($_POST['sexe'] ?? '') === 'M' ? 'selected' : '' ?>>Masculin</option>
                            <option value="F" <?= ($_POST['sexe'] ?? '') === 'F' ? 'selected' : '' ?>>Féminin</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="classe_demandee">Classe demandée</label>
                        <select name="classe_demandee" id="classe_demandee" class="form-control">
                            <option value="">— Sélectionner —</option>
                            <?php foreach ($classes as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="etablissement_precedent">Établissement précédent</label>
                        <input type="text" name="etablissement_precedent" id="etablissement_precedent" class="form-control" value="<?= htmlspecialchars($_POST['etablissement_precedent'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Étape 2: Contact -->
        <div class="card form-section">
            <div class="card-header"><h2><i class="fas fa-phone"></i> Coordonnées</h2></div>
            <div class="card-body">
                <div class="form-grid-2">
                    <div class="form-group full-width">
                        <label for="adresse">Adresse *</label>
                        <textarea name="adresse" id="adresse" class="form-control" rows="2" required><?= htmlspecialchars($_POST['adresse'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="telephone">Téléphone *</label>
                        <input type="tel" name="telephone" id="telephone" class="form-control" required value="<?= htmlspecialchars($_POST['telephone'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="email_contact">Email *</label>
                        <input type="email" name="email_contact" id="email_contact" class="form-control" required value="<?= htmlspecialchars($_POST['email_contact'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Étape 3: Documents -->
        <div class="card form-section">
            <div class="card-header"><h2><i class="fas fa-file-upload"></i> Documents</h2></div>
            <div class="card-body">
                <p class="form-help">Joignez les documents nécessaires à l'inscription.</p>
                <div id="documents-container">
                    <div class="doc-row">
                        <select name="type_document[]" class="form-control">
                            <?php foreach (InscriptionService::typesDocument() as $k => $v): ?>
                            <option value="<?= $k ?>"><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="file" name="documents[]" class="form-control">
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-outline" onclick="ajouterDocument()"><i class="fas fa-plus"></i> Ajouter un document</button>
            </div>
        </div>

        <!-- Observations -->
        <div class="card form-section">
            <div class="card-header"><h2><i class="fas fa-comment"></i> Observations</h2></div>
            <div class="card-body">
                <div class="form-group">
                    <textarea name="observations" class="form-control" rows="3" placeholder="Informations complémentaires..."><?= htmlspecialchars($_POST['observations'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-paper-plane"></i> Soumettre l'inscription</button>
            <a href="inscriptions.php" class="btn btn-outline">Annuler</a>
        </div>
    </form>
</div>

<script>
function ajouterDocument() {
    const container = document.getElementById('documents-container');
    const row = document.createElement('div');
    row.className = 'doc-row';
    row.innerHTML = `
        <select name="type_document[]" class="form-control">
            <?php foreach (InscriptionService::typesDocument() as $k => $v): ?>
            <option value="<?= $k ?>"><?= $v ?></option>
            <?php endforeach; ?>
        </select>
        <input type="file" name="documents[]" class="form-control">
        <button type="button" class="btn btn-sm btn-danger" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
    `;
    container.appendChild(row);
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
