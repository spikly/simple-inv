# simple-inv

A very simple workshop inventory/asset tracker. Keep track of which shelf, box, drawer or bin that oddly specific tool you need right now but haven't seen for months is kept in.

It holds two kinds of thing. **Parts** are stock you consume: a quantity that projects reserve and installing uses up. **Tools** are single objects you keep: no quantity, just a record of who has one and when it is due back. Which kind something is comes from the categories it is filed under, so a category files either parts or tools and everything in it behaves accordingly.

Very much work in progress. Designed for an average sized home workshop and almost definitely not scaleable to 10,000s or more items.

*Note:* there is no authentication or login process for users so this should only be used on a secure private home network.

## Screenshot

![Inventory Tracker](/assets/screenshot.png?raw=true "Inventory Tracker")

## Features

* Mark a category as filing either parts or tools, which is what decides how the things in it behave
* Add parts, tools, materials etc and categorise them by type and manufacturer
* Give these items a location in your workshop where they are kept
* Put sub-locations inside a location, so a set of drawers is one location with a sub-location per drawer
* Give these items a status (stored, broken, on loan etc)
* Attach a photo to an item, so you can see the thing you are looking for
* Put an item in more than one category
* Filter and search items by name, part number, notes, manufacturer, supplier, category, location and status
* Sign a tool out to whoever is borrowing it, with a due date, and sign it back in again
* Keep every sign-out as history, so a tool remembers where it has been
* See what is out and what is overdue on the dashboard
* Take a delivery in or write stock off by typing what changed, rather than working out the new total
* Every change to a part's quantity is recorded with what changed, what it became and why
* Track how much of a part is reserved for projects and how much is left
* Stock reserves itself against the assemblies that need it, and installing a part takes it out of stock
* Set a reorder level per part and see what is running low on the dashboard
* Get warned when more of a part is reserved than you actually hold
* Group parts into projects and assemblies, and print a shopping list of what still needs buying
* Print QR labels for locations and items; scanning one opens it in the app
* Import and export items as CSV
* Long listings are split into pages, so a few hundred parts stay quick to load

### Todo

* Somewhere to record what an item cost and where it came from

## Requirements

A webserver running Apache or Nginx with:

* PHP 7.4
* MySQL 5.6 / MariaDB 10

Older/newer versions may also and probably will work fine but are untested.

## To install

1) Create a new database and import the tables found in `database-structure.sql`

2) Copy the files from this repository into a folder on your webserver

3) Duplicate `config/sample.config.php` and rename this new config file to `user.config.php`

4) Enter your database details into the newly created `user.config.php`

5) Make `assets/uploads/items/` writable by the webserver if you want to attach photos:

```
chmod -R 775 assets/uploads
```

6) Visit http://yourserver/folder-where-you-copied-the-files to start using it

## To upgrade an existing install

Run `database-updates.sql` against your database once:

```
mariadb -u YOUR_USER -p YOUR_DATABASE < database-updates.sql
```

It is safe to run more than once, so anything already applied is skipped. It
adds the part number, reorder level, photo and timestamp columns, stops an item
being filed under the same category twice, adds the part/tool flag to
categories, turns the deployments table into the tool sign-out table, adds the
stock movement log, restates the quantities assemblies have reserved, adds the
sub-location column to locations, and drops
the unused `item_deployed_loc` column. That last column has not been read or written since
deployments moved into their own table, but check it is empty first if you have
been running this since before then:

```sql
SELECT item_id, item_name, item_deployed_loc FROM inv_items WHERE item_deployed_loc <> '';
```

### Deployments have gone

Projects and assemblies cover everything deployments were doing for parts, so
the table is now what tools are signed out in. Read what is in it before you
upgrade, because a deployment quantity has nowhere to go once one row means one
tool with one borrower:

```sql
SELECT d.dep_id, i.item_name, d.dep_quantity, d.dep_description, d.dep_timestamp
FROM inv_deployments d INNER JOIN inv_items i ON i.item_id = d.dep_item_id;
```

Nothing is deleted. Each deployment becomes an open sign-out, with its
description read as whoever has the thing, and the quantity dropped. Every
category starts out filing parts, so those rows sit dormant until you mark some
categories as tool categories; anything still against a part after that is
history that will not be shown, and you can clear it with:

```sql
DELETE l FROM inv_tool_loans l
  WHERE NOT EXISTS (
    SELECT 1 FROM categories_items ci
      INNER JOIN inv_categories c ON c.cat_id = ci.cat_id
      WHERE ci.item_id = l.loan_item_id AND c.cat_type = 'tool');
```

Reservations against assemblies are restated at the same time. A deployment
used to hold stock back from projects and no longer does, so a part that was
short because something was deployed will now reserve what it needs.

### Where the stock history starts

Nothing that happened before the upgrade can be recovered, so every item is
given one opening row saying what it held at the time. That way the history
adds up to the figure on the item instead of starting from an unexplained
number.

Every category starts out filing parts, so items you go on to mark as tools get
an opening row too. Tools have no stock and never show the history, so those
rows do nothing, but they can be cleared once the categories are sorted:

```sql
DELETE m FROM inv_stock_movements m
  WHERE EXISTS (
    SELECT 1 FROM categories_items ci
      INNER JOIN inv_categories c ON c.cat_id = ci.cat_id
      WHERE ci.item_id = m.move_item_id AND c.cat_type = 'tool');
```

### Sorting your categories out

Once upgraded, go to **Categories** and set the ones that file tools to
**Tools**. Switching a category converts everything filed under it, so you can
do this whenever you like rather than only while it is empty. An item is a tool
when its categories are tool categories, and every category on an item has to
agree.

A switch is refused where an item in the category could not make the move on
its own: one that is also filed under a category of the other kind, one whose
stock a project has reserved, or a tool that has been signed out before. The
category page names the items so you know what to sort out. Anything becoming a
tool loses its quantity and reorder level, since a tool has no stock.

## Locations and sub-locations

A location can sit inside one other location, so a set of drawers is stored as
the chest plus a location per drawer, and the item is filed in the drawer rather
than in a location of its own. Not every location needs them: one with nothing
inside it works exactly as it always has.

Add one from **Locations** with the **Add sub-location** link on the row it goes
inside, or by picking what it is **Inside** on the add and edit forms. Nesting
stops there: a location that already holds sub-locations is not offered one of
its own, so a name is never more than two deep, and a location cannot be deleted
while anything is still inside it.

Asking for what is in a location includes everything in its sub-locations, so
the chest lists what is in all of its drawers and each drawer lists only its
own.

## Settings

Beyond the database details, `config/user.config.php` takes:

```php
'site' => [
    'title'    => 'Inventory Tracker',   // shown in the header and browser tab
    'url'      => 'http://192.168.1.50/inventory/',  // only needed if QR labels
                                                     // guess the wrong address
    'per_page' => 50,   // rows per page in the listings
],

'debug' => false,   // show error details on screen instead of only logging them
```

## QR labels

**Locations &rarr; Labels** prints a code per location, sub-locations included
and each named in full, and the **Labels** link
on the Parts and Tools pages prints one per item in the current filter.
Scanning a location label opens the list of what is in it, so a code stuck on a
drawer tells you what should be inside without opening it, and one on the chest
covers every drawer in it. The address encoded is worked out from how you
reached the page; set `site.url` if that is not how the workshop reaches this
machine.

## Importing

**Parts &rarr; Import** takes a CSV with a heading row using the same columns as
the export, so the simplest route is to export what you have, edit it in a
spreadsheet, and bring it back. `Name`, `Manufacturer`, `Categories`, `Location`
and `Status` are required; separate multiple categories with `|`. Name a
sub-location with both locations, as `Tool Chest > Drawer 1`, which is how the
export writes one; a single name is always a location of its own. A `Brand`
column from an older export is read as `Manufacturer`. Manufacturers, suppliers,
categories, locations and statuses that do not exist yet are created.

`Type` is `Part` or `Tool` and defaults to `Part`. It decides which kind any
categories the file creates will file, and a row naming a category that already
files the other kind is skipped rather than guessed at. Tools are stored as one
object whatever quantity the file gives them.

You get a preview of what will happen before anything is written.
