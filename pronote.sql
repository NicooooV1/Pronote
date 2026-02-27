-- Pronote empty schema (structure only) - corrected
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
SET SESSION FOREIGN_KEY_CHECKS = 0;

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- NOTE: La création de la base et USE sont gérés par install.php
-- Ne PAS ajouter CREATE DATABASE / USE ici.

-- --------------------------------------------------------
-- Drop existing tables (if any)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `message_reactions`;
DROP TABLE IF EXISTS `message_notifications`;
DROP TABLE IF EXISTS `message_attachments`;
DROP TABLE IF EXISTS `messages`;
DROP TABLE IF EXISTS `conversation_participants`;
DROP TABLE IF EXISTS `conversations`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `notes`;
DROP TABLE IF EXISTS `absences`;
DROP TABLE IF EXISTS `professeur_classes`;
DROP TABLE IF EXISTS `rate_limits`;
DROP TABLE IF EXISTS `api_rate_limits`;
DROP TABLE IF EXISTS `retards`;
DROP TABLE IF EXISTS `user_notification_preferences`;
DROP TABLE IF EXISTS `demandes_reinitialisation`;
DROP TABLE IF EXISTS `classes`;
DROP TABLE IF EXISTS `matieres`;
DROP TABLE IF EXISTS `vie_scolaire`;
DROP TABLE IF EXISTS `parents`;
DROP TABLE IF EXISTS `professeurs`;
DROP TABLE IF EXISTS `eleves`;
DROP TABLE IF EXISTS `administrateurs`;
DROP TABLE IF EXISTS `audit_log`;
DROP TABLE IF EXISTS `session_security`;
DROP TABLE IF EXISTS `devoirs`;
DROP TABLE IF EXISTS `evenements`;
DROP TABLE IF EXISTS `justificatifs`;

-- --------------------------------------------------------
-- Tables
-- --------------------------------------------------------

CREATE TABLE `administrateurs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mail` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `identifiant` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mot_de_passe` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `adresse` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'administrateur',
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `date_creation` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `failed_login_attempts` int(3) DEFAULT 0,
  `locked_until` timestamp NULL DEFAULT NULL,
  `password_changed_at` timestamp NULL DEFAULT NULL,
  `two_factor_enabled` tinyint(1) DEFAULT 0,
  `two_factor_secret` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `identifiant` (`identifiant`),
  UNIQUE KEY `mail` (`mail`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `eleves` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_naissance` date NOT NULL,
  `classe` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `lieu_naissance` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `adresse` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mail` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telephone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `identifiant` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mot_de_passe` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `date_creation` datetime DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `failed_login_attempts` int(3) DEFAULT 0,
  `locked_until` timestamp NULL DEFAULT NULL,
  `password_changed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mail` (`mail`),
  UNIQUE KEY `identifiant` (`identifiant`),
  KEY `idx_classe` (`classe`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `professeurs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mail` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `adresse` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telephone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `identifiant` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mot_de_passe` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `professeur_principal` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'non',
  `matiere` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `date_creation` datetime DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `failed_login_attempts` int(3) DEFAULT 0,
  `locked_until` timestamp NULL DEFAULT NULL,
  `password_changed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mail` (`mail`),
  UNIQUE KEY `identifiant` (`identifiant`),
  KEY `idx_matiere` (`matiere`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `parents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mail` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `adresse` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telephone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metier` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `identifiant` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mot_de_passe` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `est_parent_eleve` enum('oui','non') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'non',
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `date_creation` datetime DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `failed_login_attempts` int(3) DEFAULT 0,
  `locked_until` timestamp NULL DEFAULT NULL,
  `password_changed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mail` (`mail`),
  UNIQUE KEY `identifiant` (`identifiant`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `vie_scolaire` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prenom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mail` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telephone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `identifiant` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mot_de_passe` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `est_CPE` enum('oui','non') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'non',
  `est_infirmerie` enum('oui','non') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'non',
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `date_creation` datetime DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `failed_login_attempts` int(3) DEFAULT 0,
  `locked_until` timestamp NULL DEFAULT NULL,
  `password_changed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mail` (`mail`),
  UNIQUE KEY `identifiant` (`identifiant`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `matieres` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `coefficient` decimal(3,2) DEFAULT 1.00,
  `couleur` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT '#3498db',
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `classes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `niveau` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `annee_scolaire` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `professeur_principal_id` int(11) DEFAULT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nom_annee` (`nom`, `annee_scolaire`),
  KEY `idx_professeur_principal` (`professeur_principal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE `notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_eleve` int(11) NOT NULL,
  `id_matiere` int(11) NOT NULL,
  `id_professeur` int(11) NOT NULL,
  `note` decimal(4,2) NOT NULL,
  `note_sur` decimal(4,2) NOT NULL DEFAULT 20.00,
  `coefficient` decimal(3,2) NOT NULL DEFAULT 1.00,
  `type_evaluation` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Contrôle',
  `date_note` date NOT NULL,
  `commentaire` text COLLATE utf8mb4_unicode_ci,
  `trimestre` int(1) NOT NULL DEFAULT 1,
  `date_creation` datetime DEFAULT current_timestamp(),
  `date_modification` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_eleve_matiere` (`id_eleve`, `id_matiere`),
  KEY `idx_professeur` (`id_professeur`),
  KEY `idx_date` (`date_note`),
  KEY `idx_trimestre` (`trimestre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE `absences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_eleve` int(11) NOT NULL,
  `date_debut` datetime NOT NULL,
  `date_fin` datetime NOT NULL,
  `type_absence` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `motif` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `justifie` tinyint(1) DEFAULT 0,
  `commentaire` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `signale_par` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date_signalement` timestamp NOT NULL DEFAULT current_timestamp(),
  `date_modification` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_eleve` (`id_eleve`),
  KEY `idx_date` (`date_debut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CONVERSATIONS
CREATE TABLE `conversations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subject` varchar(255) NOT NULL,
  `type` varchar(50) DEFAULT 'standard',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_conv_updated` (`updated_at`),
  FULLTEXT KEY `ft_conversations_subject` (`subject`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- CONVERSATION PARTICIPANTS
CREATE TABLE `conversation_participants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `conversation_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_type` enum('eleve','parent','professeur','vie_scolaire','administrateur') NOT NULL,
  `joined_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_read_at` datetime DEFAULT NULL,
  `unread_count` int(11) NOT NULL DEFAULT 0,
  `is_admin` tinyint(1) NOT NULL DEFAULT 0,
  `is_moderator` tinyint(1) NOT NULL DEFAULT 0,
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `version` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_cp_conv_user` (`conversation_id`, `user_id`, `user_type`),
  KEY `idx_cp_deleted_archived` (`is_deleted`, `is_archived`),
  CONSTRAINT `fk_cp_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- MESSAGES
CREATE TABLE `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `conversation_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `sender_type` enum('eleve','parent','professeur','vie_scolaire','administrateur') NOT NULL,
  `body` text NOT NULL,
  `original_body` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `edited_at` datetime DEFAULT NULL,
  `status` enum('normal','important','urgent','annonce') NOT NULL DEFAULT 'normal',
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by_id` int(11) DEFAULT NULL,
  `deleted_by_type` varchar(50) DEFAULT NULL,
  `parent_message_id` int(11) DEFAULT NULL,
  `is_pinned` tinyint(1) NOT NULL DEFAULT 0,
  `pinned_at` datetime DEFAULT NULL,
  `pinned_by_id` int(11) DEFAULT NULL,
  `pinned_by_type` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_conversation` (`conversation_id`),
  KEY `idx_sender` (`sender_id`,`sender_type`),
  KEY `idx_messages_conv_created` (`conversation_id`,`created_at`),
  KEY `idx_messages_parent` (`parent_message_id`),
  FULLTEXT KEY `ft_messages_body` (`body`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `message_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `message_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `message_id` (`message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `message_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `user_type` enum('eleve','parent','professeur','vie_scolaire','administrateur') NOT NULL,
  `message_id` int(11) NOT NULL,
  `notification_type` enum('unread','broadcast','mention','reply','important') NOT NULL DEFAULT 'unread',
  `notified_at` datetime NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `message_id` (`message_id`),
  KEY `idx_message_notifications_user_read` (`user_id`,`user_type`,`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` enum('creation','rappel','correction') NOT NULL,
  `id_devoir` int(11) NOT NULL,
  `statut` enum('en_attente','envoye','erreur') NOT NULL DEFAULT 'en_attente',
  `date_creation` datetime DEFAULT current_timestamp(),
  `date_envoi` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_devoir` (`id_devoir`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `professeur_classes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_professeur` int(11) NOT NULL,
  `nom_classe` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_prof_class` (`id_professeur`,`nom_classe`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `rate_limits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `user_type` varchar(50) NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `attempted_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_rate_user_action` (`user_id`,`user_type`,`action_type`,`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `api_rate_limits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `identifier` varchar(64) NOT NULL,
  `attempts` int(11) NOT NULL DEFAULT 1,
  `expires_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_identifier` (`identifier`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `retards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_eleve` int(11) NOT NULL,
  `date_retard` datetime NOT NULL,
  `duree_minutes` int(11) NOT NULL,
  `motif` varchar(255) DEFAULT NULL,
  `justifie` tinyint(1) DEFAULT 0,
  `commentaire` text DEFAULT NULL,
  `signale_par` varchar(100) NOT NULL,
  `date_signalement` timestamp NOT NULL DEFAULT current_timestamp(),
  `date_modification` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `id_eleve` (`id_eleve`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `user_notification_preferences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `user_type` enum('eleve','parent','professeur','vie_scolaire','administrateur') NOT NULL,
  `email_notifications` tinyint(1) DEFAULT 0,
  `browser_notifications` tinyint(1) DEFAULT 1,
  `notification_sound` tinyint(1) DEFAULT 1,
  `mention_notifications` tinyint(1) DEFAULT 1,
  `reply_notifications` tinyint(1) DEFAULT 1,
  `important_notifications` tinyint(1) DEFAULT 1,
  `digest_frequency` enum('never','daily','weekly') DEFAULT 'never',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user` (`user_id`,`user_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Optional tables seen earlier
CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `model_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_action` (`action`),
  KEY `idx_model` (`model`, `model_id`),
  KEY `idx_user` (`user_id`, `user_type`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `session_security` (
  `id` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `expires_at` timestamp NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`, `user_type`),
  KEY `idx_expires` (`expires_at`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `message_reactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `message_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_type` varchar(50) NOT NULL,
  `reaction` varchar(30) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_reaction` (`message_id`,`user_id`,`user_type`,`reaction`),
  CONSTRAINT `fk_reaction_message` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `devoirs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titre` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `classe` varchar(50) NOT NULL,
  `nom_matiere` varchar(100) NOT NULL,
  `nom_professeur` varchar(100) NOT NULL,
  `date_ajout` date NOT NULL,
  `date_rendu` date NOT NULL,
  `date_creation` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_classe` (`classe`),
  KEY `idx_date_rendu` (`date_rendu`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `evenements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titre` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `date_debut` datetime NOT NULL,
  `date_fin` datetime NOT NULL,
  `type_evenement` varchar(50) NOT NULL,
  `type_personnalise` varchar(100) DEFAULT NULL,
  `statut` varchar(30) DEFAULT 'actif',
  `createur` varchar(100) NOT NULL,
  `visibilite` varchar(255) NOT NULL,
  `personnes_concernees` text DEFAULT NULL,
  `lieu` varchar(100) DEFAULT NULL,
  `classes` varchar(255) DEFAULT NULL,
  `matieres` varchar(100) DEFAULT NULL,
  `date_creation` timestamp NOT NULL DEFAULT current_timestamp(),
  `date_modification` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_date_debut` (`date_debut`),
  KEY `idx_type` (`type_evenement`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `justificatifs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_eleve` int(11) NOT NULL,
  `date_soumission` date NOT NULL DEFAULT (current_date),
  `date_debut_absence` date NOT NULL,
  `date_fin_absence` date NOT NULL,
  `type` varchar(50) NOT NULL,
  `fichier` varchar(255) DEFAULT NULL,
  `motif` text DEFAULT NULL,
  `commentaire` text DEFAULT NULL,
  `traite` tinyint(1) NOT NULL DEFAULT 0,
  `approuve` tinyint(1) NOT NULL DEFAULT 0,
  `commentaire_admin` text DEFAULT NULL,
  `date_traitement` datetime DEFAULT NULL,
  `traite_par` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_eleve` (`id_eleve`),
  KEY `idx_traite` (`traite`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `demandes_reinitialisation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `user_type` varchar(30) NOT NULL,
  `date_demande` datetime NOT NULL DEFAULT current_timestamp(),
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `date_traitement` datetime DEFAULT NULL,
  `admin_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_user` (`user_id`,`user_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Foreign keys (add after all tables exist)
-- --------------------------------------------------------

ALTER TABLE `message_attachments`
  ADD CONSTRAINT `message_attachments_ibfk_1`
    FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE;

ALTER TABLE `message_notifications`
  ADD CONSTRAINT `message_notifications_ibfk_1`
    FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE;

SET SESSION FOREIGN_KEY_CHECKS = 1;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
