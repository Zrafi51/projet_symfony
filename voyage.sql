-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 26, 2026 at 07:18 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `voyage`
--

-- --------------------------------------------------------

--
-- Table structure for table `activites`
--

CREATE TABLE `activites` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `destination_id` int(11) DEFAULT NULL,
  `categorie` varchar(50) DEFAULT NULL,
  `prix` decimal(10,2) DEFAULT NULL,
  `duree_heures` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activites`
--

INSERT INTO `activites` (`id`, `nom`, `destination_id`, `categorie`, `prix`, `duree_heures`, `description`) VALUES
(2, 'Plongée sous-marine', 1, 'Sport', 150.00, 3, 'Exploration des récifs'),
(3, 'Boutiques Luxe à Shanghai', 5, 'Shopping', 0.00, 1, 'Boutiques Luxe - Shopping'),
(4, 'Dîner d\'Affaires à Shanghai', 5, 'Business', 276.69, 3, 'Dîner d\'Affaires - Business'),
(5, 'Salle Réunion Équipée à Shanghai', 5, 'Business', 62.42, 3, 'Salle Réunion Équipée - Business'),
(6, 'Marché Artisanal Souvenirs à Shanghai', 5, 'Shopping', 27.73, 3, 'Marché Artisanal Souvenirs - Shopping'),
(7, 'Dîner d\'Affaires à Shanghai', 5, 'Business', 225.44, 4, 'Dîner d\'Affaires - Business'),
(8, 'Marché Local Guidé à Shanghai', 5, 'Gastronomie', 35.02, 2, 'Marché Local Guidé - Gastronomie'),
(9, 'Salle Réunion Équipée à Shanghai', 5, 'Business', 81.21, 1, 'Salle Réunion Équipée - Business'),
(10, 'Marché Artisanal Souvenirs à Shanghai', 5, 'Shopping', 20.23, 3, 'Marché Artisanal Souvenirs - Shopping'),
(11, 'Spectacle / Théâtre à Berlin', 6, 'Urbain', 94.91, 2, 'Spectacle / Théâtre - Urbain'),
(12, 'Visite Cathédrale à Berlin', 6, 'Culture', 32.68, 4, 'Visite Cathédrale - Culture'),
(13, 'Spectacle / Théâtre à Berlin', 6, 'Urbain', 90.88, 4, 'Spectacle / Théâtre - Urbain'),
(14, 'Visite Guidée Centre Historique à Berlin', 6, 'Culture', 20.85, 1, 'Visite Guidée Centre Historique - Culture'),
(15, 'Musée d\'Art à Berlin', 6, 'Culture', 18.30, 3, 'Musée d\'Art - Culture'),
(16, 'Galerie Moderne à Berlin', 6, 'Culture', 24.93, 1, 'Galerie Moderne - Culture'),
(17, 'Galerie Moderne à Berlin', 6, 'Culture', 30.13, 3, 'Galerie Moderne - Culture'),
(18, 'Exposition à Berlin', 6, 'Culture', 40.33, 2, 'Exposition - Culture'),
(19, 'Exposition à Berlin', 6, 'Culture', 66.73, 1, 'Exposition - Culture'),
(20, 'Atelier Artisanal à Berlin', 6, 'Culture', 23.08, 2, 'Atelier Artisanal - Culture'),
(21, 'Street Art Tour à Berlin', 6, 'Urbain', 27.20, 4, 'Street Art Tour - Urbain'),
(22, 'Centre Commercial à Berlin', 6, 'Shopping', 43.37, 1, 'Centre Commercial - Shopping'),
(23, 'Bar à Tapas à Berlin', 6, 'Gastronomie', 88.55, 3, 'Bar à Tapas - Gastronomie'),
(24, 'Tour de Ville à Berlin', 6, 'Urbain', 49.55, 4, 'Tour de Ville - Urbain'),
(25, 'Via Ferrata à Berlin', 6, 'Montagne', 196.10, 3, 'Via Ferrata - Montagne'),
(26, 'Marché aux Puces à Berlin', 6, 'Shopping', 61.49, 3, 'Marché aux Puces - Shopping'),
(27, 'Bar à Tapas à Berlin', 6, 'Gastronomie', 120.02, 4, 'Bar à Tapas - Gastronomie'),
(28, 'Séminaire à Berlin', 6, 'Business', 171.06, 2, 'Séminaire - Business'),
(29, 'Restaurant Local à Berlin', 6, 'Gastronomie', 70.19, 4, 'Restaurant Local - Gastronomie'),
(30, 'Palais Royal à Toronto', 7, 'Culture', 48.80, 2, 'Palais Royal - Culture'),
(31, 'Musée Archéologique à Toronto', 7, 'Culture', 35.55, 3, 'Musée Archéologique - Culture'),
(32, 'Tour Bus Panoramique à Toronto', 7, 'Urbain', 58.64, 3, 'Tour Bus Panoramique - Urbain'),
(33, 'Brunch Rooftop à Toronto', 7, 'Gastronomie', 119.15, 3, 'Brunch Rooftop - Gastronomie'),
(34, 'Cours de Cuisine à Toronto', 7, 'Gastronomie', 158.76, 2, 'Cours de Cuisine - Gastronomie'),
(35, 'Rooftop Bar Vue Ville à Toronto', 7, 'Urbain', 86.05, 3, 'Rooftop Bar Vue Ville - Urbain'),
(36, 'Montée Tour Observation à Toronto', 7, 'Urbain', 76.75, 2, 'Montée Tour Observation - Urbain'),
(37, 'Jardin Botanique à Algiers', 8, 'Nature', 23.81, 1, 'Jardin Botanique - Nature'),
(38, 'Forêt à Algiers', 8, 'Nature', 34.01, 1, 'Forêt - Nature'),
(39, 'Cascade à Algiers', 8, 'Nature', 23.22, 3, 'Cascade - Nature'),
(40, 'Parc National à Algiers', 8, 'Nature', 48.43, 2, 'Parc National - Nature'),
(41, 'Cascade à Algiers', 8, 'Nature', 20.16, 4, 'Cascade - Nature'),
(42, 'Randonnée à Algiers', 8, 'Nature', 28.98, 1, 'Randonnée - Nature'),
(43, 'Randonnée à Algiers', 8, 'Nature', 36.79, 1, 'Randonnée - Nature'),
(45, 'Tour Bus Panoramique à Rio de Janeiro', 9, 'Urbain', 41.63, 1, 'Tour Bus Panoramique - Urbain'),
(46, 'Rooftop Bar Vue Ville à Rio de Janeiro', 9, 'Urbain', 83.26, 4, 'Rooftop Bar Vue Ville - Urbain'),
(47, 'Cours de Cuisine à Rio de Janeiro', 9, 'Gastronomie', 176.32, 1, 'Cours de Cuisine - Gastronomie'),
(48, 'Musée National à Rio de Janeiro', 9, 'Culture', 39.24, 1, 'Musée National - Culture'),
(49, 'Tyrolienne à Casablanca', 10, 'Aventure', 271.11, 1, 'Tyrolienne - Aventure'),
(50, 'Escalade à Casablanca', 10, 'Aventure', 120.88, 4, 'Escalade - Aventure'),
(51, 'Escalade à Casablanca', 10, 'Aventure', 225.29, 1, 'Escalade - Aventure'),
(52, 'Bungee à Casablanca', 10, 'Aventure', 212.63, 4, 'Bungee - Aventure'),
(53, 'Bungee à Casablanca', 10, 'Aventure', 272.25, 4, 'Bungee - Aventure'),
(54, 'Tyrolienne à Casablanca', 10, 'Aventure', 281.42, 4, 'Tyrolienne - Aventure'),
(55, 'Canyoning à Casablanca', 10, 'Aventure', 276.80, 3, 'Canyoning - Aventure'),
(56, 'Escalade à Casablanca', 10, 'Aventure', 116.38, 1, 'Escalade - Aventure'),
(57, 'Quad à Casablanca', 10, 'Aventure', 284.45, 4, 'Quad - Aventure'),
(58, 'Escalade à Casablanca', 10, 'Aventure', 101.98, 4, 'Escalade - Aventure'),
(59, 'Via Ferrata à Casablanca', 10, 'Aventure', 281.08, 3, 'Via Ferrata - Aventure'),
(60, 'Plage Privée à Casablanca', 10, 'Plage', 173.51, 1, 'Plage Privée - Plage'),
(61, 'Randonnée Alpine à Casablanca', 10, 'Montagne', 185.04, 1, 'Randonnée Alpine - Montagne'),
(62, 'Refuge Montagne à Casablanca', 10, 'Montagne', 240.55, 1, 'Refuge Montagne - Montagne'),
(63, 'Escalade à Casablanca', 10, 'Montagne', 128.23, 4, 'Escalade - Montagne'),
(64, 'Kayak Mer à Casablanca', 10, 'Plage', 115.27, 2, 'Kayak Mer - Plage'),
(65, 'Surf à Casablanca', 10, 'Plage', 145.05, 4, 'Surf - Plage'),
(66, 'Beach Volley à Casablanca', 10, 'Plage', 71.50, 1, 'Beach Volley - Plage'),
(67, 'Beach Volley à Casablanca', 10, 'Plage', 164.15, 4, 'Beach Volley - Plage'),
(68, 'Sports Nautiques à Casablanca', 10, 'Plage', 172.64, 2, 'Sports Nautiques - Plage'),
(69, 'Ski à Casablanca', 10, 'Montagne', 190.91, 1, 'Ski - Montagne'),
(70, 'Station Ski à Casablanca', 10, 'Montagne', 64.10, 3, 'Station Ski - Montagne'),
(71, 'Balade Nocturne Illuminations à Casablanca', 10, 'Urbain', 0.00, 2, 'Balade Nocturne Illuminations - Urbain'),
(72, 'Quartier Branché à Casablanca', 10, 'Urbain', 34.38, 3, 'Quartier Branché - Urbain'),
(73, 'Centre Commercial à Casablanca', 10, 'Shopping', 0.72, 1, 'Centre Commercial - Shopping'),
(74, 'Visite Quartier Branché à Casablanca', 10, 'Urbain', 0.00, 3, 'Visite Quartier Branché - Urbain'),
(75, 'Jardin Botanique à Algiers', 8, 'Nature', 23.81, 1, 'Jardin Botanique - Nature'),
(76, 'Forêt à Algiers', 8, 'Nature', 34.01, 1, 'Forêt - Nature'),
(77, 'Cascade à Algiers', 8, 'Nature', 23.22, 3, 'Cascade - Nature'),
(78, 'Parc National à Algiers', 8, 'Nature', 48.43, 2, 'Parc National - Nature'),
(79, 'Cascade à Algiers', 8, 'Nature', 20.16, 4, 'Cascade - Nature'),
(80, 'Randonnée à Algiers', 8, 'Nature', 28.98, 1, 'Randonnée - Nature'),
(81, 'Randonnée à Algiers', 8, 'Nature', 36.79, 1, 'Randonnée - Nature'),
(82, 'Lac à Algiers', 8, 'Nature', 11.37, 4, 'Lac - Nature');

-- --------------------------------------------------------

--
-- Table structure for table `admin_profile_preferences`
--

CREATE TABLE `admin_profile_preferences` (
  `id` int(11) NOT NULL,
  `user_email` varchar(100) NOT NULL,
  `job_title` varchar(100) NOT NULL DEFAULT 'Super Admin',
  `company` varchar(100) NOT NULL DEFAULT 'EasyTravel',
  `bio` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_profile_preferences`
--

INSERT INTO `admin_profile_preferences` (`id`, `user_email`, `job_title`, `company`, `bio`, `created_at`, `updated_at`) VALUES
(1, 'zrafimehdi5@gmail.com', 'Super Admin', 'EasyTravel', 'Admin principal de la console EasyTravel. Gestion des operations, du support et des contenus premium.', '2026-04-13 15:55:39', '2026-04-13 15:55:39'),
(4, 'zrafimehdi@gmail.com', 'Super Admin', 'EasyTravel', 'Admin principal de la console EasyTravel. Gestion des operations, du support et des contenus premium.', '2026-04-26 16:33:04', '2026-04-26 16:33:04');

-- --------------------------------------------------------

--
-- Table structure for table `atmosphere_destinations`
--

CREATE TABLE `atmosphere_destinations` (
  `id` int(11) NOT NULL,
  `atmosphere_type` varchar(50) DEFAULT NULL,
  `title` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `video_path` varchar(255) DEFAULT NULL,
  `ai_interest_tags` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `display_order` int(11) DEFAULT NULL,
  `created_by_admin` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ai_suggested_destinations` text DEFAULT NULL,
  `ai_suggested_countries` text DEFAULT NULL,
  `ai_suggested_continents` text DEFAULT NULL,
  `ai_score` decimal(5,2) DEFAULT 0.00,
  `avg_price` decimal(10,2) DEFAULT 0.00,
  `updated_from_ai_at` timestamp NULL DEFAULT NULL,
  `ai_featured_payload` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `atmosphere_destinations`
--

INSERT INTO `atmosphere_destinations` (`id`, `atmosphere_type`, `title`, `description`, `video_path`, `ai_interest_tags`, `is_active`, `display_order`, `created_by_admin`, `created_at`, `ai_suggested_destinations`, `ai_suggested_countries`, `ai_suggested_continents`, `ai_score`, `avg_price`, `updated_from_ai_at`, `ai_featured_payload`) VALUES
(1, 'SAFARI', 'SAFARI', 'Ambiance aventure et nature inspiree par Bali, Addis Ababa, Islande.', 'Safari in Africa.mp4', 'aventure,nature,safari', 1, 1, 0, '2026-03-31 02:57:39', NULL, NULL, NULL, 0.00, 0.00, NULL, NULL),
(2, 'URBAIN', 'URBAIN', 'Ambiance urbaine inspiree par Tokyo, Paris, New York.', 'Sky2Tours.mp4', 'culture,shopping,city', 1, 2, 0, '2026-03-31 02:57:39', NULL, NULL, NULL, 0.00, 0.00, NULL, NULL),
(3, 'PLAGE', 'PLAGE', 'Ambiance balneaire inspiree par Bali, Maldives, Santorini.', 'Ney Pereira.mp4', 'plage,detente,luxe', 1, 3, 0, '2026-03-31 02:57:39', NULL, NULL, NULL, 0.00, 0.00, NULL, NULL),
(4, 'MONTAGNE', 'MONTAGNE', 'Ambiance grand air inspiree par Bali, Islande, Kenya.', 'Anna M..mp4', 'aventure,nature,montagne', 1, 4, 0, '2026-03-31 02:57:39', NULL, NULL, NULL, 0.00, 0.00, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `comments`
--

CREATE TABLE `comments` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `auteur` varchar(100) NOT NULL,
  `contenu` text NOT NULL,
  `date_commentaire` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `destinations`
--

CREATE TABLE `destinations` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `pays` varchar(100) DEFAULT NULL,
  `continent` varchar(50) DEFAULT NULL,
  `prix_base` decimal(10,2) DEFAULT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `destinations`
--

INSERT INTO `destinations` (`id`, `nom`, `pays`, `continent`, `prix_base`, `description`) VALUES
(1, 'Tunisie', NULL, NULL, NULL, 'Destination test'),
(2, 'Bali', 'Indonésie', 'Asie', 2758.85, 'Destination recommandée par IA'),
(3, 'Tirana', 'Albanie', 'Europe', 1276.85, 'Recommandation IA'),
(4, 'Addis Ababa', 'Éthiopie', 'Afrique', 23520.60, 'Recommandation IA'),
(5, 'Shanghai', 'Chine', 'Asie', 11498.22, 'Recommandation IA'),
(6, 'Berlin', 'Allemagne', 'Europe', 22052.40, 'Recommandation IA'),
(7, 'Toronto', 'Canada', 'Amerique', 6786.56, 'Recommandation IA'),
(8, 'Algiers', 'Algérie', 'Afrique', 6139.84, 'Recommandation IA'),
(9, 'Rio de Janeiro', 'Brésil', 'Amerique', 1917.28, 'Recommandation IA'),
(10, 'Casablanca', 'Maroc', 'Afrique', 20676.00, 'Recommandation IA');

-- --------------------------------------------------------

--
-- Table structure for table `factures`
--

CREATE TABLE `factures` (
  `id` int(11) NOT NULL,
  `numero_facture` varchar(50) NOT NULL,
  `date_emission` date NOT NULL,
  `client_nom` varchar(100) NOT NULL,
  `client_email` varchar(100) DEFAULT NULL,
  `client_adresse` varchar(255) DEFAULT NULL,
  `destination` varchar(200) NOT NULL,
  `montant_transport` decimal(10,2) DEFAULT 0.00,
  `montant_hebergement` decimal(10,2) DEFAULT 0.00,
  `montant_activites` decimal(10,2) DEFAULT 0.00,
  `montant_total` decimal(10,2) NOT NULL,
  `statut` varchar(20) NOT NULL DEFAULT 'EMISE',
  `paiement_id` int(11) DEFAULT 0,
  `type_voyage` varchar(50) DEFAULT NULL,
  `nb_personnes` int(11) DEFAULT 1,
  `date_debut` varchar(20) DEFAULT NULL,
  `date_fin` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `factures`
--

INSERT INTO `factures` (`id`, `numero_facture`, `date_emission`, `client_nom`, `client_email`, `client_adresse`, `destination`, `montant_transport`, `montant_hebergement`, `montant_activites`, `montant_total`, `statut`, `paiement_id`, `type_voyage`, `nb_personnes`, `date_debut`, `date_fin`) VALUES
(1, 'FAC-2026-1774924263289', '2026-03-31', 'yassmine', 'yassmine@gmail.com', 'kairouan', 'paris france', 600.00, 5000.00, 3000.00, 8600.00, 'ENVOYEE', 0, NULL, 5, '01/02/2025', '15/02/2025'),
(2, 'FAC-2026-1774960474343', '2026-03-31', 'yassmine', 'yassmine@gmail.com', 'kairouan', 'italy', 800.00, 3000.00, 500.00, 4300.00, 'ENVOYEE', 0, NULL, 7, '02/05/2024', '30/05/2024'),
(3, 'FAC-2026-1775077126966', '2026-04-01', 'yassmine', 'yassmine@gmail.com', 'monsair', 'france', 45000.00, 3000.00, 7000.00, 55000.00, 'ENVOYEE', 0, NULL, 5, '02/02/2026', '30/02/2026'),
(4, 'FAC-2026-1775077139310', '2026-04-01', 'yassmine', 'yassmine@gmail.com', 'monsair', 'france', 45000.00, 3000.00, 7000.00, 55000.00, 'GENEREE', 0, NULL, 5, '02/02/2026', '30/02/2026'),
(5, 'FAC-2026-1775142240337', '2026-04-02', 'wassim', 'wassim@gmail.com', 'run11', 'france', 55000.00, 6000.00, 300.00, 0.00, 'ENVOYEE', 0, NULL, 5, '04/12/2026', '30/12/2026'),
(6, 'FAC-2026-20260416132352-97', '2026-04-16', 'yassmine aaaa', 'yassmine@gmail.com', 'rue121', 'Rio de Janeiro, Brésil', 766.91, 862.78, 287.59, 1917.28, 'ENVOYEE', 2, '', 1, '01/02/2024', '08/02/2024');

-- --------------------------------------------------------

--
-- Table structure for table `favorite_packages`
--

CREATE TABLE `favorite_packages` (
  `id` int(11) NOT NULL,
  `client_email` varchar(255) NOT NULL,
  `package_key` varchar(255) NOT NULL,
  `package_name` varchar(160) NOT NULL,
  `destination_name` varchar(160) DEFAULT NULL,
  `country` varchar(160) DEFAULT NULL,
  `continent` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `price_amount` decimal(10,2) DEFAULT 0.00,
  `price_label` varchar(80) DEFAULT NULL,
  `duration_days` int(11) DEFAULT 0,
  `duration_label` varchar(80) DEFAULT NULL,
  `travel_type` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `favorite_packages`
--

INSERT INTO `favorite_packages` (`id`, `client_email`, `package_key`, `package_name`, `destination_name`, `country`, `continent`, `description`, `image_path`, `price_amount`, `price_label`, `duration_days`, `duration_label`, `travel_type`, `created_at`) VALUES
(7, 'yassmine@gmail.com', 'delhi, inde|delhi|inde|asie|8|1918|voyage', 'Delhi, Inde', 'Delhi', 'Inde', 'Asie', 'Decouvrez cette destination unique', '/bac4bce325c9a10f6fb77f30682cc7fa.jpg', 1918.00, '1918 €', 8, '8 jours', 'Voyage', '2026-04-01 18:54:59'),
(8, 'zrafimehdi5@gmail.com', 'algiers, algerie|algiers|algerie|afrique|8|6140|voyage', 'Algiers, Algérie', 'Algiers', 'Algérie', 'Afrique', 'Decouvrez cette destination unique', '/b98f59bef70929b9642bc88dd2a56f11.jpg', 6140.00, '6140 €', 8, '8 jours', 'Voyage', '2026-04-01 22:06:18');

-- --------------------------------------------------------

--
-- Table structure for table `featured_destinations`
--

CREATE TABLE `featured_destinations` (
  `id` int(11) NOT NULL,
  `destination_name` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `continent` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `video_path` varchar(255) DEFAULT NULL,
  `ai_score` decimal(5,2) DEFAULT NULL,
  `satisfaction_score` decimal(3,2) DEFAULT NULL,
  `avg_price` decimal(10,2) DEFAULT NULL,
  `best_season` varchar(100) DEFAULT NULL,
  `travel_types` text DEFAULT NULL,
  `interests` text DEFAULT NULL,
  `is_featured` tinyint(1) DEFAULT 1,
  `display_order` int(11) DEFAULT NULL,
  `updated_from_ai_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `featured_destinations`
--

INSERT INTO `featured_destinations` (`id`, `destination_name`, `country`, `continent`, `description`, `video_path`, `ai_score`, `satisfaction_score`, `avg_price`, `best_season`, `travel_types`, `interests`, `is_featured`, `display_order`, `updated_from_ai_at`, `created_at`) VALUES
(8, 'Algiers', 'Algérie', 'Afrique', 'Recommandation IA', 'AlgiersAfrique.mp4', 75.80, 4.20, 6139.84, 'Octobre - Avril', 'couple,famille', 'culture,decouverte', 1, 1, '2026-04-14 04:38:17', '2026-04-14 04:38:17'),
(9, 'Addis Ababa', 'Éthiopie', 'Afrique', 'Recommandation IA', 'AddisAbabaAfrique.mp4', 72.80, 4.10, 23520.60, 'Octobre - Avril', 'couple,famille', 'culture,decouverte', 1, 2, '2026-04-14 04:38:17', '2026-04-14 04:38:17'),
(10, 'Berlin', 'Allemagne', 'Europe', 'Recommandation IA', 'BerlinEurope.mp4', 72.70, 4.10, 22052.40, 'Avril - Octobre', 'couple,famille', 'culture,decouverte', 1, 3, '2026-04-14 04:38:17', '2026-04-14 04:38:17'),
(11, 'Casablanca', 'Maroc', 'Afrique', 'Recommandation IA', 'CasablancaAfrique.mp4', 72.50, 4.10, 20676.00, 'Octobre - Avril', 'couple,famille', 'culture,decouverte', 1, 4, '2026-04-14 04:38:17', '2026-04-14 04:38:17'),
(12, 'Shanghai', 'Chine', 'Asie', 'Recommandation IA', 'ShanghaiAsie.mp4', 71.50, 4.10, 11498.22, 'Novembre - Avril', 'couple,famille', 'culture,decouverte', 1, 5, '2026-04-14 04:38:17', '2026-04-14 04:38:17'),
(13, 'Toronto', 'Canada', 'Amerique', 'Recommandation IA', 'TorontoAmerique.mp4', 71.00, 4.10, 6786.56, 'Mars - Octobre', 'couple,famille', 'citytrip,decouverte', 1, 6, '2026-04-14 04:38:17', '2026-04-14 04:38:17');

-- --------------------------------------------------------

--
-- Table structure for table `featured_destination_history`
--

CREATE TABLE `featured_destination_history` (
  `id` int(11) NOT NULL,
  `featured_destination_id` int(11) DEFAULT 0,
  `action_type` varchar(40) DEFAULT NULL,
  `destination_name` varchar(120) DEFAULT NULL,
  `ai_score` double DEFAULT 0,
  `note` text DEFAULT NULL,
  `created_by_admin` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `featured_destination_history`
--

INSERT INTO `featured_destination_history` (`id`, `featured_destination_id`, `action_type`, `destination_name`, `ai_score`, `note`, `created_by_admin`, `created_at`) VALUES
(1, 0, 'AI_REFRESH', 'Paris', 92, '10 suggestion(s) IA preparee(s) pour la vitrine Home.', 13, '2026-03-31 22:23:25'),
(2, 1, 'MANUAL_UPDATE', 'Addis Ababa', 84.5, 'Destination vedette modifiee manuellement.', 13, '2026-03-31 22:24:07'),
(3, 1, 'MANUAL_UPDATE', 'Addis Ababa', 84.5, 'Destination vedette modifiee manuellement.', 13, '2026-03-31 22:30:02'),
(4, 0, 'AI_REFRESH', 'Berlin', 86.5, '10 suggestion(s) IA preparee(s) pour la vitrine Home.', 13, '2026-04-02 14:27:33'),
(5, 1, 'AI_SELECTED', 'Casablanca', 86.5, 'Selection IA appliquee au slot #1.', 13, '2026-04-02 14:27:39'),
(6, 2, 'AI_SELECTED', 'Shanghai', 83.40585454545455, 'Selection IA appliquee au slot #2.', 13, '2026-04-02 14:27:39'),
(7, 3, 'AI_SELECTED', 'Toronto', 74.8392, 'Selection IA appliquee au slot #3.', 13, '2026-04-02 14:27:39'),
(8, 4, 'AI_SELECTED', 'Algiers', 73.66334545454545, 'Selection IA appliquee au slot #4.', 13, '2026-04-02 14:27:39'),
(9, 5, 'AI_SELECTED', 'Bali', 67.5160909090909, 'Selection IA appliquee au slot #5.', 13, '2026-04-02 14:27:39'),
(10, 1, 'REPLACED', 'Casablanca', 86.5, 'Destination remplacee manuellement depuis la liste des destinations.', 13, '2026-04-02 14:28:45'),
(11, 3, 'MANUAL_UPDATE', 'Toronto', 74.84, 'Destination vedette modifiee manuellement.', 13, '2026-04-02 14:29:03'),
(12, 0, 'AI_REFRESH', 'Berlin', 86.5, '10 suggestion(s) IA preparee(s) pour la vitrine Home.', 13, '2026-04-02 14:29:20'),
(13, 8, 'AI_REFRESH', 'Algiers', 75.8, '6 suggestion(s) preparee(s) pour la vitrine Home.', 13, '2026-04-14 04:38:17'),
(14, 9, 'AI_SELECTED', 'Addis Ababa', 72.8, 'Selection IA appliquee au slot #2.', 13, '2026-04-14 04:38:17'),
(15, 10, 'AI_SELECTED', 'Berlin', 72.7, 'Selection IA appliquee au slot #3.', 13, '2026-04-14 04:38:17'),
(16, 11, 'AI_SELECTED', 'Casablanca', 72.5, 'Selection IA appliquee au slot #4.', 13, '2026-04-14 04:38:17'),
(17, 12, 'AI_SELECTED', 'Shanghai', 71.5, 'Selection IA appliquee au slot #5.', 13, '2026-04-14 04:38:17'),
(18, 13, 'AI_SELECTED', 'Toronto', 71, 'Selection IA appliquee au slot #6.', 13, '2026-04-14 04:38:17');

-- --------------------------------------------------------

--
-- Table structure for table `forum_comments`
--

CREATE TABLE `forum_comments` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `forum_comments`
--

INSERT INTO `forum_comments` (`id`, `post_id`, `user_id`, `content`, `created_at`, `updated_at`) VALUES
(2, 1, 14, 'sa7a wassime 👏👏👏🍹', '2026-04-18 15:34:01', '2026-04-18 15:34:01'),
(3, 2, 16, 'bien wlh tanja7 elforum hethi', '2026-04-26 16:53:11', '2026-04-26 16:53:11');

-- --------------------------------------------------------

--
-- Table structure for table `forum_posts`
--

CREATE TABLE `forum_posts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(160) NOT NULL,
  `content` text NOT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `forum_posts`
--

INSERT INTO `forum_posts` (`id`, `user_id`, `title`, `content`, `image_path`, `created_at`, `updated_at`) VALUES
(1, 15, 'csqdcsqddssqdqs', 'cqsscsqdsqcsqscd', '/uploads/forum-media/post-15-20260418163938-313c59f9.png', '2026-04-18 14:39:38', '2026-04-18 14:39:38'),
(2, 15, 'n,,nn,n,', '✈️✈️✈️✈️✈️✈️✈️✈️✈️✈️🌍🌍🌍🌍🌍🌍', NULL, '2026-04-18 14:45:13', '2026-04-18 14:45:13');

-- --------------------------------------------------------

--
-- Table structure for table `forum_reactions`
--

CREATE TABLE `forum_reactions` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `reaction_code` varchar(20) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `forum_reactions`
--

INSERT INTO `forum_reactions` (`id`, `post_id`, `user_id`, `reaction_code`, `created_at`, `updated_at`) VALUES
(1, 1, 15, 'LOVE', '2026-04-18 16:39:44', '2026-04-18 16:39:52'),
(3, 1, 14, 'WOW', '2026-04-18 17:33:45', '2026-04-18 17:33:45'),
(4, 2, 16, 'LOVE', '2026-04-26 17:52:07', '2026-04-26 17:52:07');

-- --------------------------------------------------------

--
-- Table structure for table `forum_stories`
--

CREATE TABLE `forum_stories` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `caption` varchar(180) NOT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `expires_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `forum_stories`
--

INSERT INTO `forum_stories` (`id`, `user_id`, `caption`, `image_path`, `created_at`, `expires_at`) VALUES
(1, 15, 'azazz', '/uploads/forum-media/story-15-20260418164114-de0a36c3.png', '2026-04-18 16:41:14', '2026-04-19 16:41:14'),
(3, 14, '🌆🌆🌆🌆', '/uploads/forum-media/story-14-20260418190541-cf2d9189.png', '2026-04-18 19:05:41', '2026-04-19 19:05:41'),
(4, 15, '', '/uploads/forum-media/story-15-20260420150916-a3f3ead5.png', '2026-04-20 15:09:16', '2026-04-21 15:09:16');

-- --------------------------------------------------------

--
-- Table structure for table `forum_story_views`
--

CREATE TABLE `forum_story_views` (
  `id` int(11) NOT NULL,
  `story_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `viewer_key` varchar(120) DEFAULT NULL,
  `viewed_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `forum_story_views`
--

INSERT INTO `forum_story_views` (`id`, `story_id`, `user_id`, `viewer_key`, `viewed_at`) VALUES
(1, 1, 14, NULL, '2026-04-18 17:34:12'),
(3, 3, 14, NULL, '2026-04-18 19:05:49'),
(4, 1, 15, NULL, '2026-04-18 19:06:18'),
(5, 3, 15, NULL, '2026-04-18 19:06:20'),
(6, 4, 15, NULL, '2026-04-20 15:09:21');

-- --------------------------------------------------------

--
-- Table structure for table `historique_paiements`
--

CREATE TABLE `historique_paiements` (
  `id` int(11) NOT NULL,
  `package_id` int(11) DEFAULT NULL,
  `client_email` varchar(255) NOT NULL,
  `montant` decimal(10,2) NOT NULL,
  `statut` varchar(50) DEFAULT 'EN_ATTENTE',
  `methode_paiement` varchar(50) DEFAULT NULL,
  `numero_carte_masque` varchar(20) DEFAULT NULL,
  `reference_transaction` varchar(100) DEFAULT NULL,
  `date_paiement` datetime DEFAULT current_timestamp(),
  `date_validation` datetime DEFAULT NULL,
  `admin_email` varchar(255) DEFAULT NULL,
  `commentaire` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `logs_statut_packages`
--

CREATE TABLE `logs_statut_packages` (
  `id` int(11) NOT NULL,
  `package_id` int(11) DEFAULT NULL,
  `ancien_statut` varchar(50) DEFAULT NULL,
  `nouveau_statut` varchar(50) DEFAULT NULL,
  `admin_email` varchar(255) DEFAULT NULL,
  `date_changement` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `map_destinations`
--

CREATE TABLE `map_destinations` (
  `id` int(11) NOT NULL,
  `city` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `continent` varchar(50) DEFAULT NULL,
  `package_name` varchar(150) DEFAULT NULL,
  `duration` varchar(50) DEFAULT NULL,
  `price` varchar(50) DEFAULT NULL,
  `original_price` varchar(50) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `best_period` varchar(100) DEFAULT NULL,
  `includes` text DEFAULT NULL,
  `highlight_1` text DEFAULT NULL,
  `highlight_2` text DEFAULT NULL,
  `highlight_3` text DEFAULT NULL,
  `x_percent` decimal(5,3) DEFAULT NULL,
  `y_percent` decimal(5,3) DEFAULT NULL,
  `ai_score` decimal(5,2) DEFAULT 0.00,
  `ai_recommended` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `display_order` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `map_destinations`
--

INSERT INTO `map_destinations` (`id`, `city`, `country`, `continent`, `package_name`, `duration`, `price`, `original_price`, `image_path`, `description`, `best_period`, `includes`, `highlight_1`, `highlight_2`, `highlight_3`, `x_percent`, `y_percent`, `ai_score`, `ai_recommended`, `is_active`, `display_order`, `created_at`) VALUES
(1, 'Washington', 'Etats-Unis', 'Amerique', 'Pack Capital Escape', '5 jours / 4 nuits', '2490 EUR', '2890 EUR', '80281906250b49a80467292e998492eb.jpg', 'Une escapade urbaine premium entre monuments iconiques, musees et rooftops confidentiels.', 'Mars a juin', 'Vol, hotel, city pass, transferts', 'Visite guidee de la Maison Blanche', 'Croisiere coucher de soleil sur le Potomac', 'Selection gourmande et shopping', 0.243, 0.269, 91.00, 1, 1, 1, '2026-04-01 00:05:51'),
(2, 'Bogota', 'Colombie', 'Amerique', 'Pack Andes Panorama', '7 jours / 6 nuits', '1690 EUR', '1990 EUR', 'da89f34fb5595d60358fcefe64fc6659.jpg', 'Un pack vibrant entre art de rue, haute gastronomie et paysages andins a couper le souffle.', 'Decembre a mars', 'Vol, hotel boutique, guide local, excursions', 'Decouverte du quartier La Candelaria', 'Excursion privee a Monserrate', 'Atelier cafe et degustation locale', 0.323, 0.624, 86.00, 1, 1, 2, '2026-04-01 00:05:51'),
(3, 'Paris', 'France', 'Europe', 'Pack City Lights', '4 jours / 3 nuits', '1890 EUR', '2140 EUR', '3fddde5acc7047afabbb1d9dd69301cd.jpg', 'Le pack ideal pour vivre Paris avec elegance, entre adresses signatures et experiences romantiques.', 'Avril a octobre', 'Hotel 4*, petit-dejeuner, croisiere, transferts', 'Billets coupe-file pour les incontournables', 'Dinner croisiere sur la Seine', 'Guide quartier mode et art de vivre', 0.666, 0.148, 95.00, 1, 1, 3, '2026-04-01 00:05:51'),
(4, 'Tokyo', 'Japon', 'Asie', 'Pack Neo Tokyo', '8 jours / 7 nuits', '2190 EUR', '2620 EUR', 'bac4bce325c9a10f6fb77f30682cc7fa.jpg', 'Une immersion entre modernite japonaise, temples, quartiers futuristes et experiences food exclusives.', 'Mars a mai', 'Vol, hotel central, JR pass, experiences food', 'Shibuya, Asakusa et teamLab inclus', 'Journee libre a Hakone ou Nikko', 'Selection de restaurants et rooftops', 0.654, 0.302, 93.00, 1, 1, 4, '2026-04-01 00:05:51'),
(5, 'Sydney', 'Australie', 'Oceanie', 'Pack Harbour Signature', '9 jours / 8 nuits', '3190 EUR', '3690 EUR', 'vaa-720x480-sydney-vivid-sydney-2024-guide.jpg', 'Un grand voyage lifestyle entre baie mythique, plages iconiques et experiences premium au soleil.', 'Septembre a novembre', 'Vol, hotel vue baie, transferts, activites', 'Opera House et harbour cruise', 'Journee a Bondi et Blue Mountains', 'Conciergerie et programme sur mesure', 0.715, 0.306, 88.00, 1, 1, 5, '2026-04-01 00:05:51'),
(6, 'Algiers', 'Algérie', 'Afrique', 'Evasion Famille Algiers', '7 jours / 6 nuits', '6140 EUR', '7061 EUR', 'b98f59bef70929b9642bc88dd2a56f11.jpg', 'Recommandation IA', 'Juin a octobre', 'Vol,Hôtel,Petit-déjeuner,Activités famille', 'Top satisfaction voyageurs', 'Budget moyen compatible', 'Diversite geographique forte', 0.829, 0.763, 97.90, 1, 1, 1, '2026-04-14 05:05:38'),
(7, 'Berlin', 'Allemagne', 'Europe', 'Escapade Romantique', '7 jours / 6 nuits', '1890 EUR', '2174 EUR', '9b4f03d821c26c149892eb9b646573bc.jpg', 'Une parenthese chic entre capitales de l\'art de vivre, experiences a deux et hebergements de charme.', 'Avril a octobre', 'Vol,Hôtel,Petit-déjeuner,Expériences', 'Top satisfaction voyageurs', 'Budget moyen compatible', 'Diversite geographique forte', 0.466, 0.196, 96.10, 1, 1, 2, '2026-04-14 05:05:38'),
(8, 'Bali', 'Indonésie', 'Asie', 'Aventure Asiatique', '14 jours / 13 nuits', '2490 EUR', '2864 EUR', 'da89f34fb5595d60358fcefe64fc6659.jpg', 'Un circuit energie entre culture locale, food scene vibrante et grandes sensations.', 'Mars a mai', 'Vol,Hôtel,Guide,Excursions', 'Top satisfaction voyageurs', 'Budget moyen compatible', 'Diversite geographique forte', 0.809, 0.305, 93.40, 1, 1, 3, '2026-04-14 05:05:38'),
(9, 'Rio de Janeiro', 'Brésil', 'Amerique', 'Pack Rio de Janeiro', '7 jours / 6 nuits', '1917 EUR', '2205 EUR', '80281906250b49a80467292e998492eb.jpg', 'Rio de Janeiro devient un hotspot premium pour la carte interactive, avec un bon equilibre entre desirabilite, budget et satisfaction.', 'Septembre a novembre', 'Vol,Hotel,Guide', 'Top satisfaction voyageurs', 'Budget moyen compatible', 'Diversite geographique forte', 0.198, 0.396, 89.60, 1, 1, 4, '2026-04-14 05:05:38'),
(10, 'Tunisie', 'Pays', 'Monde', 'Pack Tunisie', '7 jours / 6 nuits', '1490 EUR', '1714 EUR', '3fddde5acc7047afabbb1d9dd69301cd.jpg', 'Tunisie devient un hotspot premium pour la carte interactive, avec un bon equilibre entre desirabilite, budget et satisfaction.', 'Toute l annee', 'Vol,Hotel,Guide', 'Top satisfaction voyageurs', 'Budget moyen compatible', 'Diversite geographique forte', 0.439, 0.336, 80.00, 1, 1, 5, '2026-04-14 05:05:38');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` char(36) NOT NULL,
  `session_id` char(36) NOT NULL,
  `role` enum('user','assistant','system') NOT NULL,
  `content` longtext NOT NULL,
  `content_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`content_json`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `model` varchar(64) DEFAULT NULL,
  `latency_ms` int(11) DEFAULT NULL,
  `token_count` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `session_id`, `role`, `content`, `content_json`, `created_at`, `model`, `latency_ms`, `token_count`) VALUES
('11cb29e28f1a367b43511c24bf5d3d3b', 'b80591d6-f603-4de0-a8b1-cb3d2251632e', 'user', 'Je veux personnaliser ce pack voyage.Destination actuelle: Barcelona, Espagne.Type de voyage: Voyage.Budget: 500 TND a 5000 TND.Voyageurs: 2 adultes et 0 enfants.Duree souhaitee: 8 jours.Interets: Culture, Nature.Propose une version optimisee du pack avec alternatives premium.', NULL, '2026-04-20 12:26:57', NULL, NULL, NULL),
('1dd14d301d566dabb7b058b288980861', 'b80591d6-f603-4de0-a8b1-cb3d2251632e', 'assistant', 'Je n arrive pas a joindre le service IA pour l instant (FastAPI indisponible.). Votre message a ete conserve. Reessayez dans quelques secondes.', NULL, '2026-04-19 10:21:22', NULL, NULL, NULL),
('2c52b5bf-2b43-49d2-b3b9-ab72d6f087aa', 'b80591d6-f603-4de0-a8b1-cb3d2251632e', 'user', 'Je veux personnaliser ce pack voyage.Destination actuelle: Delhi, Inde.Type de voyage: Voyage.Budget: 7000 EUR a 12000 EUR.Voyageurs: 2 adultes et 0 enfants.Duree souhaitee: 25 jours.Interets: Aventure.Propose une version optimisee du pack avec alternatives premium.', NULL, '2026-04-06 08:19:20', NULL, NULL, NULL),
('5414e52af425fc31475462ad8c5d8933', 'b80591d6-f603-4de0-a8b1-cb3d2251632e', 'user', 'Je veux personnaliser le voyage Safari Luxe a Kenya & Tanzanie', NULL, '2026-04-19 10:21:19', NULL, NULL, NULL),
('7b688720-970b-4773-98c5-b3f0ce5b8d4b', 'b80591d6-f603-4de0-a8b1-cb3d2251632e', 'assistant', 'Je n\'arrive pas a joindre le service IA pour l\'instant (Connection refused: no further information). Votre message a ete conserve. Reessayez dans quelques secondes.', NULL, '2026-04-06 08:19:20', NULL, NULL, NULL),
('99ec026787492c5bb85fc3360cf1a831', 'b80591d6-f603-4de0-a8b1-cb3d2251632e', 'assistant', 'Je n arrive pas a joindre le service IA pour l instant (FastAPI indisponible.). Votre message a ete conserve. Reessayez dans quelques secondes.', NULL, '2026-04-20 12:26:59', NULL, NULL, NULL),
('9a6181c2b8488d9debda649edadfcf7b', 'b80591d6-f603-4de0-a8b1-cb3d2251632e', 'user', 'Je veux personnaliser ce pack voyage.Destination actuelle: Algiers, Algérie.Type de voyage: Voyage.Budget: 1200 TND a 23600 TND.Voyageurs: 2 adultes et 0 enfants.Duree souhaitee: 8 jours.Interets: Culture, Nature.Propose une version optimisee du pack avec alternatives premium.', NULL, '2026-04-18 23:55:26', NULL, NULL, NULL),
('ac2685f2-166c-40e6-9cb3-f79e7577d16b', 'ae7e150a-f741-48b3-88ef-6b8eb1ceae18', 'assistant', 'Sure! How about considering Marrakech, Morocco? It offers a rich cultural experience with beautiful architecture, vibrant markets, and a mild climate. You can relax in luxurious riads and enjoy the local cuisine. Another option could be Lisbon, Portugal, where you can explore historic neighborhoods, enjoy stunning views, and unwind at charming cafes. Both destinations are great for relaxation and cultural immersion. Let me know if you\'d like more details on either of these!', NULL, '2026-04-02 14:15:57', NULL, NULL, NULL),
('ac93a64f-4268-47ea-8b5a-cea0240d0fb2', 'ae7e150a-f741-48b3-88ef-6b8eb1ceae18', 'assistant', 'It looks like you haven\'t selected any activities or hotels for your trip. Feel free to add some options to make your travel plans more exciting!', NULL, '2026-04-02 14:15:27', NULL, NULL, NULL),
('bccb03b6-5c76-4b5a-9b3f-9983f15c88d0', 'ae7e150a-f741-48b3-88ef-6b8eb1ceae18', 'user', 'propose un autre destenation svp', NULL, '2026-04-02 14:15:51', NULL, NULL, NULL),
('cd4f803a72e8ab19689232dc014e1950', 'b80591d6-f603-4de0-a8b1-cb3d2251632e', 'assistant', 'Je n arrive pas a joindre le service IA pour l instant (FastAPI indisponible.). Votre message a ete conserve. Reessayez dans quelques secondes.', NULL, '2026-04-18 23:55:28', NULL, NULL, NULL),
('f942741a-3575-447d-9064-99203d4cfa86', 'ae7e150a-f741-48b3-88ef-6b8eb1ceae18', 'user', 'Je veux personnaliser ce pack voyage.Destination actuelle: Algiers, Algérie.Type de voyage: Voyage.Budget: 500 EUR a 5000 EUR.Voyageurs: 2 adultes et 0 enfants.Duree souhaitee: 8 jours.Interets: detente, culture.Propose une version optimisee du pack avec alternatives premium.', NULL, '2026-04-02 14:15:09', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `newsletter`
--

CREATE TABLE `newsletter` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `packages`
--

CREATE TABLE `packages` (
  `id` int(11) NOT NULL,
  `destination_id` int(11) DEFAULT NULL,
  `client_nom` varchar(100) DEFAULT NULL,
  `client_email` varchar(255) DEFAULT NULL,
  `date_debut` date DEFAULT NULL,
  `date_fin` date DEFAULT NULL,
  `nb_adultes` int(11) DEFAULT NULL,
  `nb_enfants` int(11) DEFAULT NULL,
  `prix_total` decimal(10,2) DEFAULT NULL,
  `statut` varchar(50) DEFAULT 'CONFIRMEE',
  `montant_paye` decimal(10,2) DEFAULT 0.00,
  `methode_paiement` varchar(50) DEFAULT NULL,
  `reference_paiement` varchar(100) DEFAULT NULL,
  `date_reservation` timestamp NOT NULL DEFAULT current_timestamp(),
  `montant_bloque` decimal(10,2) DEFAULT 0.00,
  `admin_validation_note` text DEFAULT NULL,
  `validated_by_admin_email` varchar(255) DEFAULT NULL,
  `validated_at` datetime DEFAULT NULL,
  `created_via_admin` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `packages`
--

INSERT INTO `packages` (`id`, `destination_id`, `client_nom`, `client_email`, `date_debut`, `date_fin`, `nb_adultes`, `nb_enfants`, `prix_total`, `statut`, `montant_paye`, `methode_paiement`, `reference_paiement`, `date_reservation`, `montant_bloque`, `admin_validation_note`, `validated_by_admin_email`, `validated_at`, `created_via_admin`) VALUES
(1, 2, 'seif', NULL, '2026-12-03', '2026-12-20', 2, 2, 2758.85, 'CONFIRMEE', 0.00, NULL, NULL, '2026-04-01 11:31:22', 0.00, NULL, NULL, NULL, 0),
(2, 3, 'samira ', NULL, '2026-05-15', '2026-05-25', 2, 0, 1276.85, 'CONFIRMEE', 0.00, NULL, NULL, '2026-04-01 11:31:22', 0.00, NULL, NULL, NULL, 0),
(3, 4, 'seif', NULL, '2026-12-01', '2026-12-20', 9, 0, 23520.60, 'CONFIRMEE', 0.00, NULL, NULL, '2026-04-01 11:31:22', 0.00, NULL, NULL, NULL, 0),
(4, 5, 'seif ', NULL, '2026-06-04', '2026-06-30', 2, 0, 11498.22, 'CONFIRMEE', 0.00, NULL, NULL, '2026-04-01 11:31:22', 0.00, NULL, NULL, NULL, 0),
(5, 6, 'ssss', NULL, '2026-02-10', '2026-03-01', 2, 4, 22052.40, 'CONFIRMEE', 0.00, NULL, NULL, '2026-04-01 11:31:22', 0.00, NULL, NULL, NULL, 0),
(6, 7, '555', NULL, '2026-02-24', '2026-03-03', 2, 2, 6786.56, 'CONFIRMEE', 0.00, NULL, NULL, '2026-04-01 11:31:22', 0.00, NULL, NULL, NULL, 0),
(7, 8, 'yassmine aaaa', NULL, '2026-05-01', '2026-05-09', 2, 0, 6139.84, 'CONFIRMEE', 0.00, NULL, NULL, '2026-04-01 11:31:22', 0.00, NULL, NULL, NULL, 0),
(8, 9, 'yassmine aaaa', 'yassmine@gmail.com', '2026-05-01', '2026-05-09', 2, 0, 1917.28, 'CONFIRMEE', 0.00, NULL, NULL, '2026-04-01 12:23:01', 0.00, NULL, NULL, NULL, 0),
(9, 10, 'yassmine aaaa', 'yassmine@gmail.com', '2026-04-01', '2026-04-30', 2, 3, 20676.00, 'CONFIRMEE', 0.00, NULL, NULL, '2026-04-01 20:52:17', 0.00, NULL, NULL, NULL, 0),
(10, 8, 'wassim aaa', 'wassim@gmail.com', '2026-05-02', '2026-05-10', 2, 0, 6139.84, 'CONFIRMEE', 0.00, NULL, NULL, '2026-04-02 15:01:29', 0.00, NULL, NULL, NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `paiements`
--

CREATE TABLE `paiements` (
  `id` int(11) NOT NULL,
  `client_nom` varchar(100) NOT NULL,
  `client_email` varchar(190) DEFAULT NULL,
  `destination` varchar(200) NOT NULL,
  `montant` decimal(10,2) NOT NULL,
  `date_paiement` datetime NOT NULL DEFAULT current_timestamp(),
  `statut` varchar(20) NOT NULL DEFAULT 'PAYE',
  `reference_transaction` varchar(50) NOT NULL,
  `package_id` int(11) DEFAULT 0,
  `numero_carte_masque` varchar(30) DEFAULT NULL,
  `type_voyage` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `paiements`
--

INSERT INTO `paiements` (`id`, `client_nom`, `client_email`, `destination`, `montant`, `date_paiement`, `statut`, `reference_transaction`, `package_id`, `numero_carte_masque`, `type_voyage`) VALUES
(1, 'yassmine aaaa', NULL, 'Algiers, Algérie', 6139.84, '2026-04-01 10:39:52', 'PAYE', 'PAY-1775032792416-7844', 0, '**** **** **** 6565', 'Aventure'),
(2, 'yassmine aaaa', NULL, 'Rio de Janeiro, Brésil', 1917.28, '2026-04-01 14:23:00', 'PAYE', 'PAY-1775046180971-1903', 0, '**** **** **** 4546', 'Aventure'),
(3, 'yassmine aaaa', NULL, 'Casablanca, Maroc', 20676.00, '2026-04-01 22:52:15', 'PAYE', 'PAY-1775076735639-9387', 0, '**** **** **** 6793', 'Aventure'),
(4, 'wassim aaa', NULL, 'Algiers, Algérie', 6139.84, '2026-04-02 17:01:28', 'PAYE', 'PAY-1775142088505-1871', 0, '**** **** **** 5252', 'Aventure');

-- --------------------------------------------------------

--
-- Table structure for table `posts`
--

CREATE TABLE `posts` (
  `id` int(11) NOT NULL,
  `auteur` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `chemin_photo` varchar(500) NOT NULL,
  `likes` int(11) DEFAULT NULL,
  `date_creation` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `prompts`
--

CREATE TABLE `prompts` (
  `id` char(36) NOT NULL,
  `prompt_key` varchar(128) NOT NULL,
  `description` text DEFAULT NULL,
  `active_version_id` char(36) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `language` varchar(10) NOT NULL DEFAULT 'en'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `prompts`
--

INSERT INTO `prompts` (`id`, `prompt_key`, `description`, `active_version_id`, `created_at`, `language`) VALUES
('7615c6c4-2e21-11f1-b2f8-089798defaf9', 'destination_system', 'System prompt for destination recommendations', '761819e4-2e21-11f1-b2f8-089798defaf9', '2026-04-01 21:21:07', 'en'),
('7619f58a-2e21-11f1-b2f8-089798defaf9', 'activities_system', 'System prompt for activities suggestions', '761ab5a2-2e21-11f1-b2f8-089798defaf9', '2026-04-01 21:21:07', 'en'),
('761c075f-2e21-11f1-b2f8-089798defaf9', 'intent_system', 'System prompt for intent detection', '761c8ef2-2e21-11f1-b2f8-089798defaf9', '2026-04-01 21:21:07', 'en'),
('761dd991-2e21-11f1-b2f8-089798defaf9', 'general_chat_system', 'System prompt for general travel chat', '761e613c-2e21-11f1-b2f8-089798defaf9', '2026-04-01 21:21:07', 'en'),
('761f9ea0-2e21-11f1-b2f8-089798defaf9', 'hotel_query_system', 'System prompt for hotel search parameters', '762021c5-2e21-11f1-b2f8-089798defaf9', '2026-04-01 21:21:07', 'en'),
('76217821-2e21-11f1-b2f8-089798defaf9', 'card_system', 'System prompt for trip card generation', '76222431-2e21-11f1-b2f8-089798defaf9', '2026-04-01 21:21:07', 'en'),
('762370f3-2e21-11f1-b2f8-089798defaf9', 'destination_system', 'System prompt for destination recommendations', '7623df20-2e21-11f1-b2f8-089798defaf9', '2026-04-01 21:21:07', 'fr'),
('7625e41a-2e21-11f1-b2f8-089798defaf9', 'activities_system', 'System prompt for activities suggestions', '7626984a-2e21-11f1-b2f8-089798defaf9', '2026-04-01 21:21:07', 'fr'),
('76286e69-2e21-11f1-b2f8-089798defaf9', 'intent_system', 'System prompt for intent detection', '7629320e-2e21-11f1-b2f8-089798defaf9', '2026-04-01 21:21:07', 'fr'),
('762c17c7-2e21-11f1-b2f8-089798defaf9', 'general_chat_system', 'System prompt for general travel chat', '762cc134-2e21-11f1-b2f8-089798defaf9', '2026-04-01 21:21:07', 'fr'),
('762ed306-2e21-11f1-b2f8-089798defaf9', 'hotel_query_system', 'System prompt for hotel search parameters', '762f8140-2e21-11f1-b2f8-089798defaf9', '2026-04-01 21:21:07', 'fr'),
('76316333-2e21-11f1-b2f8-089798defaf9', 'card_system', 'System prompt for trip card generation', '76321000-2e21-11f1-b2f8-089798defaf9', '2026-04-01 21:21:07', 'fr');

-- --------------------------------------------------------

--
-- Table structure for table `prompt_versions`
--

CREATE TABLE `prompt_versions` (
  `id` char(36) NOT NULL,
  `prompt_id` char(36) NOT NULL,
  `version` int(11) NOT NULL,
  `content` longtext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` varchar(255) DEFAULT NULL,
  `note` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `prompt_versions`
--

INSERT INTO `prompt_versions` (`id`, `prompt_id`, `version`, `content`, `created_at`, `created_by`, `note`) VALUES
('761819e4-2e21-11f1-b2f8-089798defaf9', '7615c6c4-2e21-11f1-b2f8-089798defaf9', 1, 'Always reply in English.\r\n\r\nYou are an intelligent, conversational travel assistant. You have memory and context awareness.\r\n\r\nYour capabilities:\r\n- Remember previous parts of the conversation\r\n- Understand follow-up questions and refinements\r\n- Adapt responses based on what user has already seen\r\n- Be natural and conversational, not robotic\r\n- Learn from user preferences expressed in conversation\r\n\r\nCurrent conversation context is provided above. Analyze:\r\n1. What has the user already asked/seen?\r\n2. What are they asking for now?\r\n3. How should you respond differently based on context?\r\n\r\nFor destination recommendations:\r\n- If they already got suggestions, don\'t repeat the same ones\r\n- If they ask to \'shortlist\' or \'pick only X\', respect that constraint\r\n- If they say \'another one\' or \'different\', provide new options\r\n- Reference what you said before when relevant\r\n\r\nReturn ONLY valid JSON with these keys:\r\n- response: conversational reply for the user\r\n- destinations: list of recommended destinations (empty if user already picked one)\r\n- chosen_destination: set only if the user explicitly picked one\r\n- reasoning: brief internal rationale\r\n\r\nAcknowledge the conversation flow and provide thoughtful, contextual responses.', '2026-04-01 21:21:07', 'seed', 'auto seed'),
('761ab5a2-2e21-11f1-b2f8-089798defaf9', '7619f58a-2e21-11f1-b2f8-089798defaf9', 1, 'Always reply in English.\r\n\r\nYou are an expert travel guide who suggests personalized activities and experiences. Consider the user\'s preferences, travel style, and trip details from their profile. Provide diverse, authentic activities that match their interests.\r\n\r\n{destination_context}\r\n\r\nDestinations can be cities OR countries. If the user mentions a country, suggest activities across different cities/regions within that country.\r\n\r\nAvoid repeating activities already suggested earlier in this conversation.\r\nReturn a JSON object with an \'activities\' array of 4-6 specific, engaging suggestions. Each activity should be 1-2 sentences and include practical details like location, timing, or what makes it special.', '2026-04-01 21:21:07', 'seed', 'auto seed'),
('761c8ef2-2e21-11f1-b2f8-089798defaf9', '761c075f-2e21-11f1-b2f8-089798defaf9', 1, 'When you write natural language fields like \'notes\', use English.\r\n\r\nYou are an intelligent intent analyzer for a travel assistant.\r\nGiven the user\'s trip form data and the full conversation, analyze the user\'s latest message for:\r\n1. Primary intent\r\n2. Semantic context (what they\'re referring to, what to exclude/compare)\r\n3. Refinement patterns (cheaper, better, closer, different, more, other, etc.)\r\n4. Destination awareness - check if user has already mentioned a specific destination in ANY message\r\n\r\nReturn ONLY valid JSON with keys:\r\n- \'intent\': primary intent from allowed list\r\n- \'context\': semantic context object with keys like \'exclude_items\', \'compare_with\', \'location_preference\', \'price_preference\', \'mentioned_destination\', \'refinement\', \'question_type\', \'location_target\'\r\n- \'confidence\': confidence score 0-1\r\n- \'notes\': brief reasoning\r\n\r\nRefinement guidance:\r\n- If the user asks for more/other/different options, set context[\'refinement\'] to include \'more\' or \'different\'\r\n- If they ask for cheaper, set \'cheaper\'; if closer, set \'closer\'\r\n- If they mention beach or beachfront, include \'beach\'\r\n- If they mention luxury or upscale, include \'luxury\'\r\n- If they mention budget, include \'budget\'\r\n- Always keep refinement values concise (single words)\r\n\r\nCritical - Destination Detection:\r\n- If ANY message mentions a specific destination (city, country, resort), include it in context[\'mentioned_destination\']\r\n- This applies to activities, hotels, or ANY request - users often say \'activities in Paris\' or \'hotels in Tokyo\'\r\n- Even if this is their first message, extract the destination if mentioned\r\n- Always return context[\'mentioned_destination\'] as an array (empty if none)\r\n\r\nHard requirement:\r\n- If the latest user message mentions a destination, you MUST include it in context[\'mentioned_destination\']\r\n\r\nExamples:\r\n- User: \"what activities can i do in tunisia\" -> intent: suggest_activities, mentioned_destination: [\"Tunisia\"]\r\n- User: \"hotels in rome\" -> intent: search_hotels, mentioned_destination: [\"Rome\"]\r\n\r\nLocation question examples:\r\n- User: \"where is el jem?\" -> intent: general_chat, question_type: location, location_target: \"El Jem\"\r\n- User: \"where is that place you mentioned\" -> intent: general_chat, question_type: location\r\n\r\nRules for location questions:\r\n- If the user asks where a place is, set intent to \'general_chat\'\r\n- Set context[\'question_type\'] = \'location\' and context[\'location_target\'] to the place\r\n- Do NOT set mentioned_destination for location questions\r\n- If the place is implied from recent conversation, infer it for location_target\r\n\r\nAllowed intents:\r\n- \'recommend_destination\' (user wants destination recommendation - use ONLY if no destination mentioned)\r\n- \'suggest_activities\' (user asks what to do/places to visit - extract destination from context)\r\n- \'search_hotels\' (user asks about hotels, accommodation - extract destination from context)\r\n- \'create_card\' (user asks to generate a trip card)\r\n- \'general_chat\' (anything else - may still contain destination references)\r\n\r\nPay special attention to:\r\n- Destination mentions in ANY part of conversation: \'give me activities in Sousse\', \'hotels in Paris\', \'what about Tunisia\'\r\n- Exclusion words: \'except\', \'other\', \'different\', \'not those\', \'another\'\r\n- Comparison words: \'cheaper\', \'better\', \'closer\', \'near\', \'like X but Y\'\r\n- Refinements to hotel results should still use \'search_hotels\' with context\r\n- If the user says \'generate a card\' or \'create a trip card\', use intent \'create_card\'', '2026-04-01 21:21:07', 'seed', 'auto seed'),
('761e613c-2e21-11f1-b2f8-089798defaf9', '761dd991-2e21-11f1-b2f8-089798defaf9', 1, 'Always reply in English.\r\n\r\nYou are an intelligent travel assistant with memory and context awareness.\r\nYou have access to the user\'s trip form data, chosen destination, conversation history, previous search results, and recent activity suggestions. You can understand and respond to:\r\n- Comparative questions about options\r\n- Contextual follow-ups about hotels, activities, destinations\r\n- Exclusion requests (\'except those\', \'other options\')\r\n- Location-based preferences (\'closer to beach\', \'near airport\')\r\n\r\n- Location questions about places mentioned earlier (\'where is El Jem?\')\r\n\r\nRules:\r\n- Use conversation context to understand what user is referring to\r\n- Reference previous search results when relevant\r\n- Answer the user\'s latest question directly and succinctly before offering extras\r\n- This assistant is for travel help only. If the user asks for unrelated info, say you can\'t help with that\r\n- The general_chat node should answer general travel questions and not suggest activities, hotels, or destinations\r\n- If the user explicitly asks for activities/hotels/destinations, defer to those intents instead of answering here\r\n- Do not drift back to earlier topics unless they are required to answer the latest travel question\r\n- If the user\'s question is ambiguous, ask one short clarifying question\r\n- If user asks about hotels but no destination is set, ask them to choose one first\r\n- Be helpful and adaptive, not rigid\r\nYou are conversational and intelligent, not just a simple classifier.', '2026-04-01 21:21:07', 'seed', 'auto seed'),
('762021c5-2e21-11f1-b2f8-089798defaf9', '761f9ea0-2e21-11f1-b2f8-089798defaf9', 1, 'Write free-text fields like \'strategy\' and \'semantic_goals\' in English.\r\n\r\nYou are an intelligent hotel search planner for a travel assistant.\r\nYour job is to create sophisticated query parameters considering:\r\n1. User\'s explicit request and constraints\r\n2. Previous search results and user feedback\r\n3. Exclusion patterns and refinements\r\n4. Geographic and semantic understanding\r\n\r\nEndpoint: GET https://api.liteapi.travel/v3.0/data/hotels\r\n\r\nAvailable query params:\r\n- countryCode, cityName, hotelName\r\n- latitude, longitude, radius (meters, min 1000)\r\n- aiSearch (semantic search - USE THIS for natural language like \'beach\', \'downtown\', \'near airport\')\r\n- minRating, minReviewsCount, starRating\r\n- limit, offset\r\n- facilityIds, hotelTypeIds, chainIds\r\n\r\nIntelligent rules:\r\n- If user mentions \'beach\', use aiSearch with \'beach\' or coastal city names\r\n- If user says \'except those\', exclude previous hotel IDs using NOT in hotelIds\r\n- If user wants \'cheaper\', lower starRating or remove minRating\r\n- If user wants \'closer to X\', use aiSearch with location context\r\n- For geographic requests, prioritize aiSearch over cityName\r\n- Use offset for pagination when user asks for \'more\' or \'different\'\r\n\r\nReturn ONLY valid JSON:\r\n{\r\n  \"params\": { ... },\r\n  \"strategy\": \"detailed explanation of approach\",\r\n  \"exclusions\": [\"list of excluded hotel IDs or criteria\"],\r\n  \"semantic_goals\": [\"what user is really looking for\"]\r\n}\r\n', '2026-04-01 21:21:07', 'seed', 'auto seed'),
('76222431-2e21-11f1-b2f8-089798defaf9', '76217821-2e21-11f1-b2f8-089798defaf9', 1, 'Always reply in English.\r\n\r\nYou are a travel planner that produces a final trip card based on confirmed user selections.\r\nUse ONLY the selected activities and selected hotels provided. Do not invent selections.\r\nIf no selections exist for a section, return an empty list.\r\n\r\nReturn ONLY valid JSON with this shape:\r\n{\r\n  \"response\": \"short, friendly summary for the user\",\r\n  \"card\": {\r\n    \"destinations\": [],\r\n    \"trip_length_days\": 0,\r\n    \"date_range\": {\"depart_date\": \"\", \"return_date\": \"\"},\r\n    \"selected_hotels\": [],\r\n    \"selected_activities\": [],\r\n    \"schedule\": [\r\n      {\"day\": 1, \"morning\": \"\", \"afternoon\": \"\", \"evening\": \"\"}\r\n    ],\r\n    \"flight_info\": {\"avg_duration\": \"\", \"notes\": \"\"},\r\n    \"notes\": []\r\n  }\r\n}\r\nKeep the schedule realistic and aligned with the selected activities only.', '2026-04-01 21:21:07', 'seed', 'auto seed'),
('7623df20-2e21-11f1-b2f8-089798defaf9', '762370f3-2e21-11f1-b2f8-089798defaf9', 1, 'Réponds uniquement en français.\r\n\r\nTu es un assistant de voyage conversationnel intelligent. Tu as de la mémoire et une conscience du contexte.\r\n\r\nTes capacités :\r\n- Te souvenir des parties précédentes de la conversation\r\n- Comprendre les questions de suivi et les raffinements\r\n- Adapter les réponses selon ce que l\'utilisateur a déjà vu\r\n- Être naturel et conversationnel, pas robotique\r\n- Apprendre des préférences exprimées par l\'utilisateur\r\n\r\nLe contexte de conversation actuel est fourni ci-dessus. Analyse :\r\n1. Qu\'est-ce que l\'utilisateur a déjà demandé/vu ?\r\n2. Que demande-t-il maintenant ?\r\n3. Comment répondre différemment selon le contexte ?\r\n\r\nPour les recommandations de destination :\r\n- S\'il a déjà reçu des suggestions, ne répète pas les mêmes\r\n- S\'il demande de \"shortlister\" ou \"n\'en garder que X\", respecte la contrainte\r\n- S\'il dit \"une autre\" ou \"différente\", propose de nouvelles options\r\n- Référence ce que tu as dit auparavant quand c\'est pertinent\r\n\r\nRetourne UNIQUEMENT un JSON valide avec ces clés :\r\n- response: réponse conversationnelle pour l\'utilisateur\r\n- destinations: liste des destinations recommandées (vide si l\'utilisateur en a déjà choisi une)\r\n- chosen_destination: définie uniquement si l\'utilisateur en a explicitement choisi une\r\n- reasoning: bref raisonnement interne\r\n\r\nReconnais le fil de la conversation et fournis des réponses réfléchies et contextualisées.', '2026-04-01 21:21:07', 'seed', 'auto seed'),
('7626984a-2e21-11f1-b2f8-089798defaf9', '7625e41a-2e21-11f1-b2f8-089798defaf9', 1, 'Réponds uniquement en français.\r\n\r\nTu es un guide de voyage expert qui suggère des activités et expériences personnalisées. Prends en compte les préférences de l\'utilisateur, son style de voyage et les détails du profil. Propose des activités variées et authentiques qui correspondent à ses intérêts.\r\n\r\n{destination_context}\r\n\r\nLes destinations peuvent être des villes OU des pays. Si l\'utilisateur mentionne un pays, propose des activités dans différentes villes/régions de ce pays.\r\n\r\nÉvite de répéter les activités déjà suggérées plus tôt dans la conversation.\r\nRetourne un objet JSON avec un tableau \'activities\' de 4 à 6 suggestions spécifiques et engageantes. Chaque activité doit faire 1 à 2 phrases et inclure des détails pratiques comme le lieu, le moment, ou ce qui la rend spéciale.', '2026-04-01 21:21:07', 'seed', 'auto seed'),
('7629320e-2e21-11f1-b2f8-089798defaf9', '76286e69-2e21-11f1-b2f8-089798defaf9', 1, 'Quand tu écris des champs en langage naturel comme \'notes\', utilise le français.\r\n\r\nTu es un analyseur d\'intentions intelligent pour un assistant de voyage.\r\nÀ partir des données de profil et de la conversation complète, analyse le dernier message de l\'utilisateur pour :\r\n1. L\'intention principale\r\n2. Le contexte sémantique (à quoi il fait référence, quoi exclure/comparer)\r\n3. Les motifs de raffinement (moins cher, mieux, plus proche, différent, plus, autre, etc.)\r\n4. La détection de destination - vérifier si une destination a été mentionnée dans N\'IMPORTE quel message\r\n\r\nRetourne UNIQUEMENT un JSON valide avec les clés :\r\n- \'intent\' : intention principale (voir liste autorisée)\r\n- \'context\' : objet de contexte sémantique avec des clés comme \'exclude_items\', \'compare_with\', \'location_preference\', \'price_preference\', \'mentioned_destination\', \'refinement\', \'question_type\', \'location_target\'\r\n- \'confidence\' : score de confiance 0-1\r\n- \'notes\' : bref raisonnement\r\n\r\nGuidance raffinement :\r\n- Si l\'utilisateur demande plus/autre/différent, ajoute \'more\' ou \'different\' dans context[\'refinement\']\r\n- S\'il demande moins cher, ajoute \'cheaper\' ; s\'il veut plus proche, \'closer\'\r\n- S\'il mentionne plage ou bord de mer, inclure \'beach\'\r\n- S\'il mentionne luxe/haut de gamme, inclure \'luxury\'\r\n- S\'il mentionne budget, inclure \'budget\'\r\n- Garde les raffinements concis (un seul mot)\r\n\r\nCritique - Détection de destination :\r\n- Si N\'IMPORTE quel message mentionne une destination spécifique (ville, pays, station), inclure dans context[\'mentioned_destination\']\r\n- Cela s\'applique aux activités, hôtels, ou TOUTE demande - les utilisateurs disent souvent \'activités à Paris\' ou \'hôtels à Tokyo\'\r\n- Même si c\'est le premier message, extraire la destination si mentionnée\r\n- Toujours renvoyer context[\'mentioned_destination\'] comme tableau (vide sinon)\r\n\r\nExigence stricte :\r\n- Si le dernier message utilisateur mentionne une destination, tu DOIS l\'inclure dans context[\'mentioned_destination\']\r\n\r\nExemples :\r\n- Utilisateur : \"quelles activités puis-je faire en Tunisie\" -> intent: suggest_activities, mentioned_destination: [\"Tunisia\"]\r\n- Utilisateur : \"hôtels à Rome\" -> intent: search_hotels, mentioned_destination: [\"Rome\"]\r\n\r\nExemples de questions de localisation :\r\n- Utilisateur : \"où se trouve el jem ?\" -> intent: general_chat, question_type: location, location_target: \"El Jem\"\r\n- Utilisateur : \"où est l\'endroit que tu as mentionné\" -> intent: general_chat, question_type: location\r\n\r\nRègles pour les questions de localisation :\r\n- Si l\'utilisateur demande où se trouve un lieu, définir intent = \'general_chat\'\r\n- Définir context[\'question_type\'] = \'location\' et context[\'location_target\'] = le lieu\r\n- Ne PAS définir mentioned_destination pour les questions de localisation\r\n- Si le lieu est implicite dans la conversation récente, l\'inférer pour location_target\r\n\r\nIntentions autorisées :\r\n- \'recommend_destination\' (l\'utilisateur veut une recommandation - utiliser SEULEMENT si aucune destination n\'est mentionnée)\r\n- \'suggest_activities\' (l\'utilisateur demande quoi faire - extraire la destination du contexte)\r\n- \'search_hotels\' (l\'utilisateur demande des hôtels - extraire la destination du contexte)\r\n- \'create_card\' (l\'utilisateur demande une carte de voyage)\r\n- \'general_chat\' (tout le reste - peut contenir des références de destination)\r\n\r\nFais particulièrement attention à :\r\n- Les mentions de destination dans TOUTE la conversation : \'activités à Sousse\', \'hôtels à Paris\', \'et la Tunisie ?\'\r\n- Mots d\'exclusion : \'sauf\', \'autres\', \'différents\', \'pas ceux-là\', \'un autre\'\r\n- Mots de comparaison : \'moins cher\', \'mieux\', \'plus proche\', \'près de\', \'comme X mais Y\'\r\n- Les raffinements d\'hôtels doivent rester intent = \'search_hotels\' avec contexte\r\n- Si l\'utilisateur dit \'génère une carte\' ou \'crée une carte de voyage\', utiliser intent = \'create_card\'', '2026-04-01 21:21:07', 'seed', 'auto seed'),
('762cc134-2e21-11f1-b2f8-089798defaf9', '762c17c7-2e21-11f1-b2f8-089798defaf9', 1, 'Réponds uniquement en français.\r\n\r\nTu es un assistant de voyage intelligent avec mémoire et conscience du contexte.\r\nTu as accès au profil de l\'utilisateur, à la destination choisie, à l\'historique de conversation, aux résultats précédents et aux suggestions d\'activités récentes. Tu peux répondre à :\r\n- Des questions comparatives entre options\r\n- Des suivis contextuels sur hôtels, activités, destinations\r\n- Des demandes d\'exclusion (\'sauf ceux-là\', \'autres options\')\r\n- Des préférences de localisation (\'près de la plage\', \'près de l\'aéroport\')\r\n\r\n- Des questions de localisation sur des lieux mentionnés plus tôt (\'où se trouve El Jem ?\')\r\n\r\nRègles :\r\n- Utilise le contexte de conversation pour comprendre à quoi l\'utilisateur fait référence\r\n- Référence les résultats précédents quand c\'est pertinent\r\n- Réponds directement et brièvement à la dernière question avant d\'ajouter des extras\r\n- Cet assistant est uniquement pour l\'aide au voyage. Si l\'utilisateur demande autre chose, dis que tu ne peux pas\r\n- Le noeud general_chat doit répondre aux questions de voyage générales et ne pas suggérer d\'activités, hôtels ou destinations\r\n- Si l\'utilisateur demande explicitement des activités/hôtels/destinations, renvoie vers ces intentions\r\n- Ne reviens pas à des sujets anciens sauf si c\'est nécessaire pour répondre à la question actuelle\r\n- Si la question est ambiguë, pose une courte question de clarification\r\n- Si l\'utilisateur demande des hôtels sans destination, demande d\'abord laquelle\r\n- Sois utile et adaptable, pas rigide\r\nTu es conversationnel et intelligent, pas un simple classificateur.', '2026-04-01 21:21:07', 'seed', 'auto seed'),
('762f8140-2e21-11f1-b2f8-089798defaf9', '762ed306-2e21-11f1-b2f8-089798defaf9', 1, 'Écris les champs libres comme \'strategy\' et \'semantic_goals\' en français.\r\n\r\nTu es un planificateur de recherche d\'hôtels intelligent pour un assistant de voyage.\r\nTa tâche est de créer des paramètres de requête sophistiqués en tenant compte :\r\n1. De la demande explicite et des contraintes\r\n2. Des résultats précédents et des retours utilisateur\r\n3. Des exclusions et raffinements\r\n4. De la compréhension géographique et sémantique\r\n\r\nEndpoint: GET https://api.liteapi.travel/v3.0/data/hotels\r\n\r\nParamètres disponibles :\r\n- countryCode, cityName, hotelName\r\n- latitude, longitude, radius (mètres, min 1000)\r\n- aiSearch (recherche sémantique - UTILISER pour \'plage\', \'centre-ville\', \'près de l\'aéroport\')\r\n- minRating, minReviewsCount, starRating\r\n- limit, offset\r\n- facilityIds, hotelTypeIds, chainIds\r\n\r\nRègles intelligentes :\r\n- Si l\'utilisateur mentionne \'plage\', utiliser aiSearch avec \'plage\' ou des villes côtières\r\n- Si l\'utilisateur dit \'sauf ceux-là\', exclure les IDs d\'hôtels précédents avec NOT in hotelIds\r\n- Si l\'utilisateur veut \'moins cher\', baisser starRating ou retirer minRating\r\n- Si l\'utilisateur veut \'plus proche de X\', utiliser aiSearch avec le contexte de lieu\r\n- Pour les demandes géographiques, privilégier aiSearch plutôt que cityName\r\n- Utiliser offset pour la pagination si l\'utilisateur demande \'plus\' ou \'différent\'\r\n\r\nRetourne UNIQUEMENT un JSON valide :\r\n{\r\n  \"params\": { ... },\r\n  \"strategy\": \"explication détaillée de l\'approche\",\r\n  \"exclusions\": [\"liste des IDs d\'hôtels exclus ou critères\"],\r\n  \"semantic_goals\": [\"ce que l\'utilisateur cherche vraiment\"]\r\n}\r\n', '2026-04-01 21:21:07', 'seed', 'auto seed'),
('76321000-2e21-11f1-b2f8-089798defaf9', '76316333-2e21-11f1-b2f8-089798defaf9', 1, 'Réponds uniquement en français.\r\n\r\nTu es un planificateur de voyage qui produit une carte finale à partir des sélections confirmées.\r\nUtilise UNIQUEMENT les activités et hôtels sélectionnés fournis. N\'invente pas de sélections.\r\nSi aucune sélection n\'existe pour une section, retourne une liste vide.\r\n\r\nRetourne UNIQUEMENT un JSON valide avec cette forme :\r\n{\r\n  \"response\": \"résumé court et sympathique pour l\'utilisateur\",\r\n  \"card\": {\r\n    \"destinations\": [],\r\n    \"trip_length_days\": 0,\r\n    \"date_range\": {\"depart_date\": \"\", \"return_date\": \"\"},\r\n    \"selected_hotels\": [],\r\n    \"selected_activities\": [],\r\n    \"schedule\": [\r\n      {\"day\": 1, \"morning\": \"\", \"afternoon\": \"\", \"evening\": \"\"}\r\n    ],\r\n    \"flight_info\": {\"avg_duration\": \"\", \"notes\": \"\"},\r\n    \"notes\": []\r\n  }\r\n}\r\nGarde un planning réaliste, aligné uniquement avec les activités sélectionnées.', '2026-04-01 21:21:07', 'seed', 'auto seed');

-- --------------------------------------------------------

--
-- Table structure for table `reactions`
--

CREATE TABLE `reactions` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `reaction_type` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reclamation`
--

CREATE TABLE `reclamation` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `sujet` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `statut` enum('EN_ATTENTE','EN_COURS','RESOLUE','REJETEE') NOT NULL DEFAULT 'EN_ATTENTE',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reclamation`
--

INSERT INTO `reclamation` (`id`, `user_id`, `sujet`, `description`, `statut`, `created_at`) VALUES
(1, 14, 'dfdqssf', 'sdjqss;llkkjjqsdjkqsdsfdsqddsfsdsqsqdf', 'RESOLUE', '2026-04-01 00:56:31'),
(2, 15, 'szmzmzmzzz', 'sqfsfdsdfffddfssqdf', 'RESOLUE', '2026-04-02 14:54:19'),
(3, 13, 'ftftytf', 'sssreesrers', 'EN_ATTENTE', '2026-04-13 12:01:15'),
(4, 15, 'bvbvvbbv', 'cvvbvvb bv ljlkjk', 'EN_ATTENTE', '2026-04-18 06:21:47');

-- --------------------------------------------------------

--
-- Table structure for table `reponse`
--

CREATE TABLE `reponse` (
  `id` int(11) NOT NULL,
  `reclamation_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `contenu` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reponse`
--

INSERT INTO `reponse` (`id`, `reclamation_id`, `admin_id`, `contenu`, `created_at`) VALUES
(1, 1, 13, 'cdssqdsdqsddsddssqdqsdsqd', '2026-04-01 00:57:38'),
(2, 2, 13, 'qsdfdsfsdqfdfsqfzddsq', '2026-04-02 14:55:44');

-- --------------------------------------------------------

--
-- Table structure for table `revenus_admin`
--

CREATE TABLE `revenus_admin` (
  `id` int(11) NOT NULL,
  `package_id` int(11) DEFAULT NULL,
  `montant` decimal(10,2) NOT NULL,
  `source` varchar(255) DEFAULT NULL,
  `date_reception` datetime DEFAULT current_timestamp(),
  `statut` varchar(50) DEFAULT 'RECU'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` char(36) NOT NULL,
  `user_id` char(36) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `form_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`form_data`)),
  `agent_state` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`agent_state`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_message_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `title`, `form_data`, `agent_state`, `created_at`, `updated_at`, `last_message_at`) VALUES
('89d9de0a-00fd-4184-be73-14f8df2ababd', '15', 'Nouvelle discussion 6', '{\"language\":\"en\"}', '{}', '2026-04-02 15:00:11', '2026-04-02 15:00:11', NULL),
('ae7e150a-f741-48b3-88ef-6b8eb1ceae18', '13', 'Je veux personnaliser ce pack voyage.De...', '{\"language\":\"en\"}', '{}', '2026-04-02 14:15:09', '2026-04-02 14:15:57', '2026-04-02 14:15:57'),
('b80591d6-f603-4de0-a8b1-cb3d2251632e', '15', 'Je veux personnaliser ce pack voyage.De...', '{\"language\":\"en\"}', '{}', '2026-04-02 15:00:14', '2026-04-20 12:26:59', '2026-04-20 12:26:59'),
('offline-c8c84c68b325a60a51d821c3e4e3', '14', 'Nouvelle discussion', '{\"language\":\"fr\"}', '{}', '2026-04-20 05:58:10', '2026-04-20 05:58:10', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `sponsor`
--

CREATE TABLE `sponsor` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `logo_url` varchar(255) DEFAULT NULL,
  `logo_blob` mediumblob DEFAULT NULL,
  `logo_mime_type` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `site_web` varchar(255) DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `montant_sponsoring` decimal(10,2) DEFAULT NULL,
  `date_debut` date DEFAULT NULL,
  `date_fin` date DEFAULT NULL,
  `est_actif` tinyint(1) DEFAULT 1,
  `nombre_clics` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `email_source` varchar(255) DEFAULT NULL,
  `email_destination` varchar(255) DEFAULT NULL,
  `montant` decimal(10,2) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `date_transaction` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `travel_favorites`
--

CREATE TABLE `travel_favorites` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `favorite_key` varchar(180) NOT NULL,
  `destination_id` int(11) DEFAULT NULL,
  `destination_name` varchar(160) NOT NULL,
  `country` varchar(120) DEFAULT NULL,
  `continent` varchar(80) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `duration_label` varchar(80) DEFAULT NULL,
  `price_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `price_currency` varchar(3) NOT NULL DEFAULT 'TND',
  `source` varchar(40) NOT NULL DEFAULT 'database',
  `destination_url` varchar(255) NOT NULL DEFAULT '/destinations',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `travel_favorites`
--

INSERT INTO `travel_favorites` (`id`, `user_id`, `favorite_key`, `destination_id`, `destination_name`, `country`, `continent`, `image_path`, `description`, `duration_label`, `price_amount`, `price_currency`, `source`, `destination_url`, `created_at`) VALUES
(2, 15, 'flask-barcelona-espagne', 2, 'Barcelona', 'Espagne', 'Europe', '/assets/java/9b4f03d821c26c149892eb9b646573bc.jpg', 'Recommandation IA generee par Flask pour votre profil de voyage.', '8 jours', 5000.00, 'TND', 'flask', '/destinations', '2026-04-20 12:31:30');

-- --------------------------------------------------------

--
-- Table structure for table `travel_packages`
--

CREATE TABLE `travel_packages` (
  `id` int(11) NOT NULL,
  `package_name` varchar(150) DEFAULT NULL,
  `destinations` text DEFAULT NULL,
  `continent` varchar(50) DEFAULT NULL,
  `duration_days` int(11) DEFAULT NULL,
  `price_from` decimal(10,2) DEFAULT NULL,
  `price_to` decimal(10,2) DEFAULT NULL,
  `badge` varchar(50) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `travel_type` varchar(50) DEFAULT NULL,
  `interests` text DEFAULT NULL,
  `ai_generated` tinyint(1) DEFAULT 0,
  `ai_score` decimal(5,2) DEFAULT 0.00,
  `includes` text DEFAULT NULL,
  `best_period` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `display_order` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `travel_packages`
--

INSERT INTO `travel_packages` (`id`, `package_name`, `destinations`, `continent`, `duration_days`, `price_from`, `price_to`, `badge`, `image_path`, `description`, `travel_type`, `interests`, `ai_generated`, `ai_score`, `includes`, `best_period`, `is_active`, `display_order`, `created_at`) VALUES
(1, 'Escapade Romantique', 'Paris, Provence', 'Europe', 7, 1890.00, 2290.00, 'Populaire', '9b4f03d821c26c149892eb9b646573bc.jpg', 'Une parenthese chic entre capitales de l\'art de vivre, experiences a deux et hebergements de charme.', 'couple', 'romance,culture,gastronomie,detente', 1, 91.00, 'Vol,Hôtel,Petit-déjeuner,Expériences', 'Avril a octobre', 1, 1, '2026-04-01 00:05:50'),
(2, 'Aventure Asiatique', 'Thailande, Vietnam', 'Asie', 14, 2490.00, 2990.00, 'Nouveau', 'da89f34fb5595d60358fcefe64fc6659.jpg', 'Un circuit energie entre culture locale, food scene vibrante et grandes sensations.', 'aventure', 'aventure,nature,photographie,culture', 1, 87.00, 'Vol,Hôtel,Guide,Excursions', 'Mars a mai', 1, 2, '2026-04-01 00:05:50'),
(3, 'Safari Luxe', 'Kenya, Tanzanie', 'Afrique', 10, 4990.00, 5790.00, 'Exclusif', 'b98f59bef70929b9642bc88dd2a56f11.jpg', 'Un voyage signature pour vivre un safari premium, lodges d\'exception et moments rares.', 'famille', 'culture,nature,detente,gastronomie', 1, 94.00, 'Vol,Hôtel,Petit-déjeuner,Activités famille', 'Juin a octobre', 1, 3, '2026-04-01 00:05:50'),
(4, 'Evasion Famille Tokyo', 'Tokyo, Japon', 'Asie', 7, 1890.00, 3200.00, 'Exclusif', 'bac4bce325c9a10f6fb77f30682cc7fa.jpg', 'Une aventure urbaine intense entre quartiers futuristes, gastronomie et experiences design.', 'famille', 'culture,nature,detente,gastronomie', 1, 91.00, 'Vol,Hôtel,Petit-déjeuner,Activités famille', 'Mars a mai', 1, 4, '2026-04-02 14:35:30'),
(5, 'Evasion Famille Algiers', 'Algiers, Algérie', 'Afrique', 7, 6139.84, 7060.82, 'Populaire', 'b98f59bef70929b9642bc88dd2a56f11.jpg', 'Recommandation IA', 'famille', 'culture,nature,detente,gastronomie', 1, 88.50, 'Vol,Hôtel,Petit-déjeuner,Activités famille', 'Juin a octobre', 1, 5, '2026-04-02 14:35:31'),
(6, 'Evasion Famille Paris', 'Paris, France', 'Afrique', 7, 1200.00, 3200.00, 'Populaire', '9b4f03d821c26c149892eb9b646573bc.jpg', 'Un city break premium entre adresses iconiques, douceur de vivre et experiences sur mesure.', 'famille', 'culture,nature,detente,gastronomie', 1, 88.00, 'Vol,Hôtel,Petit-déjeuner,Activités famille', 'Juin a octobre', 1, 6, '2026-04-02 14:35:31');

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `id` int(11) NOT NULL,
  `nom` varchar(50) NOT NULL,
  `prenom` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `adresse` text DEFAULT NULL,
  `date_naissance` date DEFAULT NULL,
  `role` enum('ADMIN','USER','AGENT') DEFAULT 'USER',
  `photo_url` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `is_validated` tinyint(1) NOT NULL DEFAULT 1,
  `validated_at` timestamp NULL DEFAULT NULL,
  `date_inscription` date DEFAULT curdate(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`id`, `nom`, `prenom`, `email`, `password`, `telephone`, `adresse`, `date_naissance`, `role`, `photo_url`, `is_active`, `is_validated`, `validated_at`, `date_inscription`, `created_at`, `updated_at`) VALUES
(13, 'Zrafi', 'Mehdi', 'zrafimehdi5@gmail.com', 'i7DPbrmxfQ99IrRW8SElfcElTh8BZlNwR2OD6ndt9BQ=', '51418902', 'RUE N° 6', NULL, 'ADMIN', NULL, 1, 1, NULL, '2026-02-22', '2026-02-22 09:12:53', '2026-04-26 17:12:56'),
(14, 'aaaa', 'yassmine', 'yassmine@gmail.com', 'jZae727K08KaOmKSgOaGzww/XVqGr/PKEgIMkjrcbJI=', '', 'kairouan', '2016-03-05', 'ADMIN', NULL, 1, 1, '2026-03-31 02:29:38', '2026-03-31', '2026-03-30 23:15:19', '2026-04-02 14:24:58'),
(15, 'aaa', 'wassim', 'wassim@gmail.com', 'jZae727K08KaOmKSgOaGzww/XVqGr/PKEgIMkjrcbJI=', NULL, NULL, NULL, 'USER', NULL, 1, 1, '2026-04-02 14:24:27', '2026-04-02', '2026-04-02 14:22:57', '2026-04-02 14:25:12'),
(16, 'Zrafi', 'Mehdi', 'zrafimehdi@gmail.com', 'v3BGi7hyZdrY9m6r9LlPeba2RFBdwI5vPgzOa3iL5Mk=', '+21651418902', 'Rue 6 N°436\r\nSuite', '2003-07-14', 'ADMIN', '/uploads/profile-photos/zrafimehdi-gmail-com-20260426190746-5e934ccb.png', 1, 1, NULL, '2026-04-26', '2026-04-26 16:25:32', '2026-04-26 17:07:46');

-- --------------------------------------------------------

--
-- Table structure for table `user_notifications`
--

CREATE TABLE `user_notifications` (
  `id` int(11) NOT NULL,
  `recipient_email` varchar(100) NOT NULL,
  `sender_email` varchar(100) DEFAULT NULL,
  `sender_role` varchar(20) DEFAULT NULL,
  `category` varchar(30) NOT NULL DEFAULT 'ACCOUNT',
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_notifications`
--

INSERT INTO `user_notifications` (`id`, `recipient_email`, `sender_email`, `sender_role`, `category`, `title`, `message`, `is_read`, `created_at`) VALUES
(1, 'zrafimehdi5@gmail.com', 'zrafimehdi5@gmail.com', 'ADMIN', 'PASSWORD', 'Mot de passe admin modifie', 'Mehdi Zrafi a change son mot de passe admin.', 1, '2026-03-31 01:31:12'),
(2, 'zrafimehdi5@gmail.com', 'zrafimehdi5@gmail.com', 'ADMIN', 'PHOTO', 'Photo de profil admin mise a jour', 'Mehdi Zrafi a mis a jour son espace administrateur.', 1, '2026-03-31 01:31:36'),
(3, 'yassmine@gmail.com', 'zrafimehdi5@gmail.com', 'ADMIN', 'ACCOUNT', 'Compte valide par l\'administration', 'Votre compte EasyTravel a ete valide. Vous pouvez maintenant vous connecter.', 1, '2026-03-31 02:29:39'),
(4, 'zrafimehdi5@gmail.com', 'zrafimehdi5@gmail.com', 'ADMIN', 'PHOTO', 'Photo de profil admin mise a jour', 'Mehdi Zrafi a mis a jour son espace administrateur.', 1, '2026-03-31 12:35:48'),
(5, 'zrafimehdi5@gmail.com', 'yassmine@gmail.com', 'USER', 'ACCOUNT', 'Nouvelle reclamation client', 'yassmine aaaa a envoye une reclamation : dfdqssf', 1, '2026-04-01 00:56:32'),
(6, 'yassmine@gmail.com', 'zrafimehdi5@gmail.com', 'ADMIN', 'ACCOUNT', 'Nouvelle reponse a votre reclamation', 'L\'administration a repondu a votre reclamation \"dfdqssf\".', 1, '2026-04-01 00:57:38'),
(7, 'yassmine@gmail.com', 'zrafimehdi5@gmail.com', 'ADMIN', 'ACCOUNT', 'Mise a jour de votre reclamation', 'Votre reclamation \"dfdqssf\" est maintenant en cours.', 1, '2026-04-01 00:57:42'),
(8, 'yassmine@gmail.com', 'zrafimehdi5@gmail.com', 'ADMIN', 'ACCOUNT', 'Mise a jour de votre reclamation', 'Votre reclamation \"dfdqssf\" est maintenant resolue.', 1, '2026-04-01 00:59:42'),
(9, 'wassim@gmail.com', 'system@easytravel.local', 'SYSTEM', 'ACCOUNT', 'Compte cree en attente de validation', 'Bienvenue wassim, votre compte EasyTravel a ete cree et attend maintenant la validation d\'un administrateur.', 0, '2026-04-02 14:22:57'),
(10, 'zrafimehdi5@gmail.com', 'wassim@gmail.com', 'USER', 'ACCOUNT', 'Nouveau compte client a valider', 'wassim aaa a cree un nouveau compte client et attend une validation admin.', 1, '2026-04-02 14:22:58'),
(11, 'wassim@gmail.com', 'zrafimehdi5@gmail.com', 'ADMIN', 'ACCOUNT', 'Compte valide par l\'administration', 'Votre compte EasyTravel a ete valide. Vous pouvez maintenant vous connecter.', 0, '2026-04-02 14:24:27'),
(12, 'yassmine@gmail.com', 'zrafimehdi5@gmail.com', 'ADMIN', 'ACCOUNT', 'Votre rôle EasyTravel a été mis à jour', 'Votre compte est désormais configuré avec le rôle ADMIN.', 0, '2026-04-02 14:24:59'),
(13, 'yassmine@gmail.com', 'wassim@gmail.com', 'USER', 'ACCOUNT', 'Nouvelle reclamation client', 'wassim aaa a envoye une reclamation : szmzmzmzzz', 0, '2026-04-02 14:54:20'),
(14, 'zrafimehdi5@gmail.com', 'wassim@gmail.com', 'USER', 'ACCOUNT', 'Nouvelle reclamation client', 'wassim aaa a envoye une reclamation : szmzmzmzzz', 0, '2026-04-02 14:54:20'),
(15, 'wassim@gmail.com', 'zrafimehdi5@gmail.com', 'ADMIN', 'ACCOUNT', 'Nouvelle reponse a votre reclamation', 'L\'administration a repondu a votre reclamation \"szmzmzmzzz\".', 0, '2026-04-02 14:55:44'),
(16, 'yassmine@gmail.com', 'zrafimehdi5@gmail.com', 'ADMIN', 'ACCOUNT', 'Nouvelle reclamation client', 'Mehdi Zrafi a envoye une reclamation : ftftytf', 0, '2026-04-13 12:01:15'),
(17, 'zrafimehdi5@gmail.com', 'zrafimehdi5@gmail.com', 'ADMIN', 'ACCOUNT', 'Nouvelle reclamation client', 'Mehdi Zrafi a envoye une reclamation : ftftytf', 0, '2026-04-13 12:01:15'),
(18, 'wassim@gmail.com', 'zrafimehdi5@gmail.com', 'ADMIN', 'ACCOUNT', 'Mise a jour de votre reclamation', 'Votre reclamation \"szmzmzmzzz\" est maintenant resolue.', 0, '2026-04-13 12:04:35'),
(19, 'zrafimehdi5@gmail.com', 'zrafimehdi5@gmail.com', 'ADMIN', 'PREFERENCES', 'Preferences notifications admin mises a jour', 'Mehdi Zrafi a modifie ses preferences de notification admin.', 0, '2026-04-13 15:37:03'),
(20, 'zrafimehdi5@gmail.com', 'zrafimehdi5@gmail.com', 'ADMIN', 'PASSWORD', 'Mot de passe admin modifie', 'Mehdi Zrafi a change son mot de passe admin.', 0, '2026-04-13 15:55:34'),
(21, 'zrafimehdi5@gmail.com', 'zrafimehdi5@gmail.com', 'ADMIN', 'PROFILE', 'Profil admin mis a jour', 'Mehdi Zrafi a mis a jour son espace administrateur.', 0, '2026-04-13 15:55:39'),
(22, 'zrafimehdi5@gmail.com', 'zrafimehdi5@gmail.com', 'ADMIN', 'PHOTO', 'Photo de profil admin mise a jour', 'Mehdi Zrafi a mis a jour son espace administrateur.', 0, '2026-04-13 21:38:15'),
(23, 'zrafimehdi5@gmail.com', 'zrafimehdi5@gmail.com', 'ADMIN', 'PHOTO', 'Photo de profil admin mise a jour', 'Mehdi Zrafi a mis a jour son espace administrateur.', 0, '2026-04-13 22:07:43'),
(24, 'zrafimehdi5@gmail.com', 'zrafimehdi5@gmail.com', 'ADMIN', 'PROFILE', 'Profil admin mis a jour', 'Mehdi Zrafi a mis a jour son espace administrateur.', 0, '2026-04-13 22:21:46'),
(25, 'zrafimehdi5@gmail.com', 'zrafimehdi5@gmail.com', 'ADMIN', 'PHOTO', 'Photo de profil admin mise a jour', 'Mehdi Zrafi a mis a jour son espace administrateur.', 0, '2026-04-13 22:22:12'),
(26, 'zrafimehdi5@gmail.com', 'zrafimehdi5@gmail.com', 'ADMIN', 'PROFILE', 'Profil admin mis a jour', 'Mehdi Zrafi a mis a jour son espace administrateur.', 0, '2026-04-14 04:26:57'),
(27, 'yassmine@gmail.com', 'wassim@gmail.com', 'USER', 'ACCOUNT', 'Nouvelle reclamation client', 'wassim aaa a envoye une reclamation : bvbvvbbv', 0, '2026-04-18 06:21:47'),
(28, 'zrafimehdi5@gmail.com', 'wassim@gmail.com', 'USER', 'ACCOUNT', 'Nouvelle reclamation client', 'wassim aaa a envoye une reclamation : bvbvvbbv', 0, '2026-04-18 06:21:47'),
(29, 'zrafimehdi@gmail.com', 'system@easytravel.local', 'SYSTEM', 'ACCOUNT', 'Compte cree en attente de validation', 'Bienvenue Mehdi, votre compte EasyTravel a ete cree et attend maintenant la validation d\'un administrateur.', 1, '2026-04-26 16:25:32'),
(30, 'yassmine@gmail.com', 'zrafimehdi@gmail.com', 'USER', 'ACCOUNT', 'Nouveau compte client a valider', 'Mehdi Zrafi a cree un nouveau compte client et attend une validation admin.', 0, '2026-04-26 16:25:32'),
(31, 'zrafimehdi5@gmail.com', 'zrafimehdi@gmail.com', 'USER', 'ACCOUNT', 'Nouveau compte client a valider', 'Mehdi Zrafi a cree un nouveau compte client et attend une validation admin.', 0, '2026-04-26 16:25:32'),
(32, 'zrafimehdi@gmail.com', 'zrafimehdi@gmail.com', 'ADMIN', 'PROFILE', 'Photo de profil admin mise a jour', 'Mehdi Zrafi a mis a jour son avatar administrateur.', 0, '2026-04-26 16:32:41'),
(33, 'zrafimehdi@gmail.com', 'zrafimehdi@gmail.com', 'ADMIN', 'PROFILE', 'Profil admin mis a jour', 'Mehdi Zrafi a mis a jour son espace administrateur.', 0, '2026-04-26 16:33:04'),
(34, 'zrafimehdi@gmail.com', 'zrafimehdi@gmail.com', 'ADMIN', 'PROFILE', 'Profil admin mis a jour', 'Mehdi Zrafi a mis a jour son espace administrateur.', 0, '2026-04-26 16:34:20'),
(35, 'zrafimehdi@gmail.com', 'zrafimehdi@gmail.com', 'ADMIN', 'PROFILE', 'Photo de profil admin mise a jour', 'Mehdi Zrafi a mis a jour son avatar administrateur.', 0, '2026-04-26 16:37:14'),
(36, 'zrafimehdi@gmail.com', 'zrafimehdi@gmail.com', 'ADMIN', 'PROFILE', 'Photo de profil admin mise a jour', 'Mehdi Zrafi a mis a jour son avatar administrateur.', 0, '2026-04-26 17:07:46'),
(37, 'zrafimehdi@gmail.com', 'zrafimehdi@gmail.com', 'ADMIN', 'PROFILE', 'Profil admin mis a jour', 'Mehdi Zrafi a mis a jour son espace administrateur.', 0, '2026-04-26 17:07:50'),
(38, 'zrafimehdi@gmail.com', 'zrafimehdi@gmail.com', 'ADMIN', 'PROFILE', 'Profil admin mis a jour', 'Mehdi Zrafi a mis a jour son espace administrateur.', 0, '2026-04-26 17:07:55');

-- --------------------------------------------------------

--
-- Table structure for table `user_notification_preferences`
--

CREATE TABLE `user_notification_preferences` (
  `id` int(11) NOT NULL,
  `user_email` varchar(100) NOT NULL,
  `user_role` varchar(20) NOT NULL,
  `notify_security` tinyint(1) NOT NULL DEFAULT 1,
  `notify_booking` tinyint(1) NOT NULL DEFAULT 1,
  `notify_forum` tinyint(1) NOT NULL DEFAULT 1,
  `notify_offers` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_notification_preferences`
--

INSERT INTO `user_notification_preferences` (`id`, `user_email`, `user_role`, `notify_security`, `notify_booking`, `notify_forum`, `notify_offers`, `created_at`, `updated_at`) VALUES
(1, 'zrafimehdi5@gmail.com', 'ADMIN', 1, 1, 1, 0, '2026-03-31 01:30:47', '2026-03-31 01:30:47'),
(2, 'yassmine@gmail.com', 'USER', 1, 1, 1, 0, '2026-03-31 02:29:39', '2026-03-31 02:29:39'),
(3, 'wassim@gmail.com', 'USER', 1, 1, 1, 0, '2026-04-02 14:22:57', '2026-04-02 14:22:57'),
(11, 'zrafimehdi@gmail.com', 'ADMIN', 1, 1, 1, 0, '2026-04-26 16:25:32', '2026-04-26 16:32:41');

-- --------------------------------------------------------

--
-- Table structure for table `user_remember_me`
--

CREATE TABLE `user_remember_me` (
  `id` int(11) NOT NULL,
  `device_key` varchar(120) NOT NULL,
  `user_email` varchar(100) NOT NULL,
  `encrypted_password` text NOT NULL,
  `user_role` varchar(20) NOT NULL DEFAULT 'USER',
  `remembered_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_remember_me`
--

INSERT INTO `user_remember_me` (`id`, `device_key`, `user_email`, `encrypted_password`, `user_role`, `remembered_at`, `updated_at`) VALUES
(1, 'fDm2_hwOaYZqUs4LJu1HXeF9Cev2qGiCIyvpLLaJb6g', 'zrafimehdi5@gmail.com', 'y2iJJbROS2uhxQc9Pv2ArRGxXcn8gmMrxOKx06ltToSwbtw=', 'ADMIN', '2026-03-31 01:25:45', '2026-04-18 05:05:16'),
(98, 'fa8b82501c5acb2396ebc8e9466505b29f68c2fbcebe49e1a92d61f70f6f04c3', 'wassim@gmail.com', '8Mw+fJnJpdw4M+v28Gs/5HAledwj7wVMJpF+HkiIqV1LWg==', 'USER', '2026-04-17 09:23:23', '2026-04-17 09:46:37'),
(104, '10c30731a0953a9b561ef3cd736c6cadd9bf244db1573d5f46362f586f0e6575', 'wassim@gmail.com', '5xWUnovN13Ptp9cSJ89aXUym/xXJnmFXwUk/g7QBP1lDbQ==', 'USER', '2026-04-18 05:46:08', '2026-04-20 13:29:51');

-- --------------------------------------------------------

--
-- Table structure for table `voyage`
--

CREATE TABLE `voyage` (
  `idVoyage` int(11) NOT NULL,
  `destination` varchar(100) DEFAULT NULL,
  `pays` varchar(100) DEFAULT NULL,
  `dateDepart` varchar(50) DEFAULT NULL,
  `dateRetour` varchar(50) DEFAULT NULL,
  `prix` double DEFAULT NULL,
  `moyenTransport` varchar(50) DEFAULT NULL,
  `hotel` varchar(100) DEFAULT NULL,
  `nbPlaces` int(11) DEFAULT NULL,
  `disponible` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `voyage`
--

INSERT INTO `voyage` (`idVoyage`, `destination`, `pays`, `dateDepart`, `dateRetour`, `prix`, `moyenTransport`, `hotel`, `nbPlaces`, `disponible`) VALUES
(1, 'Addis Ababa', 'Éthiopie', '2026-12-01', '2026-12-20', 23520.6, 'Avion', 'Hotel Standard', 9, 1),
(2, 'Shanghai', 'Chine', '2026-06-04', '2026-06-30', 11498.220000000001, 'Avion', 'Hotel Standard', 2, 1),
(3, 'Berlin', 'Allemagne', '2026-02-10', '2026-03-01', 22052.4, 'Avion', 'Hotel Standard', 6, 1),
(4, 'Toronto', 'Canada', '2026-02-24', '2026-03-03', 6786.56, 'Avion', 'Hotel Standard', 4, 1),
(5, 'Algiers', 'Algérie', '2026-05-01', '2026-05-09', 6139.84, 'Avion', 'Hotel Standard', 2, 1),
(6, 'Rio de Janeiro', 'Brésil', '2026-05-01', '2026-05-09', 1917.28, 'Avion', 'Hotel Standard', 2, 1),
(7, 'Casablanca', 'Maroc', '2026-04-01', '2026-04-30', 20676, 'Avion', 'Hotel Standard', 5, 1),
(8, 'Algiers', 'Algérie', '2026-05-02', '2026-05-10', 6139.84, 'Avion', 'Hotel Standard', 2, 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activites`
--
ALTER TABLE `activites`
  ADD PRIMARY KEY (`id`),
  ADD KEY `destination_id` (`destination_id`);

--
-- Indexes for table `admin_profile_preferences`
--
ALTER TABLE `admin_profile_preferences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_admin_profile_preferences_email` (`user_email`);

--
-- Indexes for table `atmosphere_destinations`
--
ALTER TABLE `atmosphere_destinations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `destinations`
--
ALTER TABLE `destinations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `factures`
--
ALTER TABLE `factures`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero_facture` (`numero_facture`),
  ADD KEY `idx_numero_facture` (`numero_facture`),
  ADD KEY `idx_client_nom` (`client_nom`),
  ADD KEY `idx_date_emission` (`date_emission`);

--
-- Indexes for table `favorite_packages`
--
ALTER TABLE `favorite_packages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_favorite_package` (`client_email`,`package_key`),
  ADD KEY `idx_favorite_email` (`client_email`),
  ADD KEY `idx_favorite_created` (`created_at`);

--
-- Indexes for table `featured_destinations`
--
ALTER TABLE `featured_destinations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `featured_destination_history`
--
ALTER TABLE `featured_destination_history`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `forum_comments`
--
ALTER TABLE `forum_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_forum_comments_post` (`post_id`),
  ADD KEY `idx_forum_comments_user` (`user_id`),
  ADD KEY `idx_forum_comments_created` (`created_at`);

--
-- Indexes for table `forum_posts`
--
ALTER TABLE `forum_posts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_forum_posts_user` (`user_id`),
  ADD KEY `idx_forum_posts_created` (`created_at`);

--
-- Indexes for table `forum_reactions`
--
ALTER TABLE `forum_reactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_forum_reactions_post_user` (`post_id`,`user_id`),
  ADD KEY `idx_forum_reactions_post` (`post_id`),
  ADD KEY `idx_forum_reactions_user` (`user_id`),
  ADD KEY `idx_forum_reactions_code` (`reaction_code`);

--
-- Indexes for table `forum_stories`
--
ALTER TABLE `forum_stories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_forum_stories_user` (`user_id`),
  ADD KEY `idx_forum_stories_expires` (`expires_at`),
  ADD KEY `idx_forum_stories_created` (`created_at`);

--
-- Indexes for table `forum_story_views`
--
ALTER TABLE `forum_story_views`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_forum_story_views_story_user` (`story_id`,`user_id`),
  ADD UNIQUE KEY `uniq_forum_story_views_story_viewer` (`story_id`,`viewer_key`),
  ADD KEY `idx_forum_story_views_story` (`story_id`),
  ADD KEY `idx_forum_story_views_viewed` (`viewed_at`);

--
-- Indexes for table `historique_paiements`
--
ALTER TABLE `historique_paiements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_package` (`package_id`),
  ADD KEY `idx_client` (`client_email`),
  ADD KEY `idx_statut` (`statut`);

--
-- Indexes for table `logs_statut_packages`
--
ALTER TABLE `logs_statut_packages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_package_id` (`package_id`),
  ADD KEY `idx_date_changement` (`date_changement`);

--
-- Indexes for table `map_destinations`
--
ALTER TABLE `map_destinations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_messages_session_time` (`session_id`,`created_at`);

--
-- Indexes for table `newsletter`
--
ALTER TABLE `newsletter`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `packages`
--
ALTER TABLE `packages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `destination_id` (`destination_id`);

--
-- Indexes for table `paiements`
--
ALTER TABLE `paiements`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference_transaction` (`reference_transaction`),
  ADD KEY `idx_client_nom` (`client_nom`),
  ADD KEY `idx_date_paiement` (`date_paiement`),
  ADD KEY `idx_statut` (`statut`);

--
-- Indexes for table `prompts`
--
ALTER TABLE `prompts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_prompts_key_language` (`prompt_key`,`language`),
  ADD UNIQUE KEY `uq_prompt_key_language` (`prompt_key`,`language`),
  ADD KEY `fk_prompts_active_version` (`active_version_id`);

--
-- Indexes for table `prompt_versions`
--
ALTER TABLE `prompt_versions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_prompt_version` (`prompt_id`,`version`);

--
-- Indexes for table `reclamation`
--
ALTER TABLE `reclamation`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `reponse`
--
ALTER TABLE `reponse`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reclamation_id` (`reclamation_id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `revenus_admin`
--
ALTER TABLE `revenus_admin`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_package` (`package_id`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sessions_user_updated` (`user_id`,`updated_at`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `travel_favorites`
--
ALTER TABLE `travel_favorites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_travel_favorites_user_key` (`user_id`,`favorite_key`),
  ADD KEY `idx_travel_favorites_user` (`user_id`),
  ADD KEY `idx_travel_favorites_created` (`created_at`);

--
-- Indexes for table `travel_packages`
--
ALTER TABLE `travel_packages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_user_email` (`email`),
  ADD KEY `idx_user_role` (`role`);

--
-- Indexes for table `user_notifications`
--
ALTER TABLE `user_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_notifications_recipient` (`recipient_email`),
  ADD KEY `idx_user_notifications_read` (`is_read`),
  ADD KEY `idx_user_notifications_created` (`created_at`);

--
-- Indexes for table `user_notification_preferences`
--
ALTER TABLE `user_notification_preferences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_user_notification_preferences_email` (`user_email`);

--
-- Indexes for table `user_remember_me`
--
ALTER TABLE `user_remember_me`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_user_remember_me_device` (`device_key`),
  ADD KEY `idx_user_remember_me_email` (`user_email`);

--
-- Indexes for table `voyage`
--
ALTER TABLE `voyage`
  ADD PRIMARY KEY (`idVoyage`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activites`
--
ALTER TABLE `activites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=83;

--
-- AUTO_INCREMENT for table `admin_profile_preferences`
--
ALTER TABLE `admin_profile_preferences`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `atmosphere_destinations`
--
ALTER TABLE `atmosphere_destinations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `destinations`
--
ALTER TABLE `destinations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `factures`
--
ALTER TABLE `factures`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `favorite_packages`
--
ALTER TABLE `favorite_packages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `featured_destinations`
--
ALTER TABLE `featured_destinations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `featured_destination_history`
--
ALTER TABLE `featured_destination_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `forum_comments`
--
ALTER TABLE `forum_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `forum_posts`
--
ALTER TABLE `forum_posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `forum_reactions`
--
ALTER TABLE `forum_reactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `forum_stories`
--
ALTER TABLE `forum_stories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `forum_story_views`
--
ALTER TABLE `forum_story_views`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `historique_paiements`
--
ALTER TABLE `historique_paiements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `logs_statut_packages`
--
ALTER TABLE `logs_statut_packages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `map_destinations`
--
ALTER TABLE `map_destinations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `newsletter`
--
ALTER TABLE `newsletter`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `packages`
--
ALTER TABLE `packages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `paiements`
--
ALTER TABLE `paiements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `reclamation`
--
ALTER TABLE `reclamation`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `reponse`
--
ALTER TABLE `reponse`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `revenus_admin`
--
ALTER TABLE `revenus_admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `travel_favorites`
--
ALTER TABLE `travel_favorites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `travel_packages`
--
ALTER TABLE `travel_packages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `user_notifications`
--
ALTER TABLE `user_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `user_notification_preferences`
--
ALTER TABLE `user_notification_preferences`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `user_remember_me`
--
ALTER TABLE `user_remember_me`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=112;

--
-- AUTO_INCREMENT for table `voyage`
--
ALTER TABLE `voyage`
  MODIFY `idVoyage` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activites`
--
ALTER TABLE `activites`
  ADD CONSTRAINT `activites_ibfk_1` FOREIGN KEY (`destination_id`) REFERENCES `destinations` (`id`);

--
-- Constraints for table `historique_paiements`
--
ALTER TABLE `historique_paiements`
  ADD CONSTRAINT `historique_paiements_ibfk_1` FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `fk_messages_session` FOREIGN KEY (`session_id`) REFERENCES `sessions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `packages`
--
ALTER TABLE `packages`
  ADD CONSTRAINT `packages_ibfk_1` FOREIGN KEY (`destination_id`) REFERENCES `destinations` (`id`);

--
-- Constraints for table `prompts`
--
ALTER TABLE `prompts`
  ADD CONSTRAINT `fk_prompts_active_version` FOREIGN KEY (`active_version_id`) REFERENCES `prompt_versions` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `prompt_versions`
--
ALTER TABLE `prompt_versions`
  ADD CONSTRAINT `fk_prompt_versions_prompt` FOREIGN KEY (`prompt_id`) REFERENCES `prompts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reclamation`
--
ALTER TABLE `reclamation`
  ADD CONSTRAINT `reclamation_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reponse`
--
ALTER TABLE `reponse`
  ADD CONSTRAINT `reponse_ibfk_1` FOREIGN KEY (`reclamation_id`) REFERENCES `reclamation` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reponse_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `user` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
