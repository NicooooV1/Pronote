<?php
/**
 * Widget : Dernières notes
 * Variables disponibles : $data (tableau retourné par NoteWidgetProvider::getData)
 */
$notes = $data['notes'] ?? [];
$average = $data['average'] ?? null;
?>
<?php if ($average !== null): ?>
    <div class="widget-stat-mini">
        Moyenne générale : <strong><?= htmlspecialchars((string)$average) ?>/20</strong>
    </div>
<?php endif; ?>
<?php if (empty($notes)): ?>
    <p class="widget-empty">Aucune note enregistrée.</p>
<?php else: ?>
    <ul class="widget-list">
        <?php foreach ($notes as $note): ?>
        <li class="widget-list-item">
            <span class="widget-list-icon"><i class="fas fa-star"></i></span>
            <div class="widget-list-content">
                <div class="widget-list-title">
                    <?= htmlspecialchars($note['matiere'] ?? '—') ?>
                    &mdash; <strong><?= htmlspecialchars((string)$note['note']) ?>/<?= htmlspecialchars((string)$note['note_sur']) ?></strong>
                </div>
                <?php if (!empty($note['eleve_nom'])): ?>
                <div class="widget-list-meta"><?= htmlspecialchars($note['eleve_nom']) ?></div>
                <?php endif; ?>
                <div class="widget-list-meta"><?= htmlspecialchars(date('d/m/Y', strtotime($note['date_devoir']))) ?></div>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
    <a href="notes/notes.php" class="widget-link">Voir toutes les notes &rarr;</a>
<?php endif; ?>
