<?php
/**
 * Planificateur de tâches de maintenance automatique
 * Exécute les tâches de nettoyage et maintenance
 */

if (!defined('PRONOTE_MAINTENANCE_LOADED')) {
    define('PRONOTE_MAINTENANCE_LOADED', true);
}

/**
 * Classe principale du planificateur
 */
class MaintenanceScheduler {
    private $pdo;
    private $logFile;
    
    public function __construct() {
        $this->logFile = __DIR__ . '/../logs/maintenance_' . date('Y-m-d') . '.log';
        $this->initDatabase();
    }
    
    private function initDatabase() {
        try {
            require_once __DIR__ . '/../config/env.php';
            
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
        } catch (Exception $e) {
            $this->log("Erreur connexion DB: " . $e->getMessage());
        }
    }
    
    /**
     * Exécute toutes les tâches de maintenance
     */
    public function runAllTasks() {
        $this->log("Début de la maintenance automatique");
        
        $tasks = [
            'cleanupSessions' => 'Nettoyage des sessions expirées',
            'cleanupAuditLogs' => 'Nettoyage des logs d\'audit',
            'cleanupTempFiles' => 'Nettoyage des fichiers temporaires',
            'optimizeDatabase' => 'Optimisation de la base de données',
            'checkDiskSpace' => 'Vérification de l\'espace disque',
            'updateSecurityHeaders' => 'Mise à jour des en-têtes de sécurité'
        ];
        
        foreach ($tasks as $method => $description) {
            try {
                $this->log("Exécution: {$description}");
                $result = $this->$method();
                $this->log("Terminé: {$description} - " . ($result ? 'Succès' : 'Échec'));
            } catch (Exception $e) {
                $this->log("Erreur {$description}: " . $e->getMessage());
            }
        }
        
        $this->log("Fin de la maintenance automatique");
    }
    
    /**
     * Nettoie les sessions expirées
     */
    private function cleanupSessions() {
        if (!$this->pdo) return false;
        
        try {
            $stmt = $this->pdo->prepare("DELETE FROM session_security WHERE expires_at < NOW()");
            $stmt->execute();
            $deleted = $stmt->rowCount();
            
            $this->log("Sessions nettoyées: {$deleted}");
            return true;
        } catch (Exception $e) {
            $this->log("Erreur nettoyage sessions: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Nettoie les anciens logs d'audit
     */
    private function cleanupAuditLogs() {
        if (!$this->pdo) return false;
        
        try {
            $retentionDays = 180; // 6 mois
            $stmt = $this->pdo->prepare("DELETE FROM audit_log WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
            $stmt->execute([$retentionDays]);
            $deleted = $stmt->rowCount();
            
            $this->log("Logs d'audit nettoyés: {$deleted}");
            return true;
        } catch (Exception $e) {
            $this->log("Erreur nettoyage audit: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Nettoie les fichiers temporaires
     */
    private function cleanupTempFiles() {
        $tempDir = dirname(__DIR__, 2) . '/temp';
        if (!is_dir($tempDir)) return true;
        
        $cleaned = 0;
        $cutoff = time() - (24 * 3600); // 24 heures
        
        try {
            $files = glob($tempDir . '/*');
            foreach ($files as $file) {
                if (is_file($file) && filemtime($file) < $cutoff) {
                    if (@unlink($file)) {
                        $cleaned++;
                    }
                }
            }
            
            $this->log("Fichiers temporaires nettoyés: {$cleaned}");
            return true;
        } catch (Exception $e) {
            $this->log("Erreur nettoyage fichiers: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Optimise la base de données
     */
    private function optimizeDatabase() {
        if (!$this->pdo) return false;
        
        try {
            $tables = ['audit_log', 'session_security', 'notes', 'absences', 'messages'];
            
            foreach ($tables as $table) {
                $this->pdo->exec("OPTIMIZE TABLE `{$table}`");
            }
            
            $this->log("Base de données optimisée");
            return true;
        } catch (Exception $e) {
            $this->log("Erreur optimisation DB: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Vérifie l'espace disque disponible
     */
    private function checkDiskSpace() {
        $rootPath = dirname(__DIR__, 2);
        $freeBytes = disk_free_space($rootPath);
        $totalBytes = disk_total_space($rootPath);
        
        if ($freeBytes === false || $totalBytes === false) {
            $this->log("Impossible de vérifier l'espace disque");
            return false;
        }
        
        $freePercent = ($freeBytes / $totalBytes) * 100;
        $freeMB = round($freeBytes / 1024 / 1024, 2);
        
        $this->log("Espace disque libre: {$freeMB} MB ({$freePercent}%)");
        
        if ($freePercent < 10) {
            $this->log("ALERTE: Espace disque faible!", 'WARNING');
        }
        
        return true;
    }
    
    /**
     * Met à jour les en-têtes de sécurité
     */
    private function updateSecurityHeaders() {
        $htaccessFile = dirname(__DIR__, 2) . '/.htaccess';
        
        $securityHeaders = [
            'Header always set X-Content-Type-Options nosniff',
            'Header always set X-Frame-Options DENY',
            'Header always set X-XSS-Protection "1; mode=block"',
            'Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"',
            'Header always set Content-Security-Policy "default-src \'self\'; script-src \'self\' \'unsafe-inline\'; style-src \'self\' \'unsafe-inline\'"'
        ];
        
        try {
            if (file_exists($htaccessFile)) {
                $content = file_get_contents($htaccessFile);
                $modified = false;
                
                foreach ($securityHeaders as $header) {
                    if (strpos($content, $header) === false) {
                        $content .= "\n" . $header;
                        $modified = true;
                    }
                }
                
                if ($modified) {
                    file_put_contents($htaccessFile, $content);
                    $this->log("En-têtes de sécurité mis à jour");
                }
            }
            
            return true;
        } catch (Exception $e) {
            $this->log("Erreur mise à jour sécurité: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Journalise les messages
     */
    private function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$level}] {$message}\n";
        
        // Créer le dossier logs s'il n'existe pas
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        @file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
        
        // Logger aussi dans le journal système
        error_log("Maintenance Pronote: {$message}");
    }
}

// Exécution automatique si appelé directement
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $scheduler = new MaintenanceScheduler();
    $scheduler->runAllTasks();
}
