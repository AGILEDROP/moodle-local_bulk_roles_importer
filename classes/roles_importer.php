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
use local_bulk_roles_importer\local\exception\provider_exception;
use local_bulk_roles_importer\local\exception\transaction_rolled_back;
use local_bulk_roles_importer\local\util\revision_store;
use local_bulk_roles_importer\local\util\role_manager;
use local_bulk_roles_importer\local\util\roles_importer_strategies_manager;
use local_bulk_roles_importer\local\util\roles_importer_strategy_interface;

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

    /** @var int[] $counts Role outcome counts for the current import. */
    private array $counts = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'failed' => 0];

    /** @var int $gitfailures Git files that need to be retried after a fetch or write failure. */
    private int $gitfailures = 0;

    /** @var revision_store $revisionstore Last applied revision of each Git role file. */
    private revision_store $revisionstore;

    /**
     * Constructor.
     *
     * @param string $loggingstyle
     * @param role_manager|null $rolemanager
     * @param array|null $rolesimportstrategies
     * @param revision_store|null $revisionstore
     */
    public function __construct(
        string $loggingstyle = 'task',
        ?role_manager $rolemanager = null,
        ?array $rolesimportstrategies = null,
        ?revision_store $revisionstore = null
    ) {
        $this->rolemanager = $rolemanager ?? new role_manager();
        $this->revisionstore = $revisionstore ?? new revision_store();
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
                echo \html_writer::tag('p', s($message), ['class' => 'message']);
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

        try {
            $this->rolesimportstrategy = $this->make_strategy_instance($strategyclass);
            $this->lastchanges = $this->rolesimportstrategy->get_last_updated();
            if (!$this->lastchanges) {
                $this->log_message(get_string('error:main_branch_time', 'local_bulk_roles_importer'));
                return;
            }
            $sourcekey = null;
            $roles = $this->rolesimportstrategy->get_roles(function (\stdClass $source) use (&$sourcekey): ?\stdClass {
                $sourcekey ??= $this->revisionstore->begin_source($source);
                return $this->revisionstore->find_applied_role($source);
            });
        } catch (provider_exception $e) {
            // Log it, then let the scheduled task runner record the failure.
            $this->log_with_separators($e->getMessage(), false, true);
            throw $e;
        }

        if (empty($roles)) {
            $this->log_message(get_string('error:cannot_obtain_roles', 'local_bulk_roles_importer'));
            return;
        }

        $this->process_import($roles, $sourcekey);
    }

    /**
     * Process import roles.
     *
     * Pass one creates missing roles and decides which existing roles need updating.
     * Pass two updates existing roles and links allow lists of newly created roles,
     * so roles in the same batch can reference each other. Every failure is reported
     * and processing continues with the next role. Until failures are tracked per
     * file, the last import time is only advanced when no role failed, so failed
     * roles are retried on the next run.
     *
     * @param array $roles Array of roles.
     * @param string|null $sourcekey Git source key, if a source was selected for this run.
     * @return void
     * @throws coding_exception
     * @throws dml_exception
     */
    private function process_import(array $roles, ?string $sourcekey): void {
        $this->counts = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'failed' => 0];
        $this->gitfailures = 0;

        $this->process_new_roles($roles);
        $this->process_updated_roles($roles);

        $this->log_with_separators(
            get_string('log:summary', 'local_bulk_roles_importer', (object)$this->counts),
            false,
            true
        );

        // Git roles are tracked per file revision; the shared timestamp is only for other sources.
        $tracked = false;
        foreach ($roles as $role) {
            $tracked = $tracked || isset($role->source);
        }
        if (!$tracked) {
            if ($this->counts['failed'] > 0) {
                $this->log_with_separators(get_string('log:lastimport_kept', 'local_bulk_roles_importer'), false, true);
            } else {
                $this->rolemanager->mark_last_import_success();
            }
        }

        if ($sourcekey !== null && $this->counts['failed'] === 0) {
            $this->revisionstore->complete_source($sourcekey);
        }

        if ($this->gitfailures > 0) {
            throw new provider_exception('error:import_incomplete', $this->gitfailures);
        }
    }

    /**
     * Process creation of new roles.
     *
     * @param array $roles
     * @return void
     */
    private function process_new_roles(array $roles): void {
        foreach ($roles as $role) {
            $role->needupdate = false;
            if (!empty($role->unchanged)) {
                continue;
            }

            if (!empty($role->invalid)) {
                $role->failed = true;
                if (isset($role->fetcherror)) {
                    // The provider request failed: report it and fail the run afterwards.
                    $this->gitfailures++;
                    $this->report_failure($role, null, (string)$role->fetcherror);
                    continue;
                }
                $error = empty($role->readerror) ? 'error:not_role_xml' : 'error:cannot_read_file';
                $this->report_failure($role, null, get_string($error, 'local_bulk_roles_importer'));
                continue;
            }

            // An empty <shortname/> element is decoded as an array, so accept strings only.
            $rawshortname = is_string($role->shortname ?? null) ? $role->shortname : '';

            if (!$this->is_valid_preset($role->xml)) {
                $role->failed = true;
                $this->report_failure($role, $rawshortname, get_string('error:invalid_xml', 'local_bulk_roles_importer'));
                continue;
            }

            $shortname = role_manager::normalise_shortname($rawshortname);
            if ($shortname === '' || \core_text::strlen($shortname) > 100) {
                $role->failed = true;
                $this->report_failure(
                    $role,
                    $rawshortname,
                    get_string('error:invalid_shortname', 'local_bulk_roles_importer', $rawshortname)
                );
                continue;
            }
            $role->shortname = $shortname;

            if ($this->rolemanager->get_role($shortname)) {
                $role->needupdate = isset($role->source) || (($role->lastchange ?? 0) > $this->lastimport);
                continue;
            }

            // New roles are fully written here; pass two only links their allow lists.
            try {
                $this->rolemanager->create_role_from_xml($role->xml);
            } catch (\Throwable $e) {
                $role->failed = true;
                $this->count_git_failure($role);
                $this->report_failure($role, $shortname, $e->getMessage(), $e instanceof transaction_rolled_back);
                continue;
            }
            $role->created = true;
            $this->counts['created']++;
            $this->log_with_separators(
                '   ' . get_string('log:role_created', 'local_bulk_roles_importer', $shortname),
                false,
                true
            );
        }
    }

    /**
     * Process updates for existing roles and link allow lists of new roles.
     *
     * @param array $roles
     * @return void
     */
    private function process_updated_roles(array $roles): void {
        foreach ($roles as $role) {
            // Already reported in the first pass.
            if (!empty($role->failed)) {
                continue;
            }

            $created = !empty($role->created);
            if (!$created && empty($role->needupdate)) {
                $this->counts['unchanged']++;
                $this->log_with_separators('   -' . $role->shortname . ' [X]', false, true);
                continue;
            }

            $moodlerole = $this->rolemanager->get_role($role->shortname);
            if (!$moodlerole) {
                $this->count_git_failure($role);
                $reason = get_string('error:role_missing', 'local_bulk_roles_importer');
                if ($created) {
                    $reason = get_string('error:allows_not_linked', 'local_bulk_roles_importer', $reason);
                }
                $this->report_failure($role, $role->shortname, $reason);
                continue;
            }

            $roleid = (int)$moodlerole->id;
            try {
                $allowlists = isset($role->source) ? $this->rolemanager->resolve_allow_lists($role->xml) : null;
                if ($created) {
                    $this->rolemanager->update_role_allows_from_xml($roleid, $role->xml, $allowlists);
                    $message = get_string('log:allows_linked', 'local_bulk_roles_importer');
                } else {
                    $this->rolemanager->update_role_from_xml($roleid, $role->xml, $allowlists);
                    $this->counts['updated']++;
                    $message = get_string('log:updated', 'local_bulk_roles_importer');
                }

                // The role and its required links are applied: remember this file revision.
                if ($allowlists !== null) {
                    $this->revisionstore->set_revision($role->source, (int)$role->lastchange, $roleid, $allowlists);
                }
            } catch (\Throwable $e) {
                $this->count_git_failure($role);
                // A created role stays created; only its allow lists are missing.
                $reason = $e->getMessage();
                if ($created) {
                    $reason = get_string('error:allows_not_linked', 'local_bulk_roles_importer', $reason);
                }
                $this->report_failure($role, $role->shortname, $reason, $e instanceof transaction_rolled_back);
                continue;
            }

            $this->log_with_separators('   -' . $role->shortname . ' ' . $message, false, true);
        }
    }

    /**
     * Mark an operational failure in a Git role for a scheduled retry.
     *
     * @param \stdClass $role Role record from the strategy.
     */
    private function count_git_failure(\stdClass $role): void {
        if (isset($role->source)) {
            $this->gitfailures++;
        }
    }

    /**
     * Count and log a failed role with its file, shortname (when known) and reason.
     *
     * @param \stdClass $role Role record from the strategy.
     * @param string|null $shortname Role shortname, or null/empty when unknown.
     * @param string $reason Failure reason.
     * @param bool $rolledback Whether a database transaction was rolled back.
     * @return void
     */
    private function report_failure(\stdClass $role, ?string $shortname, string $reason, bool $rolledback = false): void {
        $this->counts['failed']++;

        $a = (object)[
            'file' => $this->get_display_filename($role),
            'shortname' => (string)$shortname,
            'reason' => $reason,
        ];
        $key = ($a->shortname === '') ? 'error:file_failed' : 'error:role_failed';
        $message = '   ' . get_string($key, 'local_bulk_roles_importer', $a);
        if ($rolledback) {
            $message .= ' ' . get_string('log:rolled_back', 'local_bulk_roles_importer');
        }
        $this->log_with_separators($message, false, true);
    }

    /**
     * Get the file name to show for a role.
     *
     * @param \stdClass $role Role record from the strategy.
     * @return string
     */
    private function get_display_filename(\stdClass $role): string {
        if (!empty($role->filename) && is_string($role->filename)) {
            return $role->filename;
        }
        return $this->importfilename ?? get_string('error:unknown_file', 'local_bulk_roles_importer');
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
