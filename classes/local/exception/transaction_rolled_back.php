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
 * Exception for a rolled-back role import transaction.
 *
 * File         transaction_rolled_back.php
 * Encoding     UTF-8
 *
 * @package     local_bulk_roles_importer
 * @copyright   Agiledrop, 2025
 * @author      Agiledrop ltd. <developer@agiledrop.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_bulk_roles_importer\local\exception;

/**
 * Thrown when a role write failed and its database transaction was rolled back.
 *
 * The original failure is available through getPrevious() and its message is kept.
 *
 * @package     local_bulk_roles_importer
 * @copyright   Agiledrop, 2025
 * @author      Agiledrop ltd. <developer@agiledrop.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class transaction_rolled_back extends \RuntimeException {
    /**
     * Constructor.
     *
     * @param \Throwable $previous The failure that caused the rollback.
     */
    public function __construct(\Throwable $previous) {
        parent::__construct($previous->getMessage(), 0, $previous);
    }
}
