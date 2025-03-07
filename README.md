# Bulk Roles Importer #

The Bulk Roles Importer plugin allows you to import multiple roles into Moodle
from XML files, either manually or automatically, using a file upload or a
connected repository.

### Manual Import

Navigate to Site administration > Plugins > Import roles from a file to manually
upload an XML file or a ZIP archive containing multiple XML files with Moodle
role definitions. After uploading, click Import, and the plugin will process the
files and display the results.

### Automatic Import

In Site administration > Plugins > Bulk Roles Importer settings, you can
configure a repository as the source for role XML files and set a schedule for
automatic imports. The plugin will periodically fetch the latest role
definitions and apply them automatically.

## Installing via uploaded ZIP file ##

1. Log in to your Moodle site as an admin and go to _Site administration >
   Plugins > Install plugins_.
2. Upload the ZIP file with the plugin code. You should only be prompted to add
   extra details if your plugin type is not automatically detected.
3. Check the plugin validation report and finish the installation.

## Installing manually ##

The plugin can be also installed by putting the contents of this directory to

    {your/moodle/dirroot}/local/bulk_roles_importer

Afterwards, log in to your Moodle site as an admin and go to _Site administration >
Notifications_ to complete the installation.

Alternatively, you can run

    $ php admin/cli/upgrade.php

to complete the installation from the command line.

## License ##

2025 Agiledrop ltd. <developer@agiledrop.com>

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program.  If not, see <https://www.gnu.org/licenses/>.
