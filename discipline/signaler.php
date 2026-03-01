<?php
/**
 * M06 – Discipline : Signaler un incident
 */

require_once __DIR__ . '/includes/DisciplineService.php';

$pageTitle = 'Signaler un incident';
$currentPage = 'signaler';
require_once __DIR__ . '/includes/header.php';
requireAuth();

if (!isAdmin() && !isVieScolaire() && !isTeacher()) {
    echo '<div class="alert alert-danger">Accès non autorisé.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$pdo = getPDO();
$service = new DisciplineService($pdo);
$user = getCurrentUser();

$success = '';
$error = '';

// ─── Recherche AJAX d'élèves ──────────────────────────────────
if (isset($_GET['ajax']) && $_GET['ajax'] === 'search_eleve') {
    header('Content-Type: application/json');
    $q = trim($_GET['q'] ?? '');
    echo json_encode($q ? $service->rechercherEleves($q) : []);
    exit;
}

// ─── Traitement du formulaire ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Session expirée. Veuillez réessayer.';
    } else {
        $eleveId = (int)($_POST['eleve_id'] ?? 0);
        $typeIncident = trim($_POST['type_incident'] ?? '');
        $gravite = trim($_POST['gravite'] ?? 'moyen');
        $description = trim($_POST['description'] ?? '');
        $lieu = trim($_POST['lieu'] ?? '');
        $temoins = trim($_POST['temoins'] ?? '');
        $dateIncident = $_POST['date_incident'] ?? date('Y-m-d\TH:i');

        if (!$eleveId || !$typeIncident || !$description) {
            $error = 'Veuillez remplir tous les champs obligatoires.';
        } else {
            try {
                // Déterminer type signaleur
                $sigType = 'administrateur';
                if (isTeacher()) $sigType = 'professeur';
                elseif (isVieScolaire()) $sigType = 'vie_scolaire';

                $incId = $service->createIncident([
                    'eleve_id'        => $eleveId,
                    'date_incident'   => str_replace('T', ' ', $dateIncident),
                    'lieu'            => $lieu,
                    'type_incident'   => $typeIncident,
                    'gravite'         => $gravite,
                    'description'     => $description,
                    'temoins'         => $temoins,
                    'signale_par_id'  => $user['id'],
                    'signale_par_type'=> $sigType,
                    'classe_id'       => null,
                ]);
                $success = "Incident #$incId signalé avec succès.";
            } catch (Exception $e) {
                $error = 'Erreur lors du signalement : ' . $e->getMessage();
            }
        }
    }
}

$typesIncident = DisciplineService::getTypesIncident();
$gravites = DisciplineService::getGravites();
?>

<h1 class="page-title"><i class="fas fa-exclamation-triangle"></i> Signaler un incident</h1>

<?php if ($success): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><i class="fas fa-times-circle"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card form-card">
    <form method="POST" id="form-incident">
        <?= csrfField() ?>

        <div class="form-section">
            <h3><i class="fas fa-user-graduate"></i> Élève concerné</h3>
            <div class="form-group">
                <label for="search_eleve">Rechercher un élève *</label>
                <input type="text" id="search_eleve" class="form-control" placeholder="Tapez le nom ou prénom..." autocomplete="off">
                <div id="search_results" class="search-dropdown"></div>
                <input type="hidden" name="eleve_id" id="eleve_id" required>
                <div id="selected_eleve" class="selected-tag" style="display:none;"></div>
            </div>
        </div>

        <div class="form-section">
            <h3><i class="fas fa-info-circle"></i> Détails de l'incident</h3>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="date_incident">Date et heure *</label>
                    <input type="datetime-local" name="date_incident" id="date_incident"
                           class="form-control" value="<?= date('Y-m-d\TH:i') ?>" required>
                </div>
                <div class="form-group col-md-6">
                    <label for="lieu">Lieu</label>
                    <input type="text" name="lieu" id="lieu" class="form-control"
                           placeholder="Ex : Salle 204, Cour, Cantine...">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="type_incident">Type d'incident *</label>
                    <select name="type_incident" id="type_incident" class="form-control" required>
                        <option value="">— Choisir —</option>
                        <?php foreach ($typesIncident as $key => $label): ?>
                        <option value="<?= $key ?>"><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-6">
                    <label for="gravite">Gravité *</label>
                    <select name="gravite" id="gravite" class="form-control" required>
                        <?php foreach ($gravites as $key => $label): ?>
                        <option value="<?= $key ?>" <?= $key === 'moyen' ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label for="description">Description détaillée *</label>
                <textarea name="description" id="description" class="form-control" rows="5"
                          required placeholder="Décrivez les faits de manière factuelle..."></textarea>
            </div>
            <div class="form-group">
                <label for="temoins">Témoins</label>
                <input type="text" name="temoins" id="temoins" class="form-control"
                       placeholder="Noms des témoins éventuels">
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-danger"><i class="fas fa-exclamation-triangle"></i> Signaler l'incident</button>
            <a href="incidents.php" class="btn btn-secondary">Annuler</a>
        </div>
    </form>
</div>

<script>
// Recherche élève avec auto-complétion
(function() {
    const input = document.getElementById('search_eleve');
    const results = document.getElementById('search_results');
    const hiddenId = document.getElementById('eleve_id');
    const selectedTag = document.getElementById('selected_eleve');
    let debounce = null;

    input.addEventListener('input', function() {
        clearTimeout(debounce);
        const q = this.value.trim();
        if (q.length < 2) { results.innerHTML = ''; results.style.display = 'none'; return; }
        debounce = setTimeout(() => {
            fetch('signaler.php?ajax=search_eleve&q=' + encodeURIComponent(q))
                .then(r => r.json())
                .then(data => {
                    if (!data.length) {
                        results.innerHTML = '<div class="search-item search-empty">Aucun élève trouvé</div>';
                    } else {
                        results.innerHTML = data.map(e =>
                            `<div class="search-item" data-id="${e.id}" data-name="${e.prenom} ${e.nom} (${e.classe || '?'})">`
                            + `<strong>${e.prenom} ${e.nom}</strong> <span class="search-classe">${e.classe || ''}</span></div>`
                        ).join('');
                    }
                    results.style.display = 'block';
                });
        }, 250);
    });

    results.addEventListener('click', function(e) {
        const item = e.target.closest('.search-item');
        if (!item || item.classList.contains('search-empty')) return;
        hiddenId.value = item.dataset.id;
        selectedTag.textContent = item.dataset.name;
        selectedTag.style.display = 'inline-block';
        input.value = '';
        results.style.display = 'none';
    });

    // Supprimer la sélection
    selectedTag.addEventListener('click', function() {
        hiddenId.value = '';
        this.style.display = 'none';
    });

    document.addEventListener('click', function(e) {
        if (!results.contains(e.target) && e.target !== input) {
            results.style.display = 'none';
        }
    });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
