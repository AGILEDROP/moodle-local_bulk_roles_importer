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

namespace local_bulk_roles_importer\util;

/**
 * Roles importer strategies manager.
 *
 * File         roles_importer_strategies_manager.php
 * Encoding     UTF-8
 *
 * @package     local_bulk_roles_importer
 *
 * @copyright   Agiledrop, 2025
 * @author      Agiledrop 2024 <developer@agiledrop.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Roles importing strategy for GitLab.
 */
class roles_importer_strategies_manager {

    /** @var roles_importer_strategy_interface[] $roles_importer_strategies Array of all roles importer strategies. */
    private static array $roles_importer_strategies = [
        'github' => '',
        'gitlab' => gitlab_roles_importer_strategy::class,
        'bitbucket' => '',
        'file' => '',
    ];

    /**
     * Get all strategies names.
     *
     * @return array
     */
    public static function get_strategies_names(): array
    {
        return array_combine(array_keys(self::$roles_importer_strategies), array_keys(self::$roles_importer_strategies));
    }

    /**
     * Get all strategies classes.
     *
     * @return array
     */
    public static function get_strategies_classes(): array
    {
        return self::$roles_importer_strategies;
    }

}
