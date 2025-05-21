<?php
// Fichier inclus depuis absences.php

// Vérifier si nous avons des absences à afficher
if (empty($absences)) {
    echo '<div class="no-data-message">
        <i class="fas fa-info-circle"></i>
        <p>Aucune absence ne correspond aux critères sélectionnés.</p>
    </div>';
    return;
}

// Tri des absences (par défaut par date)
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'date';
$order = isset($_GET['order']) ? $_GET['order'] : 'desc';

if ($sort === 'nom') {
    usort($absences, function($a, $b) use ($order) {
        $result = strcmp($a['nom'], $b['nom']);
        return $order === 'asc' ? $result : -$result;
    });
} elseif ($sort === 'classe') {
    usort($absences, function($a, $b) use ($order) {
        $result = strcmp($a['classe'], $b['classe']);
        return $order === 'asc' ? $result : -$result;
    });
} elseif ($sort === 'duree') {
    usort($absences, function($a, $b) use ($order) {
        $a_duree = strtotime($a['date_fin']) - strtotime($a['date_debut']);
        $b_duree = strtotime($b['date_fin']) - strtotime($b['date_debut']);
        $result = $a_duree - $b_duree;
        return $order === 'asc' ? $result : -$result;
    });
} else { // Par date
    usort($absences, function($a, $b) use ($order) {
        $result = strtotime($a['date_debut']) - strtotime($b['date_debut']);
        return $order === 'asc' ? $result : -$result;
    });
}

// Pagination
$absences_par_page = 20;
$nombre_absences = count($absences);
$nombre_pages = ceil($nombre_absences / $absences_par_page);
$page_courante = isset($_GET['page']) ? max(1, min($nombre_pages, intval($_GET['page']))) : 1;
$debut_absences = ($page_courante - 1) * $absences_par_page;
$absences_page = array_slice($absences, $debut_absences, $absences_par_page);
?>

<?php
/**
 * Vue Liste pour le module Absences
 * Format tabulaire avec options d'action
 */
?>
<div class="absences-list">
    <!-- En-tête de liste style accueil -->
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
        <?php foreach ($absences as $absence): ?>
            <?php
            // Formatage des dates pour l'affichage
            $dateDebut = new DateTime($absence['date_debut']);
            $dateFin = new DateTime($absence['date_fin']);
            $memeJour = $dateDebut->format('Y-m-d') === $dateFin->format('Y-m-d');
            
            // Calcul de la durée
            $interval = $dateDebut->diff($dateFin);
            $duree = '';
            
            if ($interval->d > 0) {
                $duree .= $interval->d . ' jour' . ($interval->d > 1 ? 's' : '');
                if ($interval->h > 0 || $interval->i > 0) $duree .= ' et ';
            }
            
            if ($interval->h > 0) {
                $duree .= $interval->h . ' heure' . ($interval->h > 1 ? 's' : '');
                if ($interval->i > 0) $duree .= ' et ';
            }
            
            if ($interval->i > 0 || empty($duree)) {
                $duree .= $interval->i . ' minute' . ($interval->i > 1 ? 's' : '');
            }
            ?>
            <div class="list-row absence-item">
                <?php if (isAdmin() || isVieScolaire() || isTeacher()): ?>
                    <div class="list-cell">
                        <strong><?= htmlspecialchars($absence['nom']) ?></strong> <?= htmlspecialchars($absence['prenom']) ?>
                    </div>
                    <div class="list-cell"><?= htmlspecialchars($absence['classe']) ?></div>
                <?php endif; ?>
                
                <div class="list-cell">
                    <?= $dateDebut->format('d/m/Y') ?>
                    <?php if (!$memeJour): ?>
                        au <?= $dateFin->format('d/m/Y') ?>
                    <?php endif; ?>
                </div>
                
                <div class="list-cell">
                    <div>
                        <?= $dateDebut->format('H:i') ?> - <?= $dateFin->format('H:i') ?>
                    </div>
                    <div class="text-small text-muted">
                        (<?= $duree ?>)
                    </div>
                </div>
                
                <div class="list-cell">
                    <span class="badge badge-<?= htmlspecialchars($absence['type_absence']) ?>">
                        <?php
                        switch ($absence['type_absence']) {
                            case 'cours':
                                echo 'Cours';
                                break;
                            case 'demi-journee':
                                echo 'Demi-journée';
                                break;
                            case 'journee':
                                echo 'Journée';
                                break;
                            default:
                                echo htmlspecialchars($absence['type_absence']);
                        }
                        ?>
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
                    <a href="details_absence.php?id=<?= $absence['id'] ?>" class="btn-icon" title="Voir les détails">
                        <i class="fas fa-eye"></i>
                    </a>
                    
                    <?php if (canManageAbsences()): ?>
                        <a href="modifier_absence.php?id=<?= $absence['id'] ?>" class="btn-icon" title="Modifier">
                            <i class="fas fa-edit"></i>
                        </a>
                        
                        <?php if (isAdmin() || isVieScolaire()): ?>
                            <a href="supprimer_absence.php?id=<?= $absence['id'] ?>" class="btn-icon btn-danger" 
                               title="Supprimer" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette absence ?');">
                                <i class="fas fa-trash"></i>
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => max(1, $currentPage - 1)])) ?>" class="page-link" <?= $currentPage === 1 ? 'disabled' : '' ?>>
            <i class="fas fa-chevron-left"></i> Précédent
        </a>
        
        <div class="page-numbers">
            <?php for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="page-number <?= $i === $currentPage ? 'active' : '' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
        </div>
        
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => min($totalPages, $currentPage + 1)])) ?>" class="page-link" <?= $currentPage === $totalPages ? 'disabled' : '' ?>>
            Suivant <i class="fas fa-chevron-right"></i>
        </a>
    </div>
    <?php endif; ?>
</div>

<style>
/* Style supplémentaire pour s'aligner avec l'accueil */
.absence-item {
    padding: 10px 0;
    transition: background-color 0.2s;
}

.absence-item:hover {
    background-color: #f8f9fa;
}

.list-cell {
    padding: 10px 15px;
}

.text-small {
    font-size: 12px;
}

.text-muted {
    color: #6c757d;
}
</style>