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
 * PHPUnit tests for the roles_importer class.
 *
 * File         roles_importer_test.php
 * Encoding     UTF-8
 *
 * @package     local_bulk_roles_importer
 *
 * @copyright   Agiledrop, 2025
 * @author      Agiledrop 2025 <developer@agiledrop.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_bulk_roles_importer;

defined('MOODLE_INTERNAL') || die();

use advanced_testcase;
use local_bulk_roles_importer\local\util\role_manager;
use local_bulk_roles_importer\local\util\roles_importer_strategy_interface;

/**
 * Unit tests for roles_importer class.
 *
 * @covers \local_bulk_roles_importer\roles_importer
 * @copyright   Agiledrop, 2025
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class roles_importer_test extends advanced_testcase {

    /**
     * Reset Moodle after each test.
     *
     * @return void
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * Test that new and existing roles are processed correctly.
     *
     * @return void
     */
    public function test_import_roles_creates_new_and_updates_existing_roles(): void {
        // Mock role_manager.
        $rolemanager = $this->createMock(role_manager::class);

        $rolemanager->method('get_lastimport')->willReturn(1000);
        $rolemanager->method('get_role')->willReturnCallback(function($shortname) {
            if ($shortname === 'existingrole') {
                return (object)['id' => 1, 'shortname' => 'existingrole'];
            }
            return false;
        });

        $rolemanager->expects($this->once())->method('create_role_from_xml');
        $rolemanager->expects($this->once())->method('update_role_from_xml');
        $rolemanager->expects($this->once())->method('mark_last_import_success');

        // Mock strategy.
        $newrole = (object)[
                'xml' => '<valid>xml</valid>',
                'shortname' => 'newrole',
                'lastchange' => 3000,
        ];
        $existingrole = (object)[
                'xml' => '<valid>xml</valid>',
                'shortname' => 'existingrole',
                'lastchange' => 2001,
        ];

        $strategy = $this->createMock(roles_importer_strategy_interface::class);
        $strategy->method('get_last_updated')->willReturn(2000);
        $strategy->method('get_roles')->willReturn([$newrole, $existingrole]);

        // Instantiate testable importer.
        $rolesimporter = new testable_roles_importer('task', $rolemanager, [
                'mock' => get_class($strategy),
        ]);
        $rolesimporter->set_mock_strategy($strategy);

        // Run the test.
        ob_start();
        $rolesimporter->import_roles('mock');
        $output = ob_get_clean();

        $this->assertTrue($newrole->needupdate, 'New role should be marked as needing update');
        $this->assertTrue($existingrole->needupdate, 'Existing role should be marked as needing update');
        $this->assertStringContainsString('newrole [created]', $output);
        $this->assertStringContainsString('existingrole [updated]', $output);
    }

    /**
     * Test that roles with invalid XML are skipped.
     *
     * @return void
     */
    public function test_import_roles_skips_invalid_xml(): void {
        // Mock role_manager.
        $rolemanager = $this->createMock(role_manager::class);

        $rolemanager->method('get_lastimport')->willReturn(1000);
        $rolemanager->method('get_role')->willReturn(false); // No roles exist.

        // These should NOT be called.
        $rolemanager->expects($this->never())->method('create_role_from_xml');
        $rolemanager->expects($this->never())->method('update_role_from_xml');

        // Mock strategy with "invalid" XML.
        $strategy = $this->createMock(roles_importer_strategy_interface::class);
        $strategy->method('get_last_updated')->willReturn(2000);
        $strategy->method('get_roles')->willReturn([
                (object)[
                        'xml' => '<invalid>',
                        'shortname' => 'badrole',
                        'lastchange' => 3000,
                ],
        ]);

        // Use a subclass that simulates invalid XML.
        $rolesimporter = new class('task', $rolemanager, ['mock' => get_class($strategy)]) extends roles_importer {
            /**
             * Mock strategy instance.
             *
             * @var roles_importer_strategy_interface
             */
            private roles_importer_strategy_interface $mockstrategy;

            /**
             * Inject mock strategy.
             *
             * @param roles_importer_strategy_interface $strategy
             * @return void
             */
            public function set_mock_strategy(roles_importer_strategy_interface $strategy): void {
                $this->mockstrategy = $strategy;
            }

            /**
             * Provide mock strategy instance.
             *
             * @param string $strategyclass
             * @return roles_importer_strategy_interface
             */
            protected function make_strategy_instance(string $strategyclass): roles_importer_strategy_interface {
                unset($strategyclass);

                return $this->mockstrategy;
            }

            /**
             * Simulate invalid XML.
             *
             * @param string $xml
             * @return bool
             */
            protected function is_valid_preset(string $xml): bool {
                unset($xml);

                return false; // Pretend XML is invalid.
            }
        };

        $rolesimporter->set_mock_strategy($strategy);

        // Run the test (output suppressed).
        ob_start();
        $rolesimporter->import_roles('mock');
        ob_end_clean();
    }

    /**
     * Test that import_roles exits gracefully when the strategy class is missing.
     *
     * @return void
     */
    public function test_import_roles_with_missing_strategy_class(): void {
        // Mock role_manager just for construction.
        $rolemanager = $this->createMock(role_manager::class);

        // Use invalid strategy name (no class defined).
        $rolesimporter = new roles_importer('task', $rolemanager, [
                'badsource' => '\\local_bulk_roles_importer\\nonexistent_strategy_class',
        ]);

        // Run and capture output.
        ob_start();
        $rolesimporter->import_roles('badsource');
        $output = ob_get_clean();

        $this->assertStringContainsString('ERROR - source: badsource does not exist', $output);
    }

    /**
     * Test that import_roles logs an error when get_roles() returns an empty array.
     *
     * @return void
     */
    public function test_import_roles_with_empty_role_list(): void {
        $rolemanager = $this->createMock(role_manager::class);

        $rolemanager->method('get_lastimport')->willReturn(1000);

        // Create a fake strategy that returns no roles.
        $strategy = $this->createMock(roles_importer_strategy_interface::class);
        $strategy->method('get_last_updated')->willReturn(2000);
        $strategy->method('get_roles')->willReturn([]); // No roles returned.

        // Use a custom subclass to inject the strategy.
        $rolesimporter = new class('task', $rolemanager, ['mock' => get_class($strategy)]) extends roles_importer {
            /**
             * Mock strategy instance.
             *
             * @var roles_importer_strategy_interface
             */
            private roles_importer_strategy_interface $mockstrategy;

            /**
             * Inject mock strategy.
             *
             * @param roles_importer_strategy_interface $strategy
             * @return void
             */
            public function set_mock_strategy(roles_importer_strategy_interface $strategy): void {
                $this->mockstrategy = $strategy;
            }

            /**
             * Provide mock strategy instance.
             *
             * @param string $strategyclass
             * @return roles_importer_strategy_interface
             */
            protected function make_strategy_instance(string $strategyclass): roles_importer_strategy_interface {
                unset($strategyclass);
                return $this->mockstrategy;
            }

            /**
             * Bypass preset validation.
             *
             * @param string $xml
             * @return bool
             */
            protected function is_valid_preset(string $xml): bool {
                unset($xml);

                return true;
            }
        };

        $rolesimporter->set_mock_strategy($strategy);

        ob_start();
        $rolesimporter->import_roles('mock');
        $output = ob_get_clean();

        $this->assertStringContainsString('ERROR - cannot obtain roles', $output);
    }
}

/**
 * Testable subclass of roles_importer used for mocking strategy instance.
 *
 * @package     local_bulk_roles_importer
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
