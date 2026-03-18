-- ============================================================
-- Migration 002: Sidebar enhancements, missing modules, 2FA, 
--                accueil widget config, dark theme support
-- ============================================================

-- 1. Add missing modules to modules_config (skip if already exists)
INSERT IGNORE INTO `modules_config` (`module_key`, `label`, `description`, `icon`, `category`, `enabled`, `sort_order`, `is_core`) VALUES
('cantine',              'Cantine',               'Menus et réservations de la cantine',                  'fas fa-utensils',            'logistique',    1, 56, 0),
('internat',             'Internat',              'Gestion des chambres et affectations',                 'fas fa-bed',                 'logistique',    1, 57, 0),
('garderie',             'Garderie',              'Créneaux et inscriptions garderie',                    'fas fa-child',               'logistique',    1, 58, 0),
('projets_pedagogiques', 'Projets pédagogiques',  'Suivi des projets pédagogiques',                      'fas fa-project-diagram',     'etablissement', 1, 48, 0),
('parcours_educatifs',   'Parcours éducatifs',    'Parcours citoyen, artistique, avenir, santé',         'fas fa-route',               'etablissement', 1, 49, 0),
('vie_associative',      'Vie associative',       'Associations et activités de l''établissement',       'fas fa-hands-helping',       'etablissement', 1, 50, 0);

-- 2. Fix the module key mismatch: sidebar used 'cahier_textes' but DB has 'cahierdetextes'
--    (No change needed in DB — the fix is in the PHP sidebar code)

-- 3. Add 2FA and accueil config columns to user_settings
ALTER TABLE `user_settings`
  ADD COLUMN `two_factor_enabled` tinyint(1) NOT NULL DEFAULT 0 AFTER `bio`,
  ADD COLUMN `two_factor_secret` varchar(64) DEFAULT NULL AFTER `two_factor_enabled`,
  ADD COLUMN `accueil_config` text DEFAULT NULL AFTER `two_factor_secret`;

-- 4. Add two_factor columns to each user table for login verification
ALTER TABLE `administrateurs`
  ADD COLUMN `two_factor_enabled` tinyint(1) NOT NULL DEFAULT 0 AFTER `actif`,
  ADD COLUMN `two_factor_secret` varchar(64) DEFAULT NULL AFTER `two_factor_enabled`;

ALTER TABLE `professeurs`
  ADD COLUMN `two_factor_enabled` tinyint(1) NOT NULL DEFAULT 0 AFTER `actif`,
  ADD COLUMN `two_factor_secret` varchar(64) DEFAULT NULL AFTER `two_factor_enabled`;

ALTER TABLE `eleves`
  ADD COLUMN `two_factor_enabled` tinyint(1) NOT NULL DEFAULT 0 AFTER `actif`,
  ADD COLUMN `two_factor_secret` varchar(64) DEFAULT NULL AFTER `two_factor_enabled`;

ALTER TABLE `parents`
  ADD COLUMN `two_factor_enabled` tinyint(1) NOT NULL DEFAULT 0 AFTER `actif`,
  ADD COLUMN `two_factor_secret` varchar(64) DEFAULT NULL AFTER `two_factor_enabled`;

ALTER TABLE `vie_scolaire`
  ADD COLUMN `two_factor_enabled` tinyint(1) NOT NULL DEFAULT 0 AFTER `actif`,
  ADD COLUMN `two_factor_secret` varchar(64) DEFAULT NULL AFTER `two_factor_enabled`;
