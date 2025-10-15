<?php
/**
 * Système d'audit centralisé pour tracer toutes les actions importantes
 */

if (!defined('PRONOTE_AUDIT_LOADED')) {
    define('PRONOTE_AUDIT_LOADED', true);
}

/**
 * Enregistre une action dans le journal d'audit
 * @param string $action Type d'action effectuée
 * @param string $tableAffected Table concernée
 * @param int|null $recordId ID de l'enregistrement
 * @param array|null $oldValues Anciennes valeurs
 * @param array|null $newValues Nouvelles valeurs
 */
function logAudit($action, $tableAffected = null, $recordId = null, $oldValues = null, $newValues = null) {
    try {
        $db = Database::getInstance();
        
        // Obtenir les informations de l'utilisateur
        $user = getCurrentUser();
        $userId = $user['id'] ?? null;
        $userType = $user['profil'] ?? null;
        
        // Préparer les données sensibles
        if ($oldValues) {
            $oldValues = maskSensitiveData($oldValues);
        }
        if ($newValues) {
            $newValues = maskSensitiveData($newValues);
        }
        
        // Insérer dans la table d'audit
        $sql = "INSERT INTO audit_log (
            user_id, user_type, action, table_affected, record_id,
            old_values, new_values, ip_address, user_agent, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $db->execute($sql, [
            $userId,
            $userType,
            $action,
            $tableAffected,
            $recordId,
            $oldValues ? json_encode($oldValues) : null,
            $newValues ? json_encode($newValues) : null,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 255)
        ]);
        
    } catch (Exception $e) {
        logError('Erreur audit: ' . $e->getMessage());
    }
}

/**
 * Masque les données sensibles avant l'enregistrement
 * @param array $data Données à masquer
 * @return array Données masquées
 */
function maskSensitiveData($data) {
    if (!is_array($data)) {
        return $data;
    }
    
    $sensitiveKeys = getSensitiveColumns();
    
    foreach ($data as $key => $value) {
        if (in_array($key, $sensitiveKeys)) {
            $data[$key] = '***MASKED***';
        } elseif (is_array($value)) {
            $data[$key] = maskSensitiveData($value);
        }
    }
    
    return $data;
}

/**
 * Récupère l'historique d'audit pour un enregistrement
 * @param string $table Table concernée
 * @param int $recordId ID de l'enregistrement
 * @param int $limit Nombre max de résultats
 * @return array Historique d'audit
 */
function getAuditHistory($table, $recordId, $limit = 50) {
    try {
        $db = Database::getInstance();
        
        $sql = "SELECT * FROM audit_log 
                WHERE table_affected = ? AND record_id = ?
                ORDER BY created_at DESC
                LIMIT ?";
        
        return $db->fetchAll($sql, [$table, $recordId, $limit]);
        
    } catch (Exception $e) {
        logError('Erreur récupération audit: ' . $e->getMessage());
        return [];
    }
}

/**
 * Nettoie les anciens enregistrements d'audit
 * @param int $daysToKeep Nombre de jours à conserver
 * @return int Nombre d'enregistrements supprimés
 */
function cleanOldAuditLogs($daysToKeep = 180) {
    try {
        $db = Database::getInstance();
        
        $sql = "DELETE FROM audit_log 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
        
        return $db->execute($sql, [$daysToKeep]);
        
    } catch (Exception $e) {
        logError('Erreur nettoyage audit: ' . $e->getMessage());
        return 0;
    }
}
