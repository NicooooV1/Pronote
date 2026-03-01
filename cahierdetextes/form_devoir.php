<?php
/**
 * form_devoir.php — Formulaire unifié ajout/modification de devoir (REF-2)
 *
 * ?id=X → mode édition   |   pas d'id → mode création
 *
 * Sécurité : validateCSRFToken (SEC-2), htmlspecialchars (SEC-1),
 *            DateTime::createFromFormat (SEC-3), maxlength (SEC-5)
 */
ob_start();

require_once __DIR__ . '/../API/core.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/DevoirService.php';

$pdo = getPDO();
requireAuth();

if (!canManageDevoirs()) {
    setFlashMessage('error', "Accès refusé.");
    redirectTo('cahierdetextes.php');
}

$service  = new DevoirService($pdo);
$uploader = new \API\Services\FileUploadService('devoirs');

$user          = getCurrentUser();
$user_fullname = getUserFullName();
$user_role     = getUserRole();
$user_initials = getUserInitials();

// ── Mode détection ──
$id     = isset($_GET['id']) ? intval($_GET['id']) : null;
$isEdit = $id !== null;
$devoir = null;
$fichiers = [];

if ($isEdit) {
    $devoir = $service->getDevoirById($id);
    if (!$devoir) {
        setFlashMessage('error', "Devoir introuvable.");
        redirectTo('cahierdetextes.php');
    }
    if (!$service->canUserEdit($devoir, $user_fullname, $user_role)) {
        setFlashMessage('error', "Vous n'avez pas les droits pour modifier ce devoir.");
        redirectTo('cahierdetextes.php');
    }
    $fichiers = $service->getFichiers($id);
}

// ── Valeurs par défaut ──
$defaults = [
    'titre'          => $devoir['titre']          ?? '',
    'description'    => $devoir['description']    ?? '',
    'classe'         => $devoir['classe']         ?? '',
    'nom_matiere'    => $devoir['nom_matiere']    ?? '',
    'nom_professeur' => $devoir['nom_professeur'] ?? $user_fullname,
    'date_ajout'     => $devoir['date_ajout']     ?? date('Y-m-d'),
    'date_rendu'     => $devoir['date_rendu']     ?? '',
];

$csrf_token = generateCSRFToken();
$erreur  = '';
$message = '';

// Données formulaire
$etablissement_data = $service->getEtablissementData();
$professeurs        = $service->getProfesseurs();
$prof_matiere       = '';
if (isTeacher()) {
    $pInfo = $service->getProfesseurInfo($user['id']);
    $prof_matiere = $pInfo['matiere'] ?? '';
}

// ── Suppression d'un fichier existant ──
if (isset($_GET['del_fichier']) && $isEdit) {
    if (validateCSRFToken($_GET['token'] ?? '')) {
        $service->deleteFichier(intval($_GET['del_fichier']));
    }
    // Re-generate token after consumption
    redirectTo("form_devoir.php?id={$id}");
}

// ── Traitement POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $erreur = "Erreur de validation du formulaire. Veuillez réessayer.";
    } else {
        $data = [
            'titre'          => trim($_POST['titre'] ?? ''),
            'description'    => trim($_POST['description'] ?? ''),
            'classe'         => $_POST['classe'] ?? '',
            'nom_matiere'    => $_POST['nom_matiere'] ?? '',
            'nom_professeur' => $_POST['nom_professeur'] ?? '',
            'date_ajout'     => $_POST['date_ajout'] ?? '',
            'date_rendu'     => $_POST['date_rendu'] ?? '',
        ];

        $errors = $service->validate($data);
        if (!empty($errors)) {
            $erreur   = implode('<br>', array_map('htmlspecialchars', $errors));
            $defaults = $data;
        } else {
            try {
                if ($isEdit) {
                    $service->update($id, $data);
                    $message = "Le devoir a été mis à jour avec succès.";
                } else {
                    $id = $service->create($data);
                    $message = "Le devoir a été ajouté avec succès.";
                }

                // Pièces jointes (PJ-4)
                if (!empty($_FILES['fichiers']['name'][0])) {
                    $results = $uploader->uploadMultiple($_FILES['fichiers']);
                    foreach ($results as $r) {
                        if ($r['success']) {
                            $service->addFichier($id, $r['nom_original'], $r['chemin'], $r['type_mime'], $r['taille']);
                        } else {
                            $erreur .= ($erreur ? '<br>' : '') . htmlspecialchars($r['error']);
                        }
                    }
                }

                if (empty($erreur)) {
                    setFlashMessage('success', $message);
                    redirectTo('cahierdetextes.php');
                }

                $fichiers = $service->getFichiers($id);
                $isEdit   = true;
                $devoir   = $service->getDevoirById($id);
            } catch (\PDOException $e) {
                logError("Erreur form_devoir: " . $e->getMessage());
                $erreur = "Une erreur est survenue lors de l'enregistrement.";
            }
        }
    }
    // Regenerate token for re-display
    $csrf_token = generateCSRFToken();
}

// ── Template ──
$pageTitle = $isEdit ? "Modifier un devoir" : "Ajouter un devoir";

include 'includes/header.php';
?>

            <div class="welcome-banner">
                <div class="welcome-content">
                    <h2><?= $isEdit ? 'Modifier un devoir' : 'Ajouter un devoir' ?></h2>
                    <p><?= $isEdit ? 'Mise à jour du devoir : ' . htmlspecialchars($defaults['titre']) : 'Créez un nouveau devoir' ?></p>
                </div>
                <div class="welcome-logo">
                    <i class="fas fa-<?= $isEdit ? 'edit' : 'plus-circle' ?>"></i>
                </div>
            </div>

            <div class="dashboard-content">
                <?php if ($message): ?>
                    <div class="alert-banner alert-success"><i class="fas fa-check-circle"></i><div><?= htmlspecialchars($message) ?></div><button class="alert-close">&times;</button></div>
                <?php endif; ?>
                <?php if ($erreur): ?>
                    <div class="alert-banner alert-error"><i class="fas fa-exclamation-circle"></i><div><?= $erreur ?></div><button class="alert-close">&times;</button></div>
                <?php endif; ?>

                <div class="devoir-card">
                    <div class="card-header">
                        <div class="devoir-title"><i class="fas fa-edit"></i> <?= $isEdit ? 'Modifier le devoir' : 'Créer un nouveau devoir' ?></div>
                        <div class="devoir-meta">Remplissez le formulaire ci-dessous</div>
                    </div>

                    <div class="card-body">
                        <form method="post" id="devoir-form" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                            <div class="form-grid">
                                <!-- Titre -->
                                <div class="form-group" style="grid-column: span 2;">
                                    <label class="form-label" for="titre">Titre du devoir <span class="required">*</span></label>
                                    <input type="text" name="titre" id="titre" class="form-control" required
                                           maxlength="255" placeholder="Titre du devoir"
                                           value="<?= htmlspecialchars($defaults['titre']) ?>">
                                </div>

                                <!-- Classe (SEC-1 : htmlspecialchars) -->
                                <div class="form-group">
                                    <label class="form-label" for="classe">Classe <span class="required">*</span></label>
                                    <select name="classe" id="classe" class="form-select" required>
                                        <option value="">Sélectionnez une classe</option>
                                        <?php if (!empty($etablissement_data['classes'])): ?>
                                            <?php foreach ($etablissement_data['classes'] as $niveau => $niveaux): ?>
                                                <optgroup label="<?= htmlspecialchars(ucfirst($niveau), ENT_QUOTES) ?>">
                                                    <?php foreach ($niveaux as $sousniveau => $classes): ?>
                                                        <?php foreach ($classes as $classe): ?>
                                                            <option value="<?= htmlspecialchars($classe, ENT_QUOTES) ?>"
                                                                <?= ($defaults['classe'] === $classe) ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($classe) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    <?php endforeach; ?>
                                                </optgroup>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                        <?php if (!empty($etablissement_data['primaire'])): ?>
                                            <optgroup label="Primaire">
                                                <?php foreach ($etablissement_data['primaire'] as $niveau => $classes): ?>
                                                    <?php foreach ($classes as $classe): ?>
                                                        <option value="<?= htmlspecialchars($classe, ENT_QUOTES) ?>"
                                                            <?= ($defaults['classe'] === $classe) ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($classe) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endif; ?>
                                    </select>
                                </div>

                                <!-- Matière -->
                                <div class="form-group">
                                    <label class="form-label" for="nom_matiere">Matière <span class="required">*</span></label>
                                    <select name="nom_matiere" id="nom_matiere" class="form-select" required>
                                        <option value="">Sélectionnez une matière</option>
                                        <?php if (!empty($etablissement_data['matieres'])): ?>
                                            <?php foreach ($etablissement_data['matieres'] as $matiere): ?>
                                                <option value="<?= htmlspecialchars($matiere['nom'], ENT_QUOTES) ?>"
                                                    <?= ($defaults['nom_matiere'] === $matiere['nom']) ? 'selected' : '' ?>
                                                    <?= (!$isEdit && $prof_matiere === $matiere['nom']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($matiere['nom']) ?> (<?= htmlspecialchars($matiere['code']) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>

                                <!-- Professeur -->
                                <div class="form-group">
                                    <label class="form-label" for="nom_professeur">Professeur <span class="required">*</span></label>
                                    <?php if (isTeacher()): ?>
                                        <div class="selected-user-display"><?= htmlspecialchars($defaults['nom_professeur']) ?></div>
                                        <input type="hidden" name="nom_professeur" id="nom_professeur"
                                               value="<?= htmlspecialchars($defaults['nom_professeur'], ENT_QUOTES) ?>">
                                    <?php else: ?>
                                        <select name="nom_professeur" id="nom_professeur" class="form-select" required>
                                            <option value="">Sélectionnez un professeur</option>
                                            <?php foreach ($professeurs as $prof): ?>
                                                <?php $pfn = $prof['prenom'] . ' ' . $prof['nom']; ?>
                                                <option value="<?= htmlspecialchars($pfn, ENT_QUOTES) ?>"
                                                    data-matiere="<?= htmlspecialchars($prof['matiere'], ENT_QUOTES) ?>"
                                                    <?= ($defaults['nom_professeur'] === $pfn) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($pfn) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php endif; ?>
                                </div>

                                <!-- Dates -->
                                <div class="form-group">
                                    <label class="form-label" for="date_ajout">Date d'ajout <span class="required">*</span></label>
                                    <input type="date" name="date_ajout" id="date_ajout" class="form-control" required
                                           value="<?= htmlspecialchars($defaults['date_ajout']) ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="date_rendu">Date de rendu <span class="required">*</span></label>
                                    <input type="date" name="date_rendu" id="date_rendu" class="form-control" required
                                           value="<?= htmlspecialchars($defaults['date_rendu']) ?>">
                                    <div class="text-muted" id="jours-restants" style="margin-top: 5px;"></div>
                                </div>

                                <!-- Description (SEC-5 : maxlength) -->
                                <div class="form-group" style="grid-column: span 2;">
                                    <label class="form-label" for="description">Description <span class="required">*</span></label>
                                    <textarea name="description" id="description" class="form-control" rows="6" required
                                              maxlength="5000" placeholder="Description détaillée du devoir"><?= htmlspecialchars($defaults['description']) ?></textarea>
                                </div>

                                <!-- Pièces jointes (PJ-3, PJ-4) -->
                                <div class="form-group" style="grid-column: span 2;">
                                    <label class="form-label">Pièces jointes (max 5 fichiers, 10 Mo chacun)</label>
                                    <div class="upload-zone" id="upload-zone">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        <p>Glissez vos fichiers ici ou <label for="fichiers" class="upload-browse">parcourir</label></p>
                                        <span class="text-muted">PDF, Word, Excel, images (JPG, PNG)</span>
                                        <input type="file" name="fichiers[]" id="fichiers" multiple
                                               accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.webp,.txt">
                                    </div>
                                    <div id="fichiers-preview" class="fichiers-list"></div>

                                    <?php if (!empty($fichiers)): ?>
                                        <div class="fichiers-list" style="margin-top: 10px;">
                                            <h4><i class="fas fa-paperclip"></i> Fichiers existants</h4>
                                            <?php foreach ($fichiers as $f): ?>
                                                <div class="fichier-item">
                                                    <i class="fas fa-<?= FileUploader::getFileIcon($f['type_mime']) ?>"></i>
                                                    <span><?= htmlspecialchars($f['nom_original']) ?></span>
                                                    <span class="fichier-taille"><?= FileUploader::formatBytes($f['taille']) ?></span>
                                                    <a href="?id=<?= $id ?>&del_fichier=<?= $f['id'] ?>&token=<?= urlencode(generateCSRFToken()) ?>"
                                                       class="btn btn-sm btn-danger" onclick="return confirm('Supprimer ce fichier ?');"
                                                       title="Supprimer"><i class="fas fa-trash"></i></a>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="form-actions">
                                <a href="cahierdetextes.php" class="btn btn-secondary"><i class="fas fa-times"></i> Annuler</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> <?= $isEdit ? 'Enregistrer les modifications' : 'Ajouter le devoir' ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

<?php
include 'includes/footer.php';
ob_end_flush();
?>
