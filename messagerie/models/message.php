<?php
/**
 * Modèle pour la gestion des messages
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/utils.php';
require_once __DIR__ . '/../core/uploader.php';

/**
 * Marque une conversation comme lue pour un utilisateur
 */
function markConversationAsRead($convId, $userId, $userType) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            UPDATE conversation_participants 
            SET last_read_at = NOW(), unread_count = 0
            WHERE conversation_id = ? AND user_id = ? AND user_type = ?
        ");
        $stmt->execute([$convId, $userId, $userType]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log("Erreur markConversationAsRead: " . $e->getMessage());
        return false;
    }
}

/**
 * Récupère les messages d'une conversation avec pagination
 * Corrige le problème N+1 en batch-chargeant les pièces jointes
 *
 * @param int $convId
 * @param int $userId
 * @param string $userType
 * @param int $limit   Nombre de messages à charger
 * @param int $before  Charger les messages avant cet ID (0 = depuis la fin)
 * @return array ['messages' => [...], 'has_more' => bool, 'pinned' => [...]]
 */
function getMessages($convId, $userId, $userType, $limit = 50, $before = 0) {
    global $pdo;
    
    // Vérifier que l'utilisateur est participant
    $checkParticipant = $pdo->prepare("
        SELECT id FROM conversation_participants 
        WHERE conversation_id = ? AND user_id = ? AND user_type = ? AND is_deleted = 0
    ");
    $checkParticipant->execute([$convId, $userId, $userType]);
    if (!$checkParticipant->fetch()) {
        throw new Exception("Vous n'êtes pas autorisé à accéder à cette conversation");
    }
    
    $nameCase = getUserNameCaseSQL('m.sender_id', 'm.sender_type');
    
    // Construire la requête avec pagination "avant ID"
    $whereClause = "m.conversation_id = ? AND (m.deleted_at IS NULL)";
    $params = [$userId, $userType, $userId, $userType];
    
    if ($before > 0) {
        $whereClause .= " AND m.id < ?";
        $params[] = $before;
    }
    
    $params = array_merge([$convId], $params);
    
    // Charger limit+1 pour savoir s'il y a encore des messages avant
    $stmt = $pdo->prepare("
        SELECT m.id, m.conversation_id, m.sender_id, m.sender_type, m.body, 
               m.original_body, m.status, m.parent_message_id,
               m.created_at, m.updated_at, m.edited_at, m.deleted_at,
               m.is_pinned, m.pinned_at, m.pinned_by_id, m.pinned_by_type,
               CASE 
                   WHEN cp.last_read_at IS NULL OR m.created_at > cp.last_read_at THEN 0
                   ELSE 1
               END as est_lu,
               CASE 
                   WHEN m.sender_id = ? AND m.sender_type = ? THEN 1
                   ELSE 0
               END as is_self,
               {$nameCase} as expediteur_nom,
               UNIX_TIMESTAMP(m.created_at) as timestamp
        FROM messages m
        LEFT JOIN conversation_participants cp ON (
            m.conversation_id = cp.conversation_id AND cp.user_id = ? AND cp.user_type = ?
        )
        WHERE {$whereClause}
        ORDER BY m.created_at DESC
        LIMIT ?
    ");
    $allParams = [$userId, $userType, $userId, $userType, $convId];
    if ($before > 0) {
        $allParams[] = $before;
    }
    $allParams[] = $limit + 1;
    
    $stmt->execute($allParams);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $hasMore = count($messages) > $limit;
    if ($hasMore) {
        array_pop($messages); // Retirer le message supplémentaire
    }
    
    // Inverser pour obtenir l'ordre chronologique
    $messages = array_reverse($messages);
    
    // ── FIX N+1 : batch-charger les pièces jointes ──
    if (!empty($messages)) {
        $msgIds = array_column($messages, 'id');
        $placeholders = implode(',', array_fill(0, count($msgIds), '?'));
        
        $attachStmt = $pdo->prepare("
            SELECT id, message_id, file_name as nom_fichier, file_path as chemin
            FROM message_attachments WHERE message_id IN ({$placeholders})
        ");
        $attachStmt->execute($msgIds);
        $allAttachments = $attachStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $attachByMsg = [];
        foreach ($allAttachments as $a) {
            $attachByMsg[$a['message_id']][] = $a;
        }
        
        // Batch-charger les réactions
        $reactStmt = $pdo->prepare("
            SELECT message_id, reaction, COUNT(*) as count,
                   GROUP_CONCAT(CONCAT(user_id, ':', user_type) SEPARATOR ',') as users
            FROM message_reactions 
            WHERE message_id IN ({$placeholders})
            GROUP BY message_id, reaction
        ");
        $reactStmt->execute($msgIds);
        $allReactions = $reactStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $reactionsByMsg = [];
        foreach ($allReactions as $r) {
            $reactionsByMsg[$r['message_id']][] = [
                'reaction' => $r['reaction'],
                'count' => (int) $r['count'],
                'users' => $r['users'],
                'user_reacted' => strpos($r['users'], "{$userId}:{$userType}") !== false
            ];
        }
        
        // Batch-charger les messages parents pour les réponses
        $parentIds = array_filter(array_unique(array_column($messages, 'parent_message_id')));
        $parentMessages = [];
        if (!empty($parentIds)) {
            $pPlaceholders = implode(',', array_fill(0, count($parentIds), '?'));
            $parentNameCase = getUserNameCaseSQL('pm.sender_id', 'pm.sender_type');
            $pStmt = $pdo->prepare("
                SELECT pm.id, pm.body, pm.sender_id, pm.sender_type,
                       {$parentNameCase} as expediteur_nom
                FROM messages pm WHERE pm.id IN ({$pPlaceholders})
            ");
            $pStmt->execute(array_values($parentIds));
            foreach ($pStmt->fetchAll(PDO::FETCH_ASSOC) as $pm) {
                $parentMessages[$pm['id']] = $pm;
            }
        }
        
        foreach ($messages as &$message) {
            $message['pieces_jointes'] = $attachByMsg[$message['id']] ?? [];
            $message['reactions'] = $reactionsByMsg[$message['id']] ?? [];
            $message['parent_message'] = $parentMessages[$message['parent_message_id']] ?? null;
        }
        unset($message);
    }
    
    // Récupérer les messages épinglés séparément
    $pinnedStmt = $pdo->prepare("
        SELECT m.id, m.body, m.sender_id, m.sender_type, m.pinned_at,
               " . getUserNameCaseSQL('m.sender_id', 'm.sender_type') . " as expediteur_nom,
               UNIX_TIMESTAMP(m.created_at) as timestamp
        FROM messages m
        WHERE m.conversation_id = ? AND m.is_pinned = 1 AND m.deleted_at IS NULL
        ORDER BY m.pinned_at DESC
    ");
    $pinnedStmt->execute([$convId]);
    $pinnedMessages = $pinnedStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Marquer la conversation comme lue
    markConversationAsRead($convId, $userId, $userType);
    
    return [
        'messages' => $messages,
        'has_more' => $hasMore,
        'pinned' => $pinnedMessages
    ];
}

/**
 * Récupère les messages même pour les conversations supprimées (corbeille)
 */
function getMessagesEvenIfDeleted($convId, $userId, $userType, $limit = 50, $before = 0) {
    global $pdo;
    
    // Vérifier que l'utilisateur est participant (même supprimé)
    $checkParticipant = $pdo->prepare("
        SELECT id FROM conversation_participants 
        WHERE conversation_id = ? AND user_id = ? AND user_type = ?
    ");
    $checkParticipant->execute([$convId, $userId, $userType]);
    if (!$checkParticipant->fetch()) {
        throw new Exception("Vous n'êtes pas autorisé à accéder à cette conversation");
    }
    
    $nameCase = getUserNameCaseSQL('m.sender_id', 'm.sender_type');
    
    $whereClause = "m.conversation_id = ?";
    $allParams = [$userId, $userType, $userId, $userType, $convId];
    
    if ($before > 0) {
        $whereClause .= " AND m.id < ?";
        $allParams[] = $before;
    }
    $allParams[] = $limit + 1;
    
    $stmt = $pdo->prepare("
        SELECT m.id, m.conversation_id, m.sender_id, m.sender_type, m.body, 
               m.original_body, m.status, m.parent_message_id,
               m.created_at, m.updated_at, m.edited_at, m.deleted_at,
               m.is_pinned, m.pinned_at,
               CASE WHEN cp.last_read_at IS NULL OR m.created_at > cp.last_read_at THEN 0 ELSE 1 END as est_lu,
               CASE WHEN m.sender_id = ? AND m.sender_type = ? THEN 1 ELSE 0 END as is_self,
               {$nameCase} as expediteur_nom,
               UNIX_TIMESTAMP(m.created_at) as timestamp
        FROM messages m
        LEFT JOIN conversation_participants cp ON (m.conversation_id = cp.conversation_id AND cp.user_id = ? AND cp.user_type = ?)
        WHERE {$whereClause}
        ORDER BY m.created_at DESC LIMIT ?
    ");
    $stmt->execute($allParams);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $hasMore = count($messages) > $limit;
    if ($hasMore) array_pop($messages);
    $messages = array_reverse($messages);
    
    // Batch pièces jointes
    if (!empty($messages)) {
        $msgIds = array_column($messages, 'id');
        $ph = implode(',', array_fill(0, count($msgIds), '?'));
        $aStmt = $pdo->prepare("SELECT id, message_id, file_name as nom_fichier, file_path as chemin FROM message_attachments WHERE message_id IN ($ph)");
        $aStmt->execute($msgIds);
        $byMsg = [];
        foreach ($aStmt->fetchAll(PDO::FETCH_ASSOC) as $a) $byMsg[$a['message_id']][] = $a;
        foreach ($messages as &$msg) $msg['pieces_jointes'] = $byMsg[$msg['id']] ?? [];
        unset($msg);
    }
    
    return ['messages' => $messages, 'has_more' => $hasMore, 'pinned' => []];
}

/**
 * Récupère un message par son ID
 * @param int $messageId
 * @return array|false
 */
function getMessageById($messageId) {
    global $pdo;
    
    $sql = "
        SELECT m.*, 
               1 as est_lu,
               1 as is_self,
               CASE 
                   WHEN m.sender_type = 'eleve' THEN 
                       (SELECT CONCAT(e.prenom, ' ', e.nom) FROM eleves e WHERE e.id = m.sender_id)
                   WHEN m.sender_type = 'parent' THEN 
                       (SELECT CONCAT(p.prenom, ' ', p.nom) FROM parents p WHERE p.id = m.sender_id)
                   WHEN m.sender_type = 'professeur' THEN 
                       (SELECT CONCAT(p.prenom, ' ', p.nom) FROM professeurs p WHERE p.id = m.sender_id)
                   WHEN m.sender_type = 'vie_scolaire' THEN 
                       (SELECT CONCAT(v.prenom, ' ', v.nom) FROM vie_scolaire v WHERE v.id = m.sender_id)
                   WHEN m.sender_type = 'administrateur' THEN 
                       (SELECT CONCAT(a.prenom, ' ', a.nom) FROM administrateurs a WHERE a.id = m.sender_id)
                   ELSE 'Inconnu'
               END as expediteur_nom,
               m.sender_id as expediteur_id, 
               m.sender_type as expediteur_type,
               m.body as contenu,
               m.status as status,
               m.created_at as date_envoi,
               UNIX_TIMESTAMP(m.created_at) as timestamp
        FROM messages m
        WHERE m.id = ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$messageId]);
    $message = $stmt->fetch();
    
    if ($message) {
        $attachmentStmt = $pdo->prepare("
            SELECT id, message_id, file_name as nom_fichier, file_path as chemin
            FROM message_attachments 
            WHERE message_id = ?
        ");
        $attachmentStmt->execute([$messageId]);
        $message['pieces_jointes'] = $attachmentStmt->fetchAll();
        
        // Récupérer les informations de lecture pour ce message
        $readInfoStmt = $pdo->prepare("
            SELECT COUNT(*) as total_participants,
                   SUM(CASE WHEN cp.last_read_message_id >= ? THEN 1 ELSE 0 END) as read_count
            FROM conversation_participants cp
            WHERE cp.conversation_id = ? AND cp.is_deleted = 0
        ");
        $readInfoStmt->execute([$messageId, $message['conversation_id']]);
        $readInfo = $readInfoStmt->fetch();
        
        $message['read_status'] = [
            'message_id' => $message['id'],
            'total_participants' => (int)$readInfo['total_participants'],
            'read_by_count' => (int)$readInfo['read_count'],
            'all_read' => (int)$readInfo['read_count'] === (int)$readInfo['total_participants'],
            'percentage' => $readInfo['total_participants'] > 0 ? 
                          round(($readInfo['read_count'] / $readInfo['total_participants']) * 100) : 0
        ];
    }
    
    return $message;
}

/**
 * Ajoute un nouveau message
 * @param int $convId
 * @param int $senderId
 * @param string $senderType
 * @param string $content
 * @param string $importance
 * @param bool $estAnnonce
 * @param bool $notificationObligatoire
 * @param int|null $parentMessageId
 * @param string $typeMessage
 * @param array $filesData
 * @return int
 */
function addMessage($convId, $senderId, $senderType, $content, $importance = 'normal', 
                   $estAnnonce = false, $notificationObligatoire = false,
                   $parentMessageId = null, $typeMessage = 'standard', $filesData = []) {
    global $pdo;
    
    // Vérifier que l'expéditeur est participant à la conversation
    $checkParticipant = $pdo->prepare("
        SELECT id FROM conversation_participants 
        WHERE conversation_id = ? AND user_id = ? AND user_type = ? AND is_deleted = 0
    ");
    $checkParticipant->execute([$convId, $senderId, $senderType]);
    if (!$checkParticipant->fetch()) {
        throw new Exception("Vous n'êtes pas autorisé à envoyer des messages dans cette conversation");
    }
    
    // Vérification de la longueur maximale
    $maxLength = 10000;
    if (mb_strlen($content) > $maxLength) {
        throw new Exception("Votre message est trop long (maximum $maxLength caractères)");
    }
    
    $pdo->beginTransaction();
    try {
        // Déterminer le statut du message
        $status = $estAnnonce ? 'annonce' : $importance;
        
        // Insérer le message
        $sql = "INSERT INTO messages (conversation_id, sender_id, sender_type, body, created_at, updated_at, status) 
                VALUES (?, ?, ?, ?, NOW(), NOW(), ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$convId, $senderId, $senderType, $content, $status]);
        $messageId = $pdo->lastInsertId();
        
        // Mettre à jour la date du dernier message
        $upd = $pdo->prepare("UPDATE conversations SET updated_at = NOW() WHERE id = ?");
        $upd->execute([$convId]);
        
        // Récupérer les participants
        $participantsStmt = $pdo->prepare("
            SELECT user_id, user_type FROM conversation_participants 
            WHERE conversation_id = ? AND is_deleted = 0
        ");
        $participantsStmt->execute([$convId]);
        $participants = $participantsStmt->fetchAll();
        
        // Déterminer le type de notification
        $notificationType = 'unread';
        if ($estAnnonce) {
            $notificationType = 'broadcast';
        } elseif ($importance === 'important' || $importance === 'urgent') {
            $notificationType = 'important';
        } elseif ($parentMessageId) {
            $notificationType = 'reply';
        }
        
        // Créer des notifications pour chaque participant
        $addNotification = $pdo->prepare("
            INSERT INTO message_notifications (user_id, user_type, message_id, notification_type, is_read, read_at) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        // Incrémenter le compteur de messages non lus
        $incrementUnread = $pdo->prepare("
            UPDATE conversation_participants 
            SET unread_count = unread_count + 1 
            WHERE conversation_id = ? AND user_id = ? AND user_type = ?
        ");
        
        // Mettre à jour le last_read_message_id pour l'expéditeur
        $updateReadId = $pdo->prepare("
            UPDATE conversation_participants 
            SET last_read_message_id = ?, version = version + 1
            WHERE conversation_id = ? AND user_id = ? AND user_type = ?
        ");
        $updateReadId->execute([$messageId, $convId, $senderId, $senderType]);
        
        foreach ($participants as $p) {
            // Mettre à jour last_read_message_id pour l'expéditeur
            if ($p['user_id'] == $senderId && $p['user_type'] == $senderType) {
                continue; // Déjà fait au-dessus
            }
            
            // Pour les autres participants, créer une notification et incrémenter le compteur
            $isRead = 0;
            $readAt = null;
            
            // Créer la notification
            $addNotification->execute([
                $p['user_id'], 
                $p['user_type'], 
                $messageId, 
                $notificationType,
                $isRead,
                $readAt
            ]);
            
            // Incrémenter le compteur non lu pour ce participant
            $incrementUnread->execute([
                $convId,
                $p['user_id'],
                $p['user_type']
            ]);
        }
        
        // Traiter les pièces jointes
        if (!empty($filesData) && isset($filesData['name']) && is_array($filesData['name'])) {
            $uploadedFiles = handleFileUploads($filesData);
            saveAttachments($pdo, $messageId, $uploadedFiles);
        }
        
        $pdo->commit();
        
        // ✅ NOTIFICATION WEBSOCKET - Nouveau message
        require_once __DIR__ . '/../../API/Core/WebSocket.php';
        
        // Récupérer les données complètes du message pour diffusion
        $messageData = getMessageById($messageId);
        if ($messageData) {
            \API\Core\WebSocket::notifyNewMessage($convId, $messageData);
        }
        
        // ✅ NOTIFICATIONS WEBSOCKET - Pour chaque participant
        foreach ($participants as $p) {
            if ($p['user_id'] == $senderId && $p['user_type'] == $senderType) {
                continue; // Skip expéditeur
            }
            
            // Notifier via WebSocket
            \API\Core\WebSocket::notifyUser($p['user_id'], [
                'type' => 'message',
                'convId' => $convId,
                'messageId' => $messageId,
                'senderName' => $messageData['expediteur_nom'] ?? 'Inconnu',
                'preview' => mb_substr($content, 0, 100)
            ]);
            
            // ...existing code (création notification DB)...
        }
        
        return $messageId;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Marque un message comme lu
 *
 * @param int $messageId ID du message
 * @param int $userId ID de l'utilisateur
 * @param string $userType Type d'utilisateur
 * @param int $maxRetries Nombre maximum de tentatives en cas d'échec
 * @return bool Succès de l'opération
 */
function markMessageAsRead($messageId, $userId, $userType, $maxRetries = 3) {
    global $pdo;
    
    $retriesLeft = $maxRetries;
    
    while ($retriesLeft > 0) {
        try {
            // Récupérer l'ID de la conversation
            $stmt = $pdo->prepare("SELECT conversation_id FROM messages WHERE id = ?");
            $stmt->execute([$messageId]);
            $convId = $stmt->fetchColumn();
            
            if (!$convId) {
                return false;
            }
            
            // Commencer une transaction
            $pdo->beginTransaction();
            
            // Vérifier le dernier message lu
            $stmt = $pdo->prepare("
                SELECT last_read_message_id 
                FROM conversation_participants 
                WHERE conversation_id = ? AND user_id = ? AND user_type = ?
                FOR UPDATE
            ");
            $stmt->execute([$convId, $userId, $userType]);
            $currentLastReadId = $stmt->fetchColumn();
            
            if ($currentLastReadId === null || $messageId > $currentLastReadId) {
                // Mettre à jour le dernier message lu
                $updateStmt = $pdo->prepare("
                    UPDATE conversation_participants 
                    SET last_read_message_id = ?, last_read_at = NOW()
                    WHERE conversation_id = ? AND user_id = ? AND user_type = ?
                ");
                $updateStmt->execute([$messageId, $convId, $userId, $userType]);
                
                // Mettre à jour les notifications
                $updateNotif = $pdo->prepare("
                    UPDATE notifications
                    SET is_read = true, read_at = NOW()
                    WHERE related_id = ? AND user_id = ? AND user_type = ?
                ");
                $updateNotif->execute([$messageId, $userId, $userType]);
                
                // Recalculer précisément le compteur unread_count
                $updateCount = $pdo->prepare("
                    UPDATE conversation_participants
                    SET unread_count = (
                        SELECT COUNT(*) 
                        FROM messages m
                        LEFT JOIN conversation_participants cp ON m.conversation_id = cp.conversation_id
                        WHERE m.conversation_id = ? 
                        AND cp.user_id = ? AND cp.user_type = ?
                        AND (cp.last_read_message_id IS NULL OR m.id > cp.last_read_message_id)
                        AND m.sender_id != ? AND m.sender_type != ?
                    )
                    WHERE conversation_id = ? AND user_id = ? AND user_type = ?
                ");
                $updateCount->execute([$convId, $userId, $userType, $userId, $userType, $convId, $userId, $userType]);
                
                $pdo->commit();
                return true;
            } else {
                // Conflit détecté, la version a changé entre-temps
                $pdo->rollBack();
                $retriesLeft--;
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $retriesLeft--;
            
            if ($retriesLeft === 0) {
                // Journaliser l'erreur après toutes les tentatives
                error_log("Erreur lors du marquage du message comme lu: " . $e->getMessage());
                return false;
            }
            
            // Attendre avant la prochaine tentative
            usleep(100000); // 100 ms
        }
    }
    
    return false;
}

/**
 * Récupère le statut de lecture d'un message avec des informations détaillées
 * @param int $messageId
 * @return array
 */
function getMessageReadStatus($messageId) {
    global $pdo;
    
    // Récupérer l'ID de la conversation pour ce message
    $stmt = $pdo->prepare("SELECT conversation_id FROM messages WHERE id = ?");
    $stmt->execute([$messageId]);
    $result = $stmt->fetch();
    
    if (!$result) {
        return [
            'message_id' => $messageId,
            'total_participants' => 0,
            'read_by_count' => 0,
            'all_read' => false,
            'percentage' => 0,
            'readers' => []
        ];
    }
    
    $convId = $result['conversation_id'];
    
    // Récupérer le nombre total de participants et le nombre de participants qui ont lu
    $readInfoStmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_participants,
            SUM(CASE WHEN cp.last_read_message_id >= ? THEN 1 ELSE 0 END) as read_count
        FROM conversation_participants cp
        WHERE cp.conversation_id = ? AND cp.is_deleted = 0
        AND cp.user_id != (SELECT sender_id FROM messages WHERE id = ?)
        AND cp.user_type != (SELECT sender_type FROM messages WHERE id = ?)
    ");
    $readInfoStmt->execute([$messageId, $convId, $messageId, $messageId]);
    $readInfo = $readInfoStmt->fetch();
    
    // Récupérer les participants qui ont lu
    $readersStmt = $pdo->prepare("
        SELECT cp.user_id, cp.user_type,
               CASE 
                   WHEN cp.user_type = 'eleve' THEN 
                       (SELECT CONCAT(e.prenom, ' ', e.nom) FROM eleves e WHERE e.id = cp.user_id)
                   WHEN cp.user_type = 'parent' THEN 
                       (SELECT CONCAT(p.prenom, ' ', p.nom) FROM parents p WHERE p.id = cp.user_id)
                   WHEN cp.user_type = 'professeur' THEN 
                       (SELECT CONCAT(p.prenom, ' ', p.nom) FROM professeurs p WHERE p.id = cp.user_id)
                   WHEN cp.user_type = 'vie_scolaire' THEN 
                       (SELECT CONCAT(v.prenom, ' ', v.nom) FROM vie_scolaire v WHERE v.id = cp.user_id)
                   WHEN cp.user_type = 'administrateur' THEN 
                       (SELECT CONCAT(a.prenom, ' ', a.nom) FROM administrateurs a WHERE a.id = cp.user_id)
                   ELSE 'Inconnu'
               END as nom_complet
        FROM conversation_participants cp
        WHERE cp.conversation_id = ? AND cp.last_read_message_id >= ? AND cp.is_deleted = 0
        AND cp.user_id != (SELECT sender_id FROM messages WHERE id = ?)
        AND cp.user_type != (SELECT sender_type FROM messages WHERE id = ?)
    ");
    $readersStmt->execute([$convId, $messageId, $messageId, $messageId]);
    $readers = $readersStmt->fetchAll();
    
    return [
        'message_id' => $messageId,
        'total_participants' => (int)$readInfo['total_participants'],
        'read_by_count' => (int)$readInfo['read_count'],
        'all_read' => (int)$readInfo['read_count'] === (int)$readInfo['total_participants'] && (int)$readInfo['total_participants'] > 0,
        'percentage' => $readInfo['total_participants'] > 0 ? 
                      round(($readInfo['read_count'] / $readInfo['total_participants']) * 100) : 0,
        'readers' => $readers
    ];
}

/**
 * Marque un message comme non lu
 * @param int $messageId
 * @param int $userId
 * @param string $userType
 * @return bool
 */
function markMessageAsUnread($messageId, $userId, $userType) {
    global $pdo;
    
    // Récupérer l'ID de la conversation pour ce message
    $stmt = $pdo->prepare("SELECT conversation_id FROM messages WHERE id = ?");
    $stmt->execute([$messageId]);
    $result = $stmt->fetch();
    
    if (!$result) {
        return false;
    }
    
    $convId = $result['conversation_id'];
    
    $pdo->beginTransaction();
    try {
        // Récupérer tous les messages de la conversation triés par ID
        $messagesStmt = $pdo->prepare("
            SELECT id FROM messages
            WHERE conversation_id = ?
            ORDER BY id ASC
        ");
        $messagesStmt->execute([$convId]);
        $messages = $messagesStmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Trouver le message précédent
        $prevMessageId = null;
        foreach ($messages as $mId) {
            if ((int)$mId === (int)$messageId) {
                break;
            }
            $prevMessageId = $mId;
        }
        
        // Mettre à jour le last_read_message_id avec le message précédent
        $version = time(); // Utiliser le timestamp comme nouvelle version
        $updateStmt = $pdo->prepare("
            UPDATE conversation_participants 
            SET last_read_message_id = ?, version = ?
            WHERE conversation_id = ? AND user_id = ? AND user_type = ?
        ");
        $updateStmt->execute([$prevMessageId, $version, $convId, $userId, $userType]);
        
        // Vérifier si la notification existe
        $checkStmt = $pdo->prepare("
            SELECT id, is_read FROM message_notifications 
            WHERE message_id = ? AND user_id = ? AND user_type = ?
        ");
        $checkStmt->execute([$messageId, $userId, $userType]);
        $notification = $checkStmt->fetch();
        
        if ($notification) {
            // Si la notification existe et est déjà lue, la marquer comme non lue
            if ($notification['is_read']) {
                // Marquer comme non lu et réinitialiser la date de lecture
                $updNotif = $pdo->prepare("
                    UPDATE message_notifications 
                    SET is_read = 0, read_at = NULL 
                    WHERE id = ?
                ");
                $updNotif->execute([$notification['id']]);
                
                // Recalculer le compteur de messages non lus
                $recalcUnread = $pdo->prepare("
                    UPDATE conversation_participants cp
                    SET unread_count = (
                        SELECT COUNT(*) 
                        FROM messages m
                        LEFT JOIN message_notifications mn ON m.id = mn.message_id AND mn.user_id = ? AND mn.user_type = ?
                        WHERE m.conversation_id = ? 
                        AND (mn.id IS NULL OR mn.is_read = 0)
                        AND m.sender_id != ? AND m.sender_type != ?
                    )
                    WHERE cp.conversation_id = ? AND cp.user_id = ? AND cp.user_type = ?
                ");
                $recalcUnread->execute([
                    $userId, $userType, $convId, $userId, $userType, $convId, $userId, $userType
                ]);
            }
        } else {
            // Si la notification n'existe pas, on la crée comme non lue
            $createNotif = $pdo->prepare("
                INSERT INTO message_notifications 
                (user_id, user_type, message_id, notification_type, is_read, read_at) 
                VALUES (?, ?, ?, 'unread', 0, NULL)
            ");
            $createNotif->execute([$userId, $userType, $messageId]);
            
            // Incrémenter le compteur de messages non lus
            $updCount = $pdo->prepare("
                UPDATE conversation_participants 
                SET unread_count = unread_count + 1 
                WHERE conversation_id = ? AND user_id = ? AND user_type = ?
            ");
            $updCount->execute([$convId, $userId, $userType]);
        }
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}

/**
 * Suppression soft d'un message (avec affichage "Ce message a été supprimé")
 */
function deleteMessage($messageId, $userId, $userType) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT conversation_id, sender_id, sender_type FROM messages WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$messageId]);
    $message = $stmt->fetch();
    
    if (!$message) return false;
    
    // Vérifier : auteur ou modérateur
    $isAuthor = ($message['sender_id'] == $userId && $message['sender_type'] == $userType);
    if (!$isAuthor) {
        $modStmt = $pdo->prepare("
            SELECT id FROM conversation_participants
            WHERE conversation_id = ? AND user_id = ? AND user_type = ? 
            AND (is_moderator = 1 OR is_admin = 1) AND is_deleted = 0
        ");
        $modStmt->execute([$message['conversation_id'], $userId, $userType]);
        if (!$modStmt->fetch()) return false;
    }
    
    $del = $pdo->prepare("
        UPDATE messages SET deleted_at = NOW(), deleted_by_id = ?, deleted_by_type = ?,
                            body = '[Message supprimé]'
        WHERE id = ?
    ");
    $del->execute([$userId, $userType, $messageId]);
    
    return $del->rowCount() > 0;
}

/**
 * Édite un message (autorisé pendant 5 minutes après envoi)
 */
function editMessage($messageId, $userId, $userType, $newBody) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT id, sender_id, sender_type, body, created_at 
        FROM messages 
        WHERE id = ? AND deleted_at IS NULL
    ");
    $stmt->execute([$messageId]);
    $message = $stmt->fetch();
    
    if (!$message) {
        throw new Exception("Message introuvable");
    }
    
    // Seul l'auteur peut éditer
    if ($message['sender_id'] != $userId || $message['sender_type'] != $userType) {
        throw new Exception("Vous ne pouvez modifier que vos propres messages");
    }
    
    // Vérifier le délai de 5 minutes
    $createdAt = strtotime($message['created_at']);
    if ((time() - $createdAt) > 300) {
        throw new Exception("Le délai de modification de 5 minutes est dépassé");
    }
    
    $newBody = trim($newBody);
    if (empty($newBody) || mb_strlen($newBody) > 10000) {
        throw new Exception("Le contenu du message est invalide");
    }
    
    $upd = $pdo->prepare("
        UPDATE messages 
        SET body = ?, original_body = COALESCE(original_body, ?), edited_at = NOW(), updated_at = NOW()
        WHERE id = ?
    ");
    $upd->execute([$newBody, $message['body'], $messageId]);
    
    return $upd->rowCount() > 0;
}

/**
 * Épingle ou désépingle un message (modérateurs/admins uniquement)
 */
function togglePinMessage($messageId, $userId, $userType) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT conversation_id, is_pinned FROM messages WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$messageId]);
    $message = $stmt->fetch();
    
    if (!$message) {
        throw new Exception("Message introuvable");
    }
    
    // Vérifier que l'utilisateur est modérateur/admin
    $modStmt = $pdo->prepare("
        SELECT id FROM conversation_participants
        WHERE conversation_id = ? AND user_id = ? AND user_type = ? 
        AND (is_moderator = 1 OR is_admin = 1) AND is_deleted = 0
    ");
    $modStmt->execute([$message['conversation_id'], $userId, $userType]);
    if (!$modStmt->fetch()) {
        throw new Exception("Seuls les modérateurs peuvent épingler des messages");
    }
    
    $newState = $message['is_pinned'] ? 0 : 1;
    
    $upd = $pdo->prepare("
        UPDATE messages 
        SET is_pinned = ?, 
            pinned_at = IF(? = 1, NOW(), NULL),
            pinned_by_id = IF(? = 1, ?, NULL),
            pinned_by_type = IF(? = 1, ?, NULL)
        WHERE id = ?
    ");
    $upd->execute([$newState, $newState, $newState, $userId, $newState, $userType, $messageId]);
    
    return ['pinned' => (bool) $newState];
}

/**
 * Ajoute ou retire une réaction à un message
 */
function toggleReaction($messageId, $userId, $userType, $reaction) {
    global $pdo;
    
    // Vérifier que le message existe et que l'utilisateur est participant
    $stmt = $pdo->prepare("
        SELECT m.conversation_id FROM messages m
        JOIN conversation_participants cp ON m.conversation_id = cp.conversation_id
        WHERE m.id = ? AND cp.user_id = ? AND cp.user_type = ? AND cp.is_deleted = 0 AND m.deleted_at IS NULL
    ");
    $stmt->execute([$messageId, $userId, $userType]);
    if (!$stmt->fetch()) {
        throw new Exception("Message introuvable ou accès refusé");
    }
    
    // Vérifier si la réaction existe déjà
    $existing = $pdo->prepare("
        SELECT id FROM message_reactions 
        WHERE message_id = ? AND user_id = ? AND user_type = ? AND reaction = ?
    ");
    $existing->execute([$messageId, $userId, $userType, $reaction]);
    
    if ($existing->fetch()) {
        // Retirer la réaction
        $del = $pdo->prepare("
            DELETE FROM message_reactions 
            WHERE message_id = ? AND user_id = ? AND user_type = ? AND reaction = ?
        ");
        $del->execute([$messageId, $userId, $userType, $reaction]);
        $action = 'removed';
    } else {
        // Ajouter la réaction
        $ins = $pdo->prepare("
            INSERT INTO message_reactions (message_id, user_id, user_type, reaction) VALUES (?, ?, ?, ?)
        ");
        $ins->execute([$messageId, $userId, $userType, $reaction]);
        $action = 'added';
    }
    
    // Retourner le nouveau comptage
    $countStmt = $pdo->prepare("
        SELECT reaction, COUNT(*) as count FROM message_reactions WHERE message_id = ? GROUP BY reaction
    ");
    $countStmt->execute([$messageId]);
    
    return ['action' => $action, 'reactions' => $countStmt->fetchAll(PDO::FETCH_ASSOC)];
}

/* 
 * canReplyToAnnouncement() et canSetMessageImportance() sont dans core/utils.php
 */

/**
 * Fonctions liées aux messages (doublons supprimés — sendMessageToClass est dans models/class.php)
 */
require_once __DIR__ . '/../core/utils.php';