<?php
/**
 * Widget Devoirs à faire — inclus depuis accueil.php
 * Variables attendues : $devoirs_a_faire (array)
 */
?>
<div class="widget">
    <div class="widget-header">
        <h3><i class="fas fa-book"></i> Devoirs à faire</h3>
        <a href="../cahierdetextes/cahierdetextes.php" class="widget-action">Voir tout</a>
    </div>
    <div class="widget-content">
        <?php if (empty($devoirs_a_faire)): ?>
            <div class="empty-widget-message">
                <i class="fas fa-info-circle"></i>
                <p>Aucun devoir à rendre prochainement</p>
            </div>
        <?php else: ?>
            <ul class="assignments-list">
                <?php foreach ($devoirs_a_faire as $devoir): ?>
                    <li class="assignment-item">
                        <div class="assignment-date">
                            <?= date('d/m', strtotime($devoir['date_rendu'])) ?>
                        </div>
                        <div class="assignment-details">
                            <div class="assignment-title"><?= htmlspecialchars($devoir['titre']) ?></div>
                            <div class="assignment-subject"><?= htmlspecialchars($devoir['nom_matiere'] ?? '') ?> - <?= htmlspecialchars($devoir['nom_professeur'] ?? '') ?></div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
