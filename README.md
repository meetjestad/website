Meetjestad Website
==================
This git repository contains some of the scripts used on the Meetjestad
website. There are more, which have yet to be added (possibly after some
cleanup).

This repository intentionally does not contain the main website, which is built
using [Hypha](https://github.com/PlanBCode/hypha).

This repository on github can also be used to track bugs and feature requests
concerning the website (also for things regarding the main Hypha-based
website).

Running scripts locally
-----------------------
For development and testing, it can be useful to run these scripts
locally. For this, you need:
 - A MySQL or MariaDB server
 - A webserver running PHP. Apache is recommended because of .htaccess
   support, but not required for all scripts).

To set up the code, make sure to clone this repository somewhere in the
webserver document root. Then:
 - Copy `connect.php.template` to `connect.php` and modify it with the
   right settings to connect to your MySQL/MariaDB server.
 - Edit the `development/setup_database.php` and enable some or all of
   the `$ACTION_` variables. This is a safety feature, to make sure this
   script is a no-op by default and cannot accidentally destroy
   production data.
   If your webbrowser and webserver are not running on the same system,
   you might also need to edit the IP address check at the top of the
   file (by default, it only allows access from localhost as another
   safety measure).
 - Navigate your browser to the `development/setup_database.php` script,
   which will then set up a test database with some random dummy data.

You are encouraged to look around the `setup_database.php` script, since
you might need to modify the generated data for whatever things you want
to test.

The script is also still limited and only generates some types of data.
Pullrequests for additional data generation are welcome.
