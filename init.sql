-- Configuration initiale de la base de données
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
SET COLLATION_CONNECTION = utf8mb4_unicode_ci;

-- La commande DROP DATABASE a été supprimée pour éviter la perte de données en production.
-- Si vous avez besoin de recréer la base de données, vous pouvez exécuter cette commande manuellement.
-- DROP DATABASE IF EXISTS `ameadb`;

-- Création et sélection de la base de données
CREATE DATABASE IF NOT EXISTS `ameadb`
    DEFAULT CHARACTER SET utf8mb4
    DEFAULT COLLATE utf8mb4_unicode_ci;
USE `ameadb`;
