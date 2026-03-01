<?php
/**
 * justificatifs.php — Liste des justificatifs d'absences
 * Refactorisé : AbsenceRepository + AbsenceHelper, suppression FILTER_SANITIZE_STRING,
 * suppression établissement.json, pagination via AbsenceHelper::paginate().
 */
ob_start();

require_once __DIR__ . '/../API/core.php';
require_once __DIR__ . '/includes/AbsenceRepository.php';
require_once __DIR__ . '/includes/AbsenceHelper.php';

if (!isLoggedIn() || !canManageAbsences()) {
    header('Location: ' . LOGIN_URL);
    exit;
}

$pdo  = getPDO();
$repo = new AbsenceRepository($pdo);
$user = getCurrentUser();

$user_fullname = getUserFullName();
$user_role     = getUserRole();
$user_initials = getUserInitials();

$pageTitle   = 'Justificatifs d\'absence';
$currentPage = 'justificatifs';

// --- Filtres ---
$date_debut = AbsenceHelper::sanitizeDate($_GET['date_debut'] ?? '') ?: date('Y-m-d', strtotime('-30 days'));
$date_fin   = AbsenceHelper::sanitizeDate($_GET['date_fin'] ?? '')   ?: date('Y-m-d');
$classe     = AbsenceHelper::sanitize($_GET['classe'] ?? '');
$traite     = AbsenceHelper::sanitize($_GET['traite'] ?? '');

$date_debut_formattee = date('d/m/Y', strtotime($date_debut));
$date_fin_formattee   = date('d/m/Y', strtotime($date_fin));

// --- Récupération des justificatifs ---
$justificatifs = [];
if (isAdmin() || isVieScolaire()) {
    $justificatifs = $repo->getJustificatifs([
        'date_debut' => $date_debut,
        'date_fin'   => $date_fin,
        'classe'     => $classe,
        'traite'     => $traite
    ]);
}

// --- Pagination ---
$page      = max(1, intval($_GET['page'] ?? 1));
$paginated = AbsenceHelper::paginate($justificatifs, $page, 20);

// --- Classes pour le filtre ---
$classes = AbsenceHelper::getClassesList();

// --- Succès via redirect ---
$success = isset($_GET['success']);

include 'includes/header.php';
?>

<?php if ($success): ?>
<div class="alert alert-success">
    <i class="fas fa-check-circle"></i>
    <span>Le justificatif a été traité avec succès.</span>
</div>
<?php endif; ?>

<!-- Bannière -->
<div class="welcome-banner">
    <div class="welcome-content">
        <h2>Gestion des Justificatifs</h2>
        <p>Consultez et traitez les justificatifs d'absences soumis par les élèves et leurs parents.</p>
    </div>
    <div class="welcome-icon"><i class="fas fa-file-alt"></i></div>
</div>

<!-- Filtres -->
<div class="filters-bar">
    <form id="filter-form" class="filter-form" method="get" action="justificatifs.php">
        <div class="filter-item">
            <label for="date_debut" class="filter-label">Du</label>
            <input type="date" id="date_debut" name="date_debut" value="<?= $date_debut ?>" max="<?= date('Y-m-d') ?>">
        </div>
        <div class="filter-item">
            <label for="date_fin" class="filter-label">Au</label>
            <input type="date" id="date_fin" name="date_fin" value="<?= $date_fin ?>" max="<?= date('Y-m-d') ?>">
        </div>
        <div class="filter-item">
            <label for="classe" class="filter-label">Classe</label>
            <select id="classe" name="classe">
                <option value="">Toutes les classes</option>
                <?php foreach ($classes as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>" <?= $classe === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-item">
            <label for="traite" class="filter-label">Statut</label>
            <select id="traite" name="traite">
                <option value="">Tous</option>
                <option value="oui" <?= $traite === 'oui' ? 'selected' : '' ?>>Traités</option>
                <option value="non" <?= $traite === 'non' ? 'selected' : '' ?>>Non traités</option>
            </select>
        </div>
        <div class="filter-buttons">
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filtrer</button>
            <a href="justificatifs.php" class="btn btn-secondary"><i class="fas fa-redo"></i> Réinitialiser</a>
        </div>
    </form>
</div>

<!-- Contenu principal -->
<div class="content-container">
    <div class="content-header">
        <h2>Justificatifs du <?= $date_debut_formattee ?> au <?= $date_fin_formattee ?> (<?= count($justificatifs) ?>)</h2>
    </div>

    <div class="content-body">
        <?php if (empty($paginated['items'])): ?>
            <div class="no-data-message">
                <i class="fas fa-file-alt"></i>
                <p>Aucun justificatif ne correspond aux critères sélectionnés.</p>
            </div>
        <?php else: ?>
            <div class="justificatifs-list absences-list">
                <div class="list-header">
                    <div class="list-row header-row">
                        <div class="list-cell">Élève</div>
                        <div class="list-cell">Classe</div>
                        <div class="list-cell">Date de dépôt</div>
                        <div class="list-cell">Période</div>
                        <div class="list-cell">Motif</div>
                        <div class="list-cell">Statut</div>
                        <div class="list-actions">Actions</div>
                    </div>
                </div>

                <div class="list-body">
                    <?php foreach ($paginated['items'] as $j): ?>
                    <div class="list-row justificatif-row <?= $j['traite'] ? 'traite' : 'non-traite' ?>">
                        <div class="list-cell"><strong><?= htmlspecialchars($j['nom']) ?></strong> <?= htmlspecialchars($j['prenom']) ?></div>
                        <div class="list-cell"><?= htmlspecialchars($j['classe']) ?></div>
                        <div class="list-cell"><?= isset($j['date_soumission']) ? date('d/m/Y', strtotime($j['date_soumission'])) : 'N/A' ?></div>
                        <div class="list-cell">
                            Du <?= date('d/m/Y', strtotime($j['date_debut_absence'])) ?><br>
                            au <?= date('d/m/Y', strtotime($j['date_fin_absence'])) ?>
                        </div>
                        <div class="list-cell"><?= htmlspecialchars($j['motif'] ?? 'Non spécifié') ?></div>
                        <div class="list-cell">
                            <?php if ($j['traite']): ?>
                                <span class="badge badge-success">Traité</span>
                                <span class="badge <?= $j['approuve'] ? 'badge-success' : 'badge-danger' ?>">
                                    <?= $j['approuve'] ? 'Approuvé' : 'Rejeté' ?>
                                </span>
                            <?php else: ?>
                                <span class="badge badge-warning">En attente</span>
                            <?php endif; ?>
                        </div>
                        <div class="list-actions">
                            <div class="action-buttons">
                                <a href="details_justificatif.php?id=<?= $j['id'] ?>" class="btn-icon" title="Voir les détails"><i class="fas fa-eye"></i></a>
                                <?php if (!$j['traite']): ?>
                                <a href="traiter_justificatif.php?id=<?= $j['id'] ?>" class="btn-icon" title="Traiter"><i class="fas fa-check-circle"></i></a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($paginated['totalPages'] > 1): ?>
            <div class="pagination">
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => max(1, $paginated['currentPage'] - 1)])) ?>" class="page-link" <?= $paginated['currentPage'] === 1 ? 'disabled' : '' ?>>
                    <i class="fas fa-chevron-left"></i> Précédent
                </a>
                <div class="page-numbers">
                    <?php for ($i = max(1, $paginated['currentPage'] - 2); $i <= min($paginated['totalPages'], $paginated['currentPage'] + 2); $i++): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="page-number <?= $i === $paginated['currentPage'] ? 'active' : '' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                </div>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => min($paginated['totalPages'], $paginated['currentPage'] + 1)])) ?>" class="page-link" <?= $paginated['currentPage'] === $paginated['totalPages'] ? 'disabled' : '' ?>>
                    Suivant <i class="fas fa-chevron-right"></i>
                </a>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php
include 'includes/footer.php';
ob_end_flush();
