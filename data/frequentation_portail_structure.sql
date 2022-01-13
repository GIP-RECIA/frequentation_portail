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

-- --------------------------------------------------------

--
-- Structure de la table `etablissements`
--

CREATE TABLE IF NOT EXISTS `etablissements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(4096) NOT NULL,
  `departement` int(11) NOT NULL,
  `siren` bigint(50) NOT NULL,
  `type` varchar(2048) NOT NULL,
  `total_personnes` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Structure de la table `services`
--

CREATE TABLE IF NOT EXISTS `services` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(4096) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Structure de la table `stats`
--

CREATE TABLE IF NOT EXISTS `stats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `jour` date DEFAULT '0000-00-00',
  `mois` int(11) NOT NULL,
  `annee` int(11) NOT NULL,
  `id_lycee` int(11) NOT NULL DEFAULT 0,
  `id_service` int(11) NOT NULL DEFAULT 0,
  `au_plus_quatre_fois` int(11) NOT NULL DEFAULT 0,
  `au_moins_cinq_fois` int(11) NOT NULL DEFAULT 0,
  `nb_visiteurs` int(11) NOT NULL DEFAULT 0,
  `total_visites` int(11) NOT NULL DEFAULT 0,
  `parent` int(11) NOT NULL DEFAULT 0,
  `eleve` int(11) NOT NULL DEFAULT 0,
  `enseignant` int(11) NOT NULL DEFAULT 0,
  `personnel_etablissement_non_enseignant` int(11) NOT NULL DEFAULT 0,
  `personnel_collectivite` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `id_service` (`id_service`),
  KEY `jour` (`jour`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Structure de la table `stats_etab`
--

CREATE TABLE IF NOT EXISTS `stats_etab` (
  `id_lycee` int(11) NOT NULL DEFAULT 0,
  `total_eleve` bigint(13) DEFAULT NULL,
  `total_enseignant` bigint(13) DEFAULT NULL,
  `total_personnel_etablissement_non_enseignant` bigint(13) DEFAULT NULL,
  `total_personnel_collectivite` bigint(13) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Structure de la table `stats_etab_mois`
--

CREATE TABLE IF NOT EXISTS `stats_etab_mois` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `jour` date DEFAULT '0000-00-00',
  `mois` int(11) NOT NULL,
  `annee` int(11) NOT NULL,
  `id_lycee` int(11) NOT NULL DEFAULT 0,
  `au_plus_quatre_fois` int(11) NOT NULL DEFAULT 0,
  `au_moins_cinq_fois` int(11) NOT NULL DEFAULT 0,
  `nb_visiteurs` int(11) NOT NULL DEFAULT 0,
  `total_visites` int(11) NOT NULL DEFAULT 0,
  `parent` int(11) NOT NULL DEFAULT 0,
  `eleve` int(11) NOT NULL DEFAULT 0,
  `enseignant` int(11) NOT NULL DEFAULT 0,
  `personnel_etablissement_non_enseignant` int(11) NOT NULL DEFAULT 0,
  `personnel_collectivite` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `jour` (`jour`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
