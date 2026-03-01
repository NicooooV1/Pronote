<?php
/**
 * M11 – Annonces : Modifier une annonce
 */

require_once __DIR__ . '/includes/AnnonceService.php';

$pageTitle = 'Modifier l\'annonce';
$currentPage = 'annonces';
require_once __DIR__ . '/includes/header.php';
requireAuth();

$pdo = getPDO();
$service = new AnnonceService($pdo);
$user = getCurrentUser();
$role = getUserRole();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if (!$id) {
    echo '<div class="alert alert-danger">Aucune annonce spécifiée.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$annonce = $service->getAnnonce($id);
if (!$annonce) {
    echo '<div class="alert alert-danger">Annonce introuvable.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Vérifier les permissions
if (!isAdmin() && !($annonce['auteur_id'] == $user['id'] && $annonce['auteur_type'] === $role)) {
    echo '<div class="alert alert-danger">Vous n\'avez pas la permission de modifier cette annonce.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Session expirée.';
    } else {
        try {
            $service->updateAnnonce($id, [
                'titre'           => $_POST['titre'],
                'contenu'         => $_POST['contenu'],
                'type'            => $_POST['type'] ?? 'info',
                'cible_roles'     => !empty($_POST['cible_roles']) ? $_POST['cible_roles'] : null,
                'cible_classes'   => !empty($_POST['cible_classes']) ? array_map('intval', $_POST['cible_classes']) : null,
                'publie'          => isset($_POST['publier']) ? 1 : 0,
                'epingle'         => isset($_POST['epingle']) ? 1 : 0,
                'date_expiration' => !empty($_POST['date_expiration']) ? $_POST['date_expiration'] : null,
            ]);
            $success = 'Annonce modifiée.';
            $annonce = $service->getAnnonce($id);
        } catch (Exception $e) {
            $error = 'Erreur : ' . $e->getMessage();
        }
    }
}

$classes = $service->getClasses();
$types = AnnonceService::getTypes();
$allRoles = [
    'eleve' => 'Élèves', 'parent' => 'Parents', 'professeur' => 'Professeurs',
    'vie_scolaire' => 'Vie scolaire', 'administrateur' => 'Administrateurs',
];
?>

<h1 class="page-title"><i class="fas fa-edit"></i> Modifier l'annonce</h1>

<?php if ($success): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
        <a href="detail_annonce.php?id=<?= $id ?>">Voir l'annonce</a>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><i class="fas fa-times-circle"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card form-card">
    <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="id" value="<?= $id ?>">

        <div class="form-section">
            <div class="form-group">
                <label for="titre">Titre *</label>
                <input type="text" name="titre" id="titre" class="form-control" required
                       value="<?= htmlspecialchars($annonce['titre']) ?>">
            </div>
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="type">Type</label>
                    <select name="type" id="type" class="form-control">
                        <?php foreach ($types as $key => $label): ?>
                        <option value="<?= $key ?>" <?= $annonce['type'] === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-4">
                    <label for="date_expiration">Date d'expiration</label>
                    <input type="datetime-local" name="date_expiration" id="date_expiration" class="form-control"
                           value="<?= $annonce['date_expiration'] ? date('Y-m-d\TH:i', strtotime($annonce['date_expiration'])) : '' ?>">
                </div>
            </div>
            <div class="form-group">
                <label for="contenu">Contenu *</label>
                <textarea name="contenu" id="contenu" class="form-control" rows="8" required><?= htmlspecialchars($annonce['contenu']) ?></textarea>
            </div>
        </div>

        <div class="form-section">
            <h3><i class="fas fa-crosshairs"></i> Ciblage</h3>
            <div class="form-group">
                <label>Rôles</label>
                <div class="checkbox-group">
                    <?php foreach ($allRoles as $rKey => $rLabel): ?>
                    <label class="checkbox-label">
                        <input type="checkbox" name="cible_roles[]" value="<?= $rKey ?>"
                            <?= in_array($rKey, $annonce['cible_roles']) ? 'checked' : '' ?>>
                        <?= htmlspecialchars($rLabel) ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="form-group">
                <label>Classes</label>
                <div class="checkbox-group checkbox-group-scroll">
                    <?php foreach ($classes as $c): ?>
                    <label class="checkbox-label">
                        <input type="checkbox" name="cible_classes[]" value="<?= $c['id'] ?>"
                            <?= in_array($c['id'], $annonce['cible_classes']) ? 'checked' : '' ?>>
                        <?= htmlspecialchars($c['nom']) ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="form-section">
            <div class="form-row">
                <label class="checkbox-label">
                    <input type="checkbox" name="publier" <?= $annonce['publie'] ? 'checked' : '' ?>> Publié
                </label>
                <label class="checkbox-label">
                    <input type="checkbox" name="epingle" <?= $annonce['epingle'] ? 'checked' : '' ?>> Épinglé
                </label>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
            <a href="detail_annonce.php?id=<?= $id ?>" class="btn btn-secondary">Annuler</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
