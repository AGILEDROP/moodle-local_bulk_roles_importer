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

require_once('lib.php');

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

        $setting = new admin_setting_configcheckbox(
            'local_bulk_roles_importer/taskruntimeenabled',
            new lang_string('label:taskruntimeenabled', 'local_bulk_roles_importer'),
            new lang_string('label:taskruntimeenableddescription', 'local_bulk_roles_importer'),
            '0',
        );
        $settings->add($setting);
        $settings->hide_if('local_bulk_roles_importer/taskruntimeenabled',
            'local_bulk_roles_importer/roleretrievalsource',
            'eq',
            'file');

        $setting = new admin_setting_configtime(
            'local_bulk_roles_importer/taskruntimehour',
            'local_bulk_roles_importer/taskruntimeminute',
            new lang_string('label:taskruntime', 'local_bulk_roles_importer'),
            new lang_string('label:taskruntimedescription', 'local_bulk_roles_importer'),
            ['h' => 4, 'm' => 0],
        );
        $settings->add($setting);
        $settings->hide_if('local_bulk_roles_importer/taskruntimehour',
            'local_bulk_roles_importer/roleretrievalsource',
            'eq',
            'file');

        require_once(__DIR__ . "/settings/settings_github.php");
        require_once(__DIR__ . "/settings/settings_gitlab.php");
        require_once(__DIR__ . "/settings/settings_bitbucket.php");
        require_once(__DIR__ . "/settings/settings_file.php");
    }
}
