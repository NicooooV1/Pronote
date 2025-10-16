<?php
/**
 * Audit Service - Event Sourcing pour traçabilité complète
 * Enregistre toutes les actions critiques dans la base de données
 */

namespace Pronote\Services;

class AuditService {
    protected $db;
    protected $table = 'audit_log';
    protected $sensitiveFields = [
        'password', 'mot_de_passe', 'pwd', 'mdp',
        'token', 'secret', 'api_key', 'api_secret',
        'credit_card', 'ssn', 'two_factor_secret'
    ];
    
    public function __construct($db = null) {
        if ($db === null && class_exists('\Database')) {
            $this->db = \Database::getInstance();
        } else {
            $this->db = $db;
        }
    }
    
    /**
     * Log une action
     * @param string $action Action effectuée (ex: 'model.created', 'auth.login')
     * @param mixed $model Modèle concerné ou null
     * @param array $changes Changements effectués
     * @return bool Succès de l'opération
     */
    public function log($action, $model = null, $changes = []) {
        try {
            $event = [
                'action' => $action,
                'model' => $model ? $this->getModelClass($model) : null,
                'model_id' => $model ? $this->getModelId($model) : null,
                'user_id' => $this->getCurrentUserId(),
                'user_type' => $this->getCurrentUserType(),
                'old_values' => !empty($changes['old']) ? json_encode($this->sanitize($changes['old'])) : null,
                'new_values' => !empty($changes['new']) ? json_encode($this->sanitize($changes['new'])) : null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            return $this->insert($event);
            
        } catch (\Exception $e) {
            error_log("AuditService error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log une action d'authentification
     */
    public function logAuth($action, $username = null, $success = true, $context = []) {
        $event = [
            'action' => 'auth.' . $action,
            'model' => null,
            'model_id' => null,
            'user_id' => $success ? $this->getCurrentUserId() : null,
            'user_type' => $success ? $this->getCurrentUserType() : null,
            'old_values' => null,
            'new_values' => json_encode(array_merge([
                'username' => $username,
                'success' => $success
            ], $context)),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        return $this->insert($event);
    }
    
    /**
     * Log une action de sécurité
     */
    public function logSecurity($event, $details = []) {
        return $this->log('security.' . $event, null, ['new' => $details]);
    }
    
    /**
     * Log la création d'un modèle
     */
    public function logCreated($model) {
        $attributes = $this->getModelAttributes($model);
        return $this->log('model.created', $model, [
            'new' => $attributes
        ]);
    }
    
    /**
     * Log la mise à jour d'un modèle
     */
    public function logUpdated($model, $dirty = []) {
        if (empty($dirty)) {
            return true; // Rien à logger
        }
        
        $original = $this->getOriginalAttributes($model);
        $changes = [];
        
        foreach ($dirty as $key => $newValue) {
            $changes[$key] = [
                'old' => $original[$key] ?? null,
                'new' => $newValue
            ];
        }
        
        return $this->log('model.updated', $model, [
            'old' => array_column($changes, 'old'),
            'new' => array_column($changes, 'new')
        ]);
    }
    
    /**
     * Log la suppression d'un modèle
     */
    public function logDeleted($model) {
        $attributes = $this->getModelAttributes($model);
        return $this->log('model.deleted', $model, [
            'old' => $attributes
        ]);
    }
    
    /**
     * Récupère l'historique d'un modèle
     */
    public function getHistory($model, $limit = 50) {
        $modelClass = $this->getModelClass($model);
        $modelId = $this->getModelId($model);
        
        if (!$modelClass || !$modelId) {
            return [];
        }
        
        try {
            $sql = "SELECT * FROM {$this->table} 
                    WHERE model = ? AND model_id = ?
                    ORDER BY created_at DESC
                    LIMIT ?";
            
            $pdo = $this->db->getPDO();
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$modelClass, $modelId, $limit]);
            
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
        } catch (\Exception $e) {
            error_log("AuditService getHistory error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Récupère l'historique par utilisateur
     */
    public function getHistoryByUser($userId, $userType = null, $limit = 50) {
        try {
            $sql = "SELECT * FROM {$this->table} WHERE user_id = ?";
            $params = [$userId];
            
            if ($userType !== null) {
                $sql .= " AND user_type = ?";
                $params[] = $userType;
            }
            
            $sql .= " ORDER BY created_at DESC LIMIT ?";
            $params[] = $limit;
            
            $pdo = $this->db->getPDO();
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
        } catch (\Exception $e) {
            error_log("AuditService getHistoryByUser error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Récupère l'historique par action
     */
    public function getHistoryByAction($action, $limit = 50) {
        try {
            $sql = "SELECT * FROM {$this->table} 
                    WHERE action = ?
                    ORDER BY created_at DESC
                    LIMIT ?";
            
            $pdo = $this->db->getPDO();
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$action, $limit]);
            
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
        } catch (\Exception $e) {
            error_log("AuditService getHistoryByAction error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Récupère l'historique par période
     */
    public function getHistoryByPeriod($startDate, $endDate, $limit = 100) {
        try {
            $sql = "SELECT * FROM {$this->table} 
                    WHERE created_at BETWEEN ? AND ?
                    ORDER BY created_at DESC
                    LIMIT ?";
            
            $pdo = $this->db->getPDO();
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$startDate, $endDate, $limit]);
            
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
        } catch (\Exception $e) {
            error_log("AuditService getHistoryByPeriod error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Nettoie les anciens logs
     */
    public function cleanup($daysToKeep = 180) {
        try {
            $sql = "DELETE FROM {$this->table} 
                    WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
            
            $pdo = $this->db->getPDO();
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$daysToKeep]);
            
            $deleted = $stmt->rowCount();
            
            error_log("AuditService: Cleaned up {$deleted} old audit logs");
            
            return $deleted;
            
        } catch (\Exception $e) {
            error_log("AuditService cleanup error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Statistiques d'audit
     */
    public function getStats($days = 30) {
        try {
            $sql = "SELECT 
                    action,
                    COUNT(*) as count,
                    COUNT(DISTINCT user_id) as unique_users,
                    DATE(created_at) as date
                    FROM {$this->table}
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                    GROUP BY action, DATE(created_at)
                    ORDER BY date DESC, count DESC";
            
            $pdo = $this->db->getPDO();
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$days]);
            
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
        } catch (\Exception $e) {
            error_log("AuditService getStats error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Recherche dans l'audit
     */
    public function search(array $criteria, $limit = 50) {
        try {
            $wheres = [];
            $params = [];
            
            if (isset($criteria['action'])) {
                $wheres[] = "action LIKE ?";
                $params[] = '%' . $criteria['action'] . '%';
            }
            
            if (isset($criteria['user_id'])) {
                $wheres[] = "user_id = ?";
                $params[] = $criteria['user_id'];
            }
            
            if (isset($criteria['model'])) {
                $wheres[] = "model = ?";
                $params[] = $criteria['model'];
            }
            
            if (isset($criteria['ip'])) {
                $wheres[] = "ip_address = ?";
                $params[] = $criteria['ip'];
            }
            
            if (isset($criteria['date_from'])) {
                $wheres[] = "created_at >= ?";
                $params[] = $criteria['date_from'];
            }
            
            if (isset($criteria['date_to'])) {
                $wheres[] = "created_at <= ?";
                $params[] = $criteria['date_to'];
            }
            
            $sql = "SELECT * FROM {$this->table}";
            
            if (!empty($wheres)) {
                $sql .= " WHERE " . implode(' AND ', $wheres);
            }
            
            $sql .= " ORDER BY created_at DESC LIMIT ?";
            $params[] = $limit;
            
            $pdo = $this->db->getPDO();
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
        } catch (\Exception $e) {
            error_log("AuditService search error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Insère un événement d'audit
     */
    protected function insert(array $event) {
        try {
            $columns = array_keys($event);
            $placeholders = array_fill(0, count($event), '?');
            
            $sql = sprintf(
                "INSERT INTO %s (%s) VALUES (%s)",
                $this->table,
                implode(', ', $columns),
                implode(', ', $placeholders)
            );
            
            $pdo = $this->db->getPDO();
            $stmt = $pdo->prepare($sql);
            
            return $stmt->execute(array_values($event));
            
        } catch (\Exception $e) {
            error_log("AuditService insert error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Sanitize les données sensibles
     */
    protected function sanitize($data) {
        if (!is_array($data)) {
            return $data;
        }
        
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            if (in_array(strtolower($key), $this->sensitiveFields)) {
                $sanitized[$key] = '***REDACTED***';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitize($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Récupère la classe du modèle
     */
    protected function getModelClass($model) {
        if (is_string($model)) {
            return $model;
        }
        
        if (is_object($model)) {
            return get_class($model);
        }
        
        return null;
    }
    
    /**
     * Récupère l'ID du modèle
     */
    protected function getModelId($model) {
        if (is_array($model) && isset($model['id'])) {
            return $model['id'];
        }
        
        if (is_object($model)) {
            if (isset($model->id)) {
                return $model->id;
            }
            
            if (method_exists($model, 'getAttribute')) {
                return $model->getAttribute('id');
            }
        }
        
        return null;
    }
    
    /**
     * Récupère les attributs du modèle
     */
    protected function getModelAttributes($model) {
        if (is_array($model)) {
            return $model;
        }
        
        if (is_object($model) && method_exists($model, 'toArray')) {
            return $model->toArray();
        }
        
        if (is_object($model) && isset($model->attributes)) {
            return $model->attributes;
        }
        
        return [];
    }
    
    /**
     * Récupère les attributs originaux du modèle
     */
    protected function getOriginalAttributes($model) {
        if (is_object($model) && isset($model->original)) {
            return $model->original;
        }
        
        return $this->getModelAttributes($model);
    }
    
    /**
     * Récupère l'ID de l'utilisateur actuel
     */
    protected function getCurrentUserId() {
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user']['id'])) {
            return $_SESSION['user']['id'];
        }
        
        return null;
    }
    
    /**
     * Récupère le type de l'utilisateur actuel
     */
    protected function getCurrentUserType() {
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user']['profil'])) {
            return $_SESSION['user']['profil'];
        }
        
        return null;
    }
    
    /**
     * Ajoute un champ sensible
     */
    public function addSensitiveField($field) {
        if (!in_array(strtolower($field), $this->sensitiveFields)) {
            $this->sensitiveFields[] = strtolower($field);
        }
    }
    
    /**
     * Définit les champs sensibles
     */
    public function setSensitiveFields(array $fields) {
        $this->sensitiveFields = array_map('strtolower', $fields);
    }
}

/**
 * Facade Audit
 */
class Audit {
    protected static $instance;
    
    public static function setInstance(AuditService $instance) {
        self::$instance = $instance;
    }
    
    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new AuditService();
        }
        return self::$instance;
    }
    
    public static function __callStatic($method, $args) {
        return self::getInstance()->$method(...$args);
    }
}
