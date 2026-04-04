<?php
/**
 * Widget : Absences du jour
 * Variables disponibles : $data (tableau retourné par AbsenceWidgetProvider::getData)
 */
?>
<div class="widget-stats">
    <div class="widget-stat-item">
        <span class="widget-stat-value"><?= (int)($data['absences'] ?? 0) ?></span>
        <span class="widget-stat-label">Absences</span>
    </div>
    <div class="widget-stat-item">
        <span class="widget-stat-value"><?= (int)($data['retards'] ?? 0) ?></span>
        <span class="widget-stat-label">Retards</span>
    </div>
    <div class="widget-stat-item widget-stat-warning">
        <span class="widget-stat-value"><?= (int)($data['unjustified'] ?? 0) ?></span>
        <span class="widget-stat-label">Non justifiées</span>
    </div>
    <a href="absences/absences.php" class="widget-link">Voir les absences &rarr;</a>
</div>
