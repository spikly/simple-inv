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
-- Quantities already recorded as installed are left alone. They are treated as
-- having come out of stock at the time, so this does not go back and take them
-- off item_quantity; only installs made from now on move stock.
UPDATE `inv_assembly_items` ai
  INNER JOIN `inv_items` i ON i.item_id = ai.item_id
  SET ai.quantity_allocated = GREATEST(0, LEAST(
      GREATEST(0, ai.quantity_required - ai.quantity_installed),
      i.item_quantity
        - COALESCE((SELECT SUM(d.dep_quantity) FROM `inv_deployments` d
                    WHERE d.dep_item_id = ai.item_id), 0)
        -- What the parts booked before this one have already taken. Reading the
        -- table being updated needs the derived table to copy it first.
        - COALESCE((SELECT SUM(GREATEST(0, e.quantity_required - e.quantity_installed))
                    FROM (SELECT * FROM `inv_assembly_items`) e
                    WHERE e.item_id = ai.item_id
                      AND e.assembly_item_id < ai.assembly_item_id), 0)
  ));
