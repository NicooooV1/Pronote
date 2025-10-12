<?php
/**
 * Système d'audit et de traçabilité
 * Enregistre toutes les actions importantes pour la sécurité
 */

if (!defined('PRONOTE_AUDIT_LOADED')) {
    define('PRONOTE_AUDIT_LOADED', true);
}

/**
 * Enregistre une action dans le journal d'audit
 */
function logAuditAction($action, $tableAffected = null, $recordId = null, $oldValues = null, $newValues = null) {
    try {
        // Inclure la configuration de base de données
        require_once __DIR__ . '/../config/env.php';
        
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        
        // Obtenir les informations utilisateur
        $userId = null;
        $userType = null;
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (isset($_SESSION['user'])) {
            $userId = $_SESSION['user']['id'] ?? null;
            $userType = $_SESSION['user']['profil'] ?? null;
        }
        
        // Préparer les données
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        // Convertir les valeurs en JSON si nécessaire
        $oldValuesJson = $oldValues ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null;
        $newValuesJson = $newValues ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null;
        
        // Insérer dans le journal d'audit
        $stmt = $pdo->prepare("
            INSERT INTO audit_log 
            (user_id, user_type, action, table_affected, record_id, old_values, new_values, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $userId,
            $userType,
            $action,
            $tableAffected,
            $recordId,
            $oldValuesJson,
            $newValuesJson,
            $ipAddress,
            $userAgent
        ]);
        
        return true;
        
    } catch (Exception $e) {
        // En cas d'erreur, logger dans le fichier système
        error_log("Erreur audit: " . $e->getMessage());
        return false;
    }
}

/**
 * Actions d'audit spécialisées
 */
function auditLogin($userId, $userType, $success = true) {
    $action = $success ? 'login_success' : 'login_failed';
    return logAuditAction($action, $userType, $userId);
}

function auditLogout($userId, $userType) {
    return logAuditAction('logout', $userType, $userId);
}

function auditPasswordChange($userId, $userType) {
    return logAuditAction('password_change', $userType, $userId);
}

function auditDataAccess($table, $recordId, $action = 'view') {
    return logAuditAction("data_{$action}", $table, $recordId);
}

function auditDataModification($table, $recordId, $oldData, $newData) {
    return logAuditAction('data_update', $table, $recordId, $oldData, $newData);
}

function auditDataCreation($table, $recordId, $data) {
    return logAuditAction('data_create', $table, $recordId, null, $data);
}

function auditDataDeletion($table, $recordId, $data) {
    return logAuditAction('data_delete', $table, $recordId, $data, null);
}

/**
 * Nettoie les anciens logs d'audit
 */
function cleanupAuditLogs($retentionDays = 180) {
    try {
        require_once __DIR__ . '/../config/env.php';
        
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        $stmt = $pdo->prepare("DELETE FROM audit_log WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->execute([$retentionDays]);
        
        return $stmt->rowCount();
        
    } catch (Exception $e) {
        error_log("Erreur nettoyage audit: " . $e->getMessage());
        return false;
    }
}

/**
 * Récupère les logs d'audit avec filtres
 */
function getAuditLogs($filters = [], $limit = 100, $offset = 0) {
    try {
        require_once __DIR__ . '/../config/env.php';
        
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        
        $where = [];
        $params = [];
        
        if (isset($filters['user_id'])) {
            $where[] = "user_id = ?";
            $params[] = $filters['user_id'];
        }
        
        if (isset($filters['action'])) {
            $where[] = "action LIKE ?";
            $params[] = '%' . $filters['action'] . '%';
        }
        
        if (isset($filters['date_from'])) {
            $where[] = "created_at >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (isset($filters['date_to'])) {
            $where[] = "created_at <= ?";
            $params[] = $filters['date_to'];
        }
        
        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $sql = "SELECT * FROM audit_log {$whereClause} ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("Erreur récupération audit: " . $e->getMessage());
        return [];
    }
}
