<?php
/**
 * Composant pour afficher un message dans une conversation
 * Supporte : édition, suppression douce, épinglage, réactions, threading
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
$editedAt = $message['edited_at'] ?? null;
$deletedAt = $message['deleted_at'] ?? null;
$isPinned = !empty($message['is_pinned']);
$parentId = $message['parent_message_id'] ?? null;
$reactions = $message['reactions'] ?? [];

// Déterminer si c'est le message de l'utilisateur actuel
$isSelf = isCurrentUser($senderId, $senderType, $user);

// Classes CSS
$messageClasses = ['message'];
if ($isSelf) $messageClasses[] = 'self';
if ($isRead) $messageClasses[] = 'read';
if ($isPinned) $messageClasses[] = 'pinned';
if ($deletedAt) $messageClasses[] = 'deleted';
if ($status && $status !== 'normal') $messageClasses[] = $status;

// Formater la date
$dateFormatted = date('d/m/Y H:i', $timestamp);

// Vérifier si l'utilisateur peut éditer (auteur + <5 min)
$canEdit = $isSelf && !$deletedAt && (time() - $timestamp < 300);
// Vérifier si l'utilisateur peut supprimer/épingler (modérateur ou auteur)
$canDelete = ($isSelf || (isset($isModerator) && $isModerator)) && !$deletedAt;
$canPin = isset($isModerator) && $isModerator && !$deletedAt;
?>

<div class="<?= implode(' ', $messageClasses) ?>" data-id="<?= $messageId ?>" data-timestamp="<?= $timestamp ?>" id="message-<?= $messageId ?>">
    <?php if ($isPinned): ?>
    <div class="pinned-badge"><i class="fas fa-thumbtack"></i> Épinglé</div>
    <?php endif; ?>
    
    <?php if ($parentId): ?>
    <div class="reply-quote" onclick="scrollToMessage(<?= (int)$parentId ?>)">
        <i class="fas fa-reply"></i> En réponse à un message
    </div>
    <?php endif; ?>
    
    <div class="message-header">
        <div class="sender">
            <strong><?= h($senderName) ?></strong>
            <span class="sender-type"><?= getParticipantType($senderType) ?></span>
        </div>
        <div class="message-meta">
            <?php if ($status && $status !== 'normal'): ?>
            <span class="importance-tag <?= $status ?>"><?= ucfirst($status) ?></span>
            <?php endif; ?>
            <?php if ($editedAt): ?>
            <span class="edited-tag" title="Modifié le <?= date('d/m/Y H:i', strtotime($editedAt)) ?>">
                <i class="fas fa-pencil-alt"></i> modifié
            </span>
            <?php endif; ?>
            <span class="date" title="<?= $dateFormatted ?>"><?= formatTimeAgo($timestamp) ?></span>
            
            <?php if (!$deletedAt): ?>
            <div class="message-dropdown">
                <button class="btn-icon message-menu-btn" title="Actions"><i class="fas fa-ellipsis-v"></i></button>
                <div class="message-dropdown-content">
                    <?php if ($canEdit): ?>
                    <button onclick="editMessage(<?= $messageId ?>)"><i class="fas fa-edit"></i> Modifier</button>
                    <?php endif; ?>
                    <?php if ($canDelete): ?>
                    <button onclick="deleteMessage(<?= $messageId ?>)"><i class="fas fa-trash"></i> Supprimer</button>
                    <?php endif; ?>
                    <?php if ($canPin): ?>
                    <button onclick="togglePinMessage(<?= $messageId ?>)">
                        <i class="fas fa-thumbtack"></i> <?= $isPinned ? 'Désépingler' : 'Épingler' ?>
                    </button>
                    <?php endif; ?>
                    <?php if (!$isSelf): ?>
                    <button onclick="replyToMessage(<?= $messageId ?>, '<?= h($senderName) ?>')">
                        <i class="fas fa-reply"></i> Répondre
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="message-content" id="msg-content-<?= $messageId ?>">
        <?= nl2br(linkify(h($content))) ?>
    </div>
    
    <?php if (!empty($attachments) && !$deletedAt): ?>
    <div class="attachments">
        <div class="attachments-header">
            <i class="fas fa-paperclip"></i> Pièces jointes (<?= count($attachments) ?>)
        </div>
        <?php foreach ($attachments as $attachment): ?>
        <div class="attachment-item">
            <a href="download.php?id=<?= (int)($attachment['id'] ?? 0) ?>" 
               target="_blank" 
               class="attachment-link">
                <i class="fas fa-file"></i>
                <?= h($attachment['nom_fichier'] ?? $attachment['file_name'] ?? 'Fichier') ?>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <?php if (!$deletedAt && !empty($reactions)): ?>
    <div class="message-reactions">
        <?php foreach ($reactions as $r): ?>
        <button class="reaction-badge <?= $r['user_reacted'] ? 'active' : '' ?>" 
                onclick="toggleReaction(<?= $messageId ?>, '<?= h($r['emoji']) ?>')">
            <?= $r['emoji'] ?> <span class="reaction-count"><?= (int)$r['count'] ?></span>
        </button>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <?php if (!$deletedAt): ?>
    <div class="message-reactions-add">
        <button class="btn-icon reaction-add-btn" onclick="showReactionPicker(<?= $messageId ?>)" title="Ajouter une réaction">
            <i class="far fa-smile"></i>
        </button>
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