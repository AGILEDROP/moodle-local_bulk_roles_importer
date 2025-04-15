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

/**
 * Interface for roles importer strategies.
 *
 * File         roles_importer_strategy_interface.php
 * Encoding     UTF-8
 *
 * @package     local_bulk_roles_importer
 *
 * @copyright   Agiledrop, 2025
 * @author      Agiledrop ltd. <developer@agiledrop.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Roles importing strategy interface.
 */
interface roles_importer_strategy_interface {

    /**
     * Returns the name of the strategy.
     *
     * @return string
     */
    public function get_name(): string;

    /**
     * Returns timestamp of last update at the source.
     *
     * @return int
     */
    public function get_last_updated(): int;

    /**
     * Returns an array of roles.
     *
     * @return array
     */
    public function get_roles(): array;

}
