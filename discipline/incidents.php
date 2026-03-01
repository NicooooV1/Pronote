<?php
/**
 * M06 – Discipline : Liste des incidents
 */

require_once __DIR__ . '/includes/DisciplineService.php';

$pageTitle = 'Incidents';
$currentPage = 'incidents';
require_once __DIR__ . '/includes/header.php';
requireAuth();

// Seuls admin, vie_scolaire, profs peuvent accéder
if (!isAdmin() && !isVieScolaire() && !isTeacher()) {
    echo '<div class="alert alert-danger">Accès non autorisé.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$pdo = getPDO();
$service = new DisciplineService($pdo);
$user = getCurrentUser();

// ─── Filtres ─────────────────────────────────────────────────
$filtreStatut   = $_GET['statut'] ?? '';
$filtreGravite  = $_GET['gravite'] ?? '';
$filtreClasse   = $_GET['classe'] ?? '';
$filtreDateDeb  = $_GET['date_debut'] ?? '';
$filtreDateFin  = $_GET['date_fin'] ?? '';

$filters = [];
if ($filtreStatut)  $filters['statut']     = $filtreStatut;
if ($filtreGravite) $filters['gravite']    = $filtreGravite;
if ($filtreClasse)  $filters['classe']     = $filtreClasse;
if ($filtreDateDeb) $filters['date_debut'] = $filtreDateDeb;
if ($filtreDateFin) $filters['date_fin']   = $filtreDateFin;

$incidents = $service->getIncidents($filters);
$classes   = $service->getClasses();
$gravites  = DisciplineService::getGravites();
$types     = DisciplineService::getTypesIncident();
?>

<h1 class="page-title"><i class="fas fa-exclamation-triangle"></i> Incidents disciplinaires</h1>

<!-- Filtres -->
<div class="filter-bar card">
    <form method="GET" class="filter-form">
        <div class="filter-group">
            <label for="statut">Statut</label>
            <select name="statut" id="statut">
                <option value="">Tous</option>
                <option value="signale" <?= $filtreStatut === 'signale' ? 'selected' : '' ?>>Signalé</option>
                <option value="en_cours" <?= $filtreStatut === 'en_cours' ? 'selected' : '' ?>>En cours</option>
                <option value="traite" <?= $filtreStatut === 'traite' ? 'selected' : '' ?>>Traité</option>
                <option value="classe" <?= $filtreStatut === 'classe' ? 'selected' : '' ?>>Classé</option>
            </select>
        </div>
        <div class="filter-group">
            <label for="gravite">Gravité</label>
            <select name="gravite" id="gravite">
                <option value="">Toutes</option>
                <?php foreach ($gravites as $key => $label): ?>
                <option value="<?= $key ?>" <?= $filtreGravite === $key ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label for="classe">Classe</label>
            <select name="classe" id="classe">
                <option value="">Toutes</option>
                <?php foreach ($classes as $c): ?>
                <option value="<?= htmlspecialchars($c['nom']) ?>" <?= $filtreClasse === $c['nom'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nom']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label for="date_debut">Du</label>
            <input type="date" name="date_debut" id="date_debut" value="<?= htmlspecialchars($filtreDateDeb) ?>">
        </div>
        <div class="filter-group">
            <label for="date_fin">Au</label>
            <input type="date" name="date_fin" id="date_fin" value="<?= htmlspecialchars($filtreDateFin) ?>">
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filtrer</button>
            <a href="incidents.php" class="btn btn-secondary">Réinitialiser</a>
        </div>
    </form>
</div>

<!-- Résumé -->
<div class="stats-row">
    <div class="stat-card stat-total">
        <div class="stat-number"><?= count($incidents) ?></div>
        <div class="stat-label">Incidents</div>
    </div>
    <?php
    $nbGrave = count(array_filter($incidents, fn($i) => in_array($i['gravite'], ['grave', 'tres_grave'])));
    $nbSignale = count(array_filter($incidents, fn($i) => $i['statut'] === 'signale'));
    ?>
    <div class="stat-card stat-danger">
        <div class="stat-number"><?= $nbGrave ?></div>
        <div class="stat-label">Graves / Très graves</div>
    </div>
    <div class="stat-card stat-warning">
        <div class="stat-number"><?= $nbSignale ?></div>
        <div class="stat-label">À traiter</div>
    </div>
</div>

<!-- Liste -->
<?php if (empty($incidents)): ?>
    <div class="empty-state">
        <i class="fas fa-check-circle"></i>
        <p>Aucun incident trouvé.</p>
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
                <th>Gravité</th>
                <th>Statut</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($incidents as $inc): ?>
            <tr>
                <td><?= date('d/m/Y H:i', strtotime($inc['date_incident'])) ?></td>
                <td>
                    <a href="fiche_eleve.php?id=<?= $inc['eleve_id'] ?>" class="link-eleve">
                        <?= htmlspecialchars($inc['eleve_prenom'] . ' ' . $inc['eleve_nom']) ?>
                    </a>
                </td>
                <td><?= htmlspecialchars($inc['eleve_classe'] ?? '-') ?></td>
                <td>
                    <span class="badge badge-type"><?= htmlspecialchars($types[$inc['type_incident']] ?? $inc['type_incident']) ?></span>
                </td>
                <td>
                    <span class="badge badge-gravite badge-gravite-<?= htmlspecialchars($inc['gravite']) ?>">
                        <?= htmlspecialchars($gravites[$inc['gravite']] ?? $inc['gravite']) ?>
                    </span>
                </td>
                <td>
                    <span class="badge badge-statut badge-statut-<?= htmlspecialchars($inc['statut']) ?>">
                        <?= ucfirst(str_replace('_', ' ', $inc['statut'])) ?>
                    </span>
                </td>
                <td class="actions-cell">
                    <a href="detail_incident.php?id=<?= $inc['id'] ?>" class="btn btn-sm btn-outline" title="Voir">
                        <i class="fas fa-eye"></i>
                    </a>
                    <?php if (isAdmin() || isVieScolaire()): ?>
                    <a href="traiter_incident.php?id=<?= $inc['id'] ?>" class="btn btn-sm btn-primary" title="Traiter">
                        <i class="fas fa-gavel"></i>
                    </a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
