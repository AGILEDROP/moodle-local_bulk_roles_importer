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
use core_role_preset;
use core_text;
use dml_exception;
use moodle_exception;

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
        return $this->get_role((string)$name);
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
     * Normalise a role shortname the same way Moodle parses a role preset.
     *
     * @param string $shortname Raw shortname.
     * @return string Normalised shortname (may be empty when nothing valid remains).
     */
    public static function normalise_shortname(string $shortname): string {
        return strtolower(clean_param($shortname, PARAM_ALPHANUMEXT));
    }

    /**
     * Parse a preset and validate its normalised shortname.
     *
     * @param string $xml Role definition xml.
     * @return array Parsed preset with the normalised shortname.
     * @throws moodle_exception When the preset or its shortname is invalid.
     */
    protected function parse_and_validate_preset(string $xml): array {
        $preset = core_role_preset::parse_preset($xml);
        if (!is_array($preset)) {
            throw new moodle_exception('error:invalid_xml', 'local_bulk_roles_importer');
        }

        $shortname = self::normalise_shortname((string)($preset['shortname'] ?? ''));
        if ($shortname === '' || core_text::strlen($shortname) > 100) {
            throw new moodle_exception(
                'error:invalid_shortname',
                'local_bulk_roles_importer',
                '',
                (string)($preset['shortname'] ?? '')
            );
        }
        $preset['shortname'] = $shortname;

        return $preset;
    }

    /**
     * Ensure the target role exists and matches the preset shortname.
     *
     * @param int $roleid Moodle role id.
     * @param string $shortname Normalised preset shortname.
     * @return void
     * @throws moodle_exception
     */
    protected function validate_target_role(int $roleid, string $shortname): void {
        global $DB;

        $current = $DB->get_field('role', 'shortname', ['id' => $roleid], MUST_EXIST);
        if ($current !== $shortname) {
            throw new moodle_exception('error:shortname_mismatch', 'local_bulk_roles_importer', '', $shortname);
        }
    }

    /**
     * Run the given callback inside a delegated transaction, rolling back on any failure.
     *
     * @param callable $callback Work to run.
     * @return void
     * @throws \Throwable Rethrows the original failure after rollback.
     */
    protected function run_in_transaction(callable $callback): void {
        global $DB;

        $transaction = $DB->start_delegated_transaction();
        try {
            $callback();
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }
    }

    /**
     * Create new moodle role from given xml preset.
     *
     * Role data, context levels and permissions are written directly from the parsed
     * preset in a single transaction, so a failure leaves no partial role behind and
     * nothing depends on role-editor request parameters. Allow lists are linked later
     * with update_role_allows_from_xml(), so they can reference roles created after
     * this one in the same batch.
     *
     * @param string $xml Role definition xml.
     *
     * @return int New role id.
     * @throws moodle_exception
     */
    public function create_role_from_xml(string $xml): int {
        global $DB;

        $preset = $this->parse_and_validate_preset($xml);
        if ($DB->record_exists('role', ['shortname' => $preset['shortname']])) {
            throw new moodle_exception('error:shortname_exists', 'local_bulk_roles_importer', '', $preset['shortname']);
        }

        $roleid = 0;
        $this->run_in_transaction(function () use ($preset, &$roleid): void {
            $roleid = (int)create_role(
                (string)($preset['name'] ?? ''),
                $preset['shortname'],
                (string)($preset['description'] ?? ''),
                (string)($preset['archetype'] ?? '')
            );
            $this->update_contextlevels($roleid, $preset['contextlevels'] ?? null);
            $this->update_permissions($roleid, $preset['permissions'] ?? []);
        });

        return $roleid;
    }

    /**
     * Update existing Moodle role with given xml preset.
     *
     * All changes run in one transaction. Context levels and allow lists are only
     * changed when their XML section is present; an empty section clears them.
     *
     * @param int $roleid Moodle role id.
     * @param string $xml Role definition xml.
     *
     * @return void
     * @throws moodle_exception
     */
    public function update_role_from_xml(int $roleid, string $xml): void {
        $preset = $this->parse_and_validate_preset($xml);
        $this->validate_target_role($roleid, $preset['shortname']);

        $this->run_in_transaction(function () use ($roleid, $preset): void {
            $this->update_role_info(
                $roleid,
                (string)($preset['name'] ?? ''),
                $preset['shortname'],
                (string)($preset['description'] ?? ''),
                (string)($preset['archetype'] ?? '')
            );
            $this->update_contextlevels($roleid, $preset['contextlevels'] ?? null);
            $this->apply_allows($roleid, $preset);
            $this->update_permissions($roleid, $preset['permissions'] ?? []);
        });
    }

    /**
     * Update only the allow lists of an existing role from the given xml preset.
     *
     * Used for roles created earlier in the same import, so references to roles
     * created later in the batch can be resolved without rewriting the whole role.
     *
     * @param int $roleid Moodle role id.
     * @param string $xml Role definition xml.
     * @return void
     * @throws moodle_exception
     */
    public function update_role_allows_from_xml(int $roleid, string $xml): void {
        $preset = $this->parse_and_validate_preset($xml);
        $this->validate_target_role($roleid, $preset['shortname']);

        $this->run_in_transaction(function () use ($roleid, $preset): void {
            $this->apply_allows($roleid, $preset);
        });
    }

    /**
     * Apply all allow lists present in the parsed preset.
     *
     * @param int $roleid Moodle role id.
     * @param array $preset Parsed preset.
     * @return void
     */
    protected function apply_allows(int $roleid, array $preset): void {
        foreach (['assign', 'override', 'switch', 'view'] as $allowtype) {
            $this->update_allows($allowtype, $roleid, $preset['allow' . $allowtype] ?? null);
        }
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
    protected function update_role_info(int $roleid, string $name, string $shortname, string $description, string $archetype): void {
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
     * @param array|null $contextlevels Context levels, or null to keep the current ones.
     *
     * @return void
     * @throws dml_exception
     */
    protected function update_contextlevels(int $roleid, ?array $contextlevels): void {
        global $DB;

        // Section omitted from the preset: keep current context levels.
        if ($contextlevels === null) {
            return;
        }

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
     * @param array|null $permissions Allowed role ids, or null to keep the current list.
     *
     * @return void
     * @throws dml_exception
     */
    protected function update_allows(string $allowtype, int $roleid, ?array $permissions): void {
        global $DB;

        // Section omitted from the preset: keep current allow list.
        if ($permissions === null) {
            return;
        }

        // The preset parser uses -1 for "this role"; resolve it to the role id.
        $resolved = [];
        foreach ($permissions as $permission) {
            $permission = (int)$permission;
            $resolved[] = ($permission === -1) ? $roleid : $permission;
        }
        $permissions = array_values(array_unique($resolved));

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
    protected function update_permissions(int $roleid, array $permissions): void {
        global $DB;

        // Get all defined capabilities in Moodle.
        $definedcapabilities = get_all_capabilities();

        $context = context_system::instance();

        // Current role capabilities.
        $conditions = [
            'roleid' => (int)$roleid,
            'contextid' => $context->id,
        ];
        $rolescaps = $DB->get_records_menu('role_capabilities', $conditions, '', 'capability,permission');

        foreach ($permissions as $capability => $submittedvalue) {
            // Skip capabilities that do not exist in this Moodle.
            if (!array_key_exists($capability, $definedcapabilities)) {
                continue;
            }

            $submittedvalue = (int)$submittedvalue;
            $rolehascap = array_key_exists($capability, $rolescaps);

            if ($submittedvalue === CAP_INHERIT) {
                // Explicit inherit: remove any existing system-context override.
                if ($rolehascap) {
                    unassign_capability($capability, $roleid, $context->id);
                }
                continue;
            }

            if (!$rolehascap || (int)$rolescaps[$capability] !== $submittedvalue) {
                assign_capability($capability, $submittedvalue, $roleid, $context, true);
            }
        }
    }

    /**
     * Get role for selected shortname.
     *
     * @param string $shortname Role shortname.
     * @return false|mixed
     */
    public function get_role(string $shortname): mixed {
        global $DB;

        $shortname = self::normalise_shortname($shortname);
        if ($shortname === '') {
            return false;
        }

        return $DB->get_record('role', ['shortname' => $shortname]);
    }
}
