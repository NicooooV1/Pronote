<?php
/**
 * Composant pour afficher un élément de conversation dans la liste
 */

// S'assurer que les variables nécessaires sont définies
$convId = $conversation['id'] ?? 0;
$titre = $conversation['titre'] ?? 'Sans titre';
$type = $conversation['type'] ?? 'standard';
$apercu = $conversation['apercu'] ?? '';
$dernierMessage = $conversation['dernier_message'] ?? null;
$nonLus = $conversation['non_lus'] ?? 0;
$status = $conversation['status'] ?? 'normal';

// Déterminer si la conversation est lue
$isRead = $nonLus == 0;

// Formater la date du dernier message
$dateFormatted = $dernierMessage ? formatTimeAgo($dernierMessage) : 'Jamais';

// Classes CSS conditionnelles
$itemClasses = ['conversation-item'];
if (!$isRead) {
    $itemClasses[] = 'unread';
}
if ($status === 'important' || $status === 'urgent') {
    $itemClasses[] = $status;
}

// Récupérer les noms des participants (limité aux 3 premiers)
$participantNames = [];
if (isset($conversation['participants']) && is_array($conversation['participants'])) {
    foreach (array_slice($conversation['participants'], 0, 3) as $participant) {
        if (isset($participant['nom_complet'])) {
            $participantNames[] = $participant['nom_complet'];
        }
    }
}
$participantsText = !empty($participantNames) ? implode(', ', $participantNames) : 'Aucun participant';
if (isset($conversation['participants']) && count($conversation['participants']) > 3) {
    $participantsText .= ' +' . (count($conversation['participants']) - 3);
}
?>

<div class="<?= implode(' ', $itemClasses) ?>" data-id="<?= $convId ?>">
    <div class="conversation-checkbox">
        <input type="checkbox" class="conversation-select" 
               data-id="<?= $convId ?>" 
               data-read="<?= $isRead ? '1' : '0' ?>">
    </div>
    
    <a href="conversation.php?id=<?= $convId ?>" class="conversation-link">
        <div class="conversation-icon">
            <i class="fas fa-<?= getConversationIcon($type) ?>"></i>
        </div>
        
        <div class="conversation-content">
            <div class="conversation-header">
                <h3 class="conversation-title">
                    <?= h($titre) ?>
                    <?php if (!$isRead): ?>
                    <span class="badge unread-badge"><?= $nonLus ?></span>
                    <?php endif; ?>
                </h3>
                <span class="conversation-date"><?= $dateFormatted ?></span>
            </div>
            
            <div class="conversation-participants">
                <?= h($participantsText) ?>
            </div>
            
            <?php if (!empty($apercu)): ?>
            <div class="conversation-preview">
                <?= h(truncate($apercu, 100)) ?>
            </div>
            <?php endif; ?>
            
            <?php if ($status === 'important' || $status === 'urgent'): ?>
            <div class="conversation-badges">
                <span class="badge <?= $status ?>"><?= ucfirst($status) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </a>
    
    <div class="conversation-actions">
        <button class="quick-actions-btn" onclick="toggleQuickActions(<?= $convId ?>); return false;" 
                aria-label="Actions rapides">
            <i class="fas fa-ellipsis-v"></i>
        </button>
        
        <div class="quick-actions-menu" id="quick-actions-<?= $convId ?>">
            <?php if ($currentFolder !== 'corbeille'): ?>
            <a href="#" onclick="markConversationAsRead(<?= $convId ?>); return false;">
                <i class="fas fa-envelope-open"></i> Marquer comme lu
            </a>
            <a href="#" onclick="markConversationAsUnread(<?= $convId ?>); return false;">
                <i class="fas fa-envelope"></i> Marquer comme non lu
            </a>
            <a href="#" onclick="archiveConversation(<?= $convId ?>); return false;">
                <i class="fas fa-archive"></i> Archiver
            </a>
            <a href="#" onclick="confirmDelete(<?= $convId ?>); return false;" class="danger">
                <i class="fas fa-trash"></i> Supprimer
            </a>
            <?php else: ?>
            <a href="#" onclick="restoreConversation(<?= $convId ?>); return false;">
                <i class="fas fa-undo"></i> Restaurer
            </a>
            <a href="#" onclick="confirmDeletePermanently(<?= $convId ?>); return false;" class="danger">
                <i class="fas fa-trash-alt"></i> Supprimer définitivement
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>