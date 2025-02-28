<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin administration pages are defined here.
 *
 * @package     local_bulk_roles_importer
 * @category    admin
 * @copyright   2025 Your Name <developer@agiledrop.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings_main = new admin_settingpage(
        'local_bulk_roles_importer_settings_main',
        new lang_string('settings:pagetitlemain', 'local_bulk_roles_importer')
    );
    $ADMIN->add('localplugins', $settings_main);
    $settings_file = new admin_settingpage(
        'local_bulk_roles_importer_settings_file',
        new lang_string('settings:pagetitlefile', 'local_bulk_roles_importer')
    );
    $ADMIN->add('localplugins', $settings_file);

    if ($ADMIN->fulltree) {
        // Main settings page
        require_once(__DIR__ . "/settings/settings_main.php");
        require_once(__DIR__ . "/settings/settings_github.php");
        require_once(__DIR__ . "/settings/settings_gitlab.php");
        require_once(__DIR__ . "/settings/settings_bitbucket.php");

        // File import settings page
        require_once(__DIR__ . "/settings/settings_file.php");
    }
}
