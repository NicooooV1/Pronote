<?php
/**
 * Configuration de sécurité pour la base de données
 * Améliore la structure existante avec des contraintes de sécurité
 */

if (!defined('PRONOTE_DB_SECURITY_LOADED')) {
    define('PRONOTE_DB_SECURITY_LOADED', true);
}

/**
 * Met à jour la base de données avec les améliorations de sécurité
 */
function updateDatabaseSecurity($pdo) {
    $securityUpdates = [
        // Ajouter des contraintes de sécurité manquantes
        'add_password_history' => "CREATE TABLE IF NOT EXISTS `password_history` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `user_type` enum('administrateur','professeur','eleve','parent','vie_scolaire') NOT NULL,
            `password_hash` varchar(255) NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_user` (`user_id`, `user_type`),
            KEY `idx_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        'add_audit_log' => "CREATE TABLE IF NOT EXISTS `audit_log` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) DEFAULT NULL,
            `user_type` varchar(20) DEFAULT NULL,
            `action` varchar(100) NOT NULL,
            `table_affected` varchar(50) DEFAULT NULL,
            `record_id` int(11) DEFAULT NULL,
            `old_values` json DEFAULT NULL,
            `new_values` json DEFAULT NULL,
            `ip_address` varchar(45) NOT NULL,
            `user_agent` text,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_user` (`user_id`, `user_type`),
            KEY `idx_action` (`action`),
            KEY `idx_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        'add_session_security' => "CREATE TABLE IF NOT EXISTS `session_security` (
            `id` varchar(128) NOT NULL,
            `user_id` int(11) NOT NULL,
            `user_type` varchar(20) NOT NULL,
            `ip_address` varchar(45) NOT NULL,
            `user_agent` text,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `last_activity` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `expires_at` timestamp NOT NULL,
            `is_active` tinyint(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (`id`),
            KEY `idx_user` (`user_id`, `user_type`),
            KEY `idx_expires` (`expires_at`),
            KEY `idx_active` (`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        
        // Améliorer les tables existantes
        'improve_administrateurs' => [
            "ALTER TABLE `administrateurs` ADD COLUMN `last_login` timestamp NULL DEFAULT NULL",
            "ALTER TABLE `administrateurs` ADD COLUMN `failed_login_attempts` int(3) DEFAULT 0",
            "ALTER TABLE `administrateurs` ADD COLUMN `locked_until` timestamp NULL DEFAULT NULL",
            "ALTER TABLE `administrateurs` ADD COLUMN `password_changed_at` timestamp NULL DEFAULT NULL",
            "ALTER TABLE `administrateurs` ADD COLUMN `two_factor_enabled` tinyint(1) DEFAULT 0",
            "ALTER TABLE `administrateurs` ADD COLUMN `two_factor_secret` varchar(32) DEFAULT NULL"
        ],
        
        'improve_eleves' => [
            "ALTER TABLE `eleves` ADD COLUMN `last_login` timestamp NULL DEFAULT NULL",
            "ALTER TABLE `eleves` ADD COLUMN `failed_login_attempts` int(3) DEFAULT 0",
            "ALTER TABLE `eleves` ADD COLUMN `locked_until` timestamp NULL DEFAULT NULL",
            "ALTER TABLE `eleves` ADD COLUMN `password_changed_at` timestamp NULL DEFAULT NULL"
        ],
        
        'improve_professeurs' => [
            "ALTER TABLE `professeurs` ADD COLUMN `last_login` timestamp NULL DEFAULT NULL",
            "ALTER TABLE `professeurs` ADD COLUMN `failed_login_attempts` int(3) DEFAULT 0",
            "ALTER TABLE `professeurs` ADD COLUMN `locked_until` timestamp NULL DEFAULT NULL",
            "ALTER TABLE `professeurs` ADD COLUMN `password_changed_at` timestamp NULL DEFAULT NULL"
        ]
    ];
    
    foreach ($securityUpdates as $name => $queries) {
        try {
            if (is_array($queries)) {
                foreach ($queries as $query) {
                    $pdo->exec($query);
                }
            } else {
                $pdo->exec($queries);
            }
            error_log("Sécurité DB: {$name} appliqué avec succès");
        } catch (PDOException $e) {
            // Ignorer les erreurs si les colonnes/tables existent déjà
            if (strpos($e->getMessage(), 'Duplicate column') === false && 
                strpos($e->getMessage(), 'already exists') === false) {
                error_log("Erreur sécurité DB {$name}: " . $e->getMessage());
            }
        }
    }
}

/**
 * Nettoie les anciennes entrées pour optimiser la base
 */
function cleanupOldEntries($pdo) {
    $cleanupQueries = [
        // Nettoyer les sessions expirées
        "DELETE FROM session_security WHERE expires_at < NOW()",
        
        // Garder seulement les 5 derniers mots de passe
        "DELETE p1 FROM password_history p1
         INNER JOIN (
             SELECT user_id, user_type, id
             FROM password_history p2
             WHERE p2.user_id = p1.user_id AND p2.user_type = p1.user_type
             ORDER BY created_at DESC
             LIMIT 5, 18446744073709551615
         ) p2 ON p1.id = p2.id",
        
        // Nettoyer les logs d'audit de plus de 6 mois
        "DELETE FROM audit_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH)"
    ];
    
    foreach ($cleanupQueries as $query) {
        try {
            $pdo->exec($query);
        } catch (PDOException $e) {
            error_log("Erreur nettoyage DB: " . $e->getMessage());
        }
    }
}
