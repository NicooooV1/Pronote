-- Migration 008: RGPD retention policies table
-- Date: 2025-01-01

CREATE TABLE IF NOT EXISTS rgpd_retention_policies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    table_name VARCHAR(100) NOT NULL UNIQUE,
    retention_days INT NOT NULL DEFAULT 90,
    actif TINYINT(1) NOT NULL DEFAULT 1,
    derniere_purge DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default retention policies
INSERT IGNORE INTO rgpd_retention_policies (table_name, retention_days, actif) VALUES
('audit_log', 365, 1),
('session_security', 90, 1),
('messages', 180, 1),
('notifications', 90, 1),
('rate_limits', 30, 1);

-- Add index on audit_log for efficient purge queries
ALTER TABLE audit_log ADD INDEX IF NOT EXISTS idx_audit_log_created (created_at);

-- Add index on session_security for purge
ALTER TABLE session_security ADD INDEX IF NOT EXISTS idx_session_created (created_at);
