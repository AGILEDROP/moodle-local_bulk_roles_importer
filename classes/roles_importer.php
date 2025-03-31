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

namespace local_bulk_roles_importer;

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

    /** @var string[] $LOGGING_STYLES Array of valid logging styles. */
    public const LOGGING_STYLES = [
        'task',
        'web',
    ];

    /** @var string $loggingstyle Selected logging style. */
    private string $loggingstyle;

    /** @var string $SEPARATOR Separator for logs. */
    private const SEPARATOR = '===================================================================================================';

    /**
     * Constructor.
     *
     * @param string $loggingstyle
     */
    public function __construct(string $loggingstyle = 'task') {
        $this->rolemanager = new role_manager();
        $this->rolesimportstrategies = roles_importer_strategies_manager::get_strategies_classes();
        if (in_array($loggingstyle, self::LOGGING_STYLES, true)) {
            $this->loggingstyle = $loggingstyle;
        } else {
            $this->loggingstyle = 'task';
        }
    }

    /**
     * Write out logs in preferred style.
     *
     * @param string $message The message to log.
     * @return void
     */
    private function log_message(string $message): void {
        switch ($this->loggingstyle) {
            case 'task':
                mtrace($message);
                break;
            case 'web':
                echo '<p class="message">' . $message . '</p>';
                break;
        }
    }

    /**
     * Execute scheduled task.
     *
     * @param string|null $strategy Name of the strategy to use for importing
     * roles. Default is NULL which uses the currently selected automatic
     * strategy which you can select in the settings.
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     */
    public function import_roles(string|null $strategy = null): void {
        if ($strategy === null) {
            $strategy = get_config('local_bulk_roles_importer', 'roleretrievalsource');
        }
        if (!class_exists($this->rolesimportstrategies[$strategy])) {
            $this->log_message('ERROR - source: ' . $strategy . ' does not exist');
            return;
        }

        $this->log_message(self::SEPARATOR);
        $this->log_message('  IMPORT ROLES FROM SOURCE: ' . $strategy);
        $this->log_message(self::SEPARATOR);

        $this->lastimport = $this->rolemanager->get_lastimport();

        $this->rolesimportstrategy = new $this->rolesimportstrategies[$strategy]();
        $this->lastchanges = $this->rolesimportstrategy->get_last_updated();

        if (!$this->lastchanges) {
            $this->log_message('ERROR - cannot obtain main branch last updated time');
            return;
        }

        $roles = $this->rolesimportstrategy->get_roles();

        if (empty($roles)) {
            $this->log_message('ERROR - cannot obtain roles');
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
    private function process_import(array $roles): void {

        // Loop 1 - in first loop we create roles that do not exist yet.
        foreach ($roles as $role) {
            if (!core_role_preset::is_valid_preset($role->xml)) {
                $role->needupdate = false;
                $this->log_message('invalid XML');
                $this->log_message(self::SEPARATOR);
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
            $this->log_message($message);
            $this->log_message(self::SEPARATOR);
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

            $this->log_message($message);
            $this->log_message(self::SEPARATOR);
        }
        $this->rolemanager->update_lastimport(true);
    }
}
