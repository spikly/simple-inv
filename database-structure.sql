-- phpMyAdmin SQL Dump
-- version 4.9.7
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Oct 22, 2023 at 06:57 PM
-- Server version: 10.3.29-MariaDB
-- PHP Version: 7.4.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `workshop-inventory-1`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories_items`
--

CREATE TABLE IF NOT EXISTS `categories_items` (
  `cat_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  KEY `cat_id` (`cat_id`),
  KEY `item_id` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `inv_brands`
--

CREATE TABLE IF NOT EXISTS `inv_brands` (
  `brand_id` int(11) NOT NULL AUTO_INCREMENT,
  `brand_name` text NOT NULL,
  PRIMARY KEY (`brand_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `inv_categories`
--

CREATE TABLE IF NOT EXISTS `inv_categories` (
  `cat_id` int(11) NOT NULL AUTO_INCREMENT,
  `cat_name` text NOT NULL,
  `cat_slug` text NOT NULL,
  PRIMARY KEY (`cat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `inv_deployments`
--

CREATE TABLE IF NOT EXISTS `inv_deployments` (
  `dep_id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `dep_item_id` int(11) NOT NULL,
  `dep_quantity` int(11) NOT NULL DEFAULT 1,
  `dep_description` text NOT NULL,
  `dep_timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`dep_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `inv_items`
--

CREATE TABLE IF NOT EXISTS `inv_items` (
  `item_id` int(11) NOT NULL AUTO_INCREMENT,
  `item_loc_id` int(11) NOT NULL,
  `item_brand_id` int(11) NOT NULL,
  `item_sup_id` int(11) DEFAULT NULL,
  `item_status` int(11) NOT NULL,
  `item_name` text NOT NULL,
  `item_quantity` int(11) NOT NULL DEFAULT 1,
  `item_measurement_unit` int(11) NOT NULL DEFAULT 8,
  `item_deployed_loc` text DEFAULT NULL,
  `item_notes` text DEFAULT NULL,
  PRIMARY KEY (`item_id`),
  KEY `item_loc_id` (`item_loc_id`),
  KEY `item_brand_id` (`item_brand_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `inv_locations`
--

CREATE TABLE IF NOT EXISTS `inv_locations` (
  `loc_id` int(11) NOT NULL AUTO_INCREMENT,
  `loc_name` text NOT NULL,
  PRIMARY KEY (`loc_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `inv_measurement_units`
--

CREATE TABLE IF NOT EXISTS `inv_measurement_units` (
  `unit_id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `unit_label` text NOT NULL,
  `unit_symbol` text NOT NULL,
  `unit_type` text NOT NULL,
  PRIMARY KEY (`unit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `inv_measurement_units`
--

INSERT INTO `inv_measurement_units` (`unit_id`, `unit_label`, `unit_symbol`, `unit_type`) VALUES
(1, 'Millimetres', 'mm', 'Length'),
(2, 'Centremetres', 'cm', 'Length'),
(3, 'Metres', 'm', 'Length'),
(4, 'Grams', 'g', 'Weight'),
(5, 'Kilograms', 'kg', 'Weight'),
(6, 'Millilitres', 'ml', 'Volume'),
(7, 'Litres', 'l', 'Volume'),
(8, 'Pieces', 'pcs', 'Piece');

-- --------------------------------------------------------

--
-- Table structure for table `inv_statuses`
--

CREATE TABLE IF NOT EXISTS `inv_statuses` (
  `status_id` int(11) NOT NULL AUTO_INCREMENT,
  `status_name` text NOT NULL,
  PRIMARY KEY (`status_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `inv_suppliers`
--

CREATE TABLE IF NOT EXISTS `inv_suppliers` (
  `sup_id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `sup_name` text NOT NULL,
  `sup_website` text DEFAULT NULL,
  PRIMARY KEY (`sup_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `categories_items`
--
ALTER TABLE `categories_items`
  ADD CONSTRAINT `categories_items_ibfk_1` FOREIGN KEY (`cat_id`) REFERENCES `inv_categories` (`cat_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `categories_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `inv_items` (`item_id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
