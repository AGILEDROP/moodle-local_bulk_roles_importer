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

namespace local_bulk_roles_importer\util;

use dml_exception;
use stdClass;

/**
 * Definition class for Git provider API.
 */
interface gitprovider_api_interface {

    /**
     * Get selected branch info or false.
     *
     * @param string $name
     * @return stdClass|false
     */
    public function get_branch(string $name): stdClass|false;

    /**
     * Get timestamp of main branch last updated time or false.
     *
     * @return false|int
     */
    public function get_main_branch_last_updated(): int|false;

    /**
     * Get files list for selected branch, by default from main branch.
     *
     * @param ?string $branch
     * @return array|false
     */
    public function get_files(?string $branch = null): array|false;

    /**
     * Get file content for selected filepath and branch.
     *
     * @param string $branch Branch name.
     * @param string $filepath File path.
     * @return string|false
     */
    public function get_file_content(string $branch, string $filepath): string|false;

    /**
     * Get array of roles.
     *
     * @return array
     */
    public function get_roles(): array;
}
