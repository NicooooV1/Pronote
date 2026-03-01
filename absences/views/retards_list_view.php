<?php
/**
 * Vue liste des retards — incluse depuis absences.php?type=retards.
 * Variable disponible: $retards (tableau de retards).
 */

// Pagination
$pagination = AbsenceHelper::paginate($retards, $filters['page'] ?? 1, 20);
$retards_page = $pagination['items'];
$totalPages = $pagination['total_pages'];
$currentPageNum = $pagination['current_page'];
?>
<div class="absences-list">
    <div class="list-header">
        <div class="list-row header-row retard-grid">
            <?php if (isAdmin() || isVieScolaire() || isTeacher()): ?>
                <div class="list-cell">Élève</div>
                <div class="list-cell">Classe</div>
            <?php endif; ?>
            <div class="list-cell">Date</div>
            <div class="list-cell">Durée</div>
            <div class="list-cell">Motif</div>
            <div class="list-cell">Justifié</div>
            <div class="list-actions">Actions</div>
        </div>
    </div>
    
    <div class="list-body">
        <?php foreach ($retards_page as $retard): ?>
            <div class="list-row retard-grid <?= $retard['justifie'] ? 'justified' : 'not-justified' ?>">
                <?php if (isAdmin() || isVieScolaire() || isTeacher()): ?>
                    <div class="list-cell">
                        <strong><?= htmlspecialchars($retard['nom']) ?></strong> <?= htmlspecialchars($retard['prenom']) ?>
                    </div>
                    <div class="list-cell"><?= htmlspecialchars($retard['classe']) ?></div>
                <?php endif; ?>
                <div class="list-cell">
                    <?= date('d/m/Y H:i', strtotime($retard['date_retard'] ?? $retard['date'] ?? '')) ?>
                </div>
                <div class="list-cell">
                    <?= intval($retard['duree'] ?? 0) ?> min
                </div>
                <div class="list-cell">
                    <?= !empty($retard['motif']) ? htmlspecialchars($retard['motif']) : '<em>Non spécifié</em>' ?>
                </div>
                <div class="list-cell">
                    <?php if ($retard['justifie']): ?>
                        <span class="badge badge-success">Oui</span>
                    <?php else: ?>
                        <span class="badge badge-danger">Non</span>
                    <?php endif; ?>
                </div>
                <div class="list-actions">
                    <div class="action-buttons">
                        <?php if (canManageAbsences()): ?>
                        <a href="modifier_retard.php?id=<?= $retard['id'] ?>" class="btn-icon" title="Modifier">
                            <i class="fas fa-edit"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => max(1, $currentPageNum - 1)])) ?>" 
           class="page-link" <?= $currentPageNum === 1 ? 'disabled' : '' ?>>
            <i class="fas fa-chevron-left"></i> Précédent
        </a>
        <div class="page-numbers">
            <?php for ($i = max(1, $currentPageNum - 2); $i <= min($totalPages, $currentPageNum + 2); $i++): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                   class="page-number <?= $i === $currentPageNum ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => min($totalPages, $currentPageNum + 1)])) ?>" 
           class="page-link" <?= $currentPageNum === $totalPages ? 'disabled' : '' ?>>
            Suivant <i class="fas fa-chevron-right"></i>
        </a>
    </div>
    <?php endif; ?>
</div>
