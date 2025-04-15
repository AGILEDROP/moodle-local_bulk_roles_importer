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

    /** @var roles_importer_strategy_interface[] $rolesimportstrategies All available roles importer strategies. */
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

    /** @var string|null $importfilename Name of the import file. */
    private ?string $importfilename = null;


    /**
     * Constructor.
     *
     * @param string $loggingstyle
     * @param role_manager|null $rolemanager
     * @param array|null $rolesimportstrategies
     */
    public function __construct(
            string $loggingstyle = 'task',
            ?role_manager $rolemanager = null,
            ?array $rolesimportstrategies = null
    ) {
        $this->rolemanager = $rolemanager ?? new role_manager();
        $this->rolesimportstrategies = $rolesimportstrategies ?: roles_importer_strategies_manager::get_strategies_classes();
        $this->loggingstyle = in_array($loggingstyle, self::LOGGING_STYLES, true) ? $loggingstyle : 'task';
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
                echo \html_writer::tag('p', $message, ['class' => 'message']);
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
    public function import_roles(?string $strategy = null): void {
        if ($strategy === null) {
            $strategy = get_config('local_bulk_roles_importer', 'roleretrievalsource');
        }
        if (!isset($this->rolesimportstrategies[$strategy]) || !class_exists($this->rolesimportstrategies[$strategy])) {
            $this->log_message(get_string('error:strategy_does_not_exist', 'local_bulk_roles_importer', $strategy));
            return;
        }

        $this->log_with_separators(get_string('log:import_roles', 'local_bulk_roles_importer', $strategy), true, true);

        $this->lastimport = $this->rolemanager->get_lastimport();

        $strategyclass = $this->rolesimportstrategies[$strategy];

        $this->rolesimportstrategy = $this->make_strategy_instance($strategyclass);

        $this->lastchanges = $this->rolesimportstrategy->get_last_updated();

        if (!$this->lastchanges) {
            $this->log_message(get_string('error:main_branch_time', 'local_bulk_roles_importer'));
            return;
        }

        $roles = $this->rolesimportstrategy->get_roles();

        if (empty($roles)) {
            $this->log_message(get_string('error:cannot_obtain_roles', 'local_bulk_roles_importer'));
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

        $this->process_new_roles($roles);
        $this->process_updated_roles($roles);

        $this->rolemanager->mark_last_import_success();
    }

    /**
     * Process creation of new roles.
     *
     * @param array $roles
     * @return void
     */
    private function process_new_roles(array $roles): void {
        foreach ($roles as $role) {
            // If the role is marked as invalid, skip it.
            if (isset($role->invalid) && $role->invalid) {
                continue;
            }

            if (!$this->is_valid_preset($role->xml)) {
                $role->needupdate = false;
                $this->log_with_separators(get_string('error:invalid_xml', 'local_bulk_roles_importer'), false, true);
                continue;
            }
            $moodlerole = $this->rolemanager->get_role($role->shortname);
            if ($moodlerole) {
                $lastchange = $role->lastchange ?? 0;
                $role->needupdate = ($lastchange > $this->lastimport);
                continue;
            }
            $role->needupdate = true;
            $this->rolemanager->create_role_from_xml($role->xml);

            $this->log_with_separators(
                '   ' . get_string('log:role_created',
                'local_bulk_roles_importer', $role->shortname),
                false,
                true
            );
        }
    }

    /**
     * Process updates for existing roles.
     *
     * @param array $roles
     * @return void
     */
    private function process_updated_roles(array $roles): void {
        foreach ($roles as $role) {
            // If the role is marked as invalid, log a friendly error message.
            if (isset($role->invalid) && $role->invalid) {
                $this->log_with_separators(
                        get_string('error:incorrect_format_file', 'local_bulk_roles_importer', $role->filename),
                        false,
                        true);
                continue;
            }

            $message = '   -' . $role->shortname;
            if (empty($role->needupdate)) {
                $message .= ' [X]';
            } else {
                $moodlerole = $this->rolemanager->get_role($role->shortname);
                if (!$moodlerole) {
                    $message .= ' ' . get_string('log:role_not_found', 'local_bulk_roles_importer');
                } else {
                    try {
                        $roleid = (int)$moodlerole->id;
                        $this->rolemanager->update_role_from_xml($roleid, $role->xml);
                        $message .= ' ' . get_string('log:updated', 'local_bulk_roles_importer');
                    } catch (\Throwable $e) {
                        // Log a friendly error message instead of a PHP error.
                        if ($role->filename) {
                            $filename = $role->filename;
                        } else {
                            $filename = $this->importfilename;
                        }
                        $message .= ' ' . get_string('error:incorrect_format_file', 'local_bulk_roles_importer', $filename);
                    }
                }
            }

            $this->log_with_separators($message, false, true);
        }
    }

    /**
     * Check whether the given XML string is a valid role preset.
     *
     * @param string $xml
     * @return bool
     */
    protected function is_valid_preset(string $xml): bool {
        return \core_role_preset::is_valid_preset($xml);
    }

    /**
     * Create an instance of the given roles importer strategy class.
     *
     * @param string $strategyclass
     * @return roles_importer_strategy_interface
     */
    protected function make_strategy_instance(string $strategyclass): roles_importer_strategy_interface {
        return new $strategyclass();
    }

    /**
     * Log a message with optional separators.
     *
     * @param string $message The main message.
     * @param bool $topseparator
     * @param bool $bottomseparator
     * @return void
     */
    protected function log_with_separators(string $message, bool $topseparator = false, bool $bottomseparator = false): void {
        if ($topseparator) {
            $this->log_message(self::SEPARATOR);
        }
        $this->log_message($message);
        if ($bottomseparator) {
            $this->log_message(self::SEPARATOR);
        }
    }

    /**
     * Set the filename of the import file.
     *
     * @param string $filename
     * @return void
     */
    public function set_import_filename(string $filename): void {
        $this->importfilename = $filename;
    }
}
