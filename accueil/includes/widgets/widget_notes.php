<?php
/**
 * Widget Dernières notes — inclus depuis accueil.php
 * Variables attendues : $dernieres_notes (array)
 */
?>
<div class="widget">
    <div class="widget-header">
        <h3><i class="fas fa-chart-bar"></i> Dernières notes</h3>
        <a href="../notes/notes.php" class="widget-action">Voir tout</a>
    </div>
    <div class="widget-content">
        <?php if (empty($dernieres_notes)): ?>
            <div class="empty-widget-message">
                <i class="fas fa-info-circle"></i>
                <p>Aucune note récente</p>
            </div>
        <?php else: ?>
            <ul class="grades-list">
                <?php foreach ($dernieres_notes as $note): ?>
                    <li class="grade-item">
                        <div class="grade-value"><?= htmlspecialchars($note['note']) ?>/<?= $note['note_sur'] ?? 20 ?></div>
                        <div class="grade-details">
                            <div class="grade-title"><?= htmlspecialchars($note['nom_matiere'] ?? '') ?></div>
                            <div class="grade-date"><?= date('d/m/Y', strtotime($note['date_creation'])) ?></div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
