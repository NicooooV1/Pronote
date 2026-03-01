<?php
/**
 * M06 – Discipline : Gestion des sanctions
 */

require_once __DIR__ . '/includes/DisciplineService.php';

$pageTitle = 'Sanctions';
$currentPage = 'sanctions';
require_once __DIR__ . '/includes/header.php';
requireAuth();

if (!isAdmin() && !isVieScolaire()) {
    echo '<div class="alert alert-danger">Seuls les administrateurs et la vie scolaire peuvent gérer les sanctions.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$pdo = getPDO();
$service = new DisciplineService($pdo);
$user = getCurrentUser();

$success = '';
$error = '';

// ─── Création d'une sanction ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'creer_sanction') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Session expirée.';
    } else {
        try {
            $sigType = isAdmin() ? 'administrateur' : 'vie_scolaire';
            $sanctionId = $service->createSanction([
                'incident_id'    => (int)($_POST['incident_id'] ?? 0) ?: null,
                'eleve_id'       => (int)$_POST['eleve_id'],
                'type_sanction'  => $_POST['type_sanction'],
                'motif'          => $_POST['motif'],
                'date_sanction'  => $_POST['date_sanction'] ?? date('Y-m-d'),
                'date_debut'     => $_POST['date_debut'] ?? null,
                'date_fin'       => $_POST['date_fin'] ?? null,
                'duree'          => $_POST['duree'] ?? null,
                'lieu_retenue'   => $_POST['lieu_retenue'] ?? null,
                'convocation_parent' => isset($_POST['convocation_parent']) ? 1 : 0,
                'decide_par_id'  => $user['id'],
                'decide_par_type'=> $sigType,
                'commentaire'    => $_POST['commentaire'] ?? null,
            ]);

            // Mettre à jour le statut de l'incident si lié
            if (!empty($_POST['incident_id'])) {
                $service->updateIncident((int)$_POST['incident_id'], [
                    'type_incident' => $service->getIncident((int)$_POST['incident_id'])['type_incident'],
                    'gravite'       => $service->getIncident((int)$_POST['incident_id'])['gravite'],
                    'description'   => $service->getIncident((int)$_POST['incident_id'])['description'],
                    'statut'        => 'traite',
                ]);
            }

            $success = "Sanction #$sanctionId créée avec succès.";
        } catch (Exception $e) {
            $error = 'Erreur : ' . $e->getMessage();
        }
    }
}

// ─── Filtres ──────────────────────────────────────────────────
$filtreType   = $_GET['type_sanction'] ?? '';
$filtreClasse = $_GET['classe'] ?? '';
$filters = [];
if ($filtreType)   $filters['type_sanction'] = $filtreType;
if ($filtreClasse) $filters['classe']        = $filtreClasse;

$sanctions = $service->getSanctions($filters);
$classes   = $service->getClasses();
$typesSanction = DisciplineService::getTypesSanction();

// Pour le formulaire de création — préremplir si on vient d'un incident
$fromIncident = null;
if (!empty($_GET['incident_id'])) {
    $fromIncident = $service->getIncident((int)$_GET['incident_id']);
}
?>

<h1 class="page-title"><i class="fas fa-gavel"></i> Sanctions</h1>

<?php if ($success): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><i class="fas fa-times-circle"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Modal/Formulaire de création -->
<?php if ($fromIncident || isset($_GET['new'])): ?>
<div class="card form-card" id="form-section">
    <h2><i class="fas fa-plus"></i> Nouvelle sanction</h2>
    <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="creer_sanction">

        <?php if ($fromIncident): ?>
        <input type="hidden" name="incident_id" value="<?= $fromIncident['id'] ?>">
        <input type="hidden" name="eleve_id" value="<?= $fromIncident['eleve_id'] ?>">
        <div class="alert alert-info">
            <strong>Incident #<?= $fromIncident['id'] ?></strong> — 
            <?= htmlspecialchars($fromIncident['eleve_prenom'] . ' ' . $fromIncident['eleve_nom']) ?>
            (<?= htmlspecialchars($fromIncident['eleve_classe'] ?? '-') ?>)
            <br><small><?= htmlspecialchars($fromIncident['description']) ?></small>
        </div>
        <?php else: ?>
        <div class="form-group">
            <label for="eleve_id_sanction">Élève *</label>
            <input type="text" id="search_eleve_sanction" class="form-control" placeholder="Rechercher un élève..." autocomplete="off">
            <div id="search_results_sanction" class="search-dropdown"></div>
            <input type="hidden" name="eleve_id" id="eleve_id_sanction" required>
            <div id="selected_eleve_sanction" class="selected-tag" style="display:none;"></div>
        </div>
        <?php endif; ?>

        <div class="form-row">
            <div class="form-group col-md-4">
                <label for="type_sanction">Type de sanction *</label>
                <select name="type_sanction" id="type_sanction" class="form-control" required>
                    <option value="">— Choisir —</option>
                    <?php foreach ($typesSanction as $key => $label): ?>
                    <option value="<?= $key ?>"><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group col-md-4">
                <label for="date_sanction">Date *</label>
                <input type="date" name="date_sanction" id="date_sanction" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group col-md-4">
                <label for="duree">Durée (jours)</label>
                <input type="number" name="duree" id="duree" class="form-control" min="0">
            </div>
        </div>

        <div class="form-group">
            <label for="motif">Motif *</label>
            <textarea name="motif" id="motif" class="form-control" rows="3" required
                      placeholder="Motif de la sanction..."><?= $fromIncident ? htmlspecialchars($fromIncident['description']) : '' ?></textarea>
        </div>

        <div class="form-row" id="retenue-fields" style="display:none;">
            <div class="form-group col-md-4">
                <label for="date_debut">Date début</label>
                <input type="date" name="date_debut" id="date_debut" class="form-control">
            </div>
            <div class="form-group col-md-4">
                <label for="date_fin">Date fin</label>
                <input type="date" name="date_fin" id="date_fin" class="form-control">
            </div>
            <div class="form-group col-md-4">
                <label for="lieu_retenue">Lieu retenue</label>
                <input type="text" name="lieu_retenue" id="lieu_retenue" class="form-control" placeholder="Salle de retenue">
            </div>
        </div>

        <div class="form-group">
            <label for="commentaire">Commentaire</label>
            <textarea name="commentaire" id="commentaire" class="form-control" rows="2"></textarea>
        </div>

        <div class="form-group">
            <label class="checkbox-label">
                <input type="checkbox" name="convocation_parent">
                Convoquer les parents
            </label>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Créer la sanction</button>
            <a href="sanctions.php" class="btn btn-secondary">Annuler</a>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- Filtres -->
<div class="filter-bar card">
    <form method="GET" class="filter-form">
        <div class="filter-group">
            <label for="type_sanction_f">Type</label>
            <select name="type_sanction" id="type_sanction_f">
                <option value="">Tous</option>
                <?php foreach ($typesSanction as $key => $label): ?>
                <option value="<?= $key ?>" <?= $filtreType === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label for="classe_f">Classe</label>
            <select name="classe" id="classe_f">
                <option value="">Toutes</option>
                <?php foreach ($classes as $c): ?>
                <option value="<?= htmlspecialchars($c['nom']) ?>" <?= $filtreClasse === $c['nom'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nom']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filtrer</button>
            <a href="sanctions.php" class="btn btn-secondary">Réinitialiser</a>
        </div>
    </form>
</div>

<!-- Liste des sanctions -->
<?php if (empty($sanctions)): ?>
    <div class="empty-state">
        <i class="fas fa-check-circle"></i>
        <p>Aucune sanction trouvée.</p>
    </div>
<?php else: ?>
<div class="table-responsive">
    <table class="data-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Élève</th>
                <th>Classe</th>
                <th>Type</th>
                <th>Motif</th>
                <th>Durée</th>
                <th>Parent</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($sanctions as $s): ?>
            <tr>
                <td><?= date('d/m/Y', strtotime($s['date_sanction'])) ?></td>
                <td>
                    <a href="fiche_eleve.php?id=<?= $s['eleve_id'] ?>" class="link-eleve">
                        <?= htmlspecialchars($s['eleve_prenom'] . ' ' . $s['eleve_nom']) ?>
                    </a>
                </td>
                <td><?= htmlspecialchars($s['eleve_classe'] ?? '-') ?></td>
                <td><span class="badge badge-sanction"><?= htmlspecialchars($typesSanction[$s['type_sanction']] ?? $s['type_sanction']) ?></span></td>
                <td class="text-truncate" title="<?= htmlspecialchars($s['motif']) ?>"><?= htmlspecialchars(mb_substr($s['motif'], 0, 60)) ?>...</td>
                <td><?= $s['duree'] ? $s['duree'] . 'j' : '-' ?></td>
                <td><?= $s['convocation_parent'] ? '<i class="fas fa-check text-danger"></i>' : '-' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<script>
// Afficher les champs de retenue si type = exclusion/retenue
document.getElementById('type_sanction')?.addEventListener('change', function() {
    const show = ['exclusion_temporaire', 'retenue', 'exclusion_cours'].includes(this.value);
    document.getElementById('retenue-fields').style.display = show ? 'flex' : 'none';
});

// Recherche d'élèves (si formulaire libre sans incident)
<?php if (!$fromIncident && isset($_GET['new'])): ?>
(function() {
    const input = document.getElementById('search_eleve_sanction');
    const results = document.getElementById('search_results_sanction');
    const hiddenId = document.getElementById('eleve_id_sanction');
    const selectedTag = document.getElementById('selected_eleve_sanction');
    let debounce = null;
    if (!input) return;

    input.addEventListener('input', function() {
        clearTimeout(debounce);
        const q = this.value.trim();
        if (q.length < 2) { results.innerHTML = ''; results.style.display = 'none'; return; }
        debounce = setTimeout(() => {
            fetch('signaler.php?ajax=search_eleve&q=' + encodeURIComponent(q))
                .then(r => r.json())
                .then(data => {
                    results.innerHTML = data.map(e =>
                        `<div class="search-item" data-id="${e.id}" data-name="${e.prenom} ${e.nom} (${e.classe || '?'})">`
                        + `<strong>${e.prenom} ${e.nom}</strong> <span class="search-classe">${e.classe || ''}</span></div>`
                    ).join('') || '<div class="search-item search-empty">Aucun résultat</div>';
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

    selectedTag.addEventListener('click', function() {
        hiddenId.value = '';
        this.style.display = 'none';
    });
})();
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
