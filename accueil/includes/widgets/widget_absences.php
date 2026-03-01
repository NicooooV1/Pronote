<?php
/**
 * Widget Absences du jour — inclus depuis accueil.php (vie scolaire)
 * Variables attendues : $absences_jour (array)
 */
?>
<div class="widget">
    <div class="widget-header">
        <h3><i class="fas fa-user-times"></i> Absences du jour</h3>
        <a href="../absences/absences.php" class="widget-action">Voir tout</a>
    </div>
    <div class="widget-content">
        <?php if (empty($absences_jour)): ?>
            <div class="empty-widget-message">
                <i class="fas fa-check-circle"></i>
                <p>Aucune absence signalée aujourd'hui</p>
            </div>
        <?php else: ?>
            <ul class="events-list">
                <?php foreach ($absences_jour as $abs): ?>
                    <li class="event-item">
                        <div class="event-date" style="font-size:11px;"><?= htmlspecialchars($abs['classe'] ?? '') ?></div>
                        <div class="event-details">
                            <div class="event-title"><?= htmlspecialchars($abs['nom_eleve'] ?? '') ?></div>
                            <div class="event-time"><?= htmlspecialchars($abs['statut'] ?? '') ?></div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
