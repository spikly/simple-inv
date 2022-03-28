# simple-inv

A very simple workshop inventory/asset tracker. Keep track of which shelf, box, drawer or bin that oddly specific tool you need right now but haven't seen for months is kept in.

Very much work in progress. Designed for an average sized home workshop and almost definitely not scaleable to 10,000s or more items.

*Note:* there is no authentication or login process for users so this should only be used on a secure private home network.

## Features

### Current features

* Add tools, materials etc and categorise them by type and brand
* Give these items a location in your workshop where they are stored
* Give these items a status (stored, broken, deployed etc)

![Inventory Tracker](/assets/screenshot.png?raw=true "Inventory Tracker")

### Todo

* Add a deployed location field
* Improve filtering and search capabilites
* Add ability to edit items/categories/brands etc
* Add ability to delete items/categories/brands etc

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

5) Visit http://yourserver/folder-where-you-copied-the-files to start using it
