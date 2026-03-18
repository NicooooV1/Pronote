-- Migration 003: RBAC, sécurité renforcée, tables manquantes
-- Date: 2026-03-02

-- ═══════════════ RBAC Permissions dynamiques ═══════════════
CREATE TABLE IF NOT EXISTS `rbac_permissions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `role` VARCHAR(50) NOT NULL,
    `permission` VARCHAR(100) NOT NULL,
    `granted` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_role_permission` (`role`, `permission`),
    INDEX `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════ Historique des mots de passe ═══════════════
CREATE TABLE IF NOT EXISTS `password_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `user_type` VARCHAR(50) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `changed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user` (`user_id`, `user_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════ Verrouillage anti-bruteforce amélioré ═══════════════
-- Colonnes locked_until + failed_attempts si pas déjà présentes
ALTER TABLE `eleves` ADD COLUMN IF NOT EXISTS `failed_login_attempts` INT NOT NULL DEFAULT 0;
ALTER TABLE `eleves` ADD COLUMN IF NOT EXISTS `locked_until` DATETIME NULL;
ALTER TABLE `eleves` ADD COLUMN IF NOT EXISTS `last_login_at` DATETIME NULL;
ALTER TABLE `eleves` ADD COLUMN IF NOT EXISTS `password_changed_at` DATETIME NULL;

ALTER TABLE `professeurs` ADD COLUMN IF NOT EXISTS `failed_login_attempts` INT NOT NULL DEFAULT 0;
ALTER TABLE `professeurs` ADD COLUMN IF NOT EXISTS `locked_until` DATETIME NULL;
ALTER TABLE `professeurs` ADD COLUMN IF NOT EXISTS `last_login_at` DATETIME NULL;
ALTER TABLE `professeurs` ADD COLUMN IF NOT EXISTS `password_changed_at` DATETIME NULL;

ALTER TABLE `parents` ADD COLUMN IF NOT EXISTS `failed_login_attempts` INT NOT NULL DEFAULT 0;
ALTER TABLE `parents` ADD COLUMN IF NOT EXISTS `locked_until` DATETIME NULL;
ALTER TABLE `parents` ADD COLUMN IF NOT EXISTS `last_login_at` DATETIME NULL;
ALTER TABLE `parents` ADD COLUMN IF NOT EXISTS `password_changed_at` DATETIME NULL;

ALTER TABLE `vie_scolaire` ADD COLUMN IF NOT EXISTS `failed_login_attempts` INT NOT NULL DEFAULT 0;
ALTER TABLE `vie_scolaire` ADD COLUMN IF NOT EXISTS `locked_until` DATETIME NULL;
ALTER TABLE `vie_scolaire` ADD COLUMN IF NOT EXISTS `last_login_at` DATETIME NULL;
ALTER TABLE `vie_scolaire` ADD COLUMN IF NOT EXISTS `password_changed_at` DATETIME NULL;

ALTER TABLE `administrateurs` ADD COLUMN IF NOT EXISTS `failed_login_attempts` INT NOT NULL DEFAULT 0;
ALTER TABLE `administrateurs` ADD COLUMN IF NOT EXISTS `locked_until` DATETIME NULL;
ALTER TABLE `administrateurs` ADD COLUMN IF NOT EXISTS `last_login_at` DATETIME NULL;
ALTER TABLE `administrateurs` ADD COLUMN IF NOT EXISTS `password_changed_at` DATETIME NULL;

-- ═══════════════ Index de performance ═══════════════
-- Absences
ALTER TABLE `absences` ADD INDEX IF NOT EXISTS `idx_abs_eleve` (`eleve_id`);
ALTER TABLE `absences` ADD INDEX IF NOT EXISTS `idx_abs_date` (`date_debut`, `date_fin`);
ALTER TABLE `absences` ADD INDEX IF NOT EXISTS `idx_abs_statut` (`statut`);

-- Notes
ALTER TABLE `notes` ADD INDEX IF NOT EXISTS `idx_notes_eleve` (`eleve_id`);
ALTER TABLE `notes` ADD INDEX IF NOT EXISTS `idx_notes_matiere` (`matiere_id`);

-- Messages
ALTER TABLE `messages` ADD INDEX IF NOT EXISTS `idx_msg_conv` (`conversation_id`);
ALTER TABLE `messages` ADD INDEX IF NOT EXISTS `idx_msg_created` (`created_at`);

-- Devoirs
ALTER TABLE `devoirs` ADD INDEX IF NOT EXISTS `idx_devoirs_date` (`date_rendu`);

-- Emploi du temps
ALTER TABLE `emploi_du_temps` ADD INDEX IF NOT EXISTS `idx_edt_jour` (`jour`);

-- Audit log
ALTER TABLE `audit_log` ADD INDEX IF NOT EXISTS `idx_audit_action` (`action`);
ALTER TABLE `audit_log` ADD INDEX IF NOT EXISTS `idx_audit_user` (`user_id`, `user_type`);
ALTER TABLE `audit_log` ADD INDEX IF NOT EXISTS `idx_audit_date` (`created_at`);

-- ═══════════════ Export / Historique ═══════════════
CREATE TABLE IF NOT EXISTS `export_jobs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `user_type` VARCHAR(50) NOT NULL,
    `type` ENUM('csv','pdf','xlsx') NOT NULL DEFAULT 'csv',
    `module` VARCHAR(100) NOT NULL,
    `filters` JSON NULL,
    `filename` VARCHAR(255) NULL,
    `status` ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
    `error_message` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `completed_at` TIMESTAMP NULL,
    INDEX `idx_export_user` (`user_id`, `user_type`),
    INDEX `idx_export_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════ Statut absences workflow ═══════════════
-- S'assurer que la colonne statut existe et supporte le workflow
ALTER TABLE `absences` MODIFY COLUMN IF EXISTS `statut` VARCHAR(30) NOT NULL DEFAULT 'signalée';
ALTER TABLE `absences` ADD COLUMN IF NOT EXISTS `statut` VARCHAR(30) NOT NULL DEFAULT 'signalée';
ALTER TABLE `absences` ADD COLUMN IF NOT EXISTS `validated_by` INT NULL;
ALTER TABLE `absences` ADD COLUMN IF NOT EXISTS `validated_at` DATETIME NULL;
ALTER TABLE `absences` ADD COLUMN IF NOT EXISTS `validation_comment` TEXT NULL;

-- ═══════════════ Justificatifs workflow ═══════════════
ALTER TABLE `justificatifs` ADD COLUMN IF NOT EXISTS `statut` VARCHAR(30) NOT NULL DEFAULT 'en_attente';
ALTER TABLE `justificatifs` ADD COLUMN IF NOT EXISTS `traite_par` INT NULL;
ALTER TABLE `justificatifs` ADD COLUMN IF NOT EXISTS `traite_at` DATETIME NULL;
ALTER TABLE `justificatifs` ADD COLUMN IF NOT EXISTS `commentaire_traitement` TEXT NULL;
