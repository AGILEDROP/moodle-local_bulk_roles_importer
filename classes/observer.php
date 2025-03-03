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

use core\event\config_log_created;
use local_bulk_roles_importer\roles_importer;

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
    const CONFIG_PLUGIN = 'local_bulk_roles_importer';
    /** @var string setting name for config enabled */
    const CONFIG_NAME = 'filesource';

    /**
     * Synchronises scheduled task settings with plugin settings.
     *
     * @param config_log_created $event
     */
    public static function import_roles_from_file_and_remove_file_from_config(config_log_created $event) {
        $eventdata = $event->get_data();
        $changedconfigname = $eventdata['other']['name'];
        $changedconfigplugin = $eventdata['other']['plugin'];
        $changedconfigvalue = $eventdata['other']['value'];

        if (
            $changedconfigplugin === self::CONFIG_PLUGIN
            && $changedconfigname === self::CONFIG_NAME
            && $changedconfigvalue !== ""
        ) {
            $roles_importer = new roles_importer();
            $roles_importer->import_roles('zipball');

            // TODO: (doesn't work) Remove the file and config
            set_config('filesource', null, 'local_bulk_roles_importer');
        }
    }

}
