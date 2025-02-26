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

use coding_exception;
use core\task\scheduled_task;
use core_role_preset;
use dml_exception;
use local_bulk_roles_importer\util\gitlab_api;
use local_bulk_roles_importer\util\role_manager;

/**
 * Define scheduled task for check Moodle users for system roles.
 */
class gitlab_roles_import extends scheduled_task {

    /** @var role_manager $rolemanager Role manager instance. */
    private role_manager $rolemanager;

    /** @var gitlab_api $gitlab Gitlab Api instance. */
    private gitlab_api $gitlab;

    /** @var int $lastimport Last import timestamp. */
    private $lastimport;

    /** @var int $lastchanges Last changes timestamp. */
    private $lastchanges;

    /**
     * Get task name.
     */
    public function get_name() {
        return get_string('label:taskgitlabroles', 'local_bulk_roles_importer');
    }

    /**
     * Execute scheduled task.
     */
    public function execute() {

        $this->rolemanager = new role_manager();
        $this->gitlab = new gitlab_api();

        mtrace('=======================================================================================================');
        mtrace('IMPORT ROLES FROM GITLAB');
        mtrace('=======================================================================================================');

        $rolemanager = new role_manager();
        $gitlab = new gitlab_api();

        $this->lastimport = $rolemanager->get_lastimport();
        $this->lastchanges = $gitlab->get_master_branch_last_updated();

        if (!$this->lastchanges) {
            mtrace('ERROR - cannot obtain master branch last updated time');
        } else {
            $roles = $gitlab->get_roles();
            if (empty($roles)) {
                mtrace('ERROR - cannot obtain roles');
            } else {
                $this->process_import($roles);
            }
        }
    }

    /**
     * Process import roles.
     *
     * @param array $roles Array of roles.
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     */
    private function process_import($roles) {
        $separator = '=======================================================================================================';

        // Loop 1 - in first loop we create roles that do not exist yet.
        foreach ($roles as $role) {
            if (!core_role_preset::is_valid_preset($role->xml)) {
                $role->needupdate = false;
                mtrace('unvalid XML');
                mtrace($separator);
                unset($role);
                continue;
            }

            // Get moodle role.
            $moodlerole = $this->rolemanager->get_role($role->shortname);
            if ($moodlerole) {
                // Check if need update.
                $lastchange = $role->lastchange ?? 0;
                if ($lastchange > $this->lastimport) {
                    $role->needupdate = true;
                } else {
                    $role->needupdate = false;
                }
                continue;
            }

            $role->needupdate = true;
            $this->rolemanager->create_role_from_xml($role->xml);
            $message = '   -' . $role->shortname . ' [created]';
            mtrace($message);
            mtrace($separator);
        }

        // Loop 2 - update roles.
        foreach ($roles as $role) {

            $message = '   -';
            $message .= $role->shortname;

            $needupdate = $role->needupdate ?? false;
            if (!$needupdate) {
                $message .= ' [X]';
            } else {
                // Get moodle role.
                $moodlerole = $this->rolemanager->get_role($role->shortname);
                if (!$moodlerole) {
                    $message .= ' [Role not found]';
                } else {
                    $message .= ' [updated]';
                    $roleid = (int)$moodlerole->id;
                    $this->rolemanager->update_role_from_xml($roleid, $role->xml);
                }
            }

            mtrace($message);
            mtrace('=======================================================================================================');
        }
        $this->rolemanager->update_lastimport(true);
    }
}
