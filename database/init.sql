-- Structure de la base de données AMEA
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Table: users
CREATE TABLE IF NOT EXISTS `users` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `username` varchar(50) NOT NULL,
    `password` varchar(255) NOT NULL,
    `last_name` varchar(100) NOT NULL,
    `first_name` varchar(100) NOT NULL,
    `email` varchar(100) NOT NULL,
    `role` enum('admin','user') NOT NULL DEFAULT 'user',
    `is_active` tinyint(1) NOT NULL DEFAULT 1,
    `session_version` int(11) NOT NULL DEFAULT 1,
    `last_login` datetime DEFAULT NULL,
    `created_at` datetime DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `username` (`username`),
    UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2. Table: students
CREATE TABLE IF NOT EXISTS `students` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `last_name` varchar(100) NOT NULL,
    `first_name` varchar(100) NOT NULL,
    `gender` enum('Male','Female') NOT NULL,
    `age` int(11) DEFAULT NULL,
    `birth_date` date DEFAULT NULL,
    `residence` varchar(150) DEFAULT NULL,
    `institution` varchar(200) DEFAULT NULL,
    `status` enum('PUPIL','STUDENT','TRAINEE') NOT NULL,
    `study_field` varchar(200) DEFAULT NULL,
    `study_level` varchar(100) DEFAULT NULL,
    `phone` varchar(20) DEFAULT NULL UNIQUE,
    `email` varchar(100) DEFAULT NULL UNIQUE,
    `arrival_year` int(11) DEFAULT NULL,
    `housing_type` enum('With family','Shared housing','University residence','Other','Hébergement temporaire','Location') DEFAULT NULL,
    `housing_details` varchar(100) DEFAULT NULL,
    `post_training_project` text DEFAULT NULL,
    `identity_document` varchar(255) DEFAULT NULL,
    `cv_path` varchar(255) DEFAULT NULL,
    `nationalities` text DEFAULT NULL, -- JSON formatted array
    `registration_date` datetime DEFAULT current_timestamp(),
    `graduation_date` date DEFAULT NULL,
    `consent_privacy` tinyint(1) NOT NULL DEFAULT 0,
    `consent_privacy_date` datetime DEFAULT NULL,
    `is_locked` tinyint(1) NOT NULL DEFAULT 0,
    `kyc_status` enum('PENDING_CONFIRMATION','UNDER_REVIEW','APPROVED','NEEDS_CLARIFICATION','REJECTED') NOT NULL DEFAULT 'PENDING_CONFIRMATION',
    `kyc_notes` text DEFAULT NULL,
    `review_token` varchar(64) DEFAULT NULL UNIQUE,
    `kyc_updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3. Table: institutions
CREATE TABLE IF NOT EXISTS `institutions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 4. Table: study_fields
CREATE TABLE IF NOT EXISTS `study_fields` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 5. Table: study_levels
CREATE TABLE IF NOT EXISTS `study_levels` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 6. Table: locations
CREATE TABLE IF NOT EXISTS `locations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `region` VARCHAR(100) NOT NULL,
    UNIQUE (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 7. Table: slider_images
CREATE TABLE IF NOT EXISTS `slider_images` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `image_path` varchar(255) NOT NULL,
    `title` varchar(100) DEFAULT NULL,
    `caption` text DEFAULT NULL,
    `display_order` int(11) NOT NULL DEFAULT 0,
    `is_active` tinyint(1) NOT NULL DEFAULT 1,
    `uploaded_by` int(11) DEFAULT NULL,
    `created_at` datetime DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `uploaded_by` (`uploaded_by`),
    CONSTRAINT `slider_images_ibfk_1` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 8. Table: password_resets
CREATE TABLE IF NOT EXISTS `password_resets` (
    `email` varchar(100) NOT NULL,
    `token` varchar(100) NOT NULL,
    `expires_at` datetime NOT NULL,
    PRIMARY KEY (`token`),
    KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 9. Table: settings
CREATE TABLE IF NOT EXISTS `settings` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `setting_key` varchar(50) NOT NULL,
    `setting_value` text,
    `setting_group` varchar(50) DEFAULT 'general',
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 10. Table: countries (Reference for nationalities)
CREATE TABLE IF NOT EXISTS `countries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code_iso` char(2) NOT NULL,
  `name_fr` varchar(100) NOT NULL,
  `name_en` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code_iso` (`code_iso`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Table: student_country (Pivot table for Many-to-Many relationship)
CREATE TABLE IF NOT EXISTS `student_country` (
  `student_id` int(11) NOT NULL,
  `country_id` int(11) NOT NULL,
  PRIMARY KEY (`student_id`,`country_id`),
  KEY `country_id` (`country_id`),
  CONSTRAINT `student_country_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `student_country_ibfk_2` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --- INITIAL DATA ---

-- Institutions
INSERT IGNORE INTO institutions (name) VALUES
('Université Cheikh Anta Diop (UCAD)'),
('Université Gaston Berger (UGB)'),
('Université Alioune Diop de Bambey (UADB)'),
('Université Iba-Der-Thiam de Thiès (UIDT)'),
('Université Assane Seck de Ziguinchor (UASZ)'),
('Université Virtuelle du Sénégal (UVS)'),
('Université du Sine Saloum El Hadji Ibrahima Niass (USSEIN)'),
('Université Amadou-Mahtar-M''Bow (UAM)'),
('Université du Sénégal Oriental (USO)'),
('Université Amadou Hampaté Bâ'),
('Université Dakar-Bourguiba'),
('Université du Sahel'),
('Université Catholique de l''Afrique de l''Ouest (UCAO)'),
('Université Kocc Barma'),
('Université El Hadji Ibrahima Niass'),
('Université de l''Atlantique'),
('MIT University de Dakar'),
('Université Cheikh Ahmadou Bamba Mbacké'),
('Université Polytechnique de l''Ouest Africain'),
('Université Privée de Marrakech - Sénégal'),
('École Polytechnique de Thiès'),
('Institut Supérieur de Management (ISM)'),
('Groupe IAM (Institut Africain de Management)'),
('ESGIB - École Supérieure de Génie Industriel et Biologique'),
('École Supérieure de Génie (ESGE)'),
('Institut Mariste d''Enseignement Supérieur (IMES)'),
('Institut Supérieur de Droit de Dakar (ISDD Dakar)'),
('Institut Supérieur d''Ingénierie de Formation (ISIF)'),
('École Supérieure Multinationale des Télécommunications (ESMT)'),
('École Supérieure de Technologie et de Management (ESTM Dakar)'),
('Centre de Formation Africain du Sénégal (CEFAS)'),
('Sup de Co Dakar (Groupe École supérieure de commerce de Dakar)'),
('Centre Africain d''Études Supérieures en Gestion (CESAG)'),
('Institut Supérieur d''Informatique (ISI) de Dakar'),
('BATISUP (École Supérieure du Bâtiment)'),
('BBC UNIVERSITY BRITISH BUSINESS COLLEGE (UNIVERSITY)'),
('Institut Interafricain de Formation en Assurance et en Gestion des Entreprises (IFAGE)'),
('Institut Supérieur de Technologies'),
('Institut Supérieur des Arts et Métiers du Numérique'),
('Institut Supérieur des Sciences de la Santé'),
('Institut Supérieur à Filières Multiples'),
('Institut Technique et de commerce-Dakar'),
('Institut Universitaire de l''Entreprise et du Développement'),
('Institut de Formation en Administration des Affaires'),
('IPAIM Thiès'),
('PERFORM - Institut supérieur des métiers de l''automobile et de l''aviation'),
('SUNHITECH3 AFRICA SUP''MANAGEMENT GROUPE'),
('École Supérieure de Journalisme, des Métiers de l''Internet et de la Communication'),
('Institut Supérieur d''Enseignement Professionnel Thiès'),
('ENSUP AFRIQUE -DAKAR'),
('CENTRE D''ÉTUDE DES SCIENCES ET TECHNIQUES DE L''INFORMATION'),
('Ipd Thomas Sankara institut polytechnique de Dakar'),
('Collège sacre cœur');

-- Study Fields
INSERT IGNORE INTO study_fields (name) VALUES
('Analyse et Politique Écolast_nameique'),
('Anglais'),
('Arts Plastiques'),
('Agrolast_nameie / Agriculture'),
('Biologie / Sciences de la Vie et de la Terre (SVT)'),
('Biologie Médicale'),
('Chimie'),
('Chirurgie Dentaire / Odontostomatologie'),
('Commerce International'),
('Comptabilité, Contrôle, Audit (CCA)'),
('Droit des Affaires'),
('Droit International'),
('Droit Notarial'),
('Droit Public'),
('Écolast_nameie et Gestion des Entreprises'),
('Finance, Banque, Assurance'),
('Formation des Enseignants'),
('Génie Civil'),
('Génie Électromécanique'),
('Génie Industriel'),
('Géographie'),
('Gestion des Ressources Humaines (GRH)'),
('Histoire'),
('Informatique / Génie Logiciel'),
('Journalisme & Communication'),
('Langues Étrangères Appliquées (LEA)'),
('Lettres Modernes'),
('Logistique & Transport'),
('Marketing & Communication'),
('Mathématiques'),
('Médecine'),
('Musicologie'),
('Pharmacie'),
('Physique'),
('Psychologie'),
('Réseaux & Télécommunications'),
('Sage-femme'),
('Sciences de l''Éducation'),
('Sciences Infirmières / Soins Infirmiers'),
('Sciences Politiques'),
('Sociologie');

-- Study Levels
INSERT IGNORE INTO study_levels (name) VALUES
('Seconde'),
('Première'),
('Terminale'),
('Licence 1 (L1)'),
('Licence 2 (L2)'),
('Licence 3 (L3)'),
('Master 1 (M1)'),
('Master 2 (M2)'),
('Doctorat'),
('BTS 1 (Brevet de Technicien Supérieur)'),
('BTS 2 (Brevet de Technicien Supérieur)'),
('DUT (Diplôme Universitaire de Technologie)');

-- Locations
INSERT IGNORE INTO locations (name, region) VALUES
('Le Plateau', 'Dakar'),
('Ngor', 'Dakar'),
('Point E', 'Dakar'),
('Fann Résidence', 'Dakar'),
('Mermoz', 'Dakar'),
('Sacré-Cœur', 'Dakar'),
('Mamelles', 'Dakar'),
('Almadies', 'Dakar'),
('Médina', 'Dakar'),
('Yoff', 'Dakar'),
('Ouakam', 'Dakar'),
('Liberté', 'Dakar'),
('Grand Dakar', 'Dakar'),
('Pikine', 'Dakar'),
('Guédiawaye', 'Dakar'),
('Grand Yoff', 'Dakar'),
('Île de Gorée', 'Dakar'),
('Touba', 'Autres Villes'),
('Tivaouane', 'Autres Villes'),
('Saly-Portudal', 'Autres Villes'),
('Saint-Louis', 'Autres Villes'),
('Thiès', 'Autres Villes'),
('Kaolack', 'Autres Villes'),
('Ziguinchor', 'Autres Villes'),
('Bakel', 'Autres Villes'),
('Bargny', 'Autres Villes'),
('Bignona', 'Autres Villes'),
('Cap-Skirring', 'Autres Villes'),
('Dagana', 'Autres Villes'),
('Diourbel', 'Autres Villes'),
('Elinkine', 'Autres Villes'),
('Fatick', 'Autres Villes'),
('Guédé', 'Autres Villes'),
('Joal-Fadiouth', 'Autres Villes'),
('Kaffrine', 'Autres Villes'),
('Kafountine', 'Autres Villes'),
('Kébémer', 'Autres Villes'),
('Kédougou', 'Autres Villes'),
('Kolda', 'Autres Villes'),
('Louga', 'Autres Villes'),
('Matam', 'Autres Villes'),
('Mbacké', 'Autres Villes'),
('Mbour', 'Autres Villes'),
('Podor', 'Autres Villes'),
('Popenguine', 'Autres Villes'),
('Richard Toll', 'Autres Villes'),
('Rosso', 'Autres Villes'),
('Rufisque', 'Autres Villes'),
('Sédhiou', 'Autres Villes'),
('Somone', 'Autres Villes'),
('Tambacounda', 'Autres Villes'),
('Toubab Dialaw', 'Autres Villes'),
('Palmarin', 'Autres Villes'),
('Diamniadio', 'Autres Villes');

SET FOREIGN_KEY_CHECKS = 1;
