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
 * Main plugin settings.
 *
 * File         settings_main.php
 * Encoding     UTF-8
 *
 * @package     local_bulk_roles_importer
 *
 * @copyright   Agiledrop, 2024
 * @author      Agiledrop 2024 <developer@agiledrop.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_bulk_roles_importer\util\roles_importer_strategies_manager;

defined('MOODLE_INTERNAL') || die('moodle_internal not defined');

// Source.
$rolesretrievaloptions = roles_importer_strategies_manager::get_automatic_strategies_names();
$setting = new admin_setting_configselect('local_bulk_roles_importer/roleretrievalsource',
    new lang_string('label:rolesretrievalsource', 'local_bulk_roles_importer'),
    new lang_string('label:rolesretrievalsourcedescription', 'local_bulk_roles_importer'),
    "github",
    $rolesretrievaloptions);
$settings_main->add($setting);

// Scheduled task external settings link.
$taskurl = new moodle_url('/admin/tool/task/scheduledtasks.php', [
    'action' => 'edit',
    'task' => 'local_bulk_roles_importer\task\import_roles'
]);
$settings_main->add(new admin_setting_configempty(
    'local_bulk_roles_importer/tasklink',
    new lang_string('label:scheduledtasksettings', 'local_bulk_roles_importer'),
    html_writer::link($taskurl, new lang_string('label:scheduledtasksettingsdescription', 'local_bulk_roles_importer'))
));