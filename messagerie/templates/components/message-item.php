<?php
/**
 * Composant pour afficher un message dans une conversation
 */

// S'assurer que les variables nécessaires sont définies
$messageId = $message['id'] ?? 0;
$senderId = $message['sender_id'] ?? 0;
$senderType = $message['sender_type'] ?? '';
$senderName = $message['expediteur_nom'] ?? 'Inconnu';
$content = $message['body'] ?? $message['contenu'] ?? '';
$timestamp = $message['timestamp'] ?? time();
$status = $message['status'] ?? 'normal';
$isRead = isset($message['est_lu']) && $message['est_lu'] == 1;
$attachments = $message['pieces_jointes'] ?? [];

// Déterminer si c'est le message de l'utilisateur actuel
$isSelf = isCurrentUser($senderId, $senderType, $user);

// Classes CSS
$messageClasses = ['message'];
if ($isSelf) {
    $messageClasses[] = 'self';
}
if ($isRead) {
    $messageClasses[] = 'read';
}
if ($status && $status !== 'normal') {
    $messageClasses[] = $status;
}

// Formater la date
$dateFormatted = date('d/m/Y H:i', $timestamp);
?>

<div class="<?= implode(' ', $messageClasses) ?>" data-id="<?= $messageId ?>" data-timestamp="<?= $timestamp ?>">
    <div class="message-header">
        <div class="sender">
            <strong><?= h($senderName) ?></strong>
            <span class="sender-type"><?= getParticipantType($senderType) ?></span>
        </div>
        <div class="message-meta">
            <?php if ($status && $status !== 'normal'): ?>
            <span class="importance-tag <?= $status ?>"><?= ucfirst($status) ?></span>
            <?php endif; ?>
            <span class="date" title="<?= $dateFormatted ?>"><?= formatTimeAgo($timestamp) ?></span>
        </div>
    </div>
    
    <div class="message-content">
        <?= nl2br(linkify(h($content))) ?>
    </div>
    
    <?php if (!empty($attachments)): ?>
    <div class="attachments">
        <div class="attachments-header">
            <i class="fas fa-paperclip"></i> Pièces jointes (<?= count($attachments) ?>)
        </div>
        <?php foreach ($attachments as $attachment): ?>
        <div class="attachment-item">
            <a href="<?= h($attachment['chemin'] ?? $attachment['file_path']) ?>" 
               target="_blank" 
               class="attachment-link">
                <i class="fas fa-file"></i>
                <?= h($attachment['nom_fichier'] ?? $attachment['file_name']) ?>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <div class="message-footer">
        <?php if ($isSelf): ?>
        <div class="message-status">
            <div class="message-read-status" data-message-id="<?= $messageId ?>">
                <?php if ($isRead): ?>
                <div class="all-read">
                    <i class="fas fa-check-double"></i> Vu
                </div>
                <?php else: ?>
                <div class="partial-read">
                    <i class="fas fa-check"></i> <span class="read-count">0/<?= count($participants ?? []) - 1 ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="message-actions">
            <button class="btn-icon" onclick="replyToMessage(<?= $messageId ?>, '<?= h($senderName) ?>')">
                <i class="fas fa-reply"></i> Répondre
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>