<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * File plugin settings.
 *
 * File         settings_file.php
 * Encoding     UTF-8
 *
 * @package     local_bulk_roles_importer
 *
 * @copyright   Agiledrop, 2024
 * @author      Agiledrop 2024 <developer@agiledrop.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die('moodle_internal not defined');

// Header - File.
$setting = new admin_setting_heading(
        'local_bulk_roles_importer/file_roles',
        new lang_string('header:file', 'local_bulk_roles_importer'),
        new lang_string('header:fileinfo', 'local_bulk_roles_importer'),
);
$settings_file->add($setting);
$settings_file->hide_if('local_bulk_roles_importer/file_roles',
    'local_bulk_roles_importer/roleretrievalsource',
    'neq',
    'file');

// File - source.
$setting = new admin_setting_configstoredfile(
        'local_bulk_roles_importer/filesource',
        new lang_string('label:filesource', 'local_bulk_roles_importer'),
        new lang_string('label:filesource_help', 'local_bulk_roles_importer'),
        'local_bulk_roles_importer_roles_file',
);
$settings_file->add($setting);
$settings_file->hide_if('local_bulk_roles_importer/filesource',
    'local_bulk_roles_importer/roleretrievalsource',
    'neq',
    'file');
