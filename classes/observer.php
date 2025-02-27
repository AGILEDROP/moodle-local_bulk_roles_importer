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
 * Event observer.
 *
 * @package     local_bulk_roles_importer
 * @category    admin
 * @copyright   2025 Your Name <developer@agiledrop.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Event observer.
 *
 * @package    local_bulk_roles_importer
 * @copyright  2025 Your Name <developer@agiledrop.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_bulk_roles_importer_observer {

    /** @var string plugin name for config */
    const CONFIG_ENABLED_CONFIG_PLUGIN = 'local_bulk_roles_importer';
    /** @var string setting name for config enabled */
    const CONFIG_ENABLED_CONFIG_NAME = 'taskruntimeenabled';
    /** @var string setting name for config hour */
    const CONFIG_HOUR_CONFIG_NAME = 'taskruntimehour';
    /** @var string setting name for config minute */
    const CONFIG_MINUTE_CONFIG_NAME = 'local_bulk_roles_importer/taskruntimeminute';

    /**
     * Synchronises scheduled task settings with plugin settings.
     *
     * @param \core\event\base $event
     */
    public static function syncplugintasksettings(\core\event\config_log_created $event) {
        $eventdata = $event->get_data();
        $changedconfigname = $eventdata['other']['name'];
        $changedconfigplugin = $eventdata['other']['plugin'];
        $changedconfigvalue = $eventdata['other']['value'];
        $task = core\task\manager::get_scheduled_task('\local_bulk_roles_importer\task\import_roles');

        if ($changedconfigplugin === self::CONFIG_ENABLED_CONFIG_PLUGIN) {
            if ($changedconfigname === self::CONFIG_ENABLED_CONFIG_NAME) {
                $task->set_disabled(!$changedconfigvalue);
            } elseif ($changedconfigname === self::CONFIG_HOUR_CONFIG_NAME) {
                $task->set_hour($changedconfigvalue);
            } elseif ($changedconfigname === self::CONFIG_MINUTE_CONFIG_NAME) {
                $task->set_minute($changedconfigvalue);
            }
        }
    }

}
