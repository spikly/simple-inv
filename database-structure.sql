# ************************************************************
# Sequel Ace SQL dump
# Version 20056
#
# https://sequel-ace.com/
# https://github.com/Sequel-Ace/Sequel-Ace
#
# Host: 127.0.0.1 (MySQL 5.5.5-10.6.4-MariaDB)
# Database: workshop-inventory-1
# Generation Time: 2023-10-21 17:23:16 +0000
# ************************************************************


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
SET NAMES utf8mb4;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE='NO_AUTO_VALUE_ON_ZERO', SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


# Dump of table categories_items
# ------------------------------------------------------------

CREATE TABLE `categories_items` (
  `cat_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  KEY `cat_id` (`cat_id`),
  KEY `item_id` (`item_id`),
  CONSTRAINT `categories_items_ibfk_1` FOREIGN KEY (`cat_id`) REFERENCES `inv_categories` (`cat_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `categories_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `inv_items` (`item_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



# Dump of table inv_brands
# ------------------------------------------------------------

CREATE TABLE `inv_brands` (
  `brand_id` int(11) NOT NULL AUTO_INCREMENT,
  `brand_name` text NOT NULL,
  PRIMARY KEY (`brand_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



# Dump of table inv_categories
# ------------------------------------------------------------

CREATE TABLE `inv_categories` (
  `cat_id` int(11) NOT NULL AUTO_INCREMENT,
  `cat_name` text NOT NULL,
  `cat_slug` text NOT NULL,
  PRIMARY KEY (`cat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



# Dump of table inv_deployments
# ------------------------------------------------------------

CREATE TABLE `inv_deployments` (
  `dep_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `dep_item_id` int(11) NOT NULL,
  `dep_quantity` int(11) NOT NULL DEFAULT 1,
  `dep_description` text NOT NULL,
  `dep_timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`dep_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



# Dump of table inv_items
# ------------------------------------------------------------

CREATE TABLE `inv_items` (
  `item_id` int(11) NOT NULL AUTO_INCREMENT,
  `item_loc_id` int(11) NOT NULL,
  `item_brand_id` int(11) NOT NULL,
  `item_sup_id` int(11) DEFAULT NULL,
  `item_status` int(11) NOT NULL,
  `item_name` text NOT NULL,
  `item_quantity` int(11) NOT NULL DEFAULT 1,
  `item_deployed_loc` text DEFAULT NULL,
  `item_notes` text DEFAULT NULL,
  PRIMARY KEY (`item_id`),
  KEY `item_loc_id` (`item_loc_id`),
  KEY `item_brand_id` (`item_brand_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



# Dump of table inv_locations
# ------------------------------------------------------------

CREATE TABLE `inv_locations` (
  `loc_id` int(11) NOT NULL AUTO_INCREMENT,
  `loc_name` text NOT NULL,
  PRIMARY KEY (`loc_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



# Dump of table inv_statuses
# ------------------------------------------------------------

CREATE TABLE `inv_statuses` (
  `status_id` int(11) NOT NULL AUTO_INCREMENT,
  `status_name` text NOT NULL,
  PRIMARY KEY (`status_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



# Dump of table inv_suppliers
# ------------------------------------------------------------

CREATE TABLE `inv_suppliers` (
  `sup_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `sup_name` text NOT NULL,
  `sup_website` text DEFAULT NULL,
  PRIMARY KEY (`sup_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;




/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
