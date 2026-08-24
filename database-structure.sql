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


CREATE TABLE IF NOT EXISTS inv_project_statuses (
    project_status_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_status_name VARCHAR(100) NOT NULL,
    PRIMARY KEY (project_status_id),
    UNIQUE KEY uq_project_status_name (project_status_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO inv_project_statuses
    (project_status_name)
VALUES
    ('Planning'),
    ('Active'),
    ('On Hold'),
    ('Complete'),
    ('Archived');


CREATE TABLE IF NOT EXISTS inv_projects (
    project_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_name VARCHAR(255) NOT NULL,
    project_reference VARCHAR(100) NULL,
    project_description TEXT NULL,
    project_status_id INT UNSIGNED NOT NULL,
    project_notes TEXT NULL,
    project_created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    project_updated TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (project_id),
    KEY idx_project_status (project_status_id),

    CONSTRAINT fk_project_status
        FOREIGN KEY (project_status_id)
        REFERENCES inv_project_statuses(project_status_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS inv_project_assemblies (
    assembly_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    assembly_project_id INT UNSIGNED NOT NULL,
    assembly_name VARCHAR(255) NOT NULL,
    assembly_description TEXT NULL,
    assembly_notes TEXT NULL,
    assembly_sort_order INT NOT NULL DEFAULT 0,
    assembly_created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    assembly_updated TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (assembly_id),
    KEY idx_assembly_project (assembly_project_id),

    CONSTRAINT fk_assembly_project
        FOREIGN KEY (assembly_project_id)
        REFERENCES inv_projects(project_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS inv_assembly_items (
    assembly_item_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    assembly_id INT UNSIGNED NOT NULL,
    item_id INT NOT NULL,
    quantity_required DECIMAL(12,3) NOT NULL DEFAULT 1,
    quantity_allocated DECIMAL(12,3) NOT NULL DEFAULT 0,
    quantity_installed DECIMAL(12,3) NOT NULL DEFAULT 0,
    assembly_item_notes TEXT NULL,
    assembly_item_sort_order INT NOT NULL DEFAULT 0,

    PRIMARY KEY (assembly_item_id),

    UNIQUE KEY uq_assembly_item (
        assembly_id,
        item_id
    ),

    KEY idx_assembly_item_assembly (assembly_id),
    KEY idx_assembly_item_item (item_id),

    CONSTRAINT fk_assembly_item_assembly
        FOREIGN KEY (assembly_id)
        REFERENCES inv_project_assemblies(assembly_id)
        ON DELETE CASCADE,

    CONSTRAINT fk_assembly_item_item
        FOREIGN KEY (item_id)
        REFERENCES inv_items(item_id)
        ON DELETE CASCADE
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
