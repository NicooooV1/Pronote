<?php
/**
 * M06 – Discipline : Traiter / Modifier un incident
 */

require_once __DIR__ . '/includes/DisciplineService.php';

$pageTitle = 'Traiter un incident';
$currentPage = 'incidents';
require_once __DIR__ . '/includes/header.php';
requireAuth();

if (!isAdmin() && !isVieScolaire()) {
    echo '<div class="alert alert-danger">Seuls les administrateurs et la vie scolaire peuvent traiter les incidents.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    echo '<div class="alert alert-danger">Aucun incident spécifié.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$pdo = getPDO();
$service = new DisciplineService($pdo);
$incident = $service->getIncident($id);

if (!$incident) {
    echo '<div class="alert alert-danger">Incident introuvable.</div>';
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
            $newStatut = $_POST['statut'] ?? $incident['statut'];
            $oldStatut = $incident['statut'] ?? 'signale';

            // Utiliser la machine à états si le statut change
            if ($newStatut !== $oldStatut) {
                $user = getCurrentUser();
                $commentaire = trim($_POST['commentaire_traitement'] ?? '');
                if (!$service->transitionIncident($id, $newStatut, $user['id'], $commentaire)) {
                    throw new Exception("Transition de statut invalide : $oldStatut → $newStatut");
                }
            }

            $service->updateIncident($id, [
                'type_incident' => $_POST['type_incident'],
                'gravite'       => $_POST['gravite'],
                'description'   => $_POST['description'],
                'lieu'          => $_POST['lieu'] ?? null,
                'temoins'       => $_POST['temoins'] ?? null,
                'statut'        => $newStatut,
            ]);
            $success = 'Incident mis à jour.';
            $incident = $service->getIncident($id);
        } catch (Exception $e) {
            $error = 'Erreur : ' . $e->getMessage();
        }
    }
}

$typesIncident = DisciplineService::getTypesIncident();
$gravites = DisciplineService::getGravites();
?>

<h1 class="page-title"><i class="fas fa-edit"></i> Traiter l'incident #<?= $id ?></h1>

<?php if ($success): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><i class="fas fa-times-circle"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card form-card">
    <div class="alert alert-info">
        <strong>Élève :</strong> <?= htmlspecialchars($incident['eleve_prenom'] . ' ' . $incident['eleve_nom']) ?>
        (<?= htmlspecialchars($incident['eleve_classe'] ?? '-') ?>)
        — signalé le <?= date('d/m/Y à H:i', strtotime($incident['date_incident'])) ?>
    </div>

    <form method="POST">
        <?= csrfField() ?>

        <div class="form-row">
            <div class="form-group col-md-4">
                <label for="type_incident">Type</label>
                <select name="type_incident" id="type_incident" class="form-control" required>
                    <?php foreach ($typesIncident as $key => $label): ?>
                    <option value="<?= $key ?>" <?= $incident['type_incident'] === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-md-4">
                <label for="gravite">Gravité</label>
                <select name="gravite" id="gravite" class="form-control" required>
                    <?php foreach ($gravites as $key => $label): ?>
                    <option value="<?= $key ?>" <?= $incident['gravite'] === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-md-4">
                <label for="statut">Statut</label>
                <select name="statut" id="statut" class="form-control" required>
                    <option value="signale" <?= $incident['statut'] === 'signale' ? 'selected' : '' ?>>Signalé</option>
                    <option value="en_cours" <?= $incident['statut'] === 'en_cours' ? 'selected' : '' ?>>En cours</option>
                    <option value="traite" <?= $incident['statut'] === 'traite' ? 'selected' : '' ?>>Traité</option>
                    <option value="classe" <?= $incident['statut'] === 'classe' ? 'selected' : '' ?>>Classé</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="description">Description</label>
            <textarea name="description" id="description" class="form-control" rows="5" required><?= htmlspecialchars($incident['description']) ?></textarea>
        </div>

        <div class="form-group">
            <label for="commentaire_traitement">Commentaire de traitement</label>
            <textarea name="commentaire_traitement" id="commentaire_traitement" class="form-control" rows="3" placeholder="Observations, décisions prises..."><?= htmlspecialchars($incident['commentaire_traitement'] ?? '') ?></textarea>
        </div>

        <div class="form-row">
            <div class="form-group col-md-6">
                <label for="lieu">Lieu</label>
                <input type="text" name="lieu" id="lieu" class="form-control" value="<?= htmlspecialchars($incident['lieu'] ?? '') ?>">
            </div>
            <div class="form-group col-md-6">
                <label for="temoins">Témoins</label>
                <input type="text" name="temoins" id="temoins" class="form-control" value="<?= htmlspecialchars($incident['temoins'] ?? '') ?>">
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
            <a href="sanctions.php?incident_id=<?= $id ?>" class="btn btn-danger"><i class="fas fa-gavel"></i> Sanctionner</a>
            <a href="detail_incident.php?id=<?= $id ?>" class="btn btn-secondary">Annuler</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
