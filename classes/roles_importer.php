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

namespace local_bulk_roles_importer;

/**
 * Context for roles importing strategies.
 *
 * File         roles_importer.php
 * Encoding     UTF-8
 *
 * @package     local_bulk_roles_importer
 *
 * @copyright   Agiledrop, 2025
 * @author      Agiledrop ltd. <developer@agiledrop.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use coding_exception;
use core_role_preset;
use dml_exception;
use local_bulk_roles_importer\util\role_manager;
use local_bulk_roles_importer\util\roles_importer_strategies_manager;
use local_bulk_roles_importer\util\roles_importer_strategy_interface;

/**
 * Roles importing strategy context.
 */
class roles_importer {

    /** @var role_manager $rolemanager Role manager instance. */
    private role_manager $rolemanager;

    /** @var int $lastimport Last import timestamp. */
    private int $lastimport;

    /** @var int $lastchanges Last changes timestamp. */
    private int $lastchanges;

    /** @var roles_importer_strategy_interface $rolesimportstrategy Last changes timestamp. */
    private roles_importer_strategy_interface $rolesimportstrategy;

    /** @var roles_importer_strategy_interface[] $rolesimportstrategies All available roles importer strateties. */
    private array $rolesimportstrategies;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->rolemanager = new role_manager();
        $this->rolesimportstrategies = roles_importer_strategies_manager::get_strategies_classes();
    }

    /**
     * Execute scheduled task.
     */
    public function import_roles($strategy = null) {
        if ($strategy === null) {
            $strategy = get_config('local_bulk_roles_importer', 'roleretrievalsource');
        }
        if (!class_exists($this->rolesimportstrategies[$strategy])) {
            mtrace('ERROR - source: ' . $strategy . ' does not exist');
            return;
        }

        mtrace('=======================================================================================================');
        mtrace('  IMPORT ROLES FROM SOURCE: ' . $strategy);
        mtrace('=======================================================================================================');

        $this->lastimport = $this->rolemanager->get_lastimport();

        $this->rolesimportstrategy = new $this->rolesimportstrategies[$strategy]();
        $this->lastchanges = $this->rolesimportstrategy->get_last_updated();

        if (!$this->lastchanges) {
            mtrace('ERROR - cannot obtain master branch last updated time');
            return;
        }

        $roles = $this->rolesimportstrategy->get_roles();

        if (empty($roles)) {
            mtrace('ERROR - cannot obtain roles');
            return;
        }

        $this->process_import($roles);
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
                mtrace('invalid XML');
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
