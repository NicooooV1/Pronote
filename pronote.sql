-- ============================================================
-- Pronote — Schéma complet de la base de données
-- Version définitive — Aucune migration requise
-- Intègre les modules : base, EDT, appel, discipline, annonces,
-- bulletins, devoirs rendus, compétences, documents, paramètres,
-- notifications, réunions, support, archivage, inscriptions,
-- orientation, signalements, bibliothèque, clubs, santé,
-- RGPD, examens, besoins, personnel, salles, périscolaire,
-- stages, transports, facturation, ressources, diplômes
-- ============================================================
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
SET SESSION FOREIGN_KEY_CHECKS = 0;

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- NOTE : La création de la base et USE sont gérés par install.php.
-- Ne PAS ajouter CREATE DATABASE / USE ici.

-- ============================================================
-- Drop (ordre inverse des FK)
-- ============================================================
DROP VIEW  IF EXISTS `v_users`;

-- Système : modules, SMTP, PDF templates
DROP TABLE IF EXISTS `pdf_templates`;
DROP TABLE IF EXISTS `modules_config`;
DROP TABLE IF EXISTS `smtp_config`;

-- M44 Diplômes
DROP TABLE IF EXISTS `diplomes`;
-- M36 Ressources pédagogiques
DROP TABLE IF EXISTS `ressources_pedagogiques`;
-- M33 Facturation
DROP TABLE IF EXISTS `paiements`;
DROP TABLE IF EXISTS `facture_lignes`;
DROP TABLE IF EXISTS `factures`;
-- M32 Transports / Internat
DROP TABLE IF EXISTS `internat_affectations`;
DROP TABLE IF EXISTS `internat_chambres`;
DROP TABLE IF EXISTS `inscriptions_transport`;
DROP TABLE IF EXISTS `lignes_transport`;
-- M17 Stages
DROP TABLE IF EXISTS `stages`;
-- M16 Périscolaire
DROP TABLE IF EXISTS `menus_cantine`;
DROP TABLE IF EXISTS `presences_periscolaire`;
DROP TABLE IF EXISTS `inscriptions_periscolaire`;
DROP TABLE IF EXISTS `services_periscolaires`;
-- M40 Salles & Matériels
DROP TABLE IF EXISTS `prets_materiels`;
DROP TABLE IF EXISTS `materiels`;
DROP TABLE IF EXISTS `reservations_salles`;
-- M39 Personnel
DROP TABLE IF EXISTS `remplacements`;
DROP TABLE IF EXISTS `personnel_absences`;
-- M37 Besoins particuliers
DROP TABLE IF EXISTS `plan_suivis`;
DROP TABLE IF EXISTS `plans_accompagnement`;
-- M27 Examens
DROP TABLE IF EXISTS `epreuve_convocations`;
DROP TABLE IF EXISTS `epreuve_surveillants`;
DROP TABLE IF EXISTS `epreuves`;
DROP TABLE IF EXISTS `examens`;
-- M23 RGPD
DROP TABLE IF EXISTS `rgpd_demandes`;
DROP TABLE IF EXISTS `rgpd_consentements`;
-- M31 Santé / Infirmerie
DROP TABLE IF EXISTS `passages_infirmerie`;
DROP TABLE IF EXISTS `fiches_sante`;
-- M30 Clubs
DROP TABLE IF EXISTS `club_inscriptions`;
DROP TABLE IF EXISTS `clubs`;
-- M29 Bibliothèque
DROP TABLE IF EXISTS `emprunts`;
DROP TABLE IF EXISTS `livres`;
-- M45 Signalements
DROP TABLE IF EXISTS `signalements`;
-- M28 Orientation
DROP TABLE IF EXISTS `orientation_voeux`;
DROP TABLE IF EXISTS `orientation_fiches`;
-- M26 Inscriptions
DROP TABLE IF EXISTS `inscription_documents`;
DROP TABLE IF EXISTS `inscriptions`;
-- M35 Archivage
DROP TABLE IF EXISTS `archives_annuelles`;
-- M34 Support
DROP TABLE IF EXISTS `faq_articles`;
DROP TABLE IF EXISTS `tickets_support`;
-- M14 Réunions
DROP TABLE IF EXISTS `convocations`;
DROP TABLE IF EXISTS `reunion_reservations`;
DROP TABLE IF EXISTS `reunion_creneaux`;
DROP TABLE IF EXISTS `reunions`;
-- M12 Notifications
DROP TABLE IF EXISTS `notification_preferences`;
DROP TABLE IF EXISTS `notifications_globales`;
-- Paramètres utilisateur
DROP TABLE IF EXISTS `user_settings`;
-- Documents administratifs
DROP TABLE IF EXISTS `documents`;
-- M38 Compétences
DROP TABLE IF EXISTS `competence_evaluations`;
DROP TABLE IF EXISTS `competences`;
-- M08 Devoirs rendus
DROP TABLE IF EXISTS `devoirs_rendus`;
-- M07 Bulletins
DROP TABLE IF EXISTS `bulletin_matieres`;
DROP TABLE IF EXISTS `bulletins`;
-- M11 Annonces / Sondages
DROP TABLE IF EXISTS `sondage_votes`;
DROP TABLE IF EXISTS `sondage_options`;
DROP TABLE IF EXISTS `sondages`;
DROP TABLE IF EXISTS `annonces_lues`;
DROP TABLE IF EXISTS `annonces`;
-- M06 Discipline
DROP TABLE IF EXISTS `retenue_eleves`;
DROP TABLE IF EXISTS `retenues`;
DROP TABLE IF EXISTS `sanctions`;
DROP TABLE IF EXISTS `incidents`;
-- M04 Appel
DROP TABLE IF EXISTS `appel_eleves`;
DROP TABLE IF EXISTS `appels`;
-- M03 Emploi du temps
DROP TABLE IF EXISTS `edt_modifications`;
DROP TABLE IF EXISTS `emploi_du_temps`;
DROP TABLE IF EXISTS `creneaux_horaires`;
DROP TABLE IF EXISTS `salles`;
-- Base (messagerie, cahier de textes, notes, absences, etc.)
DROP TABLE IF EXISTS `message_reports`;
DROP TABLE IF EXISTS `message_reactions`;
DROP TABLE IF EXISTS `message_notifications`;
DROP TABLE IF EXISTS `message_attachments`;
DROP TABLE IF EXISTS `messages`;
DROP TABLE IF EXISTS `conversation_participants`;
DROP TABLE IF EXISTS `conversations`;
DROP TABLE IF EXISTS `devoirs_statuts_eleve`;
DROP TABLE IF EXISTS `devoirs_fichiers`;
DROP TABLE IF EXISTS `devoirs`;
DROP TABLE IF EXISTS `justificatif_fichiers`;
DROP TABLE IF EXISTS `justificatifs`;
DROP TABLE IF EXISTS `notes`;
DROP TABLE IF EXISTS `absences`;
DROP TABLE IF EXISTS `retards`;
DROP TABLE IF EXISTS `evenements`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `professeur_classes`;
DROP TABLE IF EXISTS `parent_eleve`;
DROP TABLE IF EXISTS `rate_limits`;
DROP TABLE IF EXISTS `api_rate_limits`;
DROP TABLE IF EXISTS `login_attempts`;
DROP TABLE IF EXISTS `remember_tokens`;
DROP TABLE IF EXISTS `user_notification_preferences`;
DROP TABLE IF EXISTS `demandes_reinitialisation`;
DROP TABLE IF EXISTS `session_security`;
DROP TABLE IF EXISTS `audit_log`;
DROP TABLE IF EXISTS `matieres`;
DROP TABLE IF EXISTS `classes`;
DROP TABLE IF EXISTS `vie_scolaire`;
DROP TABLE IF EXISTS `parents`;
DROP TABLE IF EXISTS `professeurs`;
DROP TABLE IF EXISTS `eleves`;
DROP TABLE IF EXISTS `administrateurs`;
DROP TABLE IF EXISTS `etablissement_info`;
DROP TABLE IF EXISTS `periodes`;

-- ============================================================
-- 1. TABLES RÉFÉRENTIELLES
-- ============================================================

CREATE TABLE `etablissement_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) NOT NULL DEFAULT 'Établissement Scolaire',
  `adresse` varchar(255) DEFAULT NULL,
  `code_postal` varchar(10) DEFAULT NULL,
  `ville` varchar(100) DEFAULT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `fax` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `chef_etablissement` varchar(150) DEFAULT NULL,
  `academie` varchar(100) DEFAULT NULL,
  `code_uai` varchar(20) DEFAULT NULL,
  `type` varchar(30) DEFAULT 'college',
  `annee_scolaire` varchar(10) DEFAULT '2025-2026',
  `logo` varchar(255) DEFAULT NULL,
  `couleur_primaire` varchar(7) DEFAULT '#003366' COMMENT 'Couleur principale de l''établissement (hex)',
  `couleur_secondaire` varchar(7) DEFAULT '#0066cc' COMMENT 'Couleur d''accent (hex)',
  `css_personnalise` text DEFAULT NULL COMMENT 'CSS custom injecté en fin de <head>',
  `favicon` varchar(255) DEFAULT NULL COMMENT 'Chemin vers le favicon personnalisé',
  `pied_de_page` text DEFAULT NULL COMMENT 'Texte du footer (mentions légales)',
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `etablissement_info` (`id`, `nom`) VALUES (1, 'Établissement Scolaire');

CREATE TABLE `periodes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `numero` int(11) NOT NULL DEFAULT 1,
  `nom` varchar(100) NOT NULL,
  `type` varchar(20) NOT NULL DEFAULT 'trimestre',
  `date_debut` date NOT NULL,
  `date_fin` date NOT NULL,
  `annee_scolaire` varchar(10) DEFAULT '2025-2026',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `matieres` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `code` varchar(10) NOT NULL,
  `coefficient` decimal(3,2) DEFAULT 1.00,
  `couleur` varchar(7) DEFAULT '#3498db',
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `classes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(50) NOT NULL,
  `niveau` varchar(20) NOT NULL,
  `annee_scolaire` varchar(10) NOT NULL,
  `professeur_principal_id` int(11) DEFAULT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nom_annee` (`nom`, `annee_scolaire`),
  KEY `idx_professeur_principal` (`professeur_principal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. TABLES UTILISATEURS
-- ============================================================

CREATE TABLE `administrateurs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `mail` varchar(150) NOT NULL,
  `identifiant` varchar(50) NOT NULL,
  `mot_de_passe` varchar(255) NOT NULL,
  `adresse` varchar(255) DEFAULT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'administrateur',
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login` datetime NULL DEFAULT NULL,
  `failed_login_attempts` int(3) DEFAULT 0,
  `locked_until` datetime NULL DEFAULT NULL,
  `password_changed_at` datetime NULL DEFAULT NULL,
  `two_factor_enabled` tinyint(1) DEFAULT 0,
  `two_factor_secret` varchar(32) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `identifiant` (`identifiant`),
  UNIQUE KEY `mail` (`mail`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `eleves` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `date_naissance` date NOT NULL,
  `classe` varchar(50) NOT NULL,
  `lieu_naissance` varchar(100) NOT NULL,
  `adresse` varchar(255) NOT NULL,
  `mail` varchar(150) NOT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `identifiant` varchar(50) NOT NULL,
  `mot_de_passe` varchar(255) NOT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `two_factor_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `two_factor_secret` varchar(64) DEFAULT NULL,
  `date_creation` datetime DEFAULT CURRENT_TIMESTAMP,
  `last_login` datetime NULL DEFAULT NULL,
  `failed_login_attempts` int(3) DEFAULT 0,
  `locked_until` datetime NULL DEFAULT NULL,
  `password_changed_at` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mail` (`mail`),
  UNIQUE KEY `identifiant` (`identifiant`),
  KEY `idx_classe` (`classe`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `professeurs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `mail` varchar(150) NOT NULL,
  `adresse` varchar(255) NOT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `identifiant` varchar(50) NOT NULL,
  `mot_de_passe` varchar(255) NOT NULL,
  `professeur_principal` varchar(50) NOT NULL DEFAULT 'non',
  `matiere` varchar(100) NOT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `two_factor_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `two_factor_secret` varchar(64) DEFAULT NULL,
  `date_creation` datetime DEFAULT CURRENT_TIMESTAMP,
  `last_login` datetime NULL DEFAULT NULL,
  `failed_login_attempts` int(3) DEFAULT 0,
  `locked_until` datetime NULL DEFAULT NULL,
  `password_changed_at` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mail` (`mail`),
  UNIQUE KEY `identifiant` (`identifiant`),
  KEY `idx_matiere` (`matiere`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `parents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `mail` varchar(150) NOT NULL,
  `adresse` varchar(255) NOT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `metier` varchar(100) DEFAULT NULL,
  `identifiant` varchar(50) NOT NULL,
  `mot_de_passe` varchar(255) NOT NULL,
  `est_parent_eleve` enum('oui','non') NOT NULL DEFAULT 'non',
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `two_factor_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `two_factor_secret` varchar(64) DEFAULT NULL,
  `date_creation` datetime DEFAULT CURRENT_TIMESTAMP,
  `last_login` datetime NULL DEFAULT NULL,
  `failed_login_attempts` int(3) DEFAULT 0,
  `locked_until` datetime NULL DEFAULT NULL,
  `password_changed_at` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mail` (`mail`),
  UNIQUE KEY `identifiant` (`identifiant`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `vie_scolaire` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `mail` varchar(150) NOT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `identifiant` varchar(50) NOT NULL,
  `mot_de_passe` varchar(255) NOT NULL,
  `est_CPE` enum('oui','non') NOT NULL DEFAULT 'non',
  `est_infirmerie` enum('oui','non') NOT NULL DEFAULT 'non',
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `two_factor_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `two_factor_secret` varchar(64) DEFAULT NULL,
  `date_creation` datetime DEFAULT CURRENT_TIMESTAMP,
  `last_login` datetime NULL DEFAULT NULL,
  `failed_login_attempts` int(3) DEFAULT 0,
  `locked_until` datetime NULL DEFAULT NULL,
  `password_changed_at` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mail` (`mail`),
  UNIQUE KEY `identifiant` (`identifiant`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `parent_eleve` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_parent` int(11) NOT NULL,
  `id_eleve` int(11) NOT NULL,
  `lien` varchar(50) DEFAULT 'parent',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_lien` (`id_parent`, `id_eleve`),
  KEY `idx_parent` (`id_parent`),
  KEY `idx_eleve` (`id_eleve`),
  CONSTRAINT `fk_pe_parent` FOREIGN KEY (`id_parent`) REFERENCES `parents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pe_eleve` FOREIGN KEY (`id_eleve`) REFERENCES `eleves` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `professeur_classes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_professeur` int(11) NOT NULL,
  `nom_classe` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_prof_class` (`id_professeur`, `nom_classe`),
  CONSTRAINT `fk_pc_prof` FOREIGN KEY (`id_professeur`) REFERENCES `professeurs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. MODULE NOTES
-- ============================================================

CREATE TABLE `notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_eleve` int(11) NOT NULL,
  `id_matiere` int(11) NOT NULL,
  `id_professeur` int(11) NOT NULL,
  `note` decimal(4,2) NOT NULL,
  `note_sur` decimal(4,2) NOT NULL DEFAULT 20.00,
  `coefficient` decimal(3,2) NOT NULL DEFAULT 1.00,
  `type_evaluation` varchar(50) DEFAULT 'Contrôle',
  `date_note` date NOT NULL,
  `commentaire` text DEFAULT NULL,
  `trimestre` int(1) NOT NULL DEFAULT 1,
  `date_creation` datetime DEFAULT CURRENT_TIMESTAMP,
  `date_modification` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_eleve_matiere` (`id_eleve`, `id_matiere`),
  KEY `idx_matiere` (`id_matiere`),
  KEY `idx_professeur` (`id_professeur`),
  KEY `idx_date` (`date_note`),
  KEY `idx_trimestre` (`trimestre`),
  KEY `idx_eleve_matiere_trimestre` (`id_eleve`, `id_matiere`, `trimestre`),
  KEY `idx_prof_trimestre` (`id_professeur`, `trimestre`),
  CONSTRAINT `fk_notes_eleve` FOREIGN KEY (`id_eleve`) REFERENCES `eleves` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notes_matiere` FOREIGN KEY (`id_matiere`) REFERENCES `matieres` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notes_prof` FOREIGN KEY (`id_professeur`) REFERENCES `professeurs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. MODULE ABSENCES / RETARDS / JUSTIFICATIFS
-- ============================================================

CREATE TABLE `absences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_eleve` int(11) NOT NULL,
  `date_debut` datetime NOT NULL,
  `date_fin` datetime NOT NULL,
  `type_absence` varchar(50) NOT NULL,
  `motif` varchar(255) DEFAULT NULL,
  `justifie` tinyint(1) DEFAULT 0,
  `commentaire` text DEFAULT NULL,
  `signale_par` varchar(100) NOT NULL,
  `date_signalement` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_eleve` (`id_eleve`),
  KEY `idx_date` (`date_debut`),
  KEY `idx_eleve_date` (`id_eleve`, `date_debut`),
  CONSTRAINT `fk_absence_eleve` FOREIGN KEY (`id_eleve`) REFERENCES `eleves` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `retards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_eleve` int(11) NOT NULL,
  `date_retard` datetime NOT NULL,
  `duree_minutes` int(11) NOT NULL,
  `motif` varchar(255) DEFAULT NULL,
  `justifie` tinyint(1) DEFAULT 0,
  `commentaire` text DEFAULT NULL,
  `signale_par` varchar(100) NOT NULL,
  `date_signalement` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_eleve` (`id_eleve`),
  CONSTRAINT `fk_retard_eleve` FOREIGN KEY (`id_eleve`) REFERENCES `eleves` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `justificatifs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_eleve` int(11) NOT NULL,
  `date_soumission` date NOT NULL DEFAULT (CURRENT_DATE),
  `date_debut_absence` date NOT NULL,
  `date_fin_absence` date NOT NULL,
  `type` varchar(50) NOT NULL,
  `motif` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `soumis_par` varchar(100) DEFAULT NULL,
  `id_absence` int(11) DEFAULT NULL,
  `commentaire` text DEFAULT NULL,
  `traite` tinyint(1) NOT NULL DEFAULT 0,
  `approuve` tinyint(1) NOT NULL DEFAULT 0,
  `commentaire_admin` text DEFAULT NULL,
  `date_traitement` datetime DEFAULT NULL,
  `traite_par` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_eleve` (`id_eleve`),
  KEY `idx_traite` (`traite`),
  KEY `idx_absence` (`id_absence`),
  CONSTRAINT `fk_justif_eleve` FOREIGN KEY (`id_eleve`) REFERENCES `eleves` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `justificatif_fichiers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_justificatif` int(11) NOT NULL,
  `nom_original` varchar(255) NOT NULL,
  `nom_serveur` varchar(255) NOT NULL COMMENT 'Chemin relatif dans uploads/',
  `type_mime` varchar(100) NOT NULL,
  `taille` int(11) NOT NULL DEFAULT 0,
  `date_upload` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_justificatif` (`id_justificatif`),
  CONSTRAINT `fk_jf_justificatif` FOREIGN KEY (`id_justificatif`) REFERENCES `justificatifs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5. MODULE CAHIER DE TEXTES
-- ============================================================

CREATE TABLE `devoirs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titre` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `classe` varchar(50) NOT NULL,
  `nom_matiere` varchar(100) NOT NULL,
  `nom_professeur` varchar(100) NOT NULL,
  `date_ajout` date NOT NULL,
  `date_rendu` date NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_classe` (`classe`),
  KEY `idx_date_rendu` (`date_rendu`),
  KEY `idx_nom_professeur` (`nom_professeur`),
  KEY `idx_nom_matiere` (`nom_matiere`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `devoirs_fichiers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `devoir_id` int(11) NOT NULL,
  `nom_original` varchar(255) NOT NULL,
  `nom_stockage` varchar(255) NOT NULL COMMENT 'Chemin relatif dans uploads/',
  `type_mime` varchar(100) NOT NULL,
  `taille` int(11) NOT NULL DEFAULT 0,
  `date_upload` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_devoir_id` (`devoir_id`),
  CONSTRAINT `fk_fichiers_devoir` FOREIGN KEY (`devoir_id`) REFERENCES `devoirs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `devoirs_statuts_eleve` (
  `eleve_id` int(11) NOT NULL,
  `devoir_id` int(11) NOT NULL,
  `fait` tinyint(1) NOT NULL DEFAULT 0,
  `date_marque` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`eleve_id`, `devoir_id`),
  KEY `idx_devoir_statut` (`devoir_id`),
  CONSTRAINT `fk_statut_devoir` FOREIGN KEY (`devoir_id`) REFERENCES `devoirs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_statut_eleve` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` enum('creation','rappel','correction') NOT NULL,
  `id_devoir` int(11) NOT NULL,
  `statut` enum('en_attente','envoye','erreur') NOT NULL DEFAULT 'en_attente',
  `date_creation` datetime DEFAULT CURRENT_TIMESTAMP,
  `date_envoi` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_devoir` (`id_devoir`),
  CONSTRAINT `fk_notif_devoir` FOREIGN KEY (`id_devoir`) REFERENCES `devoirs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 6. MODULE AGENDA
-- ============================================================

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
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_date_debut` (`date_debut`),
  KEY `idx_type` (`type_evenement`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 7. MODULE MESSAGERIE
-- ============================================================

CREATE TABLE `conversations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subject` varchar(255) NOT NULL,
  `type` varchar(50) DEFAULT 'standard',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_message_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_conv_updated` (`updated_at`),
  KEY `idx_conv_last_msg` (`last_message_id`),
  FULLTEXT KEY `ft_conversations_subject` (`subject`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `conversation_participants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `conversation_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_type` enum('eleve','parent','professeur','vie_scolaire','administrateur') NOT NULL,
  `joined_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_read_at` datetime DEFAULT NULL,
  `last_read_message_id` int(11) DEFAULT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `conversation_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `sender_type` enum('eleve','parent','professeur','vie_scolaire','administrateur') NOT NULL,
  `body` text NOT NULL,
  `original_body` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `edited_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by_id` int(11) DEFAULT NULL,
  `deleted_by_type` varchar(50) DEFAULT NULL,
  `status` enum('normal','important','urgent','annonce') NOT NULL DEFAULT 'normal',
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `parent_message_id` int(11) DEFAULT NULL,
  `is_pinned` tinyint(1) NOT NULL DEFAULT 0,
  `pinned_at` datetime DEFAULT NULL,
  `pinned_by_id` int(11) DEFAULT NULL,
  `pinned_by_type` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_messages_conv_created` (`conversation_id`, `created_at`),
  KEY `idx_messages_sender` (`sender_id`, `sender_type`),
  KEY `idx_messages_parent` (`parent_message_id`),
  FULLTEXT KEY `ft_messages_body` (`body`),
  CONSTRAINT `fk_msg_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `message_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `message_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL COMMENT 'Chemin relatif dans uploads/',
  `type_mime` varchar(100) DEFAULT NULL,
  `taille` int(11) DEFAULT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ma_message` (`message_id`),
  CONSTRAINT `fk_ma_message` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `message_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `user_type` enum('eleve','parent','professeur','vie_scolaire','administrateur') NOT NULL,
  `message_id` int(11) NOT NULL,
  `notification_type` enum('unread','broadcast','mention','reply','important') NOT NULL DEFAULT 'unread',
  `notified_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mn_user_read` (`user_id`, `user_type`, `is_read`),
  KEY `idx_mn_message` (`message_id`),
  CONSTRAINT `fk_mn_message` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `message_reactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `message_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_type` varchar(50) NOT NULL,
  `reaction` varchar(30) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_reaction` (`message_id`, `user_id`, `user_type`, `reaction`),
  KEY `idx_reaction_message` (`message_id`),
  CONSTRAINT `fk_reaction_message` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `message_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `message_id` int(11) NOT NULL,
  `reporter_id` int(11) NOT NULL,
  `reporter_type` enum('eleve','parent','professeur','vie_scolaire','administrateur') NOT NULL,
  `reason` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `status` enum('pending','reviewed','dismissed') NOT NULL DEFAULT 'pending',
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_report_message` (`message_id`),
  KEY `idx_report_status` (`status`),
  CONSTRAINT `fk_report_message` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user` (`user_id`, `user_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 8. SÉCURITÉ & ADMINISTRATION
-- ============================================================

CREATE TABLE `rate_limits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `user_type` varchar(50) NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `attempted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rate_user_action` (`user_id`, `user_type`, `action_type`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `api_rate_limits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `identifier` varchar(64) NOT NULL,
  `attempts` int(11) NOT NULL DEFAULT 1,
  `expires_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_identifier` (`identifier`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `remember_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `user_type` enum('eleve','parent','professeur','vie_scolaire','administrateur') NOT NULL,
  `token_hash` varchar(64) NOT NULL COMMENT 'SHA-256 du token',
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_token` (`token_hash`),
  KEY `idx_user` (`user_id`, `user_type`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip` varchar(45) NOT NULL,
  `attempted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ip_time` (`ip`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `action` varchar(100) NOT NULL,
  `model` varchar(100) DEFAULT NULL,
  `model_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_type` varchar(20) DEFAULT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_action` (`action`),
  KEY `idx_model` (`model`, `model_id`),
  KEY `idx_user` (`user_id`, `user_type`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `session_security` (
  `id` varchar(128) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_type` varchar(20) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_activity` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `expires_at` datetime NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`, `user_type`),
  KEY `idx_expires` (`expires_at`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `demandes_reinitialisation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `user_type` varchar(30) NOT NULL,
  `date_demande` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `date_traitement` datetime DEFAULT NULL,
  `admin_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_user` (`user_id`, `user_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 9. MODULE EMPLOI DU TEMPS (M03)
-- ============================================================

CREATE TABLE `salles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(50) NOT NULL,
  `batiment` varchar(100) DEFAULT NULL,
  `capacite` int(11) DEFAULT NULL,
  `type` varchar(50) DEFAULT 'standard' COMMENT 'standard, labo, gymnase, info, amphi',
  `equipements` text DEFAULT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nom` (`nom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `creneaux_horaires` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `label` varchar(30) NOT NULL COMMENT 'ex: M1, M2, S1, S2',
  `heure_debut` time NOT NULL,
  `heure_fin` time NOT NULL,
  `ordre` int(11) NOT NULL DEFAULT 0,
  `type` enum('cours','pause','repas') NOT NULL DEFAULT 'cours',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `emploi_du_temps` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `classe_id` int(11) NOT NULL,
  `matiere_id` int(11) NOT NULL,
  `professeur_id` int(11) NOT NULL,
  `salle_id` int(11) DEFAULT NULL,
  `jour` enum('lundi','mardi','mercredi','jeudi','vendredi','samedi') NOT NULL,
  `creneau_id` int(11) NOT NULL,
  `heure_debut` time NOT NULL,
  `heure_fin` time NOT NULL,
  `groupe` varchar(50) DEFAULT NULL COMMENT 'null = classe entière',
  `type_cours` enum('cours','td','tp','examen','autre') NOT NULL DEFAULT 'cours',
  `recurrence` enum('hebdomadaire','quinzaine_A','quinzaine_B','ponctuel') NOT NULL DEFAULT 'hebdomadaire',
  `date_debut_validite` date DEFAULT NULL,
  `date_fin_validite` date DEFAULT NULL,
  `couleur` varchar(7) DEFAULT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_edt_classe` (`classe_id`),
  KEY `idx_edt_prof` (`professeur_id`),
  KEY `idx_edt_salle` (`salle_id`),
  KEY `idx_edt_jour_creneau` (`jour`, `creneau_id`),
  CONSTRAINT `fk_edt_classe` FOREIGN KEY (`classe_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_edt_matiere` FOREIGN KEY (`matiere_id`) REFERENCES `matieres` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_edt_prof` FOREIGN KEY (`professeur_id`) REFERENCES `professeurs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_edt_salle` FOREIGN KEY (`salle_id`) REFERENCES `salles` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_edt_creneau` FOREIGN KEY (`creneau_id`) REFERENCES `creneaux_horaires` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `edt_modifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `edt_id` int(11) NOT NULL,
  `date_cours` date NOT NULL,
  `type_modification` enum('annulation','deplacement','remplacement') NOT NULL,
  `nouveau_professeur_id` int(11) DEFAULT NULL,
  `nouvelle_salle_id` int(11) DEFAULT NULL,
  `nouvelle_heure_debut` time DEFAULT NULL,
  `nouvelle_heure_fin` time DEFAULT NULL,
  `motif` varchar(255) DEFAULT NULL,
  `createur_id` int(11) DEFAULT NULL,
  `createur_type` varchar(20) DEFAULT NULL,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_edtmod_edt` (`edt_id`),
  KEY `idx_edtmod_date` (`date_cours`),
  CONSTRAINT `fk_edtmod_edt` FOREIGN KEY (`edt_id`) REFERENCES `emploi_du_temps` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 10. MODULE APPEL / PRÉSENCE (M04)
-- ============================================================

CREATE TABLE `appels` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `edt_id` int(11) DEFAULT NULL COMMENT 'Lien optionnel avec un cours EDT',
  `classe_id` int(11) NOT NULL,
  `professeur_id` int(11) NOT NULL,
  `matiere_id` int(11) DEFAULT NULL,
  `date_appel` date NOT NULL,
  `heure_debut` time NOT NULL,
  `heure_fin` time NOT NULL,
  `type_appel` enum('cours','demi_journee','journee') NOT NULL DEFAULT 'cours',
  `statut` enum('en_cours','valide','cloture') NOT NULL DEFAULT 'en_cours',
  `commentaire` text DEFAULT NULL,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_validation` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_appel_classe` (`classe_id`),
  KEY `idx_appel_prof` (`professeur_id`),
  KEY `idx_appel_date` (`date_appel`),
  KEY `idx_appel_edt` (`edt_id`),
  CONSTRAINT `fk_appel_classe` FOREIGN KEY (`classe_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_appel_prof` FOREIGN KEY (`professeur_id`) REFERENCES `professeurs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `appel_eleves` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `appel_id` int(11) NOT NULL,
  `eleve_id` int(11) NOT NULL,
  `statut` enum('present','absent','retard','dispense','exclu') NOT NULL DEFAULT 'present',
  `heure_arrivee` time DEFAULT NULL COMMENT 'si retard',
  `duree_retard` int(11) DEFAULT NULL COMMENT 'minutes',
  `motif` varchar(255) DEFAULT NULL,
  `commentaire` text DEFAULT NULL,
  `notifie` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'parent notifié ?',
  `date_modification` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_appel_eleve` (`appel_id`, `eleve_id`),
  KEY `idx_ae_eleve` (`eleve_id`),
  KEY `idx_ae_statut` (`statut`),
  CONSTRAINT `fk_ae_appel` FOREIGN KEY (`appel_id`) REFERENCES `appels` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ae_eleve` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 11. MODULE DISCIPLINE / SANCTIONS (M06)
-- ============================================================

CREATE TABLE `incidents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `eleve_id` int(11) NOT NULL,
  `date_incident` datetime NOT NULL,
  `lieu` varchar(100) DEFAULT NULL,
  `type_incident` varchar(50) NOT NULL COMMENT 'violence, insolence, fraude, retard_repete, autre',
  `gravite` enum('mineur','moyen','grave','tres_grave') NOT NULL DEFAULT 'moyen',
  `description` text NOT NULL,
  `temoins` text DEFAULT NULL,
  `signale_par_id` int(11) NOT NULL,
  `signale_par_type` varchar(20) NOT NULL,
  `classe_id` int(11) DEFAULT NULL,
  `statut` enum('signale','en_traitement','traite','classe') NOT NULL DEFAULT 'signale',
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_incident_eleve` (`eleve_id`),
  KEY `idx_incident_date` (`date_incident`),
  KEY `idx_incident_statut` (`statut`),
  CONSTRAINT `fk_incident_eleve` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sanctions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `incident_id` int(11) DEFAULT NULL,
  `eleve_id` int(11) NOT NULL,
  `type_sanction` varchar(50) NOT NULL COMMENT 'avertissement, blame, exclusion_cours, exclusion_temporaire, retenue, autre',
  `motif` text NOT NULL,
  `date_sanction` date NOT NULL,
  `date_debut` datetime DEFAULT NULL COMMENT 'pour exclusion',
  `date_fin` datetime DEFAULT NULL COMMENT 'pour exclusion',
  `duree` varchar(50) DEFAULT NULL,
  `lieu_retenue` varchar(100) DEFAULT NULL,
  `convocation_parent` tinyint(1) NOT NULL DEFAULT 0,
  `date_convocation` datetime DEFAULT NULL,
  `parent_notifie` tinyint(1) NOT NULL DEFAULT 0,
  `decide_par_id` int(11) NOT NULL,
  `decide_par_type` varchar(20) NOT NULL,
  `commentaire` text DEFAULT NULL,
  `statut` enum('prononcee','executee','annulee') NOT NULL DEFAULT 'prononcee',
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sanction_eleve` (`eleve_id`),
  KEY `idx_sanction_incident` (`incident_id`),
  KEY `idx_sanction_date` (`date_sanction`),
  CONSTRAINT `fk_sanction_eleve` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sanction_incident` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `retenues` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `date_retenue` date NOT NULL,
  `heure_debut` time NOT NULL,
  `heure_fin` time NOT NULL,
  `lieu` varchar(100) DEFAULT NULL,
  `surveillant_id` int(11) DEFAULT NULL,
  `surveillant_type` varchar(20) DEFAULT NULL,
  `capacite_max` int(11) DEFAULT 30,
  `commentaire` text DEFAULT NULL,
  `statut` enum('planifiee','en_cours','terminee','annulee') NOT NULL DEFAULT 'planifiee',
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_retenue_date` (`date_retenue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `retenue_eleves` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `retenue_id` int(11) NOT NULL,
  `sanction_id` int(11) DEFAULT NULL,
  `eleve_id` int(11) NOT NULL,
  `present` tinyint(1) DEFAULT NULL COMMENT 'null=non pointé, 0=absent, 1=présent',
  `commentaire` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_retenue_eleve` (`retenue_id`, `eleve_id`),
  CONSTRAINT `fk_re_retenue` FOREIGN KEY (`retenue_id`) REFERENCES `retenues` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_re_eleve` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 12. MODULE ANNONCES / SONDAGES (M11)
-- ============================================================

CREATE TABLE `annonces` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titre` varchar(255) NOT NULL,
  `contenu` text NOT NULL,
  `type` enum('info','urgent','evenement','sondage') NOT NULL DEFAULT 'info',
  `auteur_id` int(11) NOT NULL,
  `auteur_type` varchar(20) NOT NULL,
  `cible_roles` varchar(255) DEFAULT NULL COMMENT 'JSON: ["eleve","parent","professeur"]',
  `cible_classes` varchar(255) DEFAULT NULL COMMENT 'JSON: [1,2,3] (ids classes)',
  `cible_niveaux` varchar(255) DEFAULT NULL COMMENT 'JSON: ["6eme","5eme"]',
  `publie` tinyint(1) NOT NULL DEFAULT 1,
  `epingle` tinyint(1) NOT NULL DEFAULT 0,
  `date_publication` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_expiration` datetime DEFAULT NULL,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_annonce_date` (`date_publication`),
  KEY `idx_annonce_auteur` (`auteur_id`, `auteur_type`),
  KEY `idx_annonce_publie` (`publie`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `annonces_lues` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `annonce_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_type` varchar(20) NOT NULL,
  `date_lecture` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_lecture` (`annonce_id`, `user_id`, `user_type`),
  CONSTRAINT `fk_al_annonce` FOREIGN KEY (`annonce_id`) REFERENCES `annonces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sondages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `annonce_id` int(11) NOT NULL,
  `question` text NOT NULL,
  `type_reponse` enum('choix_unique','choix_multiple','texte_libre') NOT NULL DEFAULT 'choix_unique',
  `anonyme` tinyint(1) NOT NULL DEFAULT 0,
  `date_fin` datetime DEFAULT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_sondage_annonce` (`annonce_id`),
  CONSTRAINT `fk_sondage_annonce` FOREIGN KEY (`annonce_id`) REFERENCES `annonces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sondage_options` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sondage_id` int(11) NOT NULL,
  `label` varchar(255) NOT NULL,
  `ordre` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_so_sondage` (`sondage_id`),
  CONSTRAINT `fk_so_sondage` FOREIGN KEY (`sondage_id`) REFERENCES `sondages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sondage_votes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sondage_id` int(11) NOT NULL,
  `option_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `user_type` varchar(20) NOT NULL,
  `texte_libre` text DEFAULT NULL,
  `date_vote` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sv_sondage` (`sondage_id`),
  KEY `idx_sv_option` (`option_id`),
  CONSTRAINT `fk_sv_sondage` FOREIGN KEY (`sondage_id`) REFERENCES `sondages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sv_option` FOREIGN KEY (`option_id`) REFERENCES `sondage_options` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 13. MODULE BULLETINS / BILANS PÉRIODIQUES (M07)
-- ============================================================

CREATE TABLE `bulletins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `eleve_id` int(11) NOT NULL,
  `classe_id` int(11) NOT NULL,
  `periode_id` int(11) NOT NULL,
  `annee_scolaire` varchar(10) NOT NULL DEFAULT '2025-2026',
  `moyenne_generale` decimal(4,2) DEFAULT NULL,
  `rang` int(11) DEFAULT NULL,
  `appreciation_generale` text DEFAULT NULL,
  `appreciation_vie_scolaire` text DEFAULT NULL,
  `avis_conseil` enum('felicitations','compliments','encouragements','avertissement_travail','avertissement_conduite','aucun') DEFAULT 'aucun',
  `nb_absences` int(11) DEFAULT 0,
  `nb_retards` int(11) DEFAULT 0,
  `statut` enum('brouillon','valide','publie','archive') NOT NULL DEFAULT 'brouillon',
  `competences_bilan` json DEFAULT NULL,
  `valide_par` int(11) DEFAULT NULL,
  `date_validation` datetime DEFAULT NULL,
  `date_publication` datetime DEFAULT NULL,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_bulletin` (`eleve_id`, `periode_id`, `annee_scolaire`),
  KEY `idx_bulletin_classe` (`classe_id`),
  KEY `idx_bulletin_periode` (`periode_id`),
  CONSTRAINT `fk_bulletin_eleve` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bulletin_classe` FOREIGN KEY (`classe_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bulletin_periode` FOREIGN KEY (`periode_id`) REFERENCES `periodes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `bulletin_matieres` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bulletin_id` int(11) NOT NULL,
  `matiere_id` int(11) NOT NULL,
  `professeur_id` int(11) NOT NULL,
  `moyenne_eleve` decimal(4,2) DEFAULT NULL,
  `moyenne_classe` decimal(4,2) DEFAULT NULL,
  `moyenne_min` decimal(4,2) DEFAULT NULL,
  `moyenne_max` decimal(4,2) DEFAULT NULL,
  `appreciation` text DEFAULT NULL,
  `coefficient` decimal(3,2) DEFAULT 1.00,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_bm` (`bulletin_id`, `matiere_id`),
  KEY `idx_bm_matiere` (`matiere_id`),
  CONSTRAINT `fk_bm_bulletin` FOREIGN KEY (`bulletin_id`) REFERENCES `bulletins` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bm_matiere` FOREIGN KEY (`matiere_id`) REFERENCES `matieres` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bm_prof` FOREIGN KEY (`professeur_id`) REFERENCES `professeurs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 14. MODULE DEVOIRS EN LIGNE — rendus élèves (M08)
-- ============================================================

CREATE TABLE `devoirs_rendus` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `devoir_id` int(11) NOT NULL,
  `eleve_id` int(11) NOT NULL,
  `contenu` text DEFAULT NULL,
  `fichier_nom` varchar(255) DEFAULT NULL,
  `fichier_chemin` varchar(255) DEFAULT NULL,
  `fichier_type` varchar(100) DEFAULT NULL,
  `fichier_taille` int(11) DEFAULT 0,
  `date_rendu` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `en_retard` tinyint(1) NOT NULL DEFAULT 0,
  `note` decimal(4,2) DEFAULT NULL,
  `note_sur` decimal(4,2) DEFAULT 20.00,
  `commentaire_prof` text DEFAULT NULL,
  `date_correction` datetime DEFAULT NULL,
  `statut` enum('rendu','corrige','a_refaire') NOT NULL DEFAULT 'rendu',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_rendu` (`devoir_id`, `eleve_id`),
  KEY `idx_rendu_eleve` (`eleve_id`),
  CONSTRAINT `fk_rendu_devoir` FOREIGN KEY (`devoir_id`) REFERENCES `devoirs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rendu_eleve` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 15. MODULE COMPÉTENCES / ÉVALUATIONS (M38)
-- ============================================================

CREATE TABLE `competences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `nom` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `domaine` varchar(100) DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `niveau` int(11) NOT NULL DEFAULT 1,
  `ordre` int(11) NOT NULL DEFAULT 0,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_comp_parent` (`parent_id`),
  KEY `idx_comp_domaine` (`domaine`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `competence_evaluations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `eleve_id` int(11) NOT NULL,
  `competence_id` int(11) NOT NULL,
  `professeur_id` int(11) NOT NULL,
  `matiere_id` int(11) DEFAULT NULL,
  `niveau_acquis` enum('non_evalue','non_acquis','en_cours','acquis','depasse') NOT NULL DEFAULT 'non_evalue',
  `commentaire` text DEFAULT NULL,
  `date_evaluation` date NOT NULL,
  `periode_id` int(11) DEFAULT NULL,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ce_eleve` (`eleve_id`),
  KEY `idx_ce_competence` (`competence_id`),
  KEY `idx_ce_prof` (`professeur_id`),
  KEY `idx_ce_date` (`date_evaluation`),
  CONSTRAINT `fk_ce_eleve` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ce_competence` FOREIGN KEY (`competence_id`) REFERENCES `competences` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ce_prof` FOREIGN KEY (`professeur_id`) REFERENCES `professeurs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 16. MODULE DOCUMENTS ADMINISTRATIFS
-- ============================================================

CREATE TABLE `documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titre` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `categorie` varchar(100) NOT NULL DEFAULT 'general' COMMENT 'general, administratif, pedagogique, reglementaire',
  `fichier_nom` varchar(255) NOT NULL,
  `fichier_chemin` varchar(255) NOT NULL,
  `fichier_type` varchar(100) NOT NULL,
  `fichier_taille` int(11) NOT NULL DEFAULT 0,
  `visibilite` varchar(255) DEFAULT NULL COMMENT 'JSON: ["eleve","parent","professeur"]',
  `auteur_id` int(11) NOT NULL,
  `auteur_type` varchar(20) NOT NULL,
  `telechargements` int(11) NOT NULL DEFAULT 0,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_doc_categorie` (`categorie`),
  KEY `idx_doc_auteur` (`auteur_id`, `auteur_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 17. MODULE PARAMÈTRES UTILISATEUR
-- ============================================================

CREATE TABLE `user_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `user_type` varchar(20) NOT NULL,
  `theme` varchar(20) NOT NULL DEFAULT 'light',
  `langue` varchar(5) NOT NULL DEFAULT 'fr',
  `notifications_email` tinyint(1) NOT NULL DEFAULT 1,
  `notifications_web` tinyint(1) NOT NULL DEFAULT 1,
  `taille_police` varchar(10) NOT NULL DEFAULT 'normal',
  `sidebar_collapsed` tinyint(1) NOT NULL DEFAULT 0,
  `avatar_chemin` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `banner_color` VARCHAR(7) DEFAULT NULL COMMENT 'Couleur de bannière profil (hex)',
  `banner_image` VARCHAR(255) DEFAULT NULL COMMENT 'Image de bannière profil',
  `accueil_config` JSON DEFAULT NULL COMMENT 'Configuration widgets accueil (JSON)',
  `date_modification` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_settings` (`user_id`, `user_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 18. MODULE NOTIFICATIONS GLOBALES (M12)
-- ============================================================

CREATE TABLE `notifications_globales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `user_type` varchar(20) NOT NULL,
  `type` varchar(50) NOT NULL COMMENT 'nouvelle_note, absence, message, devoir, bulletin, incident, annonce, reunion, general',
  `titre` varchar(255) NOT NULL,
  `contenu` text DEFAULT NULL,
  `lien` varchar(500) DEFAULT NULL COMMENT 'URL relative vers la ressource concernée',
  `icone` varchar(50) DEFAULT 'fa-bell',
  `importance` enum('basse','normale','haute','urgente') NOT NULL DEFAULT 'normale',
  `lu` tinyint(1) NOT NULL DEFAULT 0,
  `date_lecture` datetime DEFAULT NULL,
  `source_type` varchar(50) DEFAULT NULL COMMENT 'Module source: notes, absences, messagerie...',
  `source_id` int(11) DEFAULT NULL,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notif_user` (`user_id`, `user_type`),
  KEY `idx_notif_lu` (`lu`),
  KEY `idx_notif_type` (`type`),
  KEY `idx_notif_date` (`date_creation`),
  KEY `idx_notif_importance` (`importance`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notification_preferences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `user_type` varchar(20) NOT NULL,
  `type_notification` varchar(50) NOT NULL COMMENT 'nouvelle_note, absence, message, devoir, bulletin, incident',
  `canal_email` tinyint(1) NOT NULL DEFAULT 1,
  `canal_web` tinyint(1) NOT NULL DEFAULT 1,
  `canal_push` tinyint(1) NOT NULL DEFAULT 0,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_pref` (`user_id`, `user_type`, `type_notification`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 19. MODULE RÉUNIONS / RDV / CONVOCATIONS (M14)
-- ============================================================

CREATE TABLE `reunions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titre` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `type` enum('parents_profs','conseil_classe','reunion_equipe','individuel','autre') NOT NULL DEFAULT 'parents_profs',
  `date_debut` datetime NOT NULL,
  `date_fin` datetime NOT NULL,
  `lieu` varchar(255) DEFAULT NULL,
  `classe_id` int(11) DEFAULT NULL,
  `organisateur_id` int(11) NOT NULL,
  `organisateur_type` varchar(20) NOT NULL,
  `statut` enum('planifiee','en_cours','terminee','annulee') NOT NULL DEFAULT 'planifiee',
  `pv_contenu` text DEFAULT NULL COMMENT 'Procès-verbal de la réunion',
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_reunion_date` (`date_debut`),
  KEY `idx_reunion_type` (`type`),
  KEY `idx_reunion_classe` (`classe_id`),
  KEY `idx_reunion_statut` (`statut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `reunion_creneaux` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reunion_id` int(11) NOT NULL,
  `professeur_id` int(11) NOT NULL,
  `heure_debut` time NOT NULL,
  `heure_fin` time NOT NULL,
  `duree_minutes` int(11) NOT NULL DEFAULT 15,
  `salle` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_creneau_reunion` (`reunion_id`),
  KEY `idx_creneau_prof` (`professeur_id`),
  CONSTRAINT `fk_creneau_reunion` FOREIGN KEY (`reunion_id`) REFERENCES `reunions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_creneau_prof` FOREIGN KEY (`professeur_id`) REFERENCES `professeurs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `reunion_reservations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `creneau_id` int(11) NOT NULL,
  `parent_id` int(11) NOT NULL,
  `eleve_id` int(11) NOT NULL,
  `statut` enum('confirmee','annulee','en_attente') NOT NULL DEFAULT 'confirmee',
  `commentaire` text DEFAULT NULL,
  `date_reservation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_reservation` (`creneau_id`),
  KEY `idx_resa_parent` (`parent_id`),
  KEY `idx_resa_eleve` (`eleve_id`),
  CONSTRAINT `fk_resa_creneau` FOREIGN KEY (`creneau_id`) REFERENCES `reunion_creneaux` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_resa_parent` FOREIGN KEY (`parent_id`) REFERENCES `parents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_resa_eleve` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `convocations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reunion_id` int(11) DEFAULT NULL,
  `destinataire_id` int(11) NOT NULL,
  `destinataire_type` varchar(20) NOT NULL,
  `objet` varchar(255) NOT NULL,
  `contenu` text DEFAULT NULL,
  `date_convocation` date NOT NULL,
  `heure` time DEFAULT NULL,
  `lieu` varchar(255) DEFAULT NULL,
  `type` enum('reunion','conseil','disciplinaire','information','autre') NOT NULL DEFAULT 'reunion',
  `envoyee` tinyint(1) NOT NULL DEFAULT 0,
  `lue` tinyint(1) NOT NULL DEFAULT 0,
  `date_lecture` datetime DEFAULT NULL,
  `emetteur_id` int(11) NOT NULL,
  `emetteur_type` varchar(20) NOT NULL,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_conv_dest` (`destinataire_id`, `destinataire_type`),
  KEY `idx_conv_reunion` (`reunion_id`),
  KEY `idx_conv_date` (`date_convocation`),
  CONSTRAINT `fk_conv_reunion` FOREIGN KEY (`reunion_id`) REFERENCES `reunions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 20. MODULE SUPPORT & AIDE (M34)
-- ============================================================

CREATE TABLE `tickets_support` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `user_type` varchar(20) NOT NULL,
  `sujet` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `categorie` enum('technique','pedagogique','administratif','compte','autre') NOT NULL DEFAULT 'technique',
  `priorite` enum('basse','normale','haute','urgente') NOT NULL DEFAULT 'normale',
  `statut` enum('ouvert','en_cours','resolu','ferme') NOT NULL DEFAULT 'ouvert',
  `reponse` text DEFAULT NULL,
  `traite_par` int(11) DEFAULT NULL,
  `date_reponse` datetime DEFAULT NULL,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ticket_user` (`user_id`, `user_type`),
  KEY `idx_ticket_statut` (`statut`),
  KEY `idx_ticket_priorite` (`priorite`),
  KEY `idx_ticket_date` (`date_creation`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `faq_articles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `question` varchar(500) NOT NULL,
  `reponse` text NOT NULL,
  `categorie` varchar(100) NOT NULL DEFAULT 'general',
  `ordre` int(11) NOT NULL DEFAULT 0,
  `vues` int(11) NOT NULL DEFAULT 0,
  `utile_oui` int(11) NOT NULL DEFAULT 0,
  `utile_non` int(11) NOT NULL DEFAULT 0,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `auteur_id` int(11) DEFAULT NULL,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_faq_categorie` (`categorie`),
  KEY `idx_faq_actif` (`actif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 21. MODULE ARCHIVAGE FIN D'ANNÉE (M35)
-- ============================================================

CREATE TABLE `archives_annuelles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `annee_scolaire` varchar(10) NOT NULL,
  `type` varchar(50) NOT NULL COMMENT 'bulletins, notes, absences, edt, incidents, general',
  `description` text DEFAULT NULL,
  `donnees` longtext DEFAULT NULL COMMENT 'JSON des données archivées',
  `fichier_chemin` varchar(255) DEFAULT NULL,
  `taille` bigint(20) DEFAULT 0,
  `verrouille` tinyint(1) NOT NULL DEFAULT 1,
  `archive_par` int(11) NOT NULL,
  `date_archivage` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_archive_annee` (`annee_scolaire`),
  KEY `idx_archive_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 22. MODULE INSCRIPTIONS / RÉINSCRIPTIONS (M26)
-- ============================================================

CREATE TABLE `inscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `annee_scolaire` varchar(10) NOT NULL,
  `type` enum('inscription','reinscription') NOT NULL DEFAULT 'inscription',
  `nom_eleve` varchar(100) NOT NULL,
  `prenom_eleve` varchar(100) NOT NULL,
  `date_naissance` date NOT NULL,
  `sexe` enum('M','F') NOT NULL,
  `adresse` varchar(255) DEFAULT NULL,
  `classe_demandee` varchar(50) DEFAULT NULL,
  `niveau` varchar(20) DEFAULT NULL,
  `nom_parent1` varchar(200) DEFAULT NULL,
  `telephone_parent1` varchar(20) DEFAULT NULL,
  `email_parent1` varchar(150) DEFAULT NULL,
  `nom_parent2` varchar(200) DEFAULT NULL,
  `telephone_parent2` varchar(20) DEFAULT NULL,
  `email_parent2` varchar(150) DEFAULT NULL,
  `etablissement_precedent` varchar(255) DEFAULT NULL,
  `observations` text DEFAULT NULL,
  `statut` enum('en_cours','complet','valide','refuse','archive') NOT NULL DEFAULT 'en_cours',
  `commentaire_admin` text DEFAULT NULL,
  `traite_par` int(11) DEFAULT NULL,
  `date_traitement` datetime DEFAULT NULL,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_inscription_annee` (`annee_scolaire`),
  KEY `idx_inscription_statut` (`statut`),
  KEY `idx_inscription_nom` (`nom_eleve`, `prenom_eleve`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `inscription_documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `inscription_id` int(11) NOT NULL,
  `type_document` varchar(100) NOT NULL COMMENT 'livret_famille, certificat_medical, photo_identite, justif_domicile, bulletins_precedents',
  `nom_fichier` varchar(255) NOT NULL,
  `chemin_fichier` varchar(255) NOT NULL,
  `type_mime` varchar(100) NOT NULL,
  `taille` int(11) NOT NULL DEFAULT 0,
  `valide` tinyint(1) DEFAULT NULL,
  `commentaire` text DEFAULT NULL,
  `date_upload` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_insdoc_inscription` (`inscription_id`),
  CONSTRAINT `fk_insdoc_inscription` FOREIGN KEY (`inscription_id`) REFERENCES `inscriptions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 23. MODULE ORIENTATION & PARCOURS (M28)
-- ============================================================

CREATE TABLE `orientation_fiches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `eleve_id` int(11) NOT NULL,
  `annee_scolaire` varchar(10) NOT NULL,
  `classe` varchar(50) DEFAULT NULL,
  `projet_professionnel` text DEFAULT NULL,
  `centres_interet` text DEFAULT NULL,
  `points_forts` text DEFAULT NULL,
  `points_amelioration` text DEFAULT NULL,
  `commentaire_pp` text DEFAULT NULL COMMENT 'Commentaire du professeur principal',
  `commentaire_cpe` text DEFAULT NULL,
  `avis_conseil` enum('favorable','reserve','defavorable','en_attente') DEFAULT 'en_attente',
  `statut` enum('brouillon','soumise','validee','archivee') NOT NULL DEFAULT 'brouillon',
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_orient_eleve` (`eleve_id`),
  KEY `idx_orient_annee` (`annee_scolaire`),
  CONSTRAINT `fk_orient_eleve` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `orientation_voeux` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fiche_id` int(11) NOT NULL,
  `rang` int(11) NOT NULL DEFAULT 1,
  `intitule` varchar(255) NOT NULL,
  `etablissement_vise` varchar(255) DEFAULT NULL,
  `filiere` varchar(100) DEFAULT NULL,
  `motivation` text DEFAULT NULL,
  `avis_pp` enum('favorable','reserve','defavorable','en_attente') DEFAULT 'en_attente',
  `avis_conseil` enum('favorable','reserve','defavorable','en_attente') DEFAULT 'en_attente',
  PRIMARY KEY (`id`),
  KEY `idx_voeu_fiche` (`fiche_id`),
  KEY `idx_voeu_rang` (`rang`),
  CONSTRAINT `fk_voeu_fiche` FOREIGN KEY (`fiche_id`) REFERENCES `orientation_fiches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 24. MODULE SIGNALEMENTS — anti-harcèlement (M45)
-- ============================================================

CREATE TABLE `signalements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `auteur_id` int(11) NOT NULL,
  `auteur_type` varchar(20) NOT NULL,
  `type` enum('harcelement','violence','discrimination','danger','autre') NOT NULL DEFAULT 'autre',
  `description` text NOT NULL,
  `personnes_impliquees` text DEFAULT NULL,
  `lieu` varchar(255) DEFAULT NULL,
  `date_faits` date DEFAULT NULL,
  `anonyme` tinyint(1) NOT NULL DEFAULT 0,
  `urgence` enum('basse','moyenne','haute','critique') NOT NULL DEFAULT 'moyenne',
  `statut` enum('nouveau','en_cours','traite','clos','escalade') NOT NULL DEFAULT 'nouveau',
  `traite_par` int(11) DEFAULT NULL,
  `actions_prises` text DEFAULT NULL,
  `suivi` text DEFAULT NULL,
  `confidentiel` tinyint(1) NOT NULL DEFAULT 1,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modification` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_signal_auteur` (`auteur_id`, `auteur_type`),
  KEY `idx_signal_type` (`type`),
  KEY `idx_signal_statut` (`statut`),
  KEY `idx_signal_urgence` (`urgence`),
  KEY `idx_signal_date` (`date_creation`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 25. MODULE BIBLIOTHÈQUE / CDI (M29)
-- ============================================================

CREATE TABLE `livres` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titre` varchar(255) NOT NULL,
  `auteur` varchar(255) DEFAULT NULL,
  `isbn` varchar(20) DEFAULT NULL,
  `editeur` varchar(255) DEFAULT NULL,
  `annee_publication` int(4) DEFAULT NULL,
  `categorie` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `exemplaires_total` int(11) NOT NULL DEFAULT 1,
  `exemplaires_disponibles` int(11) NOT NULL DEFAULT 1,
  `emplacement` varchar(100) DEFAULT NULL COMMENT 'Rayon, étagère, etc.',
  `couverture` varchar(255) DEFAULT NULL COMMENT 'Chemin image couverture',
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_livre_titre` (`titre`),
  KEY `idx_livre_isbn` (`isbn`),
  KEY `idx_livre_categorie` (`categorie`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `emprunts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `livre_id` int(11) NOT NULL,
  `emprunteur_id` int(11) NOT NULL,
  `emprunteur_type` varchar(20) NOT NULL,
  `date_emprunt` date NOT NULL,
  `date_retour_prevue` date NOT NULL,
  `date_retour_effective` date DEFAULT NULL,
  `statut` enum('en_cours','rendu','en_retard','perdu') NOT NULL DEFAULT 'en_cours',
  `remarques` text DEFAULT NULL,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_emprunt_livre` (`livre_id`),
  KEY `idx_emprunt_user` (`emprunteur_id`, `emprunteur_type`),
  KEY `idx_emprunt_statut` (`statut`),
  KEY `idx_emprunt_retour` (`date_retour_prevue`),
  CONSTRAINT `fk_emprunt_livre` FOREIGN KEY (`livre_id`) REFERENCES `livres` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 26. MODULE CLUBS & ASSOCIATIONS (M30)
-- ============================================================

CREATE TABLE `clubs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `categorie` varchar(100) DEFAULT NULL COMMENT 'sport, culture, sciences, art, autre',
  `responsable_id` int(11) DEFAULT NULL,
  `responsable_type` varchar(20) DEFAULT NULL,
  `jour` varchar(20) DEFAULT NULL,
  `horaire_debut` time DEFAULT NULL,
  `horaire_fin` time DEFAULT NULL,
  `lieu` varchar(255) DEFAULT NULL,
  `places_max` int(11) DEFAULT NULL,
  `places_restantes` int(11) DEFAULT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_club_categorie` (`categorie`),
  KEY `idx_club_actif` (`actif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `club_inscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `club_id` int(11) NOT NULL,
  `eleve_id` int(11) NOT NULL,
  `statut` enum('inscrit','en_attente','refuse','desiste') NOT NULL DEFAULT 'inscrit',
  `date_inscription` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_club_eleve` (`club_id`, `eleve_id`),
  KEY `idx_clubinsc_eleve` (`eleve_id`),
  CONSTRAINT `fk_clubinsc_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_clubinsc_eleve` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 27. MODULE SANTÉ / INFIRMERIE (M31)
-- ============================================================

CREATE TABLE `fiches_sante` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `eleve_id` int(11) NOT NULL,
  `allergies` text DEFAULT NULL,
  `traitements` text DEFAULT NULL,
  `antecedents` text DEFAULT NULL,
  `medecin_traitant` varchar(255) DEFAULT NULL,
  `telephone_urgence` varchar(20) DEFAULT NULL,
  `contact_urgence` varchar(255) DEFAULT NULL,
  `groupe_sanguin` varchar(5) DEFAULT NULL,
  `pai` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Projet d''Accueil Individualisé',
  `pai_details` text DEFAULT NULL,
  `observations` text DEFAULT NULL,
  `date_modification` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_fiche_sante` (`eleve_id`),
  CONSTRAINT `fk_fiche_sante_eleve` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `passages_infirmerie` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `eleve_id` int(11) NOT NULL,
  `date_passage` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `motif` varchar(255) NOT NULL,
  `symptomes` text DEFAULT NULL,
  `soins_prodigues` text DEFAULT NULL,
  `medicaments_donnes` text DEFAULT NULL,
  `orientation` enum('retour_classe','repos','domicile','urgences','autre') NOT NULL DEFAULT 'retour_classe',
  `parent_prevenu` tinyint(1) NOT NULL DEFAULT 0,
  `heure_sortie` time DEFAULT NULL,
  `infirmier_id` int(11) DEFAULT NULL,
  `observations` text DEFAULT NULL,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_passage_eleve` (`eleve_id`),
  KEY `idx_passage_date` (`date_passage`),
  CONSTRAINT `fk_passage_eleve` FOREIGN KEY (`eleve_id`) REFERENCES `eleves` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 28. MODULE RGPD & AUDIT (M23)
-- ============================================================

CREATE TABLE `rgpd_consentements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `user_type` enum('administrateur','professeur','eleve','parent','vie_scolaire') NOT NULL,
  `type_consentement` varchar(100) NOT NULL COMMENT 'ex: sms, photo, donnees_medicales',
  `consenti` tinyint(1) NOT NULL DEFAULT 0,
  `date_consentement` datetime DEFAULT NULL,
  `date_retrait` datetime DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`, `user_type`),
  KEY `idx_type` (`type_consentement`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `rgpd_demandes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `user_type` enum('administrateur','professeur','eleve','parent','vie_scolaire') NOT NULL,
  `type_demande` enum('acces','rectification','suppression','portabilite','opposition') NOT NULL,
  `description` text DEFAULT NULL,
  `statut` enum('en_attente','en_cours','traitee','refusee') NOT NULL DEFAULT 'en_attente',
  `reponse` text DEFAULT NULL,
  `traite_par` int(11) DEFAULT NULL,
  `date_demande` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_traitement` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_statut` (`statut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 29. MODULE EXAMENS & ÉPREUVES (M27)
-- ============================================================

CREATE TABLE `examens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) NOT NULL,
  `type` enum('brevet','bac','bts','partiel','controle','autre') NOT NULL DEFAULT 'autre',
  `date_debut` date NOT NULL,
  `date_fin` date DEFAULT NULL,
  `description` text DEFAULT NULL,
  `statut` enum('planifie','en_cours','termine','annule') NOT NULL DEFAULT 'planifie',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_date` (`date_debut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `epreuves` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `examen_id` int(11) NOT NULL,
  `matiere_id` int(11) DEFAULT NULL,
  `intitule` varchar(255) NOT NULL,
  `date_epreuve` datetime NOT NULL,
  `duree_minutes` int(11) NOT NULL DEFAULT 120,
  `salle_id` int(11) DEFAULT NULL,
  `coefficient` decimal(4,2) DEFAULT 1.00,
  `type` enum('ecrit','oral','pratique','tp') NOT NULL DEFAULT 'ecrit',
  `consignes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_examen` (`examen_id`),
  CONSTRAINT `fk_epreuve_examen` FOREIGN KEY (`examen_id`) REFERENCES `examens` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `epreuve_surveillants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `epreuve_id` int(11) NOT NULL,
  `professeur_id` int(11) NOT NULL,
  `role` enum('surveillant','responsable') NOT NULL DEFAULT 'surveillant',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_epreuve_prof` (`epreuve_id`, `professeur_id`),
  CONSTRAINT `fk_esurv_epreuve` FOREIGN KEY (`epreuve_id`) REFERENCES `epreuves` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `epreuve_convocations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `epreuve_id` int(11) NOT NULL,
  `eleve_id` int(11) NOT NULL,
  `place` varchar(50) DEFAULT NULL COMMENT 'numéro de place',
  `present` tinyint(1) DEFAULT NULL,
  `note` decimal(5,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_epreuve_eleve` (`epreuve_id`, `eleve_id`),
  CONSTRAINT `fk_econvoc_epreuve` FOREIGN KEY (`epreuve_id`) REFERENCES `epreuves` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 30. MODULE BESOINS PARTICULIERS — PPS/PAP/PPRE (M37)
-- ============================================================

CREATE TABLE `plans_accompagnement` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `eleve_id` int(11) NOT NULL,
  `type_plan` enum('PAP','PPS','PPRE','PAI','autre') NOT NULL,
  `intitule` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `amenagements` text DEFAULT NULL COMMENT 'tiers-temps, AVS, etc.',
  `responsable_id` int(11) DEFAULT NULL,
  `responsable_type` enum('professeur','vie_scolaire','administrateur') DEFAULT 'professeur',
  `date_debut` date NOT NULL,
  `date_fin` date DEFAULT NULL,
  `statut` enum('brouillon','actif','archive','expire') NOT NULL DEFAULT 'brouillon',
  `document_path` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_eleve` (`eleve_id`),
  KEY `idx_type` (`type_plan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `plan_suivis` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `plan_id` int(11) NOT NULL,
  `auteur_id` int(11) NOT NULL,
  `auteur_type` enum('professeur','vie_scolaire','administrateur') NOT NULL,
  `date_suivi` date NOT NULL,
  `observations` text NOT NULL,
  `progres` enum('insuffisant','en_cours','satisfaisant','acquis') DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_plansuivi_plan` FOREIGN KEY (`plan_id`) REFERENCES `plans_accompagnement` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 31. MODULE GESTION DU PERSONNEL (M39)
-- ============================================================

CREATE TABLE `personnel_absences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `personnel_id` int(11) NOT NULL,
  `personnel_type` enum('professeur','vie_scolaire','administrateur') NOT NULL,
  `type_absence` enum('maladie','formation','personnel','maternite','autre') NOT NULL,
  `date_debut` date NOT NULL,
  `date_fin` date DEFAULT NULL,
  `motif` text DEFAULT NULL,
  `justificatif_path` varchar(500) DEFAULT NULL,
  `statut` enum('declaree','validee','refusee') NOT NULL DEFAULT 'declaree',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_personnel` (`personnel_id`, `personnel_type`),
  KEY `idx_dates` (`date_debut`, `date_fin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `remplacements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `absence_id` int(11) DEFAULT NULL,
  `professeur_absent_id` int(11) NOT NULL,
  `professeur_remplacant_id` int(11) DEFAULT NULL,
  `matiere_id` int(11) DEFAULT NULL,
  `classe_id` int(11) DEFAULT NULL,
  `date_debut` date NOT NULL,
  `date_fin` date DEFAULT NULL,
  `statut` enum('a_pourvoir','pourvu','annule') NOT NULL DEFAULT 'a_pourvoir',
  `commentaire` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_statut` (`statut`),
  CONSTRAINT `fk_rempl_absence` FOREIGN KEY (`absence_id`) REFERENCES `personnel_absences` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 32. MODULE SALLES & MATÉRIELS (M40)
-- ============================================================

CREATE TABLE `reservations_salles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `salle_id` int(11) NOT NULL,
  `reserveur_id` int(11) NOT NULL,
  `reserveur_type` enum('professeur','vie_scolaire','administrateur') NOT NULL,
  `objet` varchar(255) NOT NULL,
  `date_reservation` date NOT NULL,
  `heure_debut` time NOT NULL,
  `heure_fin` time NOT NULL,
  `statut` enum('en_attente','confirmee','annulee') NOT NULL DEFAULT 'en_attente',
  `recurrence` enum('aucune','hebdomadaire','mensuelle') DEFAULT 'aucune',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_salle_date` (`salle_id`, `date_reservation`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `materiels` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) NOT NULL,
  `categorie` enum('informatique','science','sport','audiovisuel','mobilier','autre') NOT NULL DEFAULT 'autre',
  `reference` varchar(100) DEFAULT NULL,
  `etat` enum('neuf','bon','usage','en_panne','reforme') NOT NULL DEFAULT 'bon',
  `salle_id` int(11) DEFAULT NULL COMMENT 'salle de stockage',
  `quantite` int(11) NOT NULL DEFAULT 1,
  `date_acquisition` date DEFAULT NULL,
  `valeur` decimal(10,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_categorie` (`categorie`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `prets_materiels` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `materiel_id` int(11) NOT NULL,
  `emprunteur_id` int(11) NOT NULL,
  `emprunteur_type` enum('professeur','eleve','vie_scolaire') NOT NULL,
  `quantite` int(11) NOT NULL DEFAULT 1,
  `date_pret` date NOT NULL,
  `date_retour_prevue` date NOT NULL,
  `date_retour_effective` date DEFAULT NULL,
  `etat_retour` varchar(255) DEFAULT NULL,
  `statut` enum('en_cours','retourne','en_retard') NOT NULL DEFAULT 'en_cours',
  PRIMARY KEY (`id`),
  KEY `idx_statut` (`statut`),
  CONSTRAINT `fk_pret_materiel` FOREIGN KEY (`materiel_id`) REFERENCES `materiels` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 33. MODULE SERVICES PÉRISCOLAIRES — Cantine, Garderie (M16bis)
-- ============================================================

CREATE TABLE `services_periscolaires` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) NOT NULL,
  `type` enum('cantine','garderie','etude','activite') NOT NULL,
  `description` text DEFAULT NULL,
  `horaires` varchar(255) DEFAULT NULL,
  `tarif` decimal(8,2) DEFAULT NULL,
  `places_max` int(11) DEFAULT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `inscriptions_periscolaire` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `service_id` int(11) NOT NULL,
  `eleve_id` int(11) NOT NULL,
  `jour` enum('lundi','mardi','mercredi','jeudi','vendredi') NOT NULL,
  `date_debut` date NOT NULL,
  `date_fin` date DEFAULT NULL,
  `statut` enum('inscrit','annule') NOT NULL DEFAULT 'inscrit',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_eleve` (`eleve_id`),
  KEY `idx_service_jour` (`service_id`, `jour`),
  CONSTRAINT `fk_inscrperi_service` FOREIGN KEY (`service_id`) REFERENCES `services_periscolaires` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `presences_periscolaire` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `inscription_id` int(11) NOT NULL,
  `date_presence` date NOT NULL,
  `present` tinyint(1) NOT NULL DEFAULT 1,
  `remarques` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_insc_date` (`inscription_id`, `date_presence`),
  CONSTRAINT `fk_presperi_insc` FOREIGN KEY (`inscription_id`) REFERENCES `inscriptions_periscolaire` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `menus_cantine` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `date_menu` date NOT NULL,
  `entree` varchar(255) DEFAULT NULL,
  `plat_principal` varchar(255) DEFAULT NULL,
  `accompagnement` varchar(255) DEFAULT NULL,
  `dessert` varchar(255) DEFAULT NULL,
  `allergenes` text DEFAULT NULL,
  `regime_special` varchar(100) DEFAULT NULL COMMENT 'végétarien, sans porc, etc.',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_date` (`date_menu`, `regime_special`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 34. MODULE STAGES & ALTERNANCE (M17bis)
-- ============================================================

CREATE TABLE `stages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `eleve_id` int(11) NOT NULL,
  `type` enum('stage','alternance','immersion') NOT NULL DEFAULT 'stage',
  `entreprise_nom` varchar(255) NOT NULL,
  `entreprise_adresse` text DEFAULT NULL,
  `entreprise_contact` varchar(255) DEFAULT NULL,
  `tuteur_nom` varchar(255) DEFAULT NULL,
  `tuteur_email` varchar(255) DEFAULT NULL,
  `tuteur_telephone` varchar(20) DEFAULT NULL,
  `professeur_referent_id` int(11) DEFAULT NULL,
  `sujet` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `date_debut` date NOT NULL,
  `date_fin` date NOT NULL,
  `convention_path` varchar(500) DEFAULT NULL,
  `statut` enum('brouillon','soumis','valide','en_cours','termine','annule') NOT NULL DEFAULT 'brouillon',
  `evaluation_entreprise` text DEFAULT NULL,
  `evaluation_note` decimal(5,2) DEFAULT NULL,
  `rapport_path` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_eleve` (`eleve_id`),
  KEY `idx_statut` (`statut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 35. MODULE TRANSPORTS / INTERNAT (M32)
-- ============================================================

CREATE TABLE `lignes_transport` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) NOT NULL,
  `type` enum('bus','navette','train','autre') NOT NULL DEFAULT 'bus',
  `itineraire` text DEFAULT NULL,
  `horaire_depart` time DEFAULT NULL,
  `horaire_arrivee` time DEFAULT NULL,
  `capacite` int(11) DEFAULT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `inscriptions_transport` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ligne_id` int(11) NOT NULL,
  `eleve_id` int(11) NOT NULL,
  `arret` varchar(255) DEFAULT NULL,
  `annee_scolaire` varchar(9) NOT NULL,
  `statut` enum('inscrit','annule') NOT NULL DEFAULT 'inscrit',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ligne_eleve` (`ligne_id`, `eleve_id`, `annee_scolaire`),
  CONSTRAINT `fk_inscrtrans_ligne` FOREIGN KEY (`ligne_id`) REFERENCES `lignes_transport` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `internat_chambres` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `numero` varchar(20) NOT NULL,
  `batiment` varchar(100) DEFAULT NULL,
  `etage` int(11) DEFAULT NULL,
  `capacite` int(11) NOT NULL DEFAULT 2,
  `type` enum('simple','double','triple','dortoir') NOT NULL DEFAULT 'double',
  `equipements` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `internat_affectations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chambre_id` int(11) NOT NULL,
  `eleve_id` int(11) NOT NULL,
  `annee_scolaire` varchar(9) NOT NULL,
  `date_debut` date NOT NULL,
  `date_fin` date DEFAULT NULL,
  `statut` enum('actif','termine') NOT NULL DEFAULT 'actif',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_eleve_annee` (`eleve_id`, `annee_scolaire`),
  CONSTRAINT `fk_intaffect_chambre` FOREIGN KEY (`chambre_id`) REFERENCES `internat_chambres` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 36. MODULE FACTURATION (M33)
-- ============================================================

CREATE TABLE `factures` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `numero` varchar(50) NOT NULL,
  `parent_id` int(11) NOT NULL,
  `eleve_id` int(11) DEFAULT NULL,
  `intitule` varchar(255) NOT NULL,
  `montant_ht` decimal(10,2) NOT NULL,
  `tva` decimal(5,2) NOT NULL DEFAULT 0.00,
  `montant_ttc` decimal(10,2) NOT NULL,
  `date_emission` date NOT NULL,
  `date_echeance` date NOT NULL,
  `statut` enum('brouillon','emise','payee','en_retard','annulee') NOT NULL DEFAULT 'brouillon',
  `type` enum('cantine','periscolaire','inscription','autre') NOT NULL DEFAULT 'autre',
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero` (`numero`),
  KEY `idx_parent` (`parent_id`),
  KEY `idx_statut` (`statut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `facture_lignes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `facture_id` int(11) NOT NULL,
  `description` varchar(255) NOT NULL,
  `quantite` int(11) NOT NULL DEFAULT 1,
  `prix_unitaire` decimal(10,2) NOT NULL,
  `montant` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_factligne_facture` FOREIGN KEY (`facture_id`) REFERENCES `factures` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `paiements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `facture_id` int(11) NOT NULL,
  `montant` decimal(10,2) NOT NULL,
  `date_paiement` date NOT NULL,
  `mode` enum('cheque','virement','especes','cb','prelevement') NOT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_paiement_facture` FOREIGN KEY (`facture_id`) REFERENCES `factures` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 37. MODULE CONTENUS PÉDAGOGIQUES AVANCÉS (M36)
-- ============================================================

CREATE TABLE `ressources_pedagogiques` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titre` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `type` enum('exercice','cours','video','document','lien','qcm') NOT NULL,
  `matiere_id` int(11) DEFAULT NULL,
  `classe_id` int(11) DEFAULT NULL,
  `auteur_id` int(11) NOT NULL,
  `contenu` longtext DEFAULT NULL COMMENT 'contenu HTML ou JSON du QCM',
  `fichier_path` varchar(500) DEFAULT NULL,
  `url_externe` varchar(500) DEFAULT NULL,
  `difficulte` enum('facile','moyen','difficile') DEFAULT 'moyen',
  `tags` varchar(500) DEFAULT NULL,
  `publie` tinyint(1) NOT NULL DEFAULT 0,
  `vues` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_matiere` (`matiere_id`),
  KEY `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 38. MODULE DIPLÔMES & RELEVÉS (M44)
-- ============================================================

CREATE TABLE `diplomes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `eleve_id` int(11) NOT NULL,
  `intitule` varchar(255) NOT NULL,
  `type` enum('brevet','bac','bts','licence','master','autre') NOT NULL,
  `mention` enum('sans','AB','B','TB','felicitations') DEFAULT NULL,
  `date_obtention` date NOT NULL,
  `numero_diplome` varchar(100) DEFAULT NULL,
  `fichier_path` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_eleve` (`eleve_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 38. CONFIGURATION SYSTÈME (SMTP, MODULES, PDF)
-- ============================================================

CREATE TABLE `smtp_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `host` varchar(255) NOT NULL DEFAULT '',
  `port` int(11) NOT NULL DEFAULT 587,
  `username` varchar(255) NOT NULL DEFAULT '',
  `password` varchar(500) NOT NULL DEFAULT '',
  `encryption` enum('tls','ssl','none') NOT NULL DEFAULT 'tls',
  `from_address` varchar(255) NOT NULL DEFAULT '',
  `from_name` varchar(255) NOT NULL DEFAULT '',
  `reply_to` varchar(255) DEFAULT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `smtp_config` (`id`, `enabled`) VALUES (1, 0);

CREATE TABLE `modules_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `module_key` varchar(50) NOT NULL,
  `label` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(50) NOT NULL DEFAULT 'fas fa-puzzle-piece',
  `category` varchar(50) NOT NULL DEFAULT 'general',
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `config_json` text DEFAULT NULL,
  `roles_autorises` json DEFAULT NULL COMMENT 'Rôles autorisés à voir ce module (null = tous)',
  `sort_order` int(11) NOT NULL DEFAULT 100,
  `is_core` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_module_key` (`module_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Modules système (ne peuvent pas être désactivés)
INSERT INTO `modules_config` (`module_key`, `label`, `description`, `icon`, `category`, `enabled`, `sort_order`, `is_core`) VALUES
('accueil',         'Accueil',                'Page d''accueil et tableau de bord',                 'fas fa-home',               'navigation', 1, 1,  1),
('messagerie',      'Messagerie',             'Messagerie interne entre utilisateurs',              'fas fa-envelope',           'navigation', 0, 2,  0),
('parametres',      'Paramètres',             'Paramètres du compte utilisateur',                   'fas fa-cog',                'navigation', 1, 3,  1),
('notifications',   'Notifications',          'Centre de notifications',                            'fas fa-bell',               'navigation', 1, 4,  1);

-- Modules scolaires
INSERT INTO `modules_config` (`module_key`, `label`, `description`, `icon`, `category`, `enabled`, `sort_order`, `is_core`) VALUES
('notes',           'Notes',                  'Gestion des notes et évaluations',                   'fas fa-chart-bar',          'scolaire', 1, 10, 0),
('agenda',          'Agenda',                 'Agenda et événements scolaires',                     'fas fa-calendar',           'scolaire', 1, 11, 0),
('cahierdetextes',  'Cahier de textes',       'Cahier de textes numérique',                         'fas fa-book',               'scolaire', 1, 12, 0),
('emploi_du_temps', 'Emploi du temps',        'Emploi du temps et modifications',                   'fas fa-table',              'scolaire', 1, 13, 0),
('bulletins',       'Bulletins',              'Bulletins scolaires par période',                     'fas fa-file-alt',           'scolaire', 1, 14, 0),
('competences',     'Compétences',            'Évaluation par compétences (socle commun)',           'fas fa-clipboard-list',     'scolaire', 1, 15, 0),
('devoirs',         'Devoirs en ligne',       'Remise de devoirs en ligne',                         'fas fa-tasks',              'scolaire', 1, 16, 0),
('examens',         'Examens',                'Organisation des examens et épreuves',                'fas fa-file-signature',     'scolaire', 1, 17, 0);

-- Modules vie scolaire
INSERT INTO `modules_config` (`module_key`, `label`, `description`, `icon`, `category`, `enabled`, `sort_order`, `is_core`) VALUES
('absences',        'Absences',               'Suivi des absences et justificatifs',                'fas fa-calendar-times',     'vie_scolaire', 1, 20, 0),
('appel',           'Appel',                  'Faire l''appel en classe',                           'fas fa-clipboard-check',    'vie_scolaire', 1, 21, 0),
('discipline',      'Discipline',             'Incidents, sanctions et retenues',                   'fas fa-gavel',              'vie_scolaire', 1, 22, 0),
('vie_scolaire',    'Vie scolaire',           'Tableau de bord vie scolaire',                       'fas fa-user-shield',        'vie_scolaire', 1, 23, 0),
('reporting',       'Reporting',              'Rapports et statistiques',                           'fas fa-chart-line',         'vie_scolaire', 1, 24, 0),
('signalements',    'Signalements',           'Signalements anonymes (harcèlement...)',             'fas fa-shield-alt',         'vie_scolaire', 1, 25, 0),
('besoins',         'Besoins particuliers',   'Suivi des élèves à besoins spécifiques (PAP, PPS)', 'fas fa-hand-holding-heart', 'vie_scolaire', 1, 26, 0);

-- Modules communication
INSERT INTO `modules_config` (`module_key`, `label`, `description`, `icon`, `category`, `enabled`, `sort_order`, `is_core`) VALUES
('annonces',        'Annonces',               'Annonces et sondages',                              'fas fa-bullhorn',           'communication', 1, 30, 0),
('reunions',        'Réunions',               'Organisation des réunions parents-profs',            'fas fa-handshake',          'communication', 1, 31, 0),
('documents',       'Documents',              'Documents administratifs',                           'fas fa-folder-open',        'communication', 1, 32, 0);

-- Modules établissement
INSERT INTO `modules_config` (`module_key`, `label`, `description`, `icon`, `category`, `enabled`, `sort_order`, `is_core`) VALUES
('trombinoscope',   'Trombinoscope',          'Annuaire avec photos',                              'fas fa-users',              'etablissement', 1, 40, 0),
('bibliotheque',    'Bibliothèque',           'Catalogue et gestion des emprunts',                 'fas fa-book-reader',        'etablissement', 1, 41, 0),
('clubs',           'Clubs',                  'Clubs et activités parascolaires',                  'fas fa-users',              'etablissement', 1, 42, 0),
('orientation',     'Orientation',            'Fiches d''orientation et vœux',                     'fas fa-compass',            'etablissement', 1, 43, 0),
('inscriptions',    'Inscriptions',           'Inscriptions et réinscriptions en ligne',           'fas fa-user-plus',          'etablissement', 1, 44, 0),
('infirmerie',      'Infirmerie',             'Passages infirmerie et fiches santé',               'fas fa-heartbeat',          'etablissement', 1, 45, 0),
('ressources',      'Ressources',             'Ressources pédagogiques partagées',                 'fas fa-book-open',          'etablissement', 1, 46, 0),
('diplomes',        'Diplômes',               'Gestion et délivrance des diplômes',                'fas fa-graduation-cap',     'etablissement', 1, 47, 0);

-- Modules logistique
INSERT INTO `modules_config` (`module_key`, `label`, `description`, `icon`, `category`, `enabled`, `sort_order`, `is_core`) VALUES
('periscolaire',    'Périscolaire',           'Cantine, garderie, activités périscolaires',        'fas fa-utensils',           'logistique', 1, 50, 0),
('stages',          'Stages',                 'Conventions de stage (3e, lycée...)',                'fas fa-briefcase',          'logistique', 1, 51, 0),
('transports',      'Transports',             'Lignes de transport et inscriptions',               'fas fa-bus',                'logistique', 1, 52, 0),
('facturation',     'Facturation',            'Factures et paiements en ligne',                    'fas fa-file-invoice-dollar','logistique', 1, 53, 0),
('salles',          'Salles & Matériels',     'Réservation de salles et prêt de matériel',         'fas fa-door-open',          'logistique', 1, 54, 0),
('personnel',       'Gestion personnel',      'Absences et remplacements du personnel',            'fas fa-user-tie',           'logistique', 1, 55, 0);

-- Modules système
INSERT INTO `modules_config` (`module_key`, `label`, `description`, `icon`, `category`, `enabled`, `sort_order`, `is_core`) VALUES
('archivage',       'Archivage',              'Archivage annuel des données',                      'fas fa-archive',            'systeme', 1, 60, 0),
('rgpd',            'RGPD & Audit',           'Conformité RGPD et journal d''audit',               'fas fa-shield-alt',         'systeme', 1, 61, 0),
('support',         'Aide & Support',         'FAQ et tickets de support',                         'fas fa-question-circle',    'systeme', 1, 62, 0);

CREATE TABLE `pdf_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `type` varchar(50) NOT NULL COMMENT 'bulletin, convocation, convention, attestation, diplome, generic',
  `description` text DEFAULT NULL,
  `header_html` text DEFAULT NULL,
  `footer_html` text DEFAULT NULL,
  `body_css` text DEFAULT NULL,
  `page_format` varchar(10) NOT NULL DEFAULT 'A4',
  `orientation` enum('portrait','landscape') NOT NULL DEFAULT 'portrait',
  `margins_json` varchar(200) NOT NULL DEFAULT '{"top":15,"right":10,"bottom":15,"left":10}',
  `show_logo` tinyint(1) NOT NULL DEFAULT 1,
  `show_etablissement` tinyint(1) NOT NULL DEFAULT 1,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Templates PDF par défaut
INSERT INTO `pdf_templates` (`name`, `type`, `description`, `is_default`, `header_html`, `footer_html`, `body_css`) VALUES
('Bulletin standard', 'bulletin', 'Template par défaut pour les bulletins scolaires', 1,
 '<div style="text-align:center;border-bottom:2px solid #333;padding-bottom:10px;margin-bottom:15px"><h2 style="margin:0">{{etablissement_nom}}</h2><p style="margin:2px 0;font-size:11px">{{etablissement_adresse}} {{etablissement_cp}} {{etablissement_ville}}</p><p style="margin:2px 0;font-size:11px">Tél: {{etablissement_tel}} — {{etablissement_email}}</p></div>',
 '<div style="text-align:center;border-top:1px solid #ccc;padding-top:8px;font-size:9px;color:#666">{{etablissement_nom}} — Bulletin généré le {{date}} — Page {{page}}/{{pages}}</div>',
 'body{font-family:Arial,sans-serif;font-size:12px;color:#333} table{width:100%;border-collapse:collapse;margin:10px 0} th,td{border:1px solid #ddd;padding:6px 8px;text-align:left} th{background:#f5f5f5;font-weight:bold} .moyenne{font-weight:bold;color:#2563eb}'),
('Convocation standard', 'convocation', 'Template pour les convocations aux réunions et examens', 1,
 '<div style="text-align:right;margin-bottom:20px"><strong>{{etablissement_nom}}</strong><br>{{etablissement_adresse}}<br>{{etablissement_cp}} {{etablissement_ville}}</div>',
 '<div style="text-align:center;font-size:9px;color:#666;border-top:1px solid #ccc;padding-top:8px">Document officiel — {{etablissement_nom}} — {{date}}</div>',
 'body{font-family:Arial,sans-serif;font-size:12px;color:#333;line-height:1.6}'),
('Attestation standard', 'attestation', 'Template pour les attestations et certificats', 1,
 '<div style="text-align:center;margin-bottom:30px"><h1 style="margin:0;color:#1a365d">{{etablissement_nom}}</h1><p style="color:#666">Académie de {{etablissement_academie}}</p></div>',
 '<div style="text-align:center;font-size:9px;color:#666;margin-top:30px">{{etablissement_nom}} — {{etablissement_adresse}} {{etablissement_cp}} {{etablissement_ville}}</div>',
 'body{font-family:Georgia,serif;font-size:13px;color:#333;line-height:1.8}'),
('Export générique', 'generic', 'Template générique pour les exports de données (listes, tableaux)', 1,
 '<div style="border-bottom:1px solid #333;padding-bottom:8px;margin-bottom:15px"><strong>{{etablissement_nom}}</strong> — {{title}}</div>',
 '<div style="text-align:right;font-size:9px;color:#999;border-top:1px solid #eee;padding-top:5px">Généré le {{date}} — Page {{page}}/{{pages}}</div>',
 'body{font-family:Arial,sans-serif;font-size:11px;color:#333} table{width:100%;border-collapse:collapse} th,td{border:1px solid #ddd;padding:4px 6px} th{background:#f0f0f0;font-size:10px}');

-- ============================================================
-- DONNÉES PAR DÉFAUT
-- ============================================================

-- Créneaux horaires type secondaire
INSERT INTO `creneaux_horaires` (`label`, `heure_debut`, `heure_fin`, `ordre`, `type`) VALUES
('M1', '08:00:00', '09:00:00', 1, 'cours'),
('M2', '09:00:00', '10:00:00', 2, 'cours'),
('Récréation', '10:00:00', '10:15:00', 3, 'pause'),
('M3', '10:15:00', '11:15:00', 4, 'cours'),
('M4', '11:15:00', '12:15:00', 5, 'cours'),
('Déjeuner', '12:15:00', '13:30:00', 6, 'repas'),
('S1', '13:30:00', '14:30:00', 7, 'cours'),
('S2', '14:30:00', '15:30:00', 8, 'cours'),
('Récréation PM', '15:30:00', '15:45:00', 9, 'pause'),
('S3', '15:45:00', '16:45:00', 10, 'cours'),
('S4', '16:45:00', '17:45:00', 11, 'cours');

-- Compétences socle commun simplifié
INSERT INTO `competences` (`code`, `nom`, `domaine`, `niveau`, `ordre`) VALUES
('D1', 'Les langages pour penser et communiquer', 'Socle commun', 1, 1),
('D1.1', 'Comprendre, s''exprimer en français', 'Socle commun', 2, 1),
('D1.2', 'Comprendre, s''exprimer en langue étrangère', 'Socle commun', 2, 2),
('D1.3', 'Comprendre, s''exprimer en langage mathématique', 'Socle commun', 2, 3),
('D1.4', 'Comprendre, s''exprimer en langage artistique', 'Socle commun', 2, 4),
('D2', 'Les méthodes et outils pour apprendre', 'Socle commun', 1, 2),
('D2.1', 'Organisation du travail personnel', 'Socle commun', 2, 1),
('D2.2', 'Coopération et réalisation de projets', 'Socle commun', 2, 2),
('D2.3', 'Médias, démarches de recherche', 'Socle commun', 2, 3),
('D2.4', 'Outils numériques', 'Socle commun', 2, 4),
('D3', 'La formation de la personne et du citoyen', 'Socle commun', 1, 3),
('D3.1', 'Expression de la sensibilité et des opinions', 'Socle commun', 2, 1),
('D3.2', 'La règle et le droit', 'Socle commun', 2, 2),
('D3.3', 'Réflexion et discernement', 'Socle commun', 2, 3),
('D3.4', 'Responsabilité, sens de l''engagement', 'Socle commun', 2, 4),
('D4', 'Les systèmes naturels et les systèmes techniques', 'Socle commun', 1, 4),
('D4.1', 'Démarches scientifiques', 'Socle commun', 2, 1),
('D4.2', 'Conception, création, réalisation', 'Socle commun', 2, 2),
('D4.3', 'Responsabilités individuelles et collectives', 'Socle commun', 2, 3),
('D5', 'Les représentations du monde et l''activité humaine', 'Socle commun', 1, 5),
('D5.1', 'L''espace et le temps', 'Socle commun', 2, 1),
('D5.2', 'Organisations et représentations du monde', 'Socle commun', 2, 2),
('D5.3', 'Invention, élaboration, production', 'Socle commun', 2, 3);

-- FAQ par défaut
INSERT INTO `faq_articles` (`question`, `reponse`, `categorie`, `ordre`) VALUES
('Comment consulter mes notes ?', 'Rendez-vous dans la section "Notes" accessible depuis le menu latéral. Vous y trouverez toutes vos évaluations classées par matière et période.', 'notes', 1),
('Comment justifier une absence ?', 'Allez dans "Absences" → "Justificatifs" puis cliquez sur "Soumettre un justificatif". Remplissez le formulaire et joignez les pièces nécessaires.', 'absences', 2),
('Comment contacter un professeur ?', 'Utilisez la "Messagerie" pour envoyer un message privé. Sélectionnez "Nouveau message" et recherchez le professeur souhaité.', 'messagerie', 3),
('Comment changer mon mot de passe ?', 'Accédez à "Paramètres" depuis le menu latéral, puis rendez-vous dans la section "Sécurité" pour modifier votre mot de passe.', 'compte', 4),
('Comment voir l''emploi du temps ?', 'Cliquez sur "Emploi du temps" dans le menu. Vous pouvez naviguer entre les semaines et voir les modifications éventuelles.', 'emploi_du_temps', 5),
('Comment rendre un devoir en ligne ?', 'Allez dans "Devoirs en ligne", trouvez le devoir concerné et cliquez sur "Rendre". Vous pouvez déposer un fichier ou saisir du texte.', 'devoirs', 6),
('Comment consulter le bulletin ?', 'Les bulletins sont accessibles dans la section "Bulletins" une fois publiés par l''administration à la fin de chaque période.', 'bulletins', 7),
('Que faire si je ne peux pas me connecter ?', 'Vérifiez votre identifiant et mot de passe. Si le problème persiste, utilisez "Mot de passe oublié" ou contactez l''administration.', 'compte', 8);

-- ============================================================
-- MODULE CANTINE (M18) — Restauration scolaire dédiée
-- ============================================================

CREATE TABLE `cantine_reservations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `eleve_id` int(11) NOT NULL,
  `date_repas` date NOT NULL,
  `type_repas` enum('dejeuner','gouter') NOT NULL DEFAULT 'dejeuner',
  `regime` varchar(50) DEFAULT NULL COMMENT 'normal, végétarien, sans porc, sans gluten, halal',
  `allergenes_declares` text DEFAULT NULL,
  `statut` enum('reserve','annule','consomme') NOT NULL DEFAULT 'reserve',
  `reserve_par` varchar(50) DEFAULT NULL COMMENT 'eleve, parent, admin',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_eleve_date_type` (`eleve_id`, `date_repas`, `type_repas`),
  KEY `idx_date` (`date_repas`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cantine_pointage` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reservation_id` int(11) NOT NULL,
  `heure_passage` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `pointe_par` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_reservation` (`reservation_id`),
  CONSTRAINT `fk_cantpoint_reserv` FOREIGN KEY (`reservation_id`) REFERENCES `cantine_reservations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cantine_tarifs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tranche` varchar(100) NOT NULL COMMENT 'Tranche QF ou catégorie',
  `tarif_repas` decimal(6,2) NOT NULL,
  `type_repas` enum('dejeuner','gouter') NOT NULL DEFAULT 'dejeuner',
  `annee_scolaire` varchar(9) NOT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- MODULE INTERNAT (M19)
-- ============================================================

CREATE TABLE `internat_reglement` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `eleve_id` int(11) NOT NULL,
  `chambre_id` int(11) NOT NULL,
  `type` enum('entree','sortie','absence','retard') NOT NULL,
  `date_heure` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `motif` varchar(255) DEFAULT NULL,
  `signale_par` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_eleve` (`eleve_id`),
  KEY `idx_chambre` (`chambre_id`),
  CONSTRAINT `fk_intreg_chambre` FOREIGN KEY (`chambre_id`) REFERENCES `internat_chambres` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `internat_incidents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chambre_id` int(11) DEFAULT NULL,
  `eleve_id` int(11) DEFAULT NULL,
  `type` enum('bruit','degradation','absence','conflit','autre') NOT NULL DEFAULT 'autre',
  `description` text NOT NULL,
  `gravite` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=mineur, 2=moyen, 3=grave',
  `date_incident` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `traite` tinyint(1) NOT NULL DEFAULT 0,
  `traite_par` int(11) DEFAULT NULL,
  `suite_donnee` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_chambre` (`chambre_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- MODULE GARDERIE (M20)
-- ============================================================

CREATE TABLE `garderie_creneaux` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL COMMENT 'Ex: Garderie matin, Garderie soir, Étude surveillée',
  `type` enum('matin','soir','mercredi','vacances') NOT NULL,
  `heure_debut` time NOT NULL,
  `heure_fin` time NOT NULL,
  `places_max` int(11) DEFAULT NULL,
  `tarif` decimal(6,2) DEFAULT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `garderie_inscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `creneau_id` int(11) NOT NULL,
  `eleve_id` int(11) NOT NULL,
  `jour` enum('lundi','mardi','mercredi','jeudi','vendredi') NOT NULL,
  `date_debut` date NOT NULL,
  `date_fin` date DEFAULT NULL,
  `inscrit_par` varchar(50) DEFAULT NULL COMMENT 'parent, admin',
  `statut` enum('actif','annule') NOT NULL DEFAULT 'actif',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_creneau_eleve_jour` (`creneau_id`, `eleve_id`, `jour`),
  CONSTRAINT `fk_gardeinsc_creneau` FOREIGN KEY (`creneau_id`) REFERENCES `garderie_creneaux` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `garderie_presences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `inscription_id` int(11) NOT NULL,
  `date_presence` date NOT NULL,
  `heure_arrivee` time DEFAULT NULL,
  `heure_depart` time DEFAULT NULL,
  `present` tinyint(1) NOT NULL DEFAULT 1,
  `remarques` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_insc_date` (`inscription_id`, `date_presence`),
  CONSTRAINT `fk_gardepres_insc` FOREIGN KEY (`inscription_id`) REFERENCES `garderie_inscriptions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- MODULE PROJETS PÉDAGOGIQUES (M41)
-- ============================================================

CREATE TABLE `projets_pedagogiques` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titre` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `objectifs` text DEFAULT NULL,
  `type` enum('EPI','projet_classe','sortie','voyage','autre') NOT NULL DEFAULT 'projet_classe',
  `responsable_id` int(11) NOT NULL COMMENT 'professeur responsable',
  `classes` varchar(500) DEFAULT NULL COMMENT 'classes concernées, CSV',
  `matieres` varchar(500) DEFAULT NULL COMMENT 'matières impliquées, CSV',
  `date_debut` date NOT NULL,
  `date_fin` date DEFAULT NULL,
  `budget` decimal(10,2) DEFAULT NULL,
  `statut` enum('brouillon','soumis','valide','en_cours','termine','annule') NOT NULL DEFAULT 'brouillon',
  `pieces_jointes` text DEFAULT NULL COMMENT 'JSON array de fichiers',
  `bilan` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_responsable` (`responsable_id`),
  KEY `idx_statut` (`statut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `projets_pedagogiques_participants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `projet_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_type` enum('professeur','eleve') NOT NULL,
  `role_projet` varchar(100) DEFAULT NULL COMMENT 'Ex: co-responsable, participant',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_projet_user` (`projet_id`, `user_id`, `user_type`),
  CONSTRAINT `fk_projpart_projet` FOREIGN KEY (`projet_id`) REFERENCES `projets_pedagogiques` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `projets_pedagogiques_etapes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `projet_id` int(11) NOT NULL,
  `titre` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `date_echeance` date DEFAULT NULL,
  `statut` enum('a_faire','en_cours','termine') NOT NULL DEFAULT 'a_faire',
  `ordre` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_projetape_projet` FOREIGN KEY (`projet_id`) REFERENCES `projets_pedagogiques` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- MODULE PARCOURS ÉDUCATIFS (M42)
-- ============================================================

CREATE TABLE `parcours_educatifs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `eleve_id` int(11) NOT NULL,
  `type_parcours` enum('avenir','sante','citoyen','PEAC') NOT NULL,
  `titre` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `date_activite` date NOT NULL,
  `competences_visees` text DEFAULT NULL,
  `validation` enum('non_valide','en_cours','valide') NOT NULL DEFAULT 'non_valide',
  `valide_par` int(11) DEFAULT NULL,
  `pieces_jointes` text DEFAULT NULL COMMENT 'JSON array de fichiers',
  `annee_scolaire` varchar(9) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_eleve` (`eleve_id`),
  KEY `idx_type` (`type_parcours`),
  KEY `idx_annee` (`annee_scolaire`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `parcours_educatifs_modeles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type_parcours` enum('avenir','sante','citoyen','PEAC') NOT NULL,
  `titre` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `niveau` varchar(50) DEFAULT NULL COMMENT 'niveau scolaire cible',
  `competences` text DEFAULT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- MODULE VIE ASSOCIATIVE / MDL (M43)
-- ============================================================

CREATE TABLE `associations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) NOT NULL,
  `type` enum('MDL','FSE','association','autre') NOT NULL DEFAULT 'MDL',
  `description` text DEFAULT NULL,
  `president_eleve_id` int(11) DEFAULT NULL,
  `referent_adulte_id` int(11) DEFAULT NULL,
  `budget_annuel` decimal(10,2) DEFAULT NULL,
  `statut` enum('active','inactive','en_creation') NOT NULL DEFAULT 'active',
  `logo_path` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `association_membres` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `association_id` int(11) NOT NULL,
  `eleve_id` int(11) NOT NULL,
  `role_membre` enum('president','vice_president','tresorier','secretaire','membre') NOT NULL DEFAULT 'membre',
  `date_adhesion` date NOT NULL,
  `cotisation_payee` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_asso_eleve` (`association_id`, `eleve_id`),
  CONSTRAINT `fk_assomembre_asso` FOREIGN KEY (`association_id`) REFERENCES `associations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `association_activites` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `association_id` int(11) NOT NULL,
  `titre` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `date_activite` datetime NOT NULL,
  `lieu` varchar(255) DEFAULT NULL,
  `budget_alloue` decimal(10,2) DEFAULT NULL,
  `budget_depense` decimal(10,2) DEFAULT NULL,
  `nb_participants` int(11) DEFAULT NULL,
  `statut` enum('planifie','en_cours','termine','annule') NOT NULL DEFAULT 'planifie',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_assoact_asso` FOREIGN KEY (`association_id`) REFERENCES `associations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `association_tresorerie` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `association_id` int(11) NOT NULL,
  `type` enum('recette','depense') NOT NULL,
  `montant` decimal(10,2) NOT NULL,
  `libelle` varchar(255) NOT NULL,
  `categorie` varchar(100) DEFAULT NULL COMMENT 'cotisations, vente, achat, etc.',
  `date_operation` date NOT NULL,
  `justificatif_path` varchar(500) DEFAULT NULL,
  `saisi_par` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_assotres_asso` FOREIGN KEY (`association_id`) REFERENCES `associations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- AGENDA : champ récurrence (Priorité 3)
-- ============================================================

ALTER TABLE `evenements` ADD COLUMN `rrule` varchar(500) DEFAULT NULL COMMENT 'RFC5545 RRULE ex: FREQ=WEEKLY;BYDAY=MO,WE;UNTIL=20260630' AFTER `date_fin`;
ALTER TABLE `evenements` ADD COLUMN `recurrence_parent_id` int(11) DEFAULT NULL COMMENT 'ID événement parent si occurrence' AFTER `rrule`;

-- ============================================================
-- 6 nouveaux modules dans modules_config
-- ============================================================

INSERT INTO `modules_config` (`module_key`, `label`, `description`, `icon`, `category`, `enabled`, `sort_order`, `is_core`) VALUES
('cantine',              'Cantine',               'Restauration scolaire : menus, réservations, pointage',     'fas fa-utensils',           'logistique', 1, 56, 0),
('internat',             'Internat',              'Gestion de l''internat : chambres, affectations, vie',      'fas fa-bed',                'logistique', 1, 57, 0),
('garderie',             'Garderie',              'Accueil périscolaire : matin, soir, mercredi',              'fas fa-child',              'logistique', 1, 58, 0),
('projets_pedagogiques', 'Projets pédagogiques',  'EPI, projets de classe, sorties et voyages',                'fas fa-project-diagram',    'scolaire',   1, 18, 0),
('parcours_educatifs',   'Parcours éducatifs',    'Parcours Avenir, Santé, Citoyen, PEAC',                     'fas fa-route',              'scolaire',   1, 19, 0),
('vie_associative',      'Vie associative',       'MDL, FSE, associations et trésorerie',                      'fas fa-hands-helping',      'etablissement', 1, 48, 0);

-- ============================================================
-- VUE UNIFIÉE DES UTILISATEURS
-- ============================================================

CREATE OR REPLACE VIEW `v_users` AS
  SELECT id, prenom, nom, CONCAT(prenom, ' ', nom) AS nom_complet, 'eleve' AS user_type FROM eleves
  UNION ALL
  SELECT id, prenom, nom, CONCAT(prenom, ' ', nom), 'parent' FROM parents
  UNION ALL
  SELECT id, prenom, nom, CONCAT(prenom, ' ', nom), 'professeur' FROM professeurs
  UNION ALL
  SELECT id, prenom, nom, CONCAT(prenom, ' ', nom), 'vie_scolaire' FROM vie_scolaire
  UNION ALL
  SELECT id, prenom, nom, CONCAT(prenom, ' ', nom), 'administrateur' FROM administrateurs;

-- ============================================================
-- M99 : RBAC Permissions dynamiques
-- ============================================================
CREATE TABLE IF NOT EXISTS `rbac_permissions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `role` VARCHAR(50) NOT NULL,
  `permission` VARCHAR(100) NOT NULL,
  `granted` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_role_permission` (`role`, `permission`),
  INDEX `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- M100 : Permissions CRUD par module (admin)
-- ============================================================
CREATE TABLE IF NOT EXISTS `module_permissions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `module_key` VARCHAR(50) NOT NULL,
  `role` VARCHAR(50) NOT NULL,
  `can_view` TINYINT(1) NOT NULL DEFAULT 1,
  `can_create` TINYINT(1) NOT NULL DEFAULT 0,
  `can_edit` TINYINT(1) NOT NULL DEFAULT 0,
  `can_delete` TINYINT(1) NOT NULL DEFAULT 0,
  `can_export` TINYINT(1) NOT NULL DEFAULT 0,
  `can_import` TINYINT(1) NOT NULL DEFAULT 0,
  `custom_permissions` JSON DEFAULT NULL COMMENT 'Permissions spécifiques au module, ex: {"can_send":true,"can_moderate":false}',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_module_role` (`module_key`, `role`),
  KEY `idx_role` (`role`),
  KEY `idx_module` (`module_key`),
  CONSTRAINT `fk_modperm_module` FOREIGN KEY (`module_key`) REFERENCES `modules_config` (`module_key`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Permissions par défaut messagerie (désactivée par défaut sauf admin)
INSERT INTO `module_permissions` (`module_key`, `role`, `can_view`, `can_create`, `can_edit`, `can_delete`, `can_export`, `custom_permissions`) VALUES
('messagerie', 'administrateur', 1, 1, 1, 1, 1, '{"can_send":true,"can_moderate":true,"can_broadcast":true}'),
('messagerie', 'professeur',     0, 0, 0, 0, 0, '{"can_send":false,"can_moderate":false}'),
('messagerie', 'vie_scolaire',   0, 0, 0, 0, 0, '{"can_send":false,"can_moderate":false}'),
('messagerie', 'eleve',          0, 0, 0, 0, 0, '{"can_send":false}'),
('messagerie', 'parent',         0, 0, 0, 0, 0, '{"can_send":false}');

-- Permissions par défaut notes
INSERT INTO `module_permissions` (`module_key`, `role`, `can_view`, `can_create`, `can_edit`, `can_delete`, `can_export`) VALUES
('notes', 'administrateur', 1, 1, 1, 1, 1),
('notes', 'professeur',     1, 1, 1, 0, 1),
('notes', 'vie_scolaire',   1, 0, 0, 0, 1),
('notes', 'eleve',          1, 0, 0, 0, 0),
('notes', 'parent',         1, 0, 0, 0, 0);

-- Permissions par défaut absences
INSERT INTO `module_permissions` (`module_key`, `role`, `can_view`, `can_create`, `can_edit`, `can_delete`, `can_export`) VALUES
('absences', 'administrateur', 1, 1, 1, 1, 1),
('absences', 'professeur',     1, 1, 1, 0, 0),
('absences', 'vie_scolaire',   1, 1, 1, 1, 1),
('absences', 'eleve',          1, 0, 0, 0, 0),
('absences', 'parent',         1, 0, 0, 0, 0);

-- ============================================================
-- M101 : Accès technicien temporaire
-- ============================================================
CREATE TABLE IF NOT EXISTS `technicien_access` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nom` VARCHAR(100) NOT NULL,
  `prenom` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) DEFAULT NULL,
  `identifiant` VARCHAR(50) NOT NULL,
  `mot_de_passe` VARCHAR(255) NOT NULL,
  `motif` TEXT NOT NULL COMMENT 'Raison de l''accès temporaire',
  `permissions` JSON NOT NULL DEFAULT ('["admin.access","admin.systeme"]') COMMENT 'Liste des permissions accordées',
  `modules_autorises` JSON DEFAULT NULL COMMENT 'null = tous les modules, sinon liste de module_key',
  `ip_whitelist` JSON DEFAULT NULL COMMENT 'IPs autorisées, null = toutes',
  `created_by` INT NOT NULL COMMENT 'ID admin qui a créé l''accès',
  `actif` TINYINT(1) NOT NULL DEFAULT 1,
  `date_debut` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_expiration` DATETIME NOT NULL COMMENT 'Expiration automatique',
  `last_login` DATETIME DEFAULT NULL,
  `login_count` INT NOT NULL DEFAULT 0,
  `revoked_at` DATETIME DEFAULT NULL,
  `revoked_by` INT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_identifiant` (`identifiant`),
  KEY `idx_actif_expiration` (`actif`, `date_expiration`),
  KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Log des actions technicien (audit renforcé)
CREATE TABLE IF NOT EXISTS `technicien_audit_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `technicien_id` INT NOT NULL,
  `action` VARCHAR(100) NOT NULL,
  `details` JSON DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_technicien` (`technicien_id`),
  KEY `idx_action` (`action`),
  KEY `idx_date` (`created_at`),
  CONSTRAINT `fk_techaudit_tech` FOREIGN KEY (`technicien_id`) REFERENCES `technicien_access` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- M102 : Profil utilisateur étendu (citation, réseaux sociaux, photo)
-- ============================================================
CREATE TABLE IF NOT EXISTS `user_profiles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `user_type` VARCHAR(20) NOT NULL,
  `citation` VARCHAR(500) DEFAULT NULL COMMENT 'Citation ou phrase de présentation',
  `site_web` VARCHAR(255) DEFAULT NULL,
  `lien_linkedin` VARCHAR(255) DEFAULT NULL,
  `lien_twitter` VARCHAR(255) DEFAULT NULL,
  `lien_github` VARCHAR(255) DEFAULT NULL,
  `lien_instagram` VARCHAR(255) DEFAULT NULL,
  `lien_autre` VARCHAR(255) DEFAULT NULL,
  `competences_tags` JSON DEFAULT NULL COMMENT 'Tags de compétences/intérêts',
  `disponibilites` VARCHAR(255) DEFAULT NULL COMMENT 'Horaires de disponibilité (texte libre)',
  `bureau` VARCHAR(100) DEFAULT NULL COMMENT 'Numéro de bureau (professeur/admin)',
  `telephone_pro` VARCHAR(20) DEFAULT NULL,
  `date_naissance_visible` TINYINT(1) NOT NULL DEFAULT 0,
  `email_visible` TINYINT(1) NOT NULL DEFAULT 0,
  `profil_public` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Visible dans le trombinoscope',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_user_profile` (`user_id`, `user_type`),
  KEY `idx_profil_public` (`profil_public`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- M103 : Configuration import/export
-- ============================================================
CREATE TABLE IF NOT EXISTS `import_export_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `type` ENUM('import','export') NOT NULL,
  `cible` VARCHAR(50) NOT NULL COMMENT 'users, config, notes, absences, etc.',
  `format` VARCHAR(20) NOT NULL DEFAULT 'csv' COMMENT 'csv, json, xlsx',
  `fichier_nom` VARCHAR(255) DEFAULT NULL,
  `fichier_chemin` VARCHAR(500) DEFAULT NULL,
  `nb_lignes_total` INT DEFAULT 0,
  `nb_lignes_traitees` INT DEFAULT 0,
  `nb_erreurs` INT DEFAULT 0,
  `erreurs_detail` JSON DEFAULT NULL,
  `options` JSON DEFAULT NULL COMMENT 'Options utilisées (mapping colonnes, etc.)',
  `statut` ENUM('en_cours','termine','erreur','annule') NOT NULL DEFAULT 'en_cours',
  `user_id` INT NOT NULL,
  `user_type` VARCHAR(20) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_type_cible` (`type`, `cible`),
  KEY `idx_statut` (`statut`),
  KEY `idx_user` (`user_id`, `user_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- M104 : Widgets personnalisables accueil
-- ============================================================
CREATE TABLE IF NOT EXISTS `dashboard_widgets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `widget_key` VARCHAR(50) NOT NULL COMMENT 'identifiant unique du widget',
  `label` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `icon` VARCHAR(50) NOT NULL DEFAULT 'fas fa-puzzle-piece',
  `type` ENUM('stats','list','chart','calendar','shortcut','custom') NOT NULL DEFAULT 'stats',
  `module_key` VARCHAR(50) DEFAULT NULL COMMENT 'Module lié (null = global)',
  `roles_autorises` JSON DEFAULT NULL COMMENT 'null = tous les rôles',
  `default_config` JSON DEFAULT NULL COMMENT 'Config par défaut du widget',
  `min_width` INT NOT NULL DEFAULT 1 COMMENT 'Largeur min en colonnes (1-4)',
  `max_width` INT NOT NULL DEFAULT 4,
  `default_width` INT NOT NULL DEFAULT 2,
  `default_height` INT NOT NULL DEFAULT 1,
  `is_default` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Affiché par défaut pour les nouveaux utilisateurs',
  `actif` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 100,
  UNIQUE KEY `uk_widget_key` (`widget_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_dashboard_config` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `user_type` VARCHAR(20) NOT NULL,
  `widget_key` VARCHAR(50) NOT NULL,
  `position_x` INT NOT NULL DEFAULT 0 COMMENT 'Colonne (0-based)',
  `position_y` INT NOT NULL DEFAULT 0 COMMENT 'Ligne (0-based)',
  `width` INT NOT NULL DEFAULT 2,
  `height` INT NOT NULL DEFAULT 1,
  `config` JSON DEFAULT NULL COMMENT 'Config spécifique utilisateur',
  `visible` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_user_widget` (`user_id`, `user_type`, `widget_key`),
  KEY `idx_user` (`user_id`, `user_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Widgets par défaut
INSERT INTO `dashboard_widgets` (`widget_key`, `label`, `description`, `icon`, `type`, `module_key`, `roles_autorises`, `default_width`, `is_default`, `sort_order`) VALUES
('prochains_evenements', 'Prochains événements', 'Événements à venir cette semaine', 'fas fa-calendar', 'list', 'agenda', NULL, 2, 1, 10),
('devoirs_a_faire',      'Devoirs à rendre',     'Devoirs en attente de rendu',      'fas fa-tasks', 'list', 'devoirs', '["eleve","parent"]', 2, 1, 20),
('dernieres_notes',      'Dernières notes',       'Notes les plus récentes',          'fas fa-chart-bar', 'list', 'notes', '["eleve","parent","professeur"]', 2, 1, 30),
('messages_non_lus',     'Messages non lus',      'Messages en attente de lecture',   'fas fa-envelope', 'stats', 'messagerie', NULL, 1, 1, 40),
('absences_du_jour',     'Absences du jour',      'Absences signalées aujourd''hui',  'fas fa-calendar-times', 'stats', 'absences', '["administrateur","vie_scolaire","professeur"]', 1, 1, 50),
('stats_rapides',        'Statistiques rapides',   'Vue d''ensemble chiffrée',         'fas fa-tachometer-alt', 'stats', NULL, '["administrateur","vie_scolaire"]', 4, 1, 5),
('emploi_du_temps_jour', 'Emploi du temps',        'Cours du jour',                    'fas fa-table', 'calendar', 'emploi_du_temps', '["eleve","professeur"]', 2, 1, 15),
('raccourcis',           'Accès rapides',          'Raccourcis vers vos modules favoris', 'fas fa-star', 'shortcut', NULL, NULL, 2, 1, 60),
('annonces_recentes',    'Annonces récentes',     'Dernières annonces et sondages',    'fas fa-bullhorn', 'list', 'annonces', NULL, 2, 1, 25),
('reunions_a_venir',     'Réunions à venir',      'Prochaines réunions planifiées',    'fas fa-handshake', 'list', 'reunions', '["professeur","parent","administrateur"]', 2, 0, 70);

-- ============================================================
-- INTERNATIONALISATION (i18n)
-- ============================================================

-- Table de traductions pour le contenu dynamique en base
CREATE TABLE IF NOT EXISTS `translations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `translatable_type` varchar(50) NOT NULL COMMENT 'Type d''entité (module, widget, announcement)',
  `translatable_id` int(11) NOT NULL COMMENT 'ID de l''entité traduite',
  `locale` varchar(10) NOT NULL COMMENT 'Code locale (fr, en, etc.)',
  `field` varchar(50) NOT NULL COMMENT 'Champ traduit (label, description, etc.)',
  `value` text NOT NULL COMMENT 'Valeur traduite',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_trans` (`translatable_type`, `translatable_id`, `locale`, `field`),
  KEY `idx_trans_lookup` (`translatable_type`, `translatable_id`, `locale`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ajouter la colonne default_locale à etablissement_info
ALTER TABLE `etablissement_info`
  ADD COLUMN IF NOT EXISTS `default_locale` varchar(10) NOT NULL DEFAULT 'fr' COMMENT 'Locale par défaut de l''établissement';

-- ============================================================
-- FEATURE FLAGS (multi-établissement)
-- ============================================================

CREATE TABLE IF NOT EXISTS `feature_flags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `flag_key` varchar(100) NOT NULL,
  `label` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `establishment_types` JSON DEFAULT NULL COMMENT 'null = tous types, ["college","lycee"] = spécifique',
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `config` JSON DEFAULT NULL COMMENT 'Configuration additionnelle du flag',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_flag` (`flag_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Feature flags par défaut
INSERT INTO `feature_flags` (`flag_key`, `label`, `establishment_types`, `enabled`) VALUES
('stages.enabled',            'Stages',                      '["lycee","superieur"]',       1),
('alternance.enabled',        'Alternance',                  '["superieur"]',               1),
('orientation.parcoursup',    'Parcoursup',                  '["lycee"]',                   1),
('orientation.ects',          'Crédits ECTS',                '["superieur"]',               1),
('bulletins.brevet',          'Bulletins Brevet',            '["college"]',                 1),
('bulletins.bac',             'Bulletins Bac',               '["lycee"]',                   1),
('examens.brevet',            'Examens Brevet',              '["college"]',                 1),
('examens.bac',               'Examens Bac',                 '["lycee"]',                   1),
('internat.enabled',          'Internat',                    '["college","lycee"]',         1),
('garderie.enabled',          'Garderie',                    '["college"]',                 1),
('cantine.enabled',           'Cantine',                     NULL,                          1),
('periscolaire.enabled',      'Périscolaire',                '["college"]',                 1),
('competences.socle',         'Socle commun de compétences', '["college"]',                 1),
('competences.referentiel',   'Référentiel de compétences',  '["lycee","superieur"]',       1);

-- ============================================================
-- API TOKENS (authentification externe)
-- ============================================================

CREATE TABLE IF NOT EXISTS `api_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `user_type` varchar(20) NOT NULL,
  `token_hash` varchar(64) NOT NULL COMMENT 'SHA-256 du token',
  `name` varchar(100) NOT NULL COMMENT 'Nom descriptif du token',
  `abilities` JSON DEFAULT NULL COMMENT 'Permissions du token (null = toutes)',
  `last_used_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_token` (`token_hash`),
  KEY `idx_api_tokens_user` (`user_id`, `user_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- WEBHOOKS (intégrations externes)
-- ============================================================

CREATE TABLE IF NOT EXISTS `webhooks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `url` varchar(500) NOT NULL,
  `events` JSON NOT NULL COMMENT 'Événements déclencheurs',
  `secret` varchar(64) NOT NULL COMMENT 'Secret HMAC-SHA256',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `last_triggered_at` datetime DEFAULT NULL,
  `failure_count` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ajouter establishment_types aux modules
ALTER TABLE `modules_config`
  ADD COLUMN IF NOT EXISTS `establishment_types` JSON DEFAULT NULL COMMENT 'null = tous types d''établissement';

-- ============================================================
-- OAuth SSO bindings
-- ============================================================
CREATE TABLE IF NOT EXISTS `oauth_bindings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `user_type` VARCHAR(20) NOT NULL,
  `provider` VARCHAR(50) NOT NULL,
  `provider_user_id` VARCHAR(255) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_binding` (`provider`, `provider_user_id`),
  KEY `idx_user` (`user_id`, `user_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Audit log : colonnes supplémentaires pour traçabilité avancée
-- ============================================================
ALTER TABLE `audit_log`
  ADD COLUMN IF NOT EXISTS `severity` ENUM('INFO','WARNING','CRITICAL') NOT NULL DEFAULT 'INFO' AFTER `user_agent`,
  ADD COLUMN IF NOT EXISTS `request_method` VARCHAR(10) DEFAULT NULL AFTER `severity`,
  ADD COLUMN IF NOT EXISTS `request_uri` VARCHAR(500) DEFAULT NULL AFTER `request_method`;

-- Index composites pour les requêtes fréquentes du dashboard admin
ALTER TABLE `audit_log`
  ADD INDEX IF NOT EXISTS `idx_severity_date` (`severity`, `created_at`),
  ADD INDEX IF NOT EXISTS `idx_action_date` (`action`, `created_at`);

-- ============================================================
-- Job Queue (G4) — file d'attente asynchrone en base
-- ============================================================
CREATE TABLE IF NOT EXISTS `job_queue` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `handler` VARCHAR(255) NOT NULL COMMENT 'Classe ou callable du job',
  `payload` JSON NOT NULL,
  `status` ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
  `attempts` INT NOT NULL DEFAULT 0,
  `max_attempts` INT NOT NULL DEFAULT 3,
  `available_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `started_at` DATETIME DEFAULT NULL,
  `completed_at` DATETIME DEFAULT NULL,
  `error_message` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_status_available` (`status`, `available_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Module Settings Schema (D5) — champs de configuration déclaratifs par module
-- ============================================================
CREATE TABLE IF NOT EXISTS `module_settings_schema` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `module_key` VARCHAR(50) NOT NULL,
  `field_key` VARCHAR(50) NOT NULL,
  `field_type` ENUM('text','number','checkbox','select','textarea','color') NOT NULL,
  `label` VARCHAR(100) NOT NULL,
  `default_value` TEXT DEFAULT NULL,
  `options` JSON DEFAULT NULL COMMENT 'Options pour les selects',
  `hint` TEXT DEFAULT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  UNIQUE KEY `uk_module_field` (`module_key`, `field_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Module Migrations (I1) — suivi des migrations SQL par module
-- ============================================================
CREATE TABLE IF NOT EXISTS `module_migrations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `module_key` VARCHAR(50) NOT NULL,
  `migration_file` VARCHAR(100) NOT NULL,
  `executed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_module_migration` (`module_key`, `migration_file`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- App Metrics (J2) — métriques applicatives
-- ============================================================
CREATE TABLE IF NOT EXISTS `app_metrics` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `metric_key` VARCHAR(100) NOT NULL,
  `metric_value` DECIMAL(12,2) NOT NULL,
  `recorded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_key_date` (`metric_key`, `recorded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Routes & tri sidebar dans modules_config (D6)
-- ============================================================
ALTER TABLE `modules_config`
  ADD COLUMN IF NOT EXISTS `route_path` VARCHAR(100) DEFAULT NULL AFTER `icon`,
  ADD COLUMN IF NOT EXISTS `sidebar_sort` INT NOT NULL DEFAULT 100 AFTER `route_path`;

-- ============================================================
SET SESSION FOREIGN_KEY_CHECKS = 1;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
