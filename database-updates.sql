--
-- Upgrade an existing database to the current structure.
--
--   mariadb -u USER -p YOUR_DATABASE < database-updates.sql
--
-- Safe to run more than once: every statement checks first, so anything
-- already applied is skipped. The IF [NOT] EXISTS clauses are MariaDB
-- syntax -- on MySQL, remove them and run only the parts you still need.
--

-- Manufacturers part number, shown on the item pages.
ALTER TABLE `inv_items`
  ADD COLUMN IF NOT EXISTS `item_part_no` text DEFAULT NULL AFTER `item_name`;

-- Reorder level. Items at or below this free quantity appear as low stock.
ALTER TABLE `inv_items`
  ADD COLUMN IF NOT EXISTS `item_min_quantity` int(11) NOT NULL DEFAULT 0 AFTER `item_quantity`;

-- Uploaded photo filename, relative to assets/uploads/items/.
ALTER TABLE `inv_items`
  ADD COLUMN IF NOT EXISTS `item_image` varchar(255) DEFAULT NULL AFTER `item_notes`;

-- Record when items are added and changed, for the dashboard.
ALTER TABLE `inv_items`
  ADD COLUMN IF NOT EXISTS `item_created_at` timestamp NOT NULL
      DEFAULT current_timestamp() AFTER `item_image`,
  ADD COLUMN IF NOT EXISTS `item_updated_at` timestamp NOT NULL
      DEFAULT current_timestamp() ON UPDATE current_timestamp() AFTER `item_created_at`;

-- Items may now belong to more than one category, so the same pairing must
-- not be stored twice. The table has no primary key, so duplicates are
-- cleared by rebuilding its contents from a DISTINCT copy.
CREATE TEMPORARY TABLE `categories_items_distinct` AS
  SELECT DISTINCT `cat_id`, `item_id` FROM `categories_items`;

DELETE FROM `categories_items`;

INSERT INTO `categories_items` (`cat_id`, `item_id`)
  SELECT `cat_id`, `item_id` FROM `categories_items_distinct`;

DROP TEMPORARY TABLE `categories_items_distinct`;

ALTER TABLE `categories_items`
  ADD UNIQUE KEY IF NOT EXISTS `uniq_cat_item` (`cat_id`,`item_id`);

-- Superseded by the inv_deployments table. Check for anything worth keeping
-- with "SELECT item_id, item_name, item_deployed_loc FROM inv_items
-- WHERE item_deployed_loc <> ''" before running this.
ALTER TABLE `inv_items` DROP COLUMN IF EXISTS `item_deployed_loc`;

-- Reservations against project assemblies are now worked out from stock rather
-- than typed in: a part holds what it still needs (required less installed) as
-- far as the item's free stock goes, oldest part first. This restates every
-- existing quantity_allocated on that basis, so nothing is reserved twice or
-- reserved out of stock that is not there.
--
-- Deployments no longer take a share of stock, so they are not subtracted here
-- either; see the tool sign-out section further down.
--
-- Quantities already recorded as installed are left alone. They are treated as
-- having come out of stock at the time, so this does not go back and take them
-- off item_quantity; only installs made from now on move stock.
UPDATE `inv_assembly_items` ai
  INNER JOIN `inv_items` i ON i.item_id = ai.item_id
  SET ai.quantity_allocated = GREATEST(0, LEAST(
      GREATEST(0, ai.quantity_required - ai.quantity_installed),
      i.item_quantity
        -- What the parts booked before this one have already taken. Reading the
        -- table being updated needs the derived table to copy it first.
        - COALESCE((SELECT SUM(GREATEST(0, e.quantity_required - e.quantity_installed))
                    FROM (SELECT * FROM `inv_assembly_items`) e
                    WHERE e.item_id = ai.item_id
                      AND e.assembly_item_id < ai.assembly_item_id), 0)
  ));

-- Categories now say whether they file parts or tools, which is what decides
-- how the items in them behave. Everything already filed stays a part.
ALTER TABLE `inv_categories`
  ADD COLUMN IF NOT EXISTS `cat_type` enum('part','tool') NOT NULL DEFAULT 'part' AFTER `cat_slug`;

-- Deployments become tool sign-out records.
--
-- Projects and assemblies cover everything deployments were doing for parts,
-- so the table is repurposed for tools instead: who has it, when it is due
-- back and when it came back. Nothing is thrown away, but a deployment against
-- something you go on to file as a part will not be shown anywhere, so it is
-- worth reading them first:
--
--   SELECT d.*, i.item_name FROM inv_deployments d
--     INNER JOIN inv_items i ON i.item_id = d.dep_item_id;
--
-- RENAME TABLE has no IF EXISTS, so it is prepared only when there is still an
-- inv_deployments table to rename.
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

-- A deployment was a quantity against a description; a loan is one tool
-- against whoever has it, so the quantity goes and the description becomes the
-- borrower. Existing rows are left open, ready to be signed back in.
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

-- Deployments were never tied to their item, so deleting an item left its rows
-- behind. Clear any that are already orphaned, then say so in the schema.
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
