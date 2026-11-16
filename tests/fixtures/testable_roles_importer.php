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
 * Test fixture for the roles importer.
 *
 * File         testable_roles_importer.php
 * Encoding     UTF-8
 *
 * @package     local_bulk_roles_importer
 * @copyright   Agiledrop, 2025
 * @author      Agiledrop ltd. <developer@agiledrop.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_bulk_roles_importer;

use local_bulk_roles_importer\local\util\roles_importer_strategy_interface;

/**
 * Testable subclass of roles_importer used for mocking strategy instance.
 *
 * @package     local_bulk_roles_importer
 * @copyright   Agiledrop, 2025
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class testable_roles_importer extends roles_importer {
    /** @var roles_importer_strategy_interface */
    private roles_importer_strategy_interface $mockstrategy;

    /**
     * Set a mock strategy to be used instead of real instantiation.
     *
     * @param roles_importer_strategy_interface $strategy
     * @return void
     */
    public function set_mock_strategy(roles_importer_strategy_interface $strategy): void {
        $this->mockstrategy = $strategy;
    }

    /**
     * Override strategy instantiation to return a mocked strategy.
     *
     * @param string $strategyclass
     * @return roles_importer_strategy_interface
     */
    protected function make_strategy_instance(string $strategyclass): roles_importer_strategy_interface {
        return $this->mockstrategy;
    }

    /**
     * Override role preset validation.
     *
     * @param string $xml
     * @return bool
     */
    protected function is_valid_preset(string $xml): bool {
        return true; // Always return true for tests.
    }
}
