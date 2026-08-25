# simple-inv

A very simple workshop inventory/asset tracker. Keep track of which shelf, box, drawer or bin that oddly specific tool you need right now but haven't seen for months is kept in.

Very much work in progress. Designed for an average sized home workshop and almost definitely not scaleable to 10,000s or more items.

*Note:* there is no authentication or login process for users so this should only be used on a secure private home network.

## Screenshot

![Inventory Tracker](/assets/screenshot.png?raw=true "Inventory Tracker")

## Features

* Add tools, materials etc and categorise them by type and brand
* Give these items a location in your workshop where they are stored or deployed
* Give these items a status (stored, broken, deployed etc)
* Attach a photo to an item, so you can see the thing you are looking for
* Put an item in more than one category
* Filter and search items by name, part number, notes, brand, supplier, category, location and status
* Track how much of an item is deployed, how much is reserved for projects, and how much is left
* Stock reserves itself against the assemblies that need it, and installing a part takes it out of stock
* Set a reorder level per item and see what is running low on the dashboard
* Get warned when more of an item is committed than you actually hold
* Group items into projects and assemblies, and print a shopping list of what still needs buying
* Print QR labels for locations and items; scanning one opens it in the app
* Import and export items as CSV

### Todo

* Somewhere to record what an item cost and where it came from
* Deployment history, so returning something keeps a record rather than deleting one

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
being filed under the same category twice, restates the quantities assemblies
have reserved now that stock reserves itself, and drops the unused
`item_deployed_loc` column. That last column has not been read or written since
deployments moved into their own table, but check it is empty first if you have
been running this since before then:

```sql
SELECT item_id, item_name, item_deployed_loc FROM inv_items WHERE item_deployed_loc <> '';
```

## Settings

Beyond the database details, `config/user.config.php` takes:

```php
'site' => [
    'title' => 'Inventory Tracker',   // shown in the header and browser tab
    'url'   => 'http://192.168.1.50/inventory/',  // only needed if QR labels
                                                  // guess the wrong address
],

'debug' => false,   // show error details on screen instead of only logging them
```

## QR labels

**Locations &rarr; Labels** prints a code per location, and **Items &rarr; Labels**
prints one per item in the current filter. Scanning a location label opens the
list of what is in it, so a code stuck on a drawer tells you what should be
inside without opening it. The address encoded is worked out from how you
reached the page; set `site.url` if that is not how the workshop reaches this
machine.

## Importing

**Items &rarr; Import** takes a CSV with a heading row using the same columns as
the export, so the simplest route is to export what you have, edit it in a
spreadsheet, and bring it back. `Name`, `Brand`, `Categories`, `Location` and
`Status` are required; separate multiple categories with `|`. Brands, suppliers,
categories, locations and statuses that do not exist yet are created. You get a
preview of what will happen before anything is written.
