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

namespace local_bulk_roles_importer\local\exception;

/**
 * Thrown when a Git provider request fails or returns an unexpected response.
 *
 * Messages never contain request headers, so access tokens are not exposed.
 *
 * @package     local_bulk_roles_importer
 * @copyright   Agiledrop, 2025
 * @author      Agiledrop ltd. <developer@agiledrop.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider_exception extends \moodle_exception {
    /**
     * Constructor.
     *
     * @param string $errorcode Language string key in local_bulk_roles_importer.
     * @param mixed $a Language string placeholder data.
     */
    public function __construct(string $errorcode, $a = null) {
        parent::__construct($errorcode, 'local_bulk_roles_importer', '', $a);
    }
}
