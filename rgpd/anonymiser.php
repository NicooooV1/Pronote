<?php
/**
 * M23 – RGPD — Anonymisation d'un utilisateur (admin)
 * Implémente le droit à l'oubli (Art. 17 RGPD)
 */
$pageTitle = 'Anonymisation';
$activePage = 'demandes';
require_once __DIR__ . '/includes/header.php';

if (!isAdmin()) { redirect('/accueil/accueil.php'); }

$message = '';
$messageType = '';
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken()) {
    $userId = (int)$_POST['user_id'];
    $userType = $_POST['user_type'] ?? '';
    $confirm = $_POST['confirmation'] ?? '';

    if ($confirm !== 'ANONYMISER') {
        $message = 'Veuillez taper ANONYMISER pour confirmer.';
        $messageType = 'danger';
    } elseif ($userId <= 0 || !in_array($userType, ['eleve', 'professeur', 'parent', 'vie_scolaire'])) {
        $message = 'Utilisateur invalide.';
        $messageType = 'danger';
    } else {
        $result = $rgpdService->anonymiserUtilisateur($userId, $userType, getUserId());
        if (!empty($result['error'])) {
            $message = 'Erreur : ' . $result['error'];
            $messageType = 'danger';
        } else {
            $message = "Utilisateur anonymisé avec succès. ID anonyme : {$result['anonym_id']}";
            $messageType = 'success';
        }
    }
}
?>

<div class="content-wrapper">
    <div class="content-header">
        <h1><i class="fas fa-user-slash"></i> Anonymisation — Droit à l'oubli</h1>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if ($result && !empty($result['actions'])): ?>
    <div class="card mb-4">
        <div class="card-header"><h2>Rapport d'anonymisation</h2></div>
        <div class="card-body">
            <ul>
                <?php foreach ($result['actions'] as $action): ?>
                <li><i class="fas fa-check text-success"></i> <?= htmlspecialchars($action) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php endif; ?>

    <div class="alert alert-danger">
        <i class="fas fa-exclamation-triangle"></i>
        <strong>ATTENTION :</strong> L'anonymisation est <strong>irréversible</strong>. 
        Les données personnelles de l'utilisateur seront remplacées par des valeurs anonymes.
        Les données statistiques (notes, absences) sont conservées de manière anonyme pour les rapports.
        Le compte sera désactivé.
    </div>

    <div class="card">
        <div class="card-header"><h2>Anonymiser un utilisateur</h2></div>
        <div class="card-body">
            <form method="post">
                <?= csrfField() ?>
                <div class="form-group">
                    <label class="form-label">Type d'utilisateur</label>
                    <select name="user_type" class="form-control" required>
                        <option value="">— Sélectionner —</option>
                        <option value="eleve">Élève</option>
                        <option value="professeur">Professeur</option>
                        <option value="parent">Parent</option>
                        <option value="vie_scolaire">Vie scolaire</option>
                    </select>
                    <small class="text-muted">Les administrateurs ne peuvent pas être anonymisés.</small>
                </div>
                <div class="form-group">
                    <label class="form-label">ID utilisateur</label>
                    <input type="number" name="user_id" class="form-control" required min="1" placeholder="Ex: 42">
                </div>
                <div class="form-group">
                    <label class="form-label">Confirmation</label>
                    <input type="text" name="confirmation" class="form-control" required 
                           placeholder="Tapez ANONYMISER pour confirmer"
                           pattern="ANONYMISER" title="Tapez exactement : ANONYMISER">
                    <small class="text-danger">Tapez <strong>ANONYMISER</strong> en majuscules pour confirmer.</small>
                </div>
                <div class="form-actions mt-3">
                    <button type="submit" class="btn btn-danger" onclick="return confirm('Dernière confirmation : êtes-vous absolument sûr ?')">
                        <i class="fas fa-user-slash"></i> Anonymiser cet utilisateur
                    </button>
                    <a href="demandes.php" class="btn btn-outline">Annuler</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
