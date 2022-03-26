# ************************************************************
# Sequel Ace SQL dump
# Version 20033
#
# https://sequel-ace.com/
# https://github.com/Sequel-Ace/Sequel-Ace
#
# Host: 127.0.0.1 (MySQL 5.5.5-10.6.4-MariaDB)
# Database: workshop-inventory-1
# Generation Time: 2022-03-26 20:31:08 +0000
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

DROP TABLE IF EXISTS `categories_items`;

CREATE TABLE `categories_items` (
  `cat_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  KEY `cat_id` (`cat_id`),
  KEY `item_id` (`item_id`),
  CONSTRAINT `categories_items_ibfk_1` FOREIGN KEY (`cat_id`) REFERENCES `inv_categories` (`cat_id`),
  CONSTRAINT `categories_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `inv_items` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



# Dump of table inv_categories
# ------------------------------------------------------------

DROP TABLE IF EXISTS `inv_categories`;

CREATE TABLE `inv_categories` (
  `cat_id` int(11) NOT NULL AUTO_INCREMENT,
  `cat_name` text NOT NULL,
  `cat_slug` text NOT NULL,
  PRIMARY KEY (`cat_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4;



# Dump of table inv_items
# ------------------------------------------------------------

DROP TABLE IF EXISTS `inv_items`;

CREATE TABLE `inv_items` (
  `item_id` int(11) NOT NULL AUTO_INCREMENT,
  `item_loc_id` int(11) NOT NULL,
  `item_status` int(11) NOT NULL,
  `item_name` text NOT NULL,
  PRIMARY KEY (`item_id`),
  KEY `item_loc_id` (`item_loc_id`),
  CONSTRAINT `inv_items_ibfk_1` FOREIGN KEY (`item_loc_id`) REFERENCES `inv_locations` (`loc_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4;



# Dump of table inv_locations
# ------------------------------------------------------------

DROP TABLE IF EXISTS `inv_locations`;

CREATE TABLE `inv_locations` (
  `loc_id` int(11) NOT NULL AUTO_INCREMENT,
  `loc_name` text NOT NULL,
  PRIMARY KEY (`loc_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4;



# Dump of table inv_statuses
# ------------------------------------------------------------

DROP TABLE IF EXISTS `inv_statuses`;

CREATE TABLE `inv_statuses` (
  `status_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `status_name` text NOT NULL,
  PRIMARY KEY (`status_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4;




/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
