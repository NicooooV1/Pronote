<?php
/**
 * Script d'initialisation et de mise à jour de la base de données
 */
require_once __DIR__ . '/../config/config.php';

// Afficher les erreurs pendant l'exécution de ce script
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Vérifier la connexion à la base de données
if (!isset($pdo)) {
    die("Erreur: La connexion à la base de données n'est pas disponible.");
}

// Fonction pour exécuter une requête SQL en toute sécurité
function executeSQL($sql, $params = []) {
    global $pdo;
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return true;
    } catch (PDOException $e) {
        echo "Erreur SQL: " . $e->getMessage() . "<br>";
        return false;
    }
}

// Fonction pour vérifier si une colonne existe dans une table
function columnExists($table, $column) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT 1 FROM information_schema.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = ? 
            AND COLUMN_NAME = ?
        ");
        $stmt->execute([$table, $column]);
        return $stmt->fetchColumn() ? true : false;
    } catch (PDOException $e) {
        echo "Erreur lors de la vérification de la colonne: " . $e->getMessage() . "<br>";
        return false;
    }
}

echo "<h1>Script de mise à jour de la base de données</h1>";

// ============================================================
// 0. Créer les tables de base si elles n'existent pas
// ============================================================
echo "<h2>Vérification des tables de base</h2>";

// Table conversations
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `conversations` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `subject` varchar(255) NOT NULL,
        `type` varchar(50) DEFAULT 'standard',
        `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_conv_updated` (`updated_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    echo "Table 'conversations' : OK<br>";
} catch (PDOException $e) {
    echo "Erreur table conversations: " . $e->getMessage() . "<br>";
}

// Table conversation_participants
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `conversation_participants` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `conversation_id` int(11) NOT NULL,
        `user_id` int(11) NOT NULL,
        `user_type` enum('eleve','parent','professeur','vie_scolaire','administrateur') NOT NULL,
        `joined_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `last_read_at` datetime DEFAULT NULL,
        `unread_count` int(11) NOT NULL DEFAULT 0,
        `is_admin` tinyint(1) NOT NULL DEFAULT 0,
        `is_moderator` tinyint(1) NOT NULL DEFAULT 0,
        `is_archived` tinyint(1) NOT NULL DEFAULT 0,
        `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
        `version` int(11) NOT NULL DEFAULT 1,
        PRIMARY KEY (`id`),
        KEY `idx_cp_conv_user` (`conversation_id`, `user_id`, `user_type`),
        KEY `idx_cp_deleted_archived` (`is_deleted`, `is_archived`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    echo "Table 'conversation_participants' : OK<br>";
} catch (PDOException $e) {
    echo "Erreur table conversation_participants: " . $e->getMessage() . "<br>";
}

// Table messages (si elle n'existe pas)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `messages` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `conversation_id` int(11) NOT NULL,
        `sender_id` int(11) NOT NULL,
        `sender_type` enum('eleve','parent','professeur','vie_scolaire','administrateur') NOT NULL,
        `body` text NOT NULL,
        `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `status` enum('normal','important','urgent','annonce') NOT NULL DEFAULT 'normal',
        PRIMARY KEY (`id`),
        KEY `idx_conversation` (`conversation_id`),
        KEY `idx_sender` (`sender_id`,`sender_type`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    echo "Table 'messages' : OK<br>";
} catch (PDOException $e) {
    echo "Erreur table messages: " . $e->getMessage() . "<br>";
}

// Table message_attachments
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `message_attachments` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `message_id` int(11) NOT NULL,
        `file_name` varchar(255) NOT NULL,
        `file_path` varchar(255) NOT NULL,
        `uploaded_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `message_id` (`message_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    echo "Table 'message_attachments' : OK<br>";
} catch (PDOException $e) {
    echo "Erreur table message_attachments: " . $e->getMessage() . "<br>";
}

// Table message_notifications
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `message_notifications` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `user_type` enum('eleve','parent','professeur','vie_scolaire','administrateur') NOT NULL,
        `message_id` int(11) NOT NULL,
        `notification_type` enum('unread','broadcast','mention','reply','important') NOT NULL DEFAULT 'unread',
        `notified_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `is_read` tinyint(1) NOT NULL DEFAULT 0,
        `read_at` datetime DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `message_id` (`message_id`),
        KEY `idx_message_notifications_user_read` (`user_id`,`user_type`,`is_read`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    echo "Table 'message_notifications' : OK<br>";
} catch (PDOException $e) {
    echo "Erreur table message_notifications: " . $e->getMessage() . "<br>";
}

// Table message_reactions
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `message_reactions` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `message_id` int(11) NOT NULL,
        `user_id` int(11) NOT NULL,
        `user_type` varchar(50) NOT NULL,
        `reaction` varchar(20) NOT NULL,
        `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_reaction` (`message_id`, `user_id`, `user_type`, `reaction`),
        KEY `idx_reaction_message` (`message_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    echo "Table 'message_reactions' : OK<br>";
} catch (PDOException $e) {
    echo "Erreur table message_reactions: " . $e->getMessage() . "<br>";
}

echo "<h2>Mises à jour des colonnes</h2>";

// Vérifier et ajouter la colonne is_deleted à la table messages si elle n'existe pas
if (!columnExists('messages', 'is_deleted')) {
    echo "Ajout de la colonne is_deleted à la table messages...<br>";
    
    if (executeSQL("ALTER TABLE messages ADD COLUMN is_deleted BOOLEAN NOT NULL DEFAULT 0")) {
        echo "Colonne is_deleted ajoutée avec succès.<br>";
    } else {
        echo "Échec de l'ajout de la colonne is_deleted.<br>";
    }
} else {
    echo "La colonne is_deleted existe déjà dans la table messages.<br>";
}

// Vérifier et ajouter la colonne version à la table conversation_participants si elle n'existe pas
if (!columnExists('conversation_participants', 'version')) {
    echo "Ajout de la colonne version à la table conversation_participants...<br>";
    
    if (executeSQL("ALTER TABLE conversation_participants ADD COLUMN version INT NOT NULL DEFAULT 1")) {
        echo "Colonne version ajoutée avec succès.<br>";
    } else {
        echo "Échec de l'ajout de la colonne version.<br>";
    }
} else {
    echo "La colonne version existe déjà dans la table conversation_participants.<br>";
}

// Vérifier et ajouter la colonne type à la table conversations si elle n'existe pas
if (!columnExists('conversations', 'type')) {
    echo "Ajout de la colonne type à la table conversations...<br>";
    
    if (executeSQL("ALTER TABLE conversations ADD COLUMN type VARCHAR(50) DEFAULT 'standard'")) {
        echo "Colonne type ajoutée avec succès.<br>";
        
        // Mettre à jour les types de conversations existantes
        echo "Mise à jour des types de conversation basés sur les messages...<br>";
        $updateSQL = "
            UPDATE conversations c
            SET c.type = 
                CASE 
                    WHEN EXISTS (SELECT 1 FROM messages m WHERE m.conversation_id = c.id AND m.status = 'annonce') THEN 'annonce'
                    ELSE 'standard'
                END
        ";
        
        if (executeSQL($updateSQL)) {
            echo "Types de conversations mis à jour avec succès.<br>";
        } else {
            echo "Échec de la mise à jour des types de conversations.<br>";
        }
    } else {
        echo "Échec de l'ajout de la colonne type.<br>";
    }
} else {
    echo "La colonne type existe déjà dans la table conversations.<br>";
}

echo "<p>Mise à jour terminée.</p>";

// Ajouter un lien pour retourner à l'application
echo '<p><a href="../index.php" style="display: inline-block; padding: 8px 12px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 4px;">Retourner à l\'application</a></p>';
?>
