--
-- Upgrade an existing database to the current structure.
--
--   mariadb -u USER -p YOUR_DATABASE < database-updates.sql
--
-- Safe to re-run: anything already applied is skipped. IF [NOT] EXISTS is
-- MariaDB syntax; on MySQL, remove it and run only the parts you need.
--

-- Manufacturer's part number.
ALTER TABLE `inv_items`
  ADD COLUMN IF NOT EXISTS `item_part_no` text DEFAULT NULL AFTER `item_name`;

-- Reorder level: items at or below this free quantity show as low stock.
ALTER TABLE `inv_items`
  ADD COLUMN IF NOT EXISTS `item_min_quantity` int(11) NOT NULL DEFAULT 0 AFTER `item_quantity`;

-- Uploaded photo filename, relative to assets/uploads/items/.
ALTER TABLE `inv_items`
  ADD COLUMN IF NOT EXISTS `item_image` varchar(255) DEFAULT NULL AFTER `item_notes`;

-- Created and updated times, for the dashboard.
ALTER TABLE `inv_items`
  ADD COLUMN IF NOT EXISTS `item_created_at` timestamp NOT NULL
      DEFAULT current_timestamp() AFTER `item_image`,
  ADD COLUMN IF NOT EXISTS `item_updated_at` timestamp NOT NULL
      DEFAULT current_timestamp() ON UPDATE current_timestamp() AFTER `item_created_at`;

-- Remove duplicate category pairings (no primary key, so rebuild from a
-- DISTINCT copy), then make them unique.
CREATE TEMPORARY TABLE `categories_items_distinct` AS
  SELECT DISTINCT `cat_id`, `item_id` FROM `categories_items`;

DELETE FROM `categories_items`;

INSERT INTO `categories_items` (`cat_id`, `item_id`)
  SELECT `cat_id`, `item_id` FROM `categories_items_distinct`;

DROP TEMPORARY TABLE `categories_items_distinct`;

ALTER TABLE `categories_items`
  ADD UNIQUE KEY IF NOT EXISTS `uniq_cat_item` (`cat_id`,`item_id`);

-- Superseded by inv_deployments. Check for anything worth keeping first:
--   SELECT item_id, item_name, item_deployed_loc FROM inv_items WHERE item_deployed_loc <> '';
ALTER TABLE `inv_items` DROP COLUMN IF EXISTS `item_deployed_loc`;

-- Recalculate assembly reservations from stock: each part holds what it still
-- needs (required less installed), up to free stock, oldest first.
-- Installed quantities and item_quantity are left alone.
UPDATE `inv_assembly_items` ai
  INNER JOIN `inv_items` i ON i.item_id = ai.item_id
  SET ai.quantity_allocated = GREATEST(0, LEAST(
      GREATEST(0, ai.quantity_required - ai.quantity_installed),
      i.item_quantity
        -- Less what earlier parts took. The derived table lets MySQL read the
        -- table being updated.
        - COALESCE((SELECT SUM(GREATEST(0, e.quantity_required - e.quantity_installed))
                    FROM (SELECT * FROM `inv_assembly_items`) e
                    WHERE e.item_id = ai.item_id
                      AND e.assembly_item_id < ai.assembly_item_id), 0)
  ));

-- Whether a category files parts or tools. Existing categories stay parts.
ALTER TABLE `inv_categories`
  ADD COLUMN IF NOT EXISTS `cat_type` enum('part','tool') NOT NULL DEFAULT 'part' AFTER `cat_slug`;

-- Deployments become tool loans. Deployments against parts will no longer be
-- shown, so review them first:
--
--   SELECT d.*, i.item_name FROM inv_deployments d
--     INNER JOIN inv_items i ON i.item_id = d.dep_item_id;
--
-- RENAME TABLE has no IF EXISTS, so it runs only if inv_deployments exists.
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_deployments') > 0,
  'RENAME TABLE `inv_deployments` TO `inv_tool_loans`',
  'DO 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `inv_tool_loans` (
  `loan_id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `loan_item_id` int(11) NOT NULL,
  `loan_to` varchar(255) NOT NULL,
  `loan_due_at` date DEFAULT NULL,
  `loan_out_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `loan_in_at` datetime DEFAULT NULL,
  `loan_notes` text DEFAULT NULL,
  PRIMARY KEY (`loan_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- The quantity goes and the description becomes the borrower. Existing rows
-- stay open, ready to be signed back in.
ALTER TABLE `inv_tool_loans`
  CHANGE COLUMN IF EXISTS `dep_id` `loan_id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  CHANGE COLUMN IF EXISTS `dep_item_id` `loan_item_id` int(11) NOT NULL,
  CHANGE COLUMN IF EXISTS `dep_description` `loan_to` varchar(255) NOT NULL,
  CHANGE COLUMN IF EXISTS `dep_timestamp` `loan_out_at` timestamp NOT NULL DEFAULT current_timestamp(),
  DROP COLUMN IF EXISTS `dep_quantity`,
  ADD COLUMN IF NOT EXISTS `loan_due_at` date DEFAULT NULL AFTER `loan_to`,
  ADD COLUMN IF NOT EXISTS `loan_in_at` datetime DEFAULT NULL AFTER `loan_out_at`,
  ADD COLUMN IF NOT EXISTS `loan_notes` text DEFAULT NULL AFTER `loan_in_at`,
  ADD KEY IF NOT EXISTS `idx_loan_item` (`loan_item_id`),
  ADD KEY IF NOT EXISTS `idx_loan_open` (`loan_item_id`,`loan_in_at`);

-- Clear orphaned loans, then add the missing foreign key.
DELETE l FROM `inv_tool_loans` l
  LEFT JOIN `inv_items` i ON i.item_id = l.loan_item_id
  WHERE i.item_id IS NULL;

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_tool_loans'
      AND CONSTRAINT_NAME = 'fk_loan_item') > 0,
  'DO 0',
  'ALTER TABLE `inv_tool_loans`
     ADD CONSTRAINT `fk_loan_item` FOREIGN KEY (`loan_item_id`)
     REFERENCES `inv_items` (`item_id`) ON DELETE CASCADE'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Stock movement log. Each part gets an opening row with its current
-- quantity, so the history adds up to the figure on the item.
CREATE TABLE IF NOT EXISTS `inv_stock_movements` (
  `move_id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `move_item_id` int(11) NOT NULL,
  `move_change` decimal(12,3) NOT NULL,
  `move_quantity_after` decimal(12,3) NOT NULL,
  `move_reason` varchar(20) NOT NULL,
  `move_note` varchar(255) DEFAULT NULL,
  `move_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`move_id`),
  KEY `idx_move_item` (`move_item_id`,`move_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_stock_movements'
      AND CONSTRAINT_NAME = 'fk_move_item') > 0,
  'DO 0',
  'ALTER TABLE `inv_stock_movements`
     ADD CONSTRAINT `fk_move_item` FOREIGN KEY (`move_item_id`)
     REFERENCES `inv_items` (`item_id`) ON DELETE CASCADE'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Only items with no history, so re-running adds nothing.
INSERT INTO `inv_stock_movements`
    (`move_item_id`, `move_change`, `move_quantity_after`, `move_reason`, `move_note`)
  SELECT i.item_id, i.item_quantity, i.item_quantity, 'opening', 'Held when the stock log began'
  FROM `inv_items` i
  WHERE NOT EXISTS (SELECT 1 FROM `inv_stock_movements` m WHERE m.move_item_id = i.item_id)
    AND NOT EXISTS (
      SELECT 1 FROM `categories_items` ci
        INNER JOIN `inv_categories` c ON c.cat_id = ci.cat_id
      WHERE ci.item_id = i.item_id AND c.cat_type = 'tool');

-- Nested locations, one level deep (enforced by the app). Existing locations
-- stay top level.
ALTER TABLE `inv_locations`
  ADD COLUMN IF NOT EXISTS `loc_parent_id` int(11) DEFAULT NULL AFTER `loc_name`,
  ADD KEY IF NOT EXISTS `idx_loc_parent` (`loc_parent_id`);

-- A backstop: the app refuses to delete a parent that has anything inside.
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_locations'
      AND CONSTRAINT_NAME = 'fk_loc_parent') > 0,
  'DO 0',
  'ALTER TABLE `inv_locations`
     ADD CONSTRAINT `fk_loc_parent` FOREIGN KEY (`loc_parent_id`)
     REFERENCES `inv_locations` (`loc_id`) ON DELETE RESTRICT ON UPDATE CASCADE'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Optional colour and product page URL.
ALTER TABLE `inv_items`
  ADD COLUMN IF NOT EXISTS `item_colour` varchar(50) DEFAULT NULL AFTER `item_part_no`,
  ADD COLUMN IF NOT EXISTS `item_product_url` varchar(255) DEFAULT NULL AFTER `item_colour`;

-- Supplier websites must now start http:// or https:// (a javascript: link
-- would run when clicked). Add https:// to bare domains only; anything
-- ambiguous is left alone. Safe to re-run.
UPDATE `inv_suppliers`
  SET `sup_website` = CONCAT('https://', `sup_website`)
  WHERE `sup_website` <> ''
    AND `sup_website` NOT LIKE 'http://%'
    AND `sup_website` NOT LIKE 'https://%'
    AND `sup_website` NOT LIKE '%:%'
    AND `sup_website` NOT LIKE '% %'
    AND `sup_website` NOT LIKE '/%'
    AND `sup_website` LIKE '%.%';

-- Anything left must be fixed by hand; until then it shows as plain text.
-- List them with:
--
--   SELECT sup_id, sup_name, sup_website FROM inv_suppliers
--   WHERE sup_website <> '' AND sup_website NOT LIKE 'http://%'
--     AND sup_website NOT LIKE 'https://%';

-- Item documents. Files live in assets/uploads/files/, which must be
-- writable by the webserver, like assets/uploads/items/.
CREATE TABLE IF NOT EXISTS `inv_item_files` (
  `file_id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `file_item_id` int(11) NOT NULL,
  `file_stored_name` varchar(255) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_size` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `file_uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`file_id`),
  KEY `idx_file_item` (`file_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Deleting an item clears its rows; the app removes the files from disk.
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inv_item_files'
      AND CONSTRAINT_NAME = 'fk_file_item') > 0,
  'DO 0',
  'ALTER TABLE `inv_item_files`
     ADD CONSTRAINT `fk_file_item` FOREIGN KEY (`file_item_id`)
     REFERENCES `inv_items` (`item_id`) ON DELETE CASCADE'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- A description for each document, as a filename often says little.
ALTER TABLE `inv_item_files`
  ADD COLUMN IF NOT EXISTS `file_description` varchar(255) DEFAULT NULL AFTER `file_name`;

-- Unique names. VARCHAR because a UNIQUE key over TEXT needs a prefix length;
-- 191 is the longest utf8mb4 column older InnoDB will index whole.
ALTER TABLE `inv_brands`     MODIFY `brand_name`  varchar(191) NOT NULL;
ALTER TABLE `inv_suppliers`  MODIFY `sup_name`    varchar(191) NOT NULL;
ALTER TABLE `inv_statuses`   MODIFY `status_name` varchar(191) NOT NULL;
ALTER TABLE `inv_categories` MODIFY `cat_name`    varchar(191) NOT NULL;
ALTER TABLE `inv_locations`  MODIFY `loc_name`    varchar(191) NOT NULL;

-- Same-named categories of different kinds are renamed apart, not merged,
-- as merging would turn tools into parts.
UPDATE `inv_categories` c
  INNER JOIN (SELECT `cat_name` FROM `inv_categories`
              GROUP BY `cat_name` HAVING COUNT(DISTINCT `cat_type`) > 1) m
    ON m.`cat_name` = c.`cat_name`
  SET c.`cat_name` = CONCAT(c.`cat_name`, ' (', IF(c.`cat_type` = 'tool', 'Tools', 'Parts'), ')'),
      c.`cat_slug` = CONCAT(c.`cat_slug`, '-', c.`cat_type`);

-- Merge remaining duplicates into the lowest id. Names compare by collation,
-- so "Acme" and "acme" merge, as the index will treat them.
UPDATE `inv_items` i
  INNER JOIN `inv_brands` b ON b.`brand_id` = i.`item_brand_id`
  INNER JOIN (SELECT MIN(`brand_id`) AS keep_id, `brand_name` FROM `inv_brands` GROUP BY `brand_name`) k
    ON k.`brand_name` = b.`brand_name`
  SET i.`item_brand_id` = k.keep_id WHERE i.`item_brand_id` <> k.keep_id;

DELETE b FROM `inv_brands` b
  INNER JOIN (SELECT MIN(`brand_id`) AS keep_id, `brand_name` FROM `inv_brands` GROUP BY `brand_name`) k
    ON k.`brand_name` = b.`brand_name`
  WHERE b.`brand_id` <> k.keep_id;

UPDATE `inv_items` i
  INNER JOIN `inv_suppliers` s ON s.`sup_id` = i.`item_sup_id`
  INNER JOIN (SELECT MIN(`sup_id`) AS keep_id, `sup_name` FROM `inv_suppliers` GROUP BY `sup_name`) k
    ON k.`sup_name` = s.`sup_name`
  SET i.`item_sup_id` = k.keep_id WHERE i.`item_sup_id` <> k.keep_id;

DELETE s FROM `inv_suppliers` s
  INNER JOIN (SELECT MIN(`sup_id`) AS keep_id, `sup_name` FROM `inv_suppliers` GROUP BY `sup_name`) k
    ON k.`sup_name` = s.`sup_name`
  WHERE s.`sup_id` <> k.keep_id;

UPDATE `inv_items` i
  INNER JOIN `inv_statuses` st ON st.`status_id` = i.`item_status`
  INNER JOIN (SELECT MIN(`status_id`) AS keep_id, `status_name` FROM `inv_statuses` GROUP BY `status_name`) k
    ON k.`status_name` = st.`status_name`
  SET i.`item_status` = k.keep_id WHERE i.`item_status` <> k.keep_id;

DELETE st FROM `inv_statuses` st
  INNER JOIN (SELECT MIN(`status_id`) AS keep_id, `status_name` FROM `inv_statuses` GROUP BY `status_name`) k
    ON k.`status_name` = st.`status_name`
  WHERE st.`status_id` <> k.keep_id;

-- IGNORE skips pairings that already exist; deleting the duplicate category
-- then cascades the leftovers away.
UPDATE IGNORE `categories_items` ci
  INNER JOIN `inv_categories` c ON c.`cat_id` = ci.`cat_id`
  INNER JOIN (SELECT MIN(`cat_id`) AS keep_id, `cat_name` FROM `inv_categories` GROUP BY `cat_name`) k
    ON k.`cat_name` = c.`cat_name`
  SET ci.`cat_id` = k.keep_id WHERE ci.`cat_id` <> k.keep_id;

DELETE c FROM `inv_categories` c
  INNER JOIN (SELECT MIN(`cat_id`) AS keep_id, `cat_name` FROM `inv_categories` GROUP BY `cat_name`) k
    ON k.`cat_name` = c.`cat_name`
  WHERE c.`cat_id` <> k.keep_id;

-- Location names are unique within their parent. Merge parents first, then
-- sub-locations within their merged parent.
UPDATE `inv_locations` child
  INNER JOIN `inv_locations` p ON p.`loc_id` = child.`loc_parent_id`
  INNER JOIN (SELECT MIN(`loc_id`) AS keep_id, `loc_name` FROM `inv_locations`
              WHERE `loc_parent_id` IS NULL GROUP BY `loc_name`) k
    ON k.`loc_name` = p.`loc_name`
  SET child.`loc_parent_id` = k.keep_id WHERE child.`loc_parent_id` <> k.keep_id;

UPDATE `inv_items` i
  INNER JOIN `inv_locations` l ON l.`loc_id` = i.`item_loc_id`
  INNER JOIN (SELECT MIN(`loc_id`) AS keep_id, `loc_name` FROM `inv_locations`
              WHERE `loc_parent_id` IS NULL GROUP BY `loc_name`) k
    ON k.`loc_name` = l.`loc_name`
  SET i.`item_loc_id` = k.keep_id
  WHERE l.`loc_parent_id` IS NULL AND i.`item_loc_id` <> k.keep_id;

DELETE l FROM `inv_locations` l
  INNER JOIN (SELECT MIN(`loc_id`) AS keep_id, `loc_name` FROM `inv_locations`
              WHERE `loc_parent_id` IS NULL GROUP BY `loc_name`) k
    ON k.`loc_name` = l.`loc_name`
  WHERE l.`loc_parent_id` IS NULL AND l.`loc_id` <> k.keep_id;

UPDATE `inv_items` i
  INNER JOIN `inv_locations` l ON l.`loc_id` = i.`item_loc_id`
  INNER JOIN (SELECT MIN(`loc_id`) AS keep_id, `loc_name`, `loc_parent_id` FROM `inv_locations`
              WHERE `loc_parent_id` IS NOT NULL GROUP BY `loc_name`, `loc_parent_id`) k
    ON k.`loc_name` = l.`loc_name` AND k.`loc_parent_id` = l.`loc_parent_id`
  SET i.`item_loc_id` = k.keep_id WHERE i.`item_loc_id` <> k.keep_id;

DELETE l FROM `inv_locations` l
  INNER JOIN (SELECT MIN(`loc_id`) AS keep_id, `loc_name`, `loc_parent_id` FROM `inv_locations`
              WHERE `loc_parent_id` IS NOT NULL GROUP BY `loc_name`, `loc_parent_id`) k
    ON k.`loc_name` = l.`loc_name` AND k.`loc_parent_id` = l.`loc_parent_id`
  WHERE l.`loc_id` <> k.keep_id;

-- MySQL counts NULLs as distinct, so this stops a repeated sub-location but not
-- two top level locations of the same name; taxonomyNameTaken() covers that.
ALTER TABLE `inv_brands`     ADD UNIQUE KEY IF NOT EXISTS `uq_brand_name`  (`brand_name`);
ALTER TABLE `inv_suppliers`  ADD UNIQUE KEY IF NOT EXISTS `uq_sup_name`    (`sup_name`);
ALTER TABLE `inv_statuses`   ADD UNIQUE KEY IF NOT EXISTS `uq_status_name` (`status_name`);
ALTER TABLE `inv_categories` ADD UNIQUE KEY IF NOT EXISTS `uq_cat_name`    (`cat_name`);
ALTER TABLE `inv_locations`  ADD UNIQUE KEY IF NOT EXISTS `uq_loc_name`    (`loc_name`, `loc_parent_id`);
