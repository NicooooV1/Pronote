<?php
/**
 * Widget Enfants — Vue parent multi-enfants (M25)
 * Affiché uniquement pour les parents.
 * Variables attendues : $dashboard (DashboardService), $user (array)
 */
$enfants = $dashboard->getEnfantsParent($user['id'] ?? 0);
$selectedEnfantId = (int)($_GET['enfant'] ?? ($_SESSION['selected_enfant_id'] ?? 0));

// Auto-sélectionner le premier enfant si non sélectionné
if (!$selectedEnfantId && !empty($enfants)) {
    $selectedEnfantId = $enfants[0]['id'];
}

// Persister la sélection
if ($selectedEnfantId) {
    $_SESSION['selected_enfant_id'] = $selectedEnfantId;
}

// Trouver l'enfant sélectionné
$enfantActif = null;
foreach ($enfants as $e) {
    if ($e['id'] === $selectedEnfantId) {
        $enfantActif = $e;
        break;
    }
}

// Résumé de l'enfant sélectionné
$resumeEnfant = $enfantActif ? $dashboard->getResumeEnfant($enfantActif['id']) : [];
?>
<div class="widget widget-enfants">
    <div class="widget-header">
        <h3><i class="fas fa-child"></i> Mes enfants</h3>
    </div>
    <div class="widget-content">
        <?php if (empty($enfants)): ?>
            <div class="empty-widget-message">
                <i class="fas fa-info-circle"></i>
                <p>Aucun enfant associé à votre compte.</p>
            </div>
        <?php else: ?>

            <!-- Sélecteur d'enfants -->
            <div class="enfant-selector">
                <?php foreach ($enfants as $e): ?>
                <a href="?enfant=<?= $e['id'] ?>"
                   class="enfant-chip <?= $e['id'] === $selectedEnfantId ? 'enfant-chip-active' : '' ?>">
                    <div class="enfant-avatar">
                        <?= strtoupper(mb_substr($e['prenom'], 0, 1)) ?><?= strtoupper(mb_substr($e['nom'], 0, 1)) ?>
                    </div>
                    <div class="enfant-info">
                        <span class="enfant-name"><?= htmlspecialchars($e['prenom'] . ' ' . $e['nom']) ?></span>
                        <span class="enfant-classe"><?= htmlspecialchars($e['classe'] ?? '-') ?></span>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>

            <?php if ($enfantActif): ?>
            <!-- Résumé de l'enfant sélectionné -->
            <div class="enfant-resume">
                <h4><?= htmlspecialchars($enfantActif['prenom']) ?> — Résumé</h4>
                <?php if (!empty($resumeEnfant)): ?>
                <div class="enfant-stats">
                    <?php foreach ($resumeEnfant as $card): ?>
                    <div class="enfant-stat-card enfant-stat-<?= $card['color'] ?>">
                        <div class="enfant-stat-icon"><i class="<?= $card['icon'] ?>"></i></div>
                        <div>
                            <div class="enfant-stat-value"><?= htmlspecialchars((string) $card['value']) ?></div>
                            <div class="enfant-stat-label"><?= htmlspecialchars($card['label']) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Liens rapides pour l'enfant -->
                <div class="enfant-quick-links">
                    <a href="../notes/notes.php?eleve=<?= $enfantActif['id'] ?>" class="enfant-link">
                        <i class="fas fa-chart-bar"></i> Notes
                    </a>
                    <a href="../emploi_du_temps/emploi_du_temps.php?eleve=<?= $enfantActif['id'] ?>" class="enfant-link">
                        <i class="fas fa-table"></i> Emploi du temps
                    </a>
                    <a href="../cahierdetextes/cahierdetextes.php?classe=<?= urlencode($enfantActif['classe'] ?? '') ?>" class="enfant-link">
                        <i class="fas fa-book"></i> Cahier de textes
                    </a>
                    <a href="../absences/absences.php?eleve=<?= $enfantActif['id'] ?>" class="enfant-link">
                        <i class="fas fa-calendar-times"></i> Absences
                    </a>
                </div>
            </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</div>
