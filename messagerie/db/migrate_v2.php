<?php
/**
 * Migration v2 — Messagerie Fronote
 * Ajout d'index, colonnes pour édition/suppression/épinglage/recherche, rate limiting
 */
require_once __DIR__ . '/../config/config.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($pdo)) {
    die("Erreur: La connexion à la base de données n'est pas disponible.");
}

function execSQL($sql, $label) {
    global $pdo;
    try {
        $pdo->exec($sql);
        echo "✅ $label<br>";
        return true;
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false || strpos($e->getMessage(), 'already exists') !== false) {
            echo "⏭️ $label (déjà existant)<br>";
            return true;
        }
        echo "❌ $label — " . $e->getMessage() . "<br>";
        return false;
    }
}

function colExists($table, $col) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $col]);
    return (bool) $stmt->fetchColumn();
}

echo "<h1>Migration v2 — Messagerie Fronote</h1>";

// ============================================================
// 1. INDEX DE PERFORMANCE
// ============================================================
echo "<h2>1. Index de performance</h2>";

execSQL("CREATE INDEX idx_cp_conv_user ON conversation_participants (conversation_id, user_id, user_type)",
    "Index composite conversation_participants(conversation_id, user_id, user_type)");

execSQL("CREATE INDEX idx_messages_conv_created ON messages (conversation_id, created_at)",
    "Index composite messages(conversation_id, created_at)");

execSQL("CREATE INDEX idx_mn_user_read ON message_notifications (user_id, user_type, is_read)",
    "Index composite message_notifications(user_id, user_type, is_read)");

execSQL("CREATE INDEX idx_messages_sender ON messages (sender_id, sender_type)",
    "Index composite messages(sender_id, sender_type)");

execSQL("CREATE INDEX idx_ma_message ON message_attachments (message_id)",
    "Index message_attachments(message_id)");

execSQL("CREATE INDEX idx_conv_updated ON conversations (updated_at DESC)",
    "Index conversations(updated_at)");

execSQL("CREATE INDEX idx_cp_deleted_archived ON conversation_participants (is_deleted, is_archived)",
    "Index conversation_participants(is_deleted, is_archived)");

// ============================================================
// 2. COLONNES POUR ÉDITION / SUPPRESSION SOFT DE MESSAGES
// ============================================================
echo "<h2>2. Édition et suppression de messages</h2>";

if (!colExists('messages', 'edited_at')) {
    execSQL("ALTER TABLE messages ADD COLUMN edited_at DATETIME DEFAULT NULL AFTER updated_at",
        "Colonne messages.edited_at");
}

if (!colExists('messages', 'deleted_at')) {
    execSQL("ALTER TABLE messages ADD COLUMN deleted_at DATETIME DEFAULT NULL AFTER edited_at",
        "Colonne messages.deleted_at");
}

if (!colExists('messages', 'deleted_by_id')) {
    execSQL("ALTER TABLE messages ADD COLUMN deleted_by_id INT DEFAULT NULL AFTER deleted_at",
        "Colonne messages.deleted_by_id");
}

if (!colExists('messages', 'deleted_by_type')) {
    execSQL("ALTER TABLE messages ADD COLUMN deleted_by_type VARCHAR(50) DEFAULT NULL AFTER deleted_by_id",
        "Colonne messages.deleted_by_type");
}

if (!colExists('messages', 'original_body')) {
    execSQL("ALTER TABLE messages ADD COLUMN original_body TEXT DEFAULT NULL AFTER body",
        "Colonne messages.original_body (historique d'édition)");
}

if (!colExists('messages', 'parent_message_id')) {
    execSQL("ALTER TABLE messages ADD COLUMN parent_message_id INT DEFAULT NULL AFTER status",
        "Colonne messages.parent_message_id");
    execSQL("CREATE INDEX idx_messages_parent ON messages (parent_message_id)",
        "Index messages(parent_message_id)");
}

// ============================================================
// 3. ÉPINGLAGE DE MESSAGES
// ============================================================
echo "<h2>3. Épinglage de messages</h2>";

if (!colExists('messages', 'is_pinned')) {
    execSQL("ALTER TABLE messages ADD COLUMN is_pinned TINYINT(1) NOT NULL DEFAULT 0",
        "Colonne messages.is_pinned");
}

if (!colExists('messages', 'pinned_at')) {
    execSQL("ALTER TABLE messages ADD COLUMN pinned_at DATETIME DEFAULT NULL",
        "Colonne messages.pinned_at");
}

if (!colExists('messages', 'pinned_by_id')) {
    execSQL("ALTER TABLE messages ADD COLUMN pinned_by_id INT DEFAULT NULL",
        "Colonne messages.pinned_by_id");
}

if (!colExists('messages', 'pinned_by_type')) {
    execSQL("ALTER TABLE messages ADD COLUMN pinned_by_type VARCHAR(50) DEFAULT NULL",
        "Colonne messages.pinned_by_type");
}

// ============================================================
// 4. RECHERCHE FULL-TEXT
// ============================================================
echo "<h2>4. Recherche full-text</h2>";

execSQL("ALTER TABLE messages ADD FULLTEXT INDEX ft_messages_body (body)",
    "Index FULLTEXT messages(body)");

execSQL("ALTER TABLE conversations ADD FULLTEXT INDEX ft_conversations_subject (subject)",
    "Index FULLTEXT conversations(subject)");

// ============================================================
// 5. TABLE DE RATE LIMITING
// ============================================================
echo "<h2>5. Rate limiting</h2>";

execSQL("CREATE TABLE IF NOT EXISTS rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    user_type VARCHAR(50) NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rate_user_action (user_id, user_type, action_type, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "Table rate_limits");

// ============================================================
// 6. TABLE DE RÉACTIONS AUX MESSAGES
// ============================================================
echo "<h2>6. Réactions aux messages</h2>";

execSQL("CREATE TABLE IF NOT EXISTS message_reactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    user_id INT NOT NULL,
    user_type VARCHAR(50) NOT NULL,
    reaction VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_reaction (message_id, user_id, user_type, reaction),
    INDEX idx_reaction_message (message_id),
    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "Table message_reactions");

// ============================================================
// 7. NETTOYAGE AUTOMATIQUE DES RATE LIMITS
// ============================================================
echo "<h2>7. Nettoyage automatique</h2>";

// Supprimer les entrées de rate limiting vieilles de plus de 1 heure
try {
    $pdo->exec("DELETE FROM rate_limits WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    echo "✅ Nettoyage des anciennes entrées de rate limiting<br>";
} catch (PDOException $e) {
    // Table may not exist yet
}

echo "<hr><p>✅ Migration v2 terminée.</p>";
echo '<p><a href="../index.php" style="display:inline-block;padding:8px 12px;background:#009b72;color:white;text-decoration:none;border-radius:4px;">Retourner à la messagerie</a></p>';
?>
