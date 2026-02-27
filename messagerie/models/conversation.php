<?php
/**
 * Modèle pour la gestion des conversations
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/utils.php';

/**
 * Sous-requête réutilisable pour obtenir le nom complet d'un utilisateur
 * @return string SQL CASE expression
 */
function getUserNameCaseSQL(string $idCol = 'cp.user_id', string $typeCol = 'cp.user_type'): string {
    return "CASE 
        WHEN {$typeCol} = 'eleve' THEN (SELECT CONCAT(e.prenom, ' ', e.nom) FROM eleves e WHERE e.id = {$idCol})
        WHEN {$typeCol} = 'parent' THEN (SELECT CONCAT(p.prenom, ' ', p.nom) FROM parents p WHERE p.id = {$idCol})
        WHEN {$typeCol} = 'professeur' THEN (SELECT CONCAT(p.prenom, ' ', p.nom) FROM professeurs p WHERE p.id = {$idCol})
        WHEN {$typeCol} = 'vie_scolaire' THEN (SELECT CONCAT(v.prenom, ' ', v.nom) FROM vie_scolaire v WHERE v.id = {$idCol})
        WHEN {$typeCol} = 'administrateur' THEN (SELECT CONCAT(a.prenom, ' ', a.nom) FROM administrateurs a WHERE a.id = {$idCol})
        ELSE 'Inconnu'
    END";
}

/**
 * Récupère les conversations d'un utilisateur avec pagination
 * Corrige le problème N+1 en batch-chargeant les participants
 *
 * @param int $userId
 * @param string $userType
 * @param string $dossier
 * @param int $limit  Nombre de conversations par page
 * @param int $offset Décalage pour la pagination
 * @return array ['conversations' => [...], 'total' => int, 'has_more' => bool]
 */
function getConversations($userId, $userType, $dossier = 'reception', $limit = 20, $offset = 0) {
    global $pdo;
    
    $baseQuery = "
        SELECT c.id, c.subject as titre, 
               COALESCE(c.type, 
                   CASE WHEN EXISTS (SELECT 1 FROM messages WHERE conversation_id = c.id AND status = 'annonce') 
                        THEN 'annonce' ELSE 'standard' END
               ) as type,
               c.created_at as date_creation, 
               c.updated_at as dernier_message,
               (SELECT body FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as apercu,
               (SELECT status FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as status,
               cp.unread_count as non_lus
        FROM conversations c
        JOIN conversation_participants cp ON c.id = cp.conversation_id
        WHERE cp.user_id = ? AND cp.user_type = ?
    ";
    
    $countQuery = "
        SELECT COUNT(*) FROM conversations c
        JOIN conversation_participants cp ON c.id = cp.conversation_id
        WHERE cp.user_id = ? AND cp.user_type = ?
    ";
    
    $params = [$userId, $userType];
    $countParams = [$userId, $userType];
    
    $folderCondition = '';
    switch ($dossier) {
        case 'archives':
            $folderCondition = " AND cp.is_archived = 1 AND cp.is_deleted = 0";
            break;
        case 'corbeille':
            $folderCondition = " AND cp.is_deleted = 1";
            break;
        case 'envoyes':
            $folderCondition = " AND cp.is_archived = 0 AND cp.is_deleted = 0 
                          AND EXISTS (SELECT 1 FROM messages WHERE conversation_id = c.id AND sender_id = ? AND sender_type = ?)";
            $params[] = $userId;
            $params[] = $userType;
            $countParams[] = $userId;
            $countParams[] = $userType;
            break;
        case 'information':
            $folderCondition = " AND cp.is_archived = 0 AND cp.is_deleted = 0 
                          AND EXISTS (SELECT 1 FROM messages WHERE conversation_id = c.id AND status = 'annonce')";
            break;
        case 'reception':
        default:
            $folderCondition = " AND cp.is_archived = 0 AND cp.is_deleted = 0 
                          AND NOT EXISTS (SELECT 1 FROM messages WHERE conversation_id = c.id AND status = 'annonce')";
    }
    
    $baseQuery .= $folderCondition . " ORDER BY c.updated_at DESC LIMIT ? OFFSET ?";
    $countQuery .= $folderCondition;
    
    // Compter le total
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($countParams);
    $total = (int) $countStmt->fetchColumn();
    
    // Récupérer la page courante
    $params[] = $limit;
    $params[] = $offset;
    $stmt = $pdo->prepare($baseQuery);
    $stmt->execute($params);
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ── FIX N+1 : batch-charger les participants en UNE seule requête ──
    if (!empty($conversations)) {
        $convIds = array_column($conversations, 'id');
        $placeholders = implode(',', array_fill(0, count($convIds), '?'));
        $nameCase = getUserNameCaseSQL();
        
        $participantsStmt = $pdo->prepare("
            SELECT cp.conversation_id, cp.user_id, cp.user_type, cp.is_admin, cp.is_moderator,
                   {$nameCase} as nom_complet
            FROM conversation_participants cp
            WHERE cp.conversation_id IN ({$placeholders}) AND cp.is_deleted = 0
        ");
        $participantsStmt->execute($convIds);
        $allParticipants = $participantsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Indexer par conversation_id
        $participantsByConv = [];
        foreach ($allParticipants as $p) {
            $participantsByConv[$p['conversation_id']][] = $p;
        }
        
        foreach ($conversations as &$conversation) {
            $conversation['participants'] = $participantsByConv[$conversation['id']] ?? [];
        }
        unset($conversation);
    }
    
    return [
        'conversations' => $conversations,
        'total' => $total,
        'has_more' => ($offset + $limit) < $total
    ];
}

/**
 * Recherche dans les conversations d'un utilisateur (full-text)
 *
 * @param int $userId
 * @param string $userType
 * @param string $query Texte recherché
 * @param int $limit
 * @param int $offset
 * @return array
 */
function searchConversations($userId, $userType, $query, $limit = 20, $offset = 0) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT DISTINCT c.id, c.subject as titre,
               COALESCE(c.type, 'standard') as type,
               c.created_at as date_creation,
               c.updated_at as dernier_message,
               cp.unread_count as non_lus,
               MATCH(c.subject) AGAINST (? IN BOOLEAN MODE) as relevance_subject,
               (SELECT MAX(MATCH(m2.body) AGAINST (? IN BOOLEAN MODE))
                FROM messages m2 WHERE m2.conversation_id = c.id) as relevance_body
        FROM conversations c
        JOIN conversation_participants cp ON c.id = cp.conversation_id
        LEFT JOIN messages m ON m.conversation_id = c.id
        WHERE cp.user_id = ? AND cp.user_type = ? AND cp.is_deleted = 0
          AND (MATCH(c.subject) AGAINST (? IN BOOLEAN MODE) OR MATCH(m.body) AGAINST (? IN BOOLEAN MODE))
        ORDER BY (COALESCE(relevance_subject, 0) * 2 + COALESCE(relevance_body, 0)) DESC
        LIMIT ? OFFSET ?
    ");
    $searchTerm = '*' . str_replace(' ', '* *', trim($query)) . '*';
    $stmt->execute([$searchTerm, $searchTerm, $userId, $userType, $searchTerm, $searchTerm, $limit, $offset]);
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Batch-charger les participants
    if (!empty($conversations)) {
        $convIds = array_column($conversations, 'id');
        $placeholders = implode(',', array_fill(0, count($convIds), '?'));
        $nameCase = getUserNameCaseSQL();
        
        $pStmt = $pdo->prepare("
            SELECT cp.conversation_id, cp.user_id, cp.user_type, cp.is_admin, cp.is_moderator,
                   {$nameCase} as nom_complet
            FROM conversation_participants cp
            WHERE cp.conversation_id IN ({$placeholders}) AND cp.is_deleted = 0
        ");
        $pStmt->execute($convIds);
        $grouped = [];
        foreach ($pStmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $grouped[$p['conversation_id']][] = $p;
        }
        foreach ($conversations as &$conv) {
            $conv['participants'] = $grouped[$conv['id']] ?? [];
        }
        unset($conv);
    }
    
    return $conversations;
}

/**
 * Crée une nouvelle conversation
 * @param string $titre
 * @param string $type
 * @param int $createurId
 * @param string $createurType
 * @param array $participants
 * @return int
 */
function createConversation($titre, $type, $createurId, $createurType, $participants) {
    global $pdo;
    
    $pdo->beginTransaction();
    try {
        $sql = "INSERT INTO conversations (subject, created_at, updated_at) VALUES (?, NOW(), NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$titre]);
        $convId = $pdo->lastInsertId();
        
        $sql = "INSERT INTO conversation_participants 
                (conversation_id, user_id, user_type, joined_at, is_admin) 
                VALUES (?, ?, ?, NOW(), 1)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$convId, $createurId, $createurType]);
        
        $sql = "INSERT INTO conversation_participants 
                (conversation_id, user_id, user_type, joined_at) 
                VALUES (?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        foreach ($participants as $p) {
            $stmt->execute([$convId, $p['id'], $p['type']]);
        }
        
        $pdo->commit();
        return $convId;
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Récupère les informations d'une conversation
 * @param int $convId
 * @return array|false
 */
function getConversationInfo($convId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT c.id, c.subject as titre, 
        CASE 
            WHEN EXISTS (SELECT 1 FROM messages WHERE conversation_id = c.id AND status = 'annonce') THEN 'annonce'
            ELSE 'standard'
        END as type
        FROM conversations c
        WHERE c.id = ?
    ");
    $stmt->execute([$convId]);
    return $stmt->fetch();
}

/**
 * Archiver une conversation pour un utilisateur
 * @param int $convId
 * @param int $userId
 * @param string $userType
 * @return bool
 */
function archiveConversation($convId, $userId, $userType) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        UPDATE conversation_participants 
        SET is_archived = 1 
        WHERE conversation_id = ? AND user_id = ? AND user_type = ?
    ");
    $stmt->execute([$convId, $userId, $userType]);
    
    return $stmt->rowCount() > 0;
}

/**
 * Désarchive une conversation pour un utilisateur
 * @param int $convId
 * @param int $userId
 * @param string $userType
 * @return bool
 */
function unarchiveConversation($convId, $userId, $userType) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        UPDATE conversation_participants 
        SET is_archived = 0 
        WHERE conversation_id = ? AND user_id = ? AND user_type = ? AND is_deleted = 0
    ");
    $stmt->execute([$convId, $userId, $userType]);
    
    return $stmt->rowCount() > 0;
}

/**
 * Supprimer une conversation pour un utilisateur
 * @param int $convId
 * @param int $userId
 * @param string $userType
 * @return bool
 */
function deleteConversation($convId, $userId, $userType) {
    global $pdo;
    
    // Marquer les notifications comme lues d'abord
    $stmt = $pdo->prepare("
        UPDATE message_notifications AS mn
        JOIN messages AS m ON mn.message_id = m.id
        SET mn.is_read = 1
        WHERE m.conversation_id = ? AND mn.user_id = ? AND mn.user_type = ? AND mn.is_read = 0
    ");
    $stmt->execute([$convId, $userId, $userType]);
    
    // Mettre à jour la dernière lecture
    $stmt = $pdo->prepare("
        UPDATE conversation_participants 
        SET last_read_at = NOW() 
        WHERE conversation_id = ? AND user_id = ? AND user_type = ?
    ");
    $stmt->execute([$convId, $userId, $userType]);
    
    // Marquer comme supprimé
    $stmt = $pdo->prepare("
        UPDATE conversation_participants 
        SET is_deleted = 1 
        WHERE conversation_id = ? AND user_id = ? AND user_type = ?
    ");
    $stmt->execute([$convId, $userId, $userType]);
    
    return $stmt->rowCount() > 0;
}

/**
 * Restaure une conversation depuis la corbeille
 * @param int $convId
 * @param int $userId
 * @param string $userType
 * @return bool
 */
function restoreConversation($convId, $userId, $userType) {
    global $pdo;
    
    $pdo->beginTransaction();
    
    try {
        // Vérifier si un participant actif existe déjà
        $checkStmt = $pdo->prepare("
            SELECT COUNT(*) FROM conversation_participants 
            WHERE conversation_id = ? AND user_id = ? AND user_type = ? AND is_deleted = 0
        ");
        $checkStmt->execute([$convId, $userId, $userType]);
        $exists = $checkStmt->fetchColumn() > 0;
        
        if ($exists) {
            $pdo->commit();
            return true;
        }
        
        // Récupérer l'ID du participant supprimé
        $getIdStmt = $pdo->prepare("
            SELECT id FROM conversation_participants 
            WHERE conversation_id = ? AND user_id = ? AND user_type = ? AND is_deleted = 1
            ORDER BY id ASC LIMIT 1
        ");
        $getIdStmt->execute([$convId, $userId, $userType]);
        $recordId = $getIdStmt->fetchColumn();
        
        if ($recordId) {
            // Restaurer le participant
            $updateStmt = $pdo->prepare("
                UPDATE conversation_participants 
                SET is_deleted = 0, is_archived = 0 
                WHERE id = ?
            ");
            $updateStmt->execute([$recordId]);
            
            // Supprimer les doublons
            $deleteOthersStmt = $pdo->prepare("
                DELETE FROM conversation_participants 
                WHERE conversation_id = ? AND user_id = ? AND user_type = ? AND id != ?
            ");
            $deleteOthersStmt->execute([$convId, $userId, $userType, $recordId]);
        } else {
            // Créer un nouveau participant
            $insertStmt = $pdo->prepare("
                INSERT INTO conversation_participants 
                (conversation_id, user_id, user_type, joined_at, is_deleted, is_archived)
                VALUES (?, ?, ?, NOW(), 0, 0)
            ");
            $insertStmt->execute([$convId, $userId, $userType]);
        }
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Supprime définitivement une conversation pour un utilisateur
 * @param int $convId
 * @param int $userId
 * @param string $userType
 * @return bool
 */
function deletePermanently($convId, $userId, $userType) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        DELETE FROM conversation_participants 
        WHERE conversation_id = ? AND user_id = ? AND user_type = ?
    ");
    $stmt->execute([$convId, $userId, $userType]);
    
    return $stmt->rowCount() > 0;
}

/**
 * Supprime définitivement plusieurs conversations pour un utilisateur
 * @param array $convIds
 * @param int $userId
 * @param string $userType
 * @return int
 */
function deleteMultipleConversations($convIds, $userId, $userType) {
    global $pdo;
    
    if (empty($convIds)) {
        return 0;
    }
    
    $placeholders = implode(',', array_fill(0, count($convIds), '?'));
    
    $stmt = $pdo->prepare("
        DELETE FROM conversation_participants 
        WHERE conversation_id IN ($placeholders) AND user_id = ? AND user_type = ?
    ");
    
    $params = array_merge($convIds, [$userId, $userType]);
    $stmt->execute($params);
    
    return $stmt->rowCount();
}