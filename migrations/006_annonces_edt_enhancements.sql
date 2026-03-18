-- Migration 006: Annonces scheduling + Emploi du temps enhancements
-- Annonces : colonne notified pour publication programmée
ALTER TABLE `annonces` ADD COLUMN IF NOT EXISTS `notified` TINYINT(1) NOT NULL DEFAULT 0 AFTER `epingle`;
CREATE INDEX IF NOT EXISTS `idx_annonces_scheduled` ON `annonces` (`publie`, `date_publication`, `notified`);
CREATE INDEX IF NOT EXISTS `idx_annonces_active` ON `annonces` (`publie`, `date_expiration`);

-- Emploi du temps : table conflits détectés (cache)
CREATE TABLE IF NOT EXISTS `edt_conflits` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `cours_id_1` INT NOT NULL,
    `cours_id_2` INT NOT NULL,
    `type_conflit` ENUM('professeur', 'salle', 'classe') NOT NULL,
    `date_conflit` DATE NOT NULL,
    `resolu` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_conflits_date` (`date_conflit`, `resolu`),
    INDEX `idx_conflits_cours` (`cours_id_1`, `cours_id_2`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Emploi du temps : table modifications temporaires
CREATE TABLE IF NOT EXISTS `edt_modifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `cours_id` INT NOT NULL,
    `date_original` DATE NOT NULL,
    `type_modif` ENUM('annulation', 'deplacement', 'remplacement') NOT NULL,
    `nouvelle_date` DATE NULL,
    `nouveau_creneau` VARCHAR(20) NULL,
    `nouvelle_salle` VARCHAR(100) NULL,
    `professeur_remplacant_id` INT NULL,
    `motif` TEXT NULL,
    `cree_par_id` INT NOT NULL,
    `cree_par_type` VARCHAR(20) NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_modifs_cours` (`cours_id`, `date_original`),
    INDEX `idx_modifs_date` (`date_original`, `type_modif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
