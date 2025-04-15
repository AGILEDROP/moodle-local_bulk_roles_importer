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
 * Task to import roles from selected source.
 *
 * File         import_roles.php
 * Encoding     UTF-8
 *
 * @package     local_bulk_roles_importer
 *
 * @copyright   Agiledrop, 2025
 * @author      Agiledrop ltd. <developer@agiledrop.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_bulk_roles_importer\task;

use core\task\scheduled_task;
use local_bulk_roles_importer\roles_importer;
use local_bulk_roles_importer\util\roles_importer_strategies_manager;

/**
 * Define scheduled task for check Moodle users for system roles.
 */
class import_roles extends scheduled_task {

    /**
     * Get task name.
     */
    public function get_name(): \lang_string|string {
        return get_string('label:taskimportroles', 'local_bulk_roles_importer');
    }

    /**
     * Execute scheduled task.
     *
     * @return void
     */
    public function execute(): void {
        $strategy = get_config('local_bulk_roles_importer', 'roleretrievalsource');
        if (!roles_importer_strategies_manager::is_strategy_automatic($strategy)) {
            mtrace(get_string('error:strategy_not_automatic', 'local_bulk_roles_importer', $strategy));
            return;
        }

        $rolesimporter = new roles_importer();
        $rolesimporter->import_roles($strategy);
    }
}
