-- phpMyAdmin SQL Dump
-- version 5.0.2
-- https://www.phpmyadmin.net/
--
-- Hôte : localhost
-- Généré le : lun. 15 mars 2021 à 15:21
-- Version du serveur :  10.3.22-MariaDB-0+deb10u1
-- Version de PHP : 7.3.19-1~deb10u1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `frequentation_portail`
--
-- CREATE DATABASE IF NOT EXISTS `frequentation_portail` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
-- USE `frequentation_portail`;

-- SET foreign_key_checks = 0;
-- DROP TABLE `etablissements`, `mois`, `services`, `stats_etabs`, `stats_services`, `types`;

-- --------------------------------------------------------

--
-- Structure de la table `types`
--

CREATE TABLE IF NOT EXISTS `types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(32) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Structure de la table `etablissements`
--

CREATE TABLE IF NOT EXISTS `etablissements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_type` int(11) NOT NULL,
  `nom` varchar(255) NOT NULL,
  `departement` int(11) NOT NULL,
  `siren` varchar(15) NOT NULL,
  `uai` varchar(8),
  PRIMARY KEY (`id`),
  FOREIGN KEY (`id_type`) REFERENCES `types` (`id`),
  CONSTRAINT UQ_etab UNIQUE (siren, nom, departement, id_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Structure de la table `services`
--

CREATE TABLE IF NOT EXISTS `services` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Structure de la table `mois`
--

CREATE TABLE IF NOT EXISTS `mois` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mois` int(11) NOT NULL,
  `annee` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Structure de la table `stats_services`
--

CREATE TABLE IF NOT EXISTS `stats_services` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_mois` int(11) NOT NULL,
  `id_etablissement` int(11) NOT NULL,
  `id_service` int(11) NOT NULL,
  -- Section Parents
  `parent__au_plus_quatre_fois` int(11) NOT NULL DEFAULT 0,
  `parent__au_moins_cinq_fois` int(11) NOT NULL DEFAULT 0,
  `parent__total_sessions` int(11) NOT NULL DEFAULT 0,
  `parent__differents_users` int(11) NOT NULL DEFAULT 0,
  -- Section Eleves
  `eleve__au_plus_quatre_fois` int(11) NOT NULL DEFAULT 0,
  `eleve__au_moins_cinq_fois` int(11) NOT NULL DEFAULT 0,
  `eleve__total_sessions` int(11) NOT NULL DEFAULT 0,
  `eleve__differents_users` int(11) NOT NULL DEFAULT 0,
  -- Section Enseignant
  `enseignant__au_plus_quatre_fois` int(11) NOT NULL DEFAULT 0,
  `enseignant__au_moins_cinq_fois` int(11) NOT NULL DEFAULT 0,
  `enseignant__total_sessions` int(11) NOT NULL DEFAULT 0,
  `enseignant__differents_users` int(11) NOT NULL DEFAULT 0,
  -- Section Personnel d'établissement non enseignant
  `perso_etab_non_ens__au_plus_quatre_fois` int(11) NOT NULL DEFAULT 0,
  `perso_etab_non_ens__au_moins_cinq_fois` int(11) NOT NULL DEFAULT 0,
  `perso_etab_non_ens__total_sessions` int(11) NOT NULL DEFAULT 0,
  `perso_etab_non_ens__differents_users` int(11) NOT NULL DEFAULT 0,
  -- Section Personnel de collectivité
  `perso_collec__au_plus_quatre_fois` int(11) NOT NULL DEFAULT 0,
  `perso_collec__au_moins_cinq_fois` int(11) NOT NULL DEFAULT 0,
  `perso_collec__total_sessions` int(11) NOT NULL DEFAULT 0,
  `perso_collec__differents_users` int(11) NOT NULL DEFAULT 0,
  -- Section Tuteur de stage
  `tuteur_stage__au_plus_quatre_fois` int(11) NOT NULL DEFAULT 0,
  `tuteur_stage__au_moins_cinq_fois` int(11) NOT NULL DEFAULT 0,
  `tuteur_stage__total_sessions` int(11) NOT NULL DEFAULT 0,
  `tuteur_stage__differents_users` int(11) NOT NULL DEFAULT 0,
  -- Section global
  `au_plus_quatre_fois` int(11) NOT NULL DEFAULT 0,
  `au_moins_cinq_fois` int(11) NOT NULL DEFAULT 0,
  `total_sessions` int(11) NOT NULL DEFAULT 0,
  `differents_users` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`id_mois`) REFERENCES `mois` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`id_etablissement`) REFERENCES `etablissements` (`id`),
  FOREIGN KEY (`id_service`) REFERENCES `services` (`id`),
  CONSTRAINT UQ_service_rec UNIQUE (id_mois, id_etablissement, id_service)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Structure de la table `stats_etab`
--

CREATE TABLE IF NOT EXISTS `stats_etabs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_mois` int(11) NOT NULL,
  `id_etablissement` int(11) NOT NULL,
  -- Section Parents
  `parent__total_pers` int(11) NOT NULL DEFAULT 0,
  `parent__total_pers_actives` int(11) NOT NULL DEFAULT 0,
  `parent__au_plus_quatre_fois` int(11) NOT NULL DEFAULT 0,
  `parent__au_moins_cinq_fois` int(11) NOT NULL DEFAULT 0,
  `parent__total_sessions` int(11) NOT NULL DEFAULT 0,
  `parent__differents_users` int(11) NOT NULL DEFAULT 0,
  `parent__tps_moyen_minutes` int(11) NOT NULL DEFAULT 0,
  -- Section Eleves
  `eleve__total_pers` int(11) NOT NULL DEFAULT 0,
  `eleve__total_pers_actives` int(11) NOT NULL DEFAULT 0,
  `eleve__au_plus_quatre_fois` int(11) NOT NULL DEFAULT 0,
  `eleve__au_moins_cinq_fois` int(11) NOT NULL DEFAULT 0,
  `eleve__total_sessions` int(11) NOT NULL DEFAULT 0,
  `eleve__differents_users` int(11) NOT NULL DEFAULT 0,
  `eleve__tps_moyen_minutes` int(11) NOT NULL DEFAULT 0,
  -- Section Enseignant
  `enseignant__total_pers` int(11) NOT NULL DEFAULT 0,
  `enseignant__total_pers_actives` int(11) NOT NULL DEFAULT 0,
  `enseignant__au_plus_quatre_fois` int(11) NOT NULL DEFAULT 0,
  `enseignant__au_moins_cinq_fois` int(11) NOT NULL DEFAULT 0,
  `enseignant__total_sessions` int(11) NOT NULL DEFAULT 0,
  `enseignant__differents_users` int(11) NOT NULL DEFAULT 0,
  `enseignant__tps_moyen_minutes` int(11) NOT NULL DEFAULT 0,
  -- Section Personnel d'établissement non enseignant
  `perso_etab_non_ens__total_pers` int(11) NOT NULL DEFAULT 0,
  `perso_etab_non_ens__total_pers_actives` int(11) NOT NULL DEFAULT 0,
  `perso_etab_non_ens__au_plus_quatre_fois` int(11) NOT NULL DEFAULT 0,
  `perso_etab_non_ens__au_moins_cinq_fois` int(11) NOT NULL DEFAULT 0,
  `perso_etab_non_ens__total_sessions` int(11) NOT NULL DEFAULT 0,
  `perso_etab_non_ens__differents_users` int(11) NOT NULL DEFAULT 0,
  `perso_etab_non_ens__tps_moyen_minutes` int(11) NOT NULL DEFAULT 0,
  -- Section Personnel de collectivité
  `perso_collec__total_pers` int(11) NOT NULL DEFAULT 0,
  `perso_collec__total_pers_actives` int(11) NOT NULL DEFAULT 0,
  `perso_collec__au_plus_quatre_fois` int(11) NOT NULL DEFAULT 0,
  `perso_collec__au_moins_cinq_fois` int(11) NOT NULL DEFAULT 0,
  `perso_collec__total_sessions` int(11) NOT NULL DEFAULT 0,
  `perso_collec__differents_users` int(11) NOT NULL DEFAULT 0,
  `perso_collec__tps_moyen_minutes` int(11) NOT NULL DEFAULT 0,
  -- Section Tuteur de stage
  `tuteur_stage__total_pers` int(11) NOT NULL DEFAULT 0,
  `tuteur_stage__total_pers_actives` int(11) NOT NULL DEFAULT 0,
  `tuteur_stage__au_plus_quatre_fois` int(11) NOT NULL DEFAULT 0,
  `tuteur_stage__au_moins_cinq_fois` int(11) NOT NULL DEFAULT 0,
  `tuteur_stage__total_sessions` int(11) NOT NULL DEFAULT 0,
  `tuteur_stage__differents_users` int(11) NOT NULL DEFAULT 0,
  `tuteur_stage__tps_moyen_minutes` int(11) NOT NULL DEFAULT 0,
  -- Section global
  `total_pers_actives` int(11) NOT NULL DEFAULT 0,
  `au_plus_quatre_fois` int(11) NOT NULL DEFAULT 0,
  `au_moins_cinq_fois` int(11) NOT NULL DEFAULT 0,
  `total_sessions` int(11) NOT NULL DEFAULT 0,
  `differents_users` int(11) NOT NULL DEFAULT 0,
  `tps_moyen_minutes` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`id_mois`) REFERENCES `mois` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`id_etablissement`) REFERENCES `etablissements` (`id`),
  CONSTRAINT UQ_etab_rec UNIQUE (id_mois, id_etablissement)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
