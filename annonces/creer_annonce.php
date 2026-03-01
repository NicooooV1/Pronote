<?php
/**
 * M11 – Annonces : Créer une annonce (avec sondage optionnel)
 */

require_once __DIR__ . '/includes/AnnonceService.php';

$pageTitle = 'Nouvelle annonce';
$currentPage = 'creer';
require_once __DIR__ . '/includes/header.php';
requireAuth();

if (!isAdmin() && !isVieScolaire() && !isTeacher()) {
    echo '<div class="alert alert-danger">Accès non autorisé.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$pdo = getPDO();
$service = new AnnonceService($pdo);
$user = getCurrentUser();
$role = getUserRole();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Session expirée.';
    } else {
        $titre = trim($_POST['titre'] ?? '');
        $contenu = trim($_POST['contenu'] ?? '');
        $type = $_POST['type'] ?? 'info';

        if (!$titre || !$contenu) {
            $error = 'Le titre et le contenu sont obligatoires.';
        } else {
            try {
                $cibleRoles = $_POST['cible_roles'] ?? [];
                $cibleClasses = $_POST['cible_classes'] ?? [];

                $annonceId = $service->createAnnonce([
                    'titre'           => $titre,
                    'contenu'         => $contenu,
                    'type'            => $type,
                    'auteur_id'       => $user['id'],
                    'auteur_type'     => $role,
                    'cible_roles'     => !empty($cibleRoles) ? $cibleRoles : null,
                    'cible_classes'   => !empty($cibleClasses) ? array_map('intval', $cibleClasses) : null,
                    'publie'          => isset($_POST['publier']) ? 1 : 0,
                    'epingle'         => isset($_POST['epingle']) ? 1 : 0,
                    'date_publication'=> $_POST['date_publication'] ?? date('Y-m-d H:i:s'),
                    'date_expiration' => !empty($_POST['date_expiration']) ? $_POST['date_expiration'] : null,
                ]);

                // Créer le sondage si type = sondage
                if ($type === 'sondage' && !empty($_POST['sondage_question'])) {
                    $options = array_filter($_POST['sondage_options'] ?? [], fn($o) => trim($o) !== '');
                    $service->createSondage($annonceId, [
                        'question'     => $_POST['sondage_question'],
                        'type_reponse' => $_POST['sondage_type'] ?? 'choix_unique',
                        'anonyme'      => isset($_POST['sondage_anonyme']) ? 1 : 0,
                        'date_fin'     => !empty($_POST['sondage_date_fin']) ? $_POST['sondage_date_fin'] : null,
                        'options'      => $options,
                    ]);
                }

                $success = "Annonce créée avec succès.";
            } catch (Exception $e) {
                $error = 'Erreur : ' . $e->getMessage();
            }
        }
    }
}

$classes = $service->getClasses();
$types = AnnonceService::getTypes();
$roles = [
    'eleve'          => 'Élèves',
    'parent'         => 'Parents',
    'professeur'     => 'Professeurs',
    'vie_scolaire'   => 'Vie scolaire',
    'administrateur' => 'Administrateurs',
];
?>

<h1 class="page-title"><i class="fas fa-plus-circle"></i> Nouvelle annonce</h1>

<?php if ($success): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
        <a href="annonces.php">Voir les annonces</a>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><i class="fas fa-times-circle"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card form-card">
    <form method="POST" id="form-annonce">
        <?= csrfField() ?>

        <!-- Informations principales -->
        <div class="form-section">
            <h3><i class="fas fa-info-circle"></i> Contenu</h3>
            <div class="form-group">
                <label for="titre">Titre *</label>
                <input type="text" name="titre" id="titre" class="form-control" required
                       placeholder="Titre de l'annonce" maxlength="255">
            </div>

            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="type">Type *</label>
                    <select name="type" id="type" class="form-control" required>
                        <?php foreach ($types as $key => $label): ?>
                        <option value="<?= $key ?>"><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-4">
                    <label for="date_publication">Date de publication</label>
                    <input type="datetime-local" name="date_publication" id="date_publication"
                           class="form-control" value="<?= date('Y-m-d\TH:i') ?>">
                </div>
                <div class="form-group col-md-4">
                    <label for="date_expiration">Date d'expiration</label>
                    <input type="datetime-local" name="date_expiration" id="date_expiration" class="form-control">
                </div>
            </div>

            <div class="form-group">
                <label for="contenu">Contenu *</label>
                <textarea name="contenu" id="contenu" class="form-control" rows="8" required
                          placeholder="Rédigez le contenu de l'annonce..."></textarea>
            </div>
        </div>

        <!-- Ciblage -->
        <div class="form-section">
            <h3><i class="fas fa-crosshairs"></i> Ciblage <small class="text-muted">(laisser vide = tout le monde)</small></h3>

            <div class="form-group">
                <label>Rôles ciblés</label>
                <div class="checkbox-group">
                    <?php foreach ($roles as $rKey => $rLabel): ?>
                    <label class="checkbox-label">
                        <input type="checkbox" name="cible_roles[]" value="<?= $rKey ?>">
                        <?= htmlspecialchars($rLabel) ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group">
                <label>Classes ciblées</label>
                <div class="checkbox-group checkbox-group-scroll">
                    <?php foreach ($classes as $c): ?>
                    <label class="checkbox-label">
                        <input type="checkbox" name="cible_classes[]" value="<?= $c['id'] ?>">
                        <?= htmlspecialchars($c['nom']) ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Sondage (conditionnel) -->
        <div class="form-section" id="section-sondage" style="display:none;">
            <h3><i class="fas fa-poll"></i> Sondage</h3>
            <div class="form-group">
                <label for="sondage_question">Question</label>
                <input type="text" name="sondage_question" id="sondage_question" class="form-control"
                       placeholder="Posez votre question...">
            </div>
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="sondage_type">Type de réponse</label>
                    <select name="sondage_type" id="sondage_type" class="form-control">
                        <option value="choix_unique">Choix unique</option>
                        <option value="choix_multiple">Choix multiple</option>
                        <option value="texte_libre">Texte libre</option>
                    </select>
                </div>
                <div class="form-group col-md-4">
                    <label for="sondage_date_fin">Date de fin du sondage</label>
                    <input type="datetime-local" name="sondage_date_fin" id="sondage_date_fin" class="form-control">
                </div>
                <div class="form-group col-md-4">
                    <label class="checkbox-label" style="margin-top:1.5rem;">
                        <input type="checkbox" name="sondage_anonyme"> Sondage anonyme
                    </label>
                </div>
            </div>

            <div id="options-container">
                <label>Options de réponse</label>
                <div class="option-row">
                    <input type="text" name="sondage_options[]" class="form-control" placeholder="Option 1">
                    <button type="button" class="btn btn-sm btn-danger remove-option" style="display:none;">✕</button>
                </div>
                <div class="option-row">
                    <input type="text" name="sondage_options[]" class="form-control" placeholder="Option 2">
                    <button type="button" class="btn btn-sm btn-danger remove-option" style="display:none;">✕</button>
                </div>
            </div>
            <button type="button" id="add-option" class="btn btn-sm btn-secondary">
                <i class="fas fa-plus"></i> Ajouter une option
            </button>
        </div>

        <!-- Options de publication -->
        <div class="form-section">
            <div class="form-row">
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="publier" checked> Publier immédiatement
                    </label>
                </div>
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="epingle"> Épingler en haut
                    </label>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Publier l'annonce</button>
            <a href="annonces.php" class="btn btn-secondary">Annuler</a>
        </div>
    </form>
</div>

<script>
// Afficher/masquer la section sondage
document.getElementById('type').addEventListener('change', function() {
    document.getElementById('section-sondage').style.display = this.value === 'sondage' ? 'block' : 'none';
});

// Ajouter/supprimer des options
document.getElementById('add-option').addEventListener('click', function() {
    const container = document.getElementById('options-container');
    const count = container.querySelectorAll('.option-row').length + 1;
    const row = document.createElement('div');
    row.className = 'option-row';
    row.innerHTML = `
        <input type="text" name="sondage_options[]" class="form-control" placeholder="Option ${count}">
        <button type="button" class="btn btn-sm btn-danger remove-option">✕</button>
    `;
    container.appendChild(row);
    updateRemoveButtons();
});

document.getElementById('options-container').addEventListener('click', function(e) {
    if (e.target.closest('.remove-option')) {
        e.target.closest('.option-row').remove();
        updateRemoveButtons();
    }
});

function updateRemoveButtons() {
    const rows = document.querySelectorAll('#options-container .option-row');
    rows.forEach(row => {
        const btn = row.querySelector('.remove-option');
        btn.style.display = rows.length > 2 ? 'inline-block' : 'none';
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
