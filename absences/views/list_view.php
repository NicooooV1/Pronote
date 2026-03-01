<?php
/**
 * views/list_view.php — Vue liste des absences
 * Refactorisé : utilise AbsenceHelper::paginate(), inline CSS supprimé → absences.css.
 * Variables attendues depuis absences.php : $absences, $user_role.
 */

if (empty($absences)) {
    echo '<div class="no-data-message">
        <i class="fas fa-info-circle"></i>
        <p>Aucune absence ne correspond aux critères sélectionnés.</p>
    </div>';
    return;
}

// Tri
$sort  = $_GET['sort']  ?? 'date';
$order = $_GET['order'] ?? 'desc';

usort($absences, function ($a, $b) use ($sort, $order) {
    switch ($sort) {
        case 'nom':
            $result = strcmp($a['nom'], $b['nom']);
            break;
        case 'classe':
            $result = strcmp($a['classe'], $b['classe']);
            break;
        case 'duree':
            $result = (strtotime($a['date_fin']) - strtotime($a['date_debut']))
                    - (strtotime($b['date_fin']) - strtotime($b['date_debut']));
            break;
        default:
            $result = strtotime($a['date_debut']) - strtotime($b['date_debut']);
    }
    return $order === 'asc' ? $result : -$result;
});

// Pagination via Helper
$page      = max(1, intval($_GET['page'] ?? 1));
$paginated = AbsenceHelper::paginate($absences, $page, 20);
?>
<div class="content-section">
    <div class="absences-list">
        <div class="list-header">
            <div class="list-row header-row">
                <?php if (isAdmin() || isVieScolaire() || isTeacher()): ?>
                    <div class="list-cell">Élève</div>
                    <div class="list-cell">Classe</div>
                <?php endif; ?>
                <div class="list-cell">Date</div>
                <div class="list-cell">Durée</div>
                <div class="list-cell">Type</div>
                <div class="list-cell">Statut</div>
                <div class="list-actions">Actions</div>
            </div>
        </div>

        <div class="list-body">
            <?php foreach ($paginated['items'] as $absence): ?>
            <?php
                $dateDebut = new DateTime($absence['date_debut']);
                $dateFin   = new DateTime($absence['date_fin']);
                $memeJour  = $dateDebut->format('Y-m-d') === $dateFin->format('Y-m-d');
                $duree     = AbsenceHelper::formatDurationBetween($absence['date_debut'], $absence['date_fin']);
            ?>
            <div class="list-row absence-item">
                <?php if (isAdmin() || isVieScolaire() || isTeacher()): ?>
                    <div class="list-cell"><strong><?= htmlspecialchars($absence['nom']) ?></strong> <?= htmlspecialchars($absence['prenom']) ?></div>
                    <div class="list-cell"><?= htmlspecialchars($absence['classe']) ?></div>
                <?php endif; ?>

                <div class="list-cell">
                    <?= $dateDebut->format('d/m/Y') ?>
                    <?php if (!$memeJour): ?> au <?= $dateFin->format('d/m/Y') ?><?php endif; ?>
                </div>

                <div class="list-cell">
                    <div><?= $dateDebut->format('H:i') ?> - <?= $dateFin->format('H:i') ?></div>
                    <div class="text-small text-muted">(<?= $duree ?>)</div>
                </div>

                <div class="list-cell">
                    <span class="badge badge-<?= htmlspecialchars($absence['type_absence']) ?>">
                        <?= AbsenceHelper::typeLabel($absence['type_absence']) ?>
                    </span>
                </div>

                <div class="list-cell">
                    <?php if ($absence['justifie']): ?>
                        <span class="badge badge-success">Justifiée</span>
                    <?php else: ?>
                        <span class="badge badge-danger">Non justifiée</span>
                    <?php endif; ?>
                </div>

                <div class="list-actions">
                    <a href="details_absence.php?id=<?= $absence['id'] ?>" class="btn-icon" title="Voir les détails"><i class="fas fa-eye"></i></a>
                    <?php if (canManageAbsences()): ?>
                        <a href="modifier_absence.php?id=<?= $absence['id'] ?>" class="btn-icon" title="Modifier"><i class="fas fa-edit"></i></a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
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
    </div>
</div>
