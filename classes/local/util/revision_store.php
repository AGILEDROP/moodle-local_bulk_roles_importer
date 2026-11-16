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

namespace local_bulk_roles_importer\local\util;

use stdClass;

/**
 * Stores the last successfully applied Git revision of each role file.
 *
 * Blob revisions are scoped to the configured Git source and file path.
 *
 * @package     local_bulk_roles_importer
 * @copyright   Agiledrop, 2025
 * @author      Agiledrop ltd. <developer@agiledrop.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class revision_store {
    /** @var string Table name. */
    public const TABLE = 'local_bulk_roles_importer_rev';

    /** @var string Plugin configuration component. */
    private const COMPONENT = 'local_bulk_roles_importer';

    /**
     * Start importing a source. Invalidate revisions when another source may have written roles.
     *
     * @param stdClass $source Provider, API URL, repository and branch.
     * @return string Source key for completing the import.
     */
    public function begin_source(stdClass $source): string {
        global $DB;

        $key = self::make_source_key($source);
        $pending = get_config(self::COMPONENT, 'gitpendingsource');
        $previous = $pending ?: get_config(self::COMPONENT, 'gitappliedsource');
        if ($previous !== $key) {
            $transaction = $DB->start_delegated_transaction();
            $DB->delete_records(self::TABLE);
            set_config('gitpendingsource', $key, self::COMPONENT);
            $transaction->allow_commit();
        }
        return $key;
    }

    /**
     * Mark the source transition complete after the entire import succeeds.
     *
     * @param string $key Source key returned by begin_source().
     * @return void
     */
    public function complete_source(string $key): void {
        if (get_config(self::COMPONENT, 'gitpendingsource') === $key) {
            set_config('gitappliedsource', $key, self::COMPONENT);
            unset_config('gitpendingsource', self::COMPONENT);
        }
    }

    /**
     * Identify a configured provider, including the API URL of self-hosted instances.
     *
     * @param stdClass $source Provider, API URL, repository and branch.
     * @return string
     */
    private static function make_source_key(stdClass $source): string {
        return sha1(serialize([$source->provider, $source->endpoint, $source->repository, $source->branch]));
    }

    /**
     * Build the lookup key of a source.
     *
     * @param stdClass $source Object with provider, API URL, repository, branch and path.
     * @return string
     */
    public static function make_key(stdClass $source): string {
        return sha1(self::make_source_key($source) . ':' . $source->path);
    }

    /**
     * Find the existing role if the blob and its linked role targets are still current.
     *
     * @param stdClass $source Git file source and blob revision.
     * @return stdClass|null Existing role, or null if the file needs reapplying.
     */
    public function find_applied_role(stdClass $source): ?stdClass {
        global $DB;

        $record = $DB->get_record(self::TABLE, ['sourcekey' => self::make_key($source), 'revision' => $source->revision]);
        if (!$record || !$record->roleid) {
            return null;
        }

        $roleids = json_decode($record->allowtargets ?? '[]', true) ?? [];
        $roleids[] = (int)$record->roleid;
        $roleids = array_unique($roleids);
        $roles = $DB->get_records_list('role', 'id', $roleids, '', 'id,shortname');
        return count($roles) === count($roleids) ? $roles[$record->roleid] : null;
    }

    /**
     * Record that a revision of a source file was applied successfully.
     *
     * @param stdClass $source Git file source and blob revision.
     * @param int $revisiontime Commit time of the repository snapshot.
     * @param int $roleid Moodle id of the role that was written.
     * @param array $allowlists Target role IDs per allow list.
     * @return void
     */
    public function set_revision(stdClass $source, int $revisiontime, int $roleid, array $allowlists): void {
        global $DB;

        $key = self::make_key($source);
        $record = $DB->get_record(self::TABLE, ['sourcekey' => $key]);
        if (!$record) {
            $record = (object)[
                'sourcekey' => $key,
                'provider' => $source->provider,
                'repository' => $source->repository,
                'branch' => $source->branch,
                'filepath' => $source->path,
            ];
        }
        $record->revision = $source->revision;
        $record->revisiontime = $revisiontime;
        $record->roleid = $roleid;
        $record->allowtargets = json_encode(array_values(array_unique(array_merge([], ...array_values($allowlists)))));
        $record->timemodified = time();

        if (empty($record->id)) {
            $DB->insert_record(self::TABLE, $record);
        } else {
            $DB->update_record(self::TABLE, $record);
        }
    }
}
