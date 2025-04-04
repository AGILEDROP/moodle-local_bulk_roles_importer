# Bulk Roles Importer #

Bulk Roles Importer is a Moodle plugin that enables administrators to import multiple roles into Moodle.
Role definitions are provided in XML format, and you can choose to either upload the files manually or
configure an external repository for automatic updates.

### Features ###
-  **Manual Import:**
Upload a single XML file or a ZIP archive containing multiple XML files with role definitions.

- **Automatic Import:**
Configure a repository (e.g. GitHub, GitLab) as the source for role XML files and define a schedule for periodic imports.
At each scheduled time, the plugin will fetch the latest role definitions and update Moodle roles accordingly.

### Manual Import

Navigate to _Site administration > Users > Permissions > Import roles from a file_ to manually upload an XML file
or a ZIP archive containing multiple XML files with Moodle role definitions. After uploading, click Import.
The plugin will process each file and display the results. If a file is not in the correct XML format
(i.e. the XML does not contain a <role> element),
the plugin will log an error message with the file name and skip that file.

### Automatic Import

In _Site administration > Users > Permissions > Bulk Roles Importer settings_, you can configure a repository
(for example, GitHub, GitLab) as the source for your role XML files.
You can also set a schedule for automatic imports so that the plugin periodically checks the repository
for updated role definitions and applies the changes automatically.
Detailed logging is provided during the import process, including notifications of successful role creation,
updates, and any errors encountered with specific files.

### Example Role XML

For your convenience, an example role XML file is provided in the `/samples` directory. This sample demonstrates
a simplified role definition that includes only a few key permissions, making it easier to customize for your needs.

## Installing via uploaded ZIP file ##

1. Log in to your Moodle site as an admin and go to _Site administration >
   Plugins > Install plugins_.
2. Upload the ZIP file with the plugin code. You should only be prompted to add
   extra details if your plugin type is not automatically detected.
3. Check the plugin validation report and finish the installation.

## Installing manually ##

The plugin can be also installed by putting the contents of this directory to

    {your/moodle/dirroot}/local/bulk_roles_importer

Afterwards, log in to your Moodle site as an admin and go to _Site
administration > Notifications_ to complete the installation.

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
