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
 * Abstract class - Git provider API.
 *
 * File         gitprovider_api.php
 * Encoding     UTF-8
 *
 * @package     local_bulk_roles_importer
 *
 * @copyright   Agiledrop, 2025
 * @author      Agiledrop ltd. <developer@agiledrop.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_bulk_roles_importer\local\util;

use stdClass;

/**
 * Definition class for Git provider API.
 *
 * Every method throws a provider_exception when a request fails or the response
 * is not what the provider documents.
 */
interface gitprovider_api_interface {
    /**
     * Get the head commit of the configured branch.
     *
     * @return stdClass Object with sha (commit id) and time (commit timestamp).
     */
    public function get_head(): stdClass;

    /**
     * Get timestamp of the configured branch head commit.
     *
     * @return int
     */
    public function get_main_branch_last_updated(): int;

    /**
     * Get all XML files on the configured branch, including nested folders.
     *
     * @return stdClass[] File records with path and blob revision.
     */
    public function get_files(): array;

    /**
     * Get file content at the given ref.
     *
     * @param string $ref Branch name or commit id.
     * @param string $filepath File path.
     * @return string
     */
    public function get_file_content(string $ref, string $filepath): string;

    /**
     * Get array of roles.
     *
     * @param callable|null $findapplied Callback returning the existing role for an applied Git blob, or null.
     * @return array
     */
    public function get_roles(?callable $findapplied = null): array;
}
