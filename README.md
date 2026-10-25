# simple-inv

A very simple workshop inventory/asset tracker. Keep track of which shelf, box, drawer or bin that oddly specific tool you need right now but haven't seen for months is kept in.

It holds two kinds of thing. **Parts** are stock you consume: a quantity that projects reserve and installing uses up. **Tools** are single objects you keep: no quantity, just a record of who has one and when it is due back.
Designed for a home workshop and almost definitely not scaleable to 10,000s or more items.

*Note:* there is no authentication or login process for users so this should only be used on a secure private home network.

## Screenshot

![Inventory Tracker](/assets/screenshot.png?raw=true "Inventory Tracker")

### Todo

* Somewhere to record what an item cost and where it came from

## Requirements

A webserver running Apache or Nginx with:

* PHP 8.4+
* MySQL 8+ / MariaDB 10.11+

Older/newer versions may also work fine but are untested.

## To install

1) Create a new database and import the tables found in `database-structure.sql`

2) Copy the files from this repository into a folder on your webserver

3) Duplicate `config/sample.config.php` and rename this new config file to `user.config.php`

4) Enter your database details into the newly created `user.config.php`

5) Make `assets/uploads/` writable by the webserver if you want to attach photos
and documents. Photos are kept in `items/`, documents in `files/` and the
thumbnails of documents that are pictures in `files/thumbs/`, all of which are
created when they are first needed:

```
chmod -R 775 assets/uploads
```

6) Visit http://yourserver/folder-where-you-copied-the-files to start using it

## To upgrade an existing install

Run `database-updates.sql` against your database once:

```
mariadb -u YOUR_USER -p YOUR_DATABASE < database-updates.sql
```

## Settings

Beyond the database details, `config/user.config.php` takes:

```php
'site' => [
    'title'    => 'Inventory Tracker',   // shown in the header and browser tab
    'url'      => 'http://192.168.1.50/inventory/',
    'per_page' => 50,   // rows per page in the listings
],

'debug' => false,   // show error details on screen instead of only logging them
```
