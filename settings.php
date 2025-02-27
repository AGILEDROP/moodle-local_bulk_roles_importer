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

use local_bulk_roles_importer\util\roles_importer_strategies_manager;

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_bulk_roles_importer_settings',
        new lang_string('settings:pagetitle', 'local_bulk_roles_importer')
    );
    $ADMIN->add('localplugins', $settings);

    if ($ADMIN->fulltree) {
        $rolesretrievaloptions = roles_importer_strategies_manager::get_automatic_strategies_names();
        $setting = new admin_setting_configselect('local_bulk_roles_importer/roleretrievalsource',
            new lang_string('label:rolesretrievalsource', 'local_bulk_roles_importer'),
            new lang_string('label:rolesretrievalsourcedescription', 'local_bulk_roles_importer'),
            "github",
            $rolesretrievaloptions);
        $settings->add($setting);

        $taskurl = new moodle_url('/admin/tool/task/scheduledtasks.php', [
            'action' => 'edit',
            'task' => 'local_bulk_roles_importer\task\import_roles'
        ]);
        $settings->add(new admin_setting_configempty(
            'local_bulk_roles_importer/tasklink',
            new lang_string('label:scheduledtasksettings', 'local_bulk_roles_importer'),
            html_writer::link($taskurl, new lang_string('label:scheduledtasksettingsdescription', 'local_bulk_roles_importer'))
        ));

        require_once(__DIR__ . "/settings/settings_github.php");
        require_once(__DIR__ . "/settings/settings_gitlab.php");
        require_once(__DIR__ . "/settings/settings_bitbucket.php");
        require_once(__DIR__ . "/settings/settings_file.php");
    }
}
