-- Structure de la base de données
CREATE TABLE `personnes` (
  `id_personne` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `sexe` enum('Masculin','Féminin') NOT NULL,
  `age` int(11) DEFAULT NULL,
  `date_naissance` date NOT NULL,
  `lieu_residence` varchar(150) NOT NULL,
  `etablissement` varchar(200) NOT NULL,
  `statut` enum('Élève','Étudiant','Stagiaire') NOT NULL,
  `domaine_etudes` varchar(200) NOT NULL,
  `niveau_etudes` varchar(100) NOT NULL,
  `telephone` varchar(20) NOT NULL UNIQUE,
  `email` varchar(100) NOT NULL UNIQUE,
  `annee_arrivee` int(11) DEFAULT NULL,
  `type_logement` enum('En famille','En colocation','En résidence universitaire','Autre') NOT NULL,
  `precision_logement` varchar(100) DEFAULT NULL,
  `projet_apres_formation` text DEFAULT NULL,
  `date_enregistrement` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id_personne`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `users` (
  `id_user` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('admin','utilisateur') NOT NULL DEFAULT 'utilisateur',
  `est_actif` tinyint(1) NOT NULL DEFAULT 1,
  `derniere_connexion` datetime DEFAULT NULL,
  `date_creation` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id_user`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;