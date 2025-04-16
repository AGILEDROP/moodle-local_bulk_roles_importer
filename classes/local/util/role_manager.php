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
 * Utility class - role manager.
 *
 * File         role_manager.php
 * Encoding     UTF-8
 *
 * @package     local_bulk_roles_importer
 *
 * @copyright   Agiledrop, 2025
 * @author      Agiledrop ltd. <developer@agiledrop.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_bulk_roles_importer\local\util;

use coding_exception;
use context_system;
use core_role_define_role_table_advanced;
use core_role_preset;
use dml_exception;

/**
 * Utility class definition - role manager.
 */
class role_manager {

    /** @var int $lastimport Timestamp of last role importing. */
    private int $lastimport;

    /**
     * Constructor method.
     */
    public function __construct() {
        $this->set_lastimport();
    }

    /**
     * Set last role importing timestamp.
     *
     * @return void
     * @throws dml_exception
     */
    public function set_lastimport(): void {
        $lastimport = get_config('local_bulk_roles_importer', 'roleslastimport');
        if (!$lastimport) {
            $lastimport = -1;
            $this->mark_last_import_failed();
        }
        $this->lastimport = $lastimport;
    }

    /**
     * Get last role importing timestamp.
     *
     * @return int
     */
    public function get_lastimport(): int {
        return $this->lastimport;
    }

    /**
     * Check if role with given shortname exist.
     *
     * @param string $name Role shortname.
     * @return false|mixed Return role data object if exist otherwise false.
     */
    public function role_exist($name): mixed {
        $roles = get_all_roles();

        foreach ($roles as $role) {
            if ($role->shortname == $name) {
                return $role;
            }
        }

        return false;
    }

    /**
     * Update last role importing timestamp in config to mark a successful import.
     *
     * @return void
     */
    public function mark_last_import_success(): void {
        set_config('roleslastimport', time(), 'local_bulk_roles_importer');
    }

    /**
     * Update last role importing timestamp in config to mark a failed import.
     *
     * @return void
     */
    public function mark_last_import_failed(): void {
        set_config('roleslastimport', -1, 'local_bulk_roles_importer');
    }

    /**
     * Create new moodle role from given xml preset.
     *
     * @param string $xml Role definition xml.
     *
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     */
    public function create_role_from_xml($xml): void {
        $options = [
            'shortname' => 1,
            'name' => 1,
            'description' => 1,
            'permissions' => 1,
            'archetype' => 1,
            'contextlevels' => 1,
            'allowassign' => 1,
            'allowoverride' => 1,
            'allowswitch' => 1,
            'allowview' => 1,
        ];
        $context = context_system::instance();
        $roledefiner = new core_role_define_role_table_advanced($context, 0);
        $roledefiner->force_preset($xml, $options);
        $roledefiner->read_submitted_permissions();
        $roledefiner->save_changes();
    }

    /**
     * Update existing Moodle role with given xml preset.
     *
     * @param int $roleid Moodle role id.
     * @param string $xml Role definition xml.
     *
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     */
    public function update_role_from_xml(int $roleid, string $xml): void {

        $preset = core_role_preset::parse_preset($xml);

        // Update general role info.
        $name = $preset['name'] ?? '';
        $shortname = $preset['shortname'] ?? '';
        $description = $preset['description'] ?? '';
        $archetype = $preset['archetype'] ?? '';

        $this->update_role_info($roleid, $name, $shortname, $description, $archetype);

        // Update context levels.
        $contextlevels = $preset['contextlevels'] ?? [];
        $this->update_contextlevels($roleid, $contextlevels);

        // Update allow assign.
        $this->update_allows('assign', $roleid, $preset['allowassign']);

        // Update allow override.
        $this->update_allows('override', $roleid, $preset['allowoverride']);

        // Update allow switch.
        $this->update_allows('switch', $roleid, $preset['allowswitch']);

        // Update  allow view.
        $this->update_allows('view', $roleid, $preset['allowview']);

        // Update permissions.
        $this->update_permissions($roleid, $preset['permissions']);
    }

    /**
     * Update basic Moodle role information.
     *
     * @param int $roleid Moodle role id.
     * @param string $name Visible role name.
     * @param string $shortname Role shortname.
     * @param string $description Role description.
     * @param string $archetype Role archetype.
     *
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     */
    private function update_role_info(int $roleid, string $name, string $shortname, string $description, string $archetype): void {
        global $DB;

        $conditions = [
            'id' => $roleid,
            'name' => $name,
            'description' => $description,
            'archetype' => $archetype,
        ];

        $DB->update_record('role', $conditions);

        \core\event\role_updated::create([
                'objectid' => $roleid,
                'context' => \context_system::instance(),
                'other' => [
                        'name' => $name,
                        'shortname' => $shortname,
                        'description' => $description,
                        'archetype' => $archetype,
                        'contextlevels' => get_role_contextlevels($roleid),
                ],
        ])->trigger();
    }

    /**
     * Update context levels assignments for selected role with given context levels.
     *
     * @param int $roleid Moodle role id.
     * @param array $contextlevels Context levels.
     *
     * @return void
     * @throws dml_exception
     */
    private function update_contextlevels(int $roleid, array $contextlevels): void {
        global $DB;

        // Get current context levels.
        $currentcontexts = get_role_contextlevels($roleid);

        foreach ($currentcontexts as $context) {
            // Check if current context is not in submitted context, delete them.
            if (!in_array((int)$context, $contextlevels)) {
                // Delete context.
                $conditions = [
                    'roleid' => $roleid,
                    'contextlevel' => (int)$context,
                ];
                $DB->delete_records('role_context_levels', $conditions);
            }
        }

        // Get trough submitted context.
        foreach ($contextlevels as $contextlevel) {
            $conditions = [
                'roleid' => $roleid,
                'contextlevel' => (int)$contextlevel,
            ];

            if ($DB->record_exists('role_context_levels', $conditions)) {
                continue;
            }

            $DB->insert_record('role_context_levels', $conditions);
        }
    }

    /**
     * Update moodle allows assignments for selected role.
     *
     * @param string $allowtype Type, ie: assign, override, switch and view.
     * @param int $roleid Moodle role id.
     * @param array $permissions List of assigned allows.
     *
     * @return void
     * @throws dml_exception
     */
    private function update_allows(string $allowtype, int $roleid, array $permissions): void {
        global $DB;

        // Remove value '-1' from permissions.
        foreach ($permissions as $key => $permission) {
            if ($permission == -1) {
                unset($permissions[$key]);
            }
        }

        $tablename = 'role_allow_' . $allowtype;
        $keyname = 'allow' . $allowtype;

        // Get current allows.
        $rows = $DB->get_records($tablename, ['roleid' => $roleid]);
        $currentallows = [];
        foreach ($rows as $row) {
            $currentallows[] = (int)$row->$keyname;
        }

        foreach ($currentallows as $currentallow) {
            // Check if current allow is not in submitted permissions, then remove it.
            if (!in_array($currentallow, $permissions)) {
                $conditions = [
                    'roleid' => $roleid,
                    $keyname => $currentallow,
                ];
                $DB->delete_records($tablename, $conditions);

                // Trigger event for removed role allowance.
                $eventclass = "\\core\\event\\role_allow_{$allowtype}_updated";
                $eventclass::create([
                        'context' => \context_system::instance(),
                        'objectid' => $roleid,
                        'other' => ['targetroleid' => $currentallow, 'allow' => false],
                ])->trigger();
            }
        }

        foreach ($permissions as $permission) {
            $conditions = [
                'roleid' => $roleid,
                $keyname => $permission,
            ];

            if ($DB->record_exists($tablename, $conditions)) {
                continue;
            }

            $DB->insert_record($tablename, $conditions);

            // Trigger event for added role allowance.
            $eventclass = "\\core\\event\\role_allow_{$allowtype}_updated";
            $eventclass::create([
                    'context' => \context_system::instance(),
                    'objectid' => $roleid,
                    'other' => ['targetroleid' => $permission, 'allow' => true],
            ])->trigger();
        }
    }

    /**
     * Update permissions for selected role.
     *
     * @param int $roleid Moodle role id.
     * @param array $permissions Array of permissions.
     *
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     */
    private function update_permissions(int $roleid, array $permissions): void {
        global $DB;

        // Get all defined capabilities in Moodle.
        $definedcapabilities = get_all_capabilities();

        $context = context_system::instance();

        // Current role capabilities.
        $conditions = [
            'roleid' => (int)$roleid,
            'contextid' => $context->id,
        ];
        $rolescaps = $DB->get_records_menu('role_capabilities', $conditions, 'capability,permission');

        // Get through capabilities.
        $changedcapability = 0;
        foreach ($permissions as $capability => $submittedvalue) {
            // Check if capability exist in current moodle.
            $exist = array_key_exists($capability, $definedcapabilities);
            if (!$exist) {
                continue;
            }

            // Check if role has assigned capability.
            $rolehascap = array_key_exists($capability, $rolescaps);
            if ($rolehascap) {
                $currentvalue = (int)$rolescaps[$capability];

                if ($currentvalue != $submittedvalue) {
                    // Change value.
                    assign_capability($capability, $submittedvalue, $roleid, $context, true);
                    $changedcapability ++;
                }
            } else {
                if ($submittedvalue != 0) {
                    // Create new entry for current role.
                    assign_capability($capability, $submittedvalue, $roleid, $context, true);
                    $changedcapability ++;
                }
            }

            unset($submittedvalue);
            unset($currentvalue);
        }
    }

    /**
     * Get role for selected shortname.
     *
     * @param string $shortname Role shortname.
     * @return false|mixed
     */
    public function get_role(string $shortname): mixed {
        $roles = get_all_roles();

        foreach ($roles as $role) {
            if ($role->shortname == $shortname) {
                return $role;
            }
        }

        return false;
    }
}
