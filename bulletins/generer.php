<?php
/**
 * Bulletins — Génération des bulletins pour une classe/période
 */
require_once __DIR__ . '/includes/BulletinService.php';
$currentPage = 'generer';
$pageTitle = 'Générer les bulletins';
require_once __DIR__ . '/includes/header.php';
requireAuth();

if (!isAdmin() && !isVieScolaire()) {
    header('Location: bulletins.php');
    exit;
}

$pdo = getPDO();
$service = new BulletinService($pdo);
$classes = $service->getClasses();
$periodes = $service->getPeriodes();
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken()) {
    $classeId = (int)$_POST['classe_id'];
    $periodeId = (int)$_POST['periode_id'];
    
    try {
        $count = $service->genererBulletinsClasse($classeId, $periodeId);
        $message = "{$count} bulletin(s) générés/mis à jour avec succès.";
        $messageType = 'success';
    } catch (Exception $e) {
        $message = "Erreur : " . $e->getMessage();
        $messageType = 'error';
    }
}

$selectedClasse = (int)($_GET['classe'] ?? 0);
$selectedPeriode = (int)($_GET['periode'] ?? 0);
?>

<div class="page-header">
    <h1><i class="fas fa-cogs"></i> Générer les bulletins</h1>
</div>

<?php if ($message): ?>
<div class="alert alert-<?= $messageType ?>">
    <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
    <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><h3>Paramètres de génération</h3></div>
    <div class="card-body">
        <p class="text-muted">La génération calcule automatiquement les moyennes à partir des notes saisies, compte les absences/retards de la période et crée les lignes par matière.</p>
        <form method="POST" class="form-generate">
            <?= csrfField() ?>
            <div class="form-row">
                <div class="form-group">
                    <label for="classe_id">Classe</label>
                    <select name="classe_id" id="classe_id" class="form-select" required>
                        <option value="">— Sélectionner —</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $selectedClasse == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="periode_id">Période</label>
                    <select name="periode_id" id="periode_id" class="form-select" required>
                        <option value="">— Sélectionner —</option>
                        <?php foreach ($periodes as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= $selectedPeriode == $p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['nom']) ?> (<?= formatDate($p['date_debut']) ?> - <?= formatDate($p['date_fin']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-sync"></i> Générer les bulletins</button>
                <a href="bulletins.php" class="btn btn-outline">Retour</a>
            </div>
        </form>
    </div>
</div>

<div class="card mt-1">
    <div class="card-header"><h3>Comment ça marche ?</h3></div>
    <div class="card-body">
        <div class="steps-grid">
            <div class="step"><span class="step-num">1</span><h4>Calcul des moyennes</h4><p>Les notes saisies dans le module Notes sont récupérées et les moyennes par matière et générale sont calculées.</p></div>
            <div class="step"><span class="step-num">2</span><h4>Compteur absences</h4><p>Les absences et retards de la période sont compilés automatiquement.</p></div>
            <div class="step"><span class="step-num">3</span><h4>Classement</h4><p>Les rangs sont calculés par rapport à la moyenne générale de la classe.</p></div>
            <div class="step"><span class="step-num">4</span><h4>Appréciations</h4><p>Les professeurs et l'équipe pédagogique ajoutent leurs appréciations via l'éditeur.</p></div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
