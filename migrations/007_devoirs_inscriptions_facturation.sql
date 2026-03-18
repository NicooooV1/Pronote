-- Migration 007: Devoir submissions, inscriptions workflow, facturation
-- Table soumissions de devoirs (élèves)
CREATE TABLE IF NOT EXISTS `devoir_submissions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `devoir_id` INT NOT NULL,
    `eleve_id` INT NOT NULL,
    `fichier_path` VARCHAR(500) NULL,
    `fichier_nom` VARCHAR(255) NULL,
    `soumis_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `statut` ENUM('soumis', 'evalue', 'renvoye') NOT NULL DEFAULT 'soumis',
    `note` DECIMAL(5,2) NULL,
    `commentaire_prof` TEXT NULL,
    `evalue_at` DATETIME NULL,
    UNIQUE KEY `uk_devoir_eleve` (`devoir_id`, `eleve_id`),
    INDEX `idx_submissions_devoir` (`devoir_id`, `statut`),
    INDEX `idx_submissions_eleve` (`eleve_id`, `soumis_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Inscriptions : workflow
ALTER TABLE `inscriptions` ADD COLUMN IF NOT EXISTS `workflow_statut` ENUM(
    'brouillon', 'soumise', 'en_cours_examen', 'documents_requis',
    'acceptee', 'refusee', 'liste_attente', 'annulee'
) NOT NULL DEFAULT 'brouillon' AFTER `statut`;
ALTER TABLE `inscriptions` ADD COLUMN IF NOT EXISTS `traite_par_id` INT NULL;
ALTER TABLE `inscriptions` ADD COLUMN IF NOT EXISTS `traite_at` DATETIME NULL;
ALTER TABLE `inscriptions` ADD COLUMN IF NOT EXISTS `commentaire_traitement` TEXT NULL;
ALTER TABLE `inscriptions` ADD COLUMN IF NOT EXISTS `priorite` INT NOT NULL DEFAULT 0;
CREATE INDEX IF NOT EXISTS `idx_inscriptions_workflow` ON `inscriptions` (`workflow_statut`, `annee_scolaire`);

-- Facturation : statuts enrichis
ALTER TABLE `factures` ADD COLUMN IF NOT EXISTS `rappel_envoye` TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE `factures` ADD COLUMN IF NOT EXISTS `rappel_date` DATETIME NULL;
ALTER TABLE `factures` ADD COLUMN IF NOT EXISTS `reference` VARCHAR(50) NULL;
CREATE INDEX IF NOT EXISTS `idx_factures_statut_echeance` ON `factures` (`statut`, `date_echeance`);
CREATE INDEX IF NOT EXISTS `idx_factures_eleve` ON `factures` (`eleve_id`, `annee_scolaire`);
