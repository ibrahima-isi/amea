-- Configuration initiale de la base de données
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
SET COLLATION_CONNECTION = utf8mb4_unicode_ci;

-- Création et sélection de la base de données
DROP DATABASE IF EXISTS `ameadb`;
CREATE DATABASE IF NOT EXISTS `ameadb`
    DEFAULT CHARACTER SET utf8mb4
    DEFAULT COLLATE utf8mb4_unicode_ci;
USE `ameadb`;
