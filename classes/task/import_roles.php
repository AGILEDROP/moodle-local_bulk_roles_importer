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

namespace local_bulk_roles_importer\task;

/**
 * Task to import roles from GitLab repository.
 *
 * File         gitlab_roles_import.php
 * Encoding     UTF-8
 *
 * @package     local_bulk_roles_importer
 *
 * @copyright   Agiledrop, 2025
 * @author      Agiledrop 2024 <developer@agiledrop.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\task\scheduled_task;
use local_bulk_roles_importer\roles_importer;

/**
 * Define scheduled task for check Moodle users for system roles.
 */
class import_roles extends scheduled_task {
    /**
     * Get task name.
     */
    public function get_name() {
        return get_string('label:taskimportroles', 'local_bulk_roles_importer');
    }

    /**
     * Execute scheduled task.
     */
    public function execute() {
        $roles_importer = new roles_importer();
        $roles_importer->import_roles();
    }
}
