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
 * @author      Agiledrop ltd. <developer@agiledrop.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Roles importer strategies manager.
 */
class roles_importer_strategies_manager {

    /** @var array $roles_importer_strategies Array of all roles importer strategies. */
    private static array $roles_importer_strategies = [
        'github' => [
            'name' => 'Github',
            'class' => github_roles_importer_strategy::class,
            'automatic' => true,
        ],
        'gitlab' => [
            'name' => 'Gitlab',
            'class' => gitlab_roles_importer_strategy::class,
            'automatic' => true,
        ],
        'zipball' => [
            'name' => 'Zip',
            'class' => zipball_roles_importer_strategy::class,
            'automatic' => false,
        ],
    ];

    /**
     * Get all strategies names.
     *
     * @return array
     */
    public static function get_strategies_names(): array
    {
        $mapfunction = function($value) {
            return $value['name'];
        };
        return array_map($mapfunction, self::$roles_importer_strategies);
    }

    /**
     * Get all strategies names.
     *
     * @return array
     */
    public static function get_automatic_strategies_names(): array
    {
        $filterfunction = function($value) {
            return $value['automatic'];
        };
        $mapfunction = function($value) {
            return $value['name'];
        };
        $filteredarray = array_filter(self::$roles_importer_strategies, $filterfunction);
        return array_map($mapfunction, $filteredarray);
    }

    /**
     * Get all strategies classes.
     *
     * @return array
     */
    public static function get_strategies_classes(): array
    {
        $mapfunction = function($value) {
            return $value['class'];
        };
        return array_map($mapfunction, self::$roles_importer_strategies);
    }

    /**
     * Get all strategies classes.
     *
     * @return bool
     */
    public static function is_strategy_automatic($strategy): bool
    {
        $isautomatic = self::$roles_importer_strategies[$strategy]['automatic'] ?? false;
        return $isautomatic;
    }

}
