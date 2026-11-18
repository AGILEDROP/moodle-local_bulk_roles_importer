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
use local_bulk_roles_importer\local\traits\role_file_processor;
use local_bulk_roles_importer\local\exception\provider_exception;
use local_bulk_roles_importer\local\exception\transaction_rolled_back;
use local_bulk_roles_importer\local\util\revision_store;
use local_bulk_roles_importer\local\util\role_manager;
use local_bulk_roles_importer\local\util\roles_importer_strategy_interface;

require_once(__DIR__ . '/fixtures/testable_roles_importer.php');

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
        $created = false;
        $rolemanager->method('get_role')->willReturnCallback(function ($shortname) use (&$created) {
            if ($shortname === 'existingrole') {
                return (object)['id' => 1, 'shortname' => 'existingrole'];
            }
            if ($shortname === 'newrole' && $created) {
                return (object)['id' => 2, 'shortname' => 'newrole'];
            }
            return false;
        });

        $rolemanager->expects($this->once())->method('create_role_from_xml')
            ->willReturnCallback(function () use (&$created): int {
                $created = true;
                return 2;
            });
        // Existing role gets a full update; the new role only gets its allow lists linked.
        $rolemanager->expects($this->once())->method('update_role_from_xml')->with(1, $this->anything());
        $rolemanager->expects($this->once())->method('update_role_allows_from_xml')->with(2, $this->anything());
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

        $this->assertTrue($newrole->created, 'New role should be marked as created');
        $this->assertFalse($newrole->needupdate, 'New role should not get a second full write');
        $this->assertTrue($existingrole->needupdate, 'Existing role should be marked as needing update');
        $this->assertStringContainsString('newrole [created]', $output);
        $this->assertStringContainsString('existingrole [updated]', $output);
        $this->assertStringContainsString('newrole [created, allow lists linked]', $output);
        $this->assertStringContainsString('Created: 1, updated: 1, unchanged: 0, failed: 0', $output);
    }

    /**
     * Lookup, creation, and linking agree on Moodle's preset shortname.
     */
    public function test_import_roles_normalises_accented_shortname(): void {
        global $DB;

        $role = (object)[
            'xml' => '<role><shortname>Učitelj</shortname><allowassign><shortname>uitelj</shortname></allowassign></role>',
            'shortname' => 'Učitelj',
        ];
        $strategy = $this->createMock(roles_importer_strategy_interface::class);
        $strategy->method('get_last_updated')->willReturn(2000);
        $strategy->method('get_roles')->willReturn([$role]);
        $rolesimporter = new testable_roles_importer('task', new role_manager(), ['mock' => get_class($strategy)]);
        $rolesimporter->set_mock_strategy($strategy);

        ob_start();
        $rolesimporter->import_roles('mock');
        $output = ob_get_clean();

        $roleid = $DB->get_field('role', 'id', ['shortname' => 'uitelj'], MUST_EXIST);
        $this->assertTrue($DB->record_exists('role_allow_assign', ['roleid' => $roleid, 'allowassign' => $roleid]));
        $this->assertStringContainsString('uitelj [created, allow lists linked]', $output);
    }

    /**
     * Test that a failed role update is reported and blocks the last import timestamp.
     *
     * @return void
     */
    public function test_import_roles_failed_update_is_reported_and_retried(): void {
        $rolemanager = $this->createMock(role_manager::class);
        $rolemanager->method('get_lastimport')->willReturn(1000);
        $rolemanager->method('get_role')->willReturn((object)['id' => 1, 'shortname' => 'existingrole']);
        $rolemanager->method('update_role_from_xml')->willThrowException(new \moodle_exception(
            'error:unknown',
            'local_bulk_roles_importer'
        ));
        $rolemanager->expects($this->never())->method('mark_last_import_success');

        $strategy = $this->createMock(roles_importer_strategy_interface::class);
        $strategy->method('get_last_updated')->willReturn(2000);
        $strategy->method('get_roles')->willReturn([
            (object)['xml' => '<valid>xml</valid>', 'shortname' => 'ExistingRole', 'lastchange' => 2001],
        ]);

        $rolesimporter = new testable_roles_importer('task', $rolemanager, ['mock' => get_class($strategy)]);
        $rolesimporter->set_mock_strategy($strategy);

        ob_start();
        $rolesimporter->import_roles('mock');
        $output = ob_get_clean();

        // Shortname is normalised before lookup.
        $this->assertStringContainsString('FAILED - unknown file (existingrole): Unknown error', $output);
        // Nothing reports a rollback unless a transaction was rolled back.
        $this->assertStringNotContainsString('[changes rolled back]', $output);
        $this->assertStringContainsString('failed: 1', $output);
    }

    /**
     * Test that a failure while linking allow lists of a new role is reported and keeps the import retryable.
     *
     * @return void
     */
    public function test_import_roles_failed_allow_linking_is_reported_and_retried(): void {
        $created = false;
        $rolemanager = $this->createMock(role_manager::class);
        $rolemanager->method('get_lastimport')->willReturn(1000);
        $rolemanager->method('get_role')->willReturnCallback(function () use (&$created) {
            return $created ? (object)['id' => 5, 'shortname' => 'newrole'] : false;
        });
        $rolemanager->expects($this->once())->method('create_role_from_xml')
            ->willReturnCallback(function () use (&$created): int {
                $created = true;
                return 5;
            });
        $rolemanager->expects($this->once())->method('update_role_allows_from_xml')->with(5, $this->anything())
            ->willThrowException(new \moodle_exception('error:unknown', 'local_bulk_roles_importer'));
        $rolemanager->expects($this->never())->method('update_role_from_xml');
        $rolemanager->expects($this->never())->method('mark_last_import_success');

        $strategy = $this->createMock(roles_importer_strategy_interface::class);
        $strategy->method('get_last_updated')->willReturn(2000);
        $strategy->method('get_roles')->willReturn([
            (object)['xml' => '<valid>xml</valid>', 'shortname' => 'NewRole', 'lastchange' => 2001],
        ]);

        $rolesimporter = new testable_roles_importer('task', $rolemanager, ['mock' => get_class($strategy)]);
        $rolesimporter->set_mock_strategy($strategy);

        ob_start();
        $rolesimporter->import_roles('mock');
        $output = ob_get_clean();

        $this->assertStringContainsString('newrole [created]', $output);
        $this->assertStringContainsString(
            '(newrole): role was created, but its allow lists were not linked: Unknown error',
            $output
        );
        $this->assertStringContainsString('Created: 1, updated: 0, unchanged: 0, failed: 1', $output);
        $this->assertStringContainsString('last import time was not updated', $output);
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
        $rolemanager->expects($this->never())->method('mark_last_import_success');

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
        $rolesimporter = new class ('task', $rolemanager, ['mock' => get_class($strategy)]) extends roles_importer {
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
     * An invalid shortname is reported as plain text and leaves the timestamp unchanged.
     */
    public function test_import_roles_escapes_invalid_shortname_in_web_log(): void {
        $rolemanager = $this->createMock(role_manager::class);
        $rolemanager->expects($this->never())->method('get_role');
        $rolemanager->expects($this->never())->method('mark_last_import_success');

        $shortname = '<' . str_repeat('a', 101) . '&>';
        $strategy = $this->createMock(roles_importer_strategy_interface::class);
        $strategy->method('get_last_updated')->willReturn(2000);
        $strategy->method('get_roles')->willReturn([(object)['xml' => '<role/>', 'shortname' => $shortname]]);
        $rolesimporter = new testable_roles_importer('web', $rolemanager, ['mock' => get_class($strategy)]);
        $rolesimporter->set_mock_strategy($strategy);

        ob_start();
        $rolesimporter->import_roles('mock');
        $output = ob_get_clean();

        $this->assertStringContainsString(s($shortname), $output);
        $this->assertStringNotContainsString($shortname, $output);
    }

    /**
     * A role missing in the second pass leaves the timestamp unchanged.
     */
    public function test_import_roles_does_not_mark_success_when_role_disappears(): void {
        $rolemanager = $this->createMock(role_manager::class);
        $rolemanager->method('get_lastimport')->willReturn(1000);
        $rolemanager->expects($this->exactly(2))->method('get_role')->with('existingrole')
            ->willReturn((object)['id' => 1], false);
        $rolemanager->expects($this->never())->method('update_role_from_xml');
        $rolemanager->expects($this->never())->method('mark_last_import_success');

        $strategy = $this->createMock(roles_importer_strategy_interface::class);
        $strategy->method('get_last_updated')->willReturn(2000);
        $strategy->method('get_roles')->willReturn([
            (object)['xml' => '<role/>', 'shortname' => 'existingrole', 'lastchange' => 2001],
        ]);
        $rolesimporter = new testable_roles_importer('task', $rolemanager, ['mock' => get_class($strategy)]);
        $rolesimporter->set_mock_strategy($strategy);

        ob_start();
        $rolesimporter->import_roles('mock');
        $output = ob_get_clean();

        $this->assertStringContainsString('(existingrole): role not found', $output);
    }

    /**
     * A rolled-back write is reported as rolled back, with file name and shortname.
     *
     * @return void
     */
    public function test_import_roles_reports_rollback_only_for_rolled_back_transactions(): void {
        $rolemanager = $this->createMock(role_manager::class);
        $rolemanager->method('get_lastimport')->willReturn(1000);
        $rolemanager->method('get_role')->willReturnCallback(function ($shortname) {
            return in_array($shortname, ['rolledback', 'validation']) ? (object)['id' => 1] : false;
        });
        $rolemanager->method('update_role_from_xml')->willReturnCallback(function ($roleid, $xml) {
            if ($xml === 'rollback') {
                throw new transaction_rolled_back(new \coding_exception('write failed'));
            }
            throw new \moodle_exception('error:shortname_mismatch', 'local_bulk_roles_importer', '', 'validation');
        });
        $rolemanager->expects($this->never())->method('mark_last_import_success');

        $strategy = $this->createMock(roles_importer_strategy_interface::class);
        $strategy->method('get_last_updated')->willReturn(2000);
        $strategy->method('get_roles')->willReturn([
            (object)['xml' => 'rollback', 'shortname' => 'rolledback', 'lastchange' => 2001, 'filename' => 'a.xml'],
            (object)['xml' => 'validate', 'shortname' => 'validation', 'lastchange' => 2001, 'filename' => 'b.xml'],
        ]);
        $rolesimporter = new testable_roles_importer('task', $rolemanager, ['mock' => get_class($strategy)]);
        $rolesimporter->set_mock_strategy($strategy);

        ob_start();
        $rolesimporter->import_roles('mock');
        $output = ob_get_clean();

        $this->assertMatchesRegularExpression(
            '/FAILED - a\.xml \(rolledback\): .*write failed.* \[changes rolled back\]/',
            $output
        );
        $this->assertMatchesRegularExpression('/FAILED - b\.xml \(validation\): [^\n]*shortname "validation"\n/', $output);
        $this->assertSame(1, substr_count($output, '[changes rolled back]'));
    }

    /**
     * Failures do not stop independent roles, and the summary counts every outcome.
     *
     * @return void
     */
    public function test_import_roles_continues_after_failures_and_counts_outcomes(): void {
        $rolemanager = $this->createMock(role_manager::class);
        $rolemanager->method('get_lastimport')->willReturn(1000);
        $rolemanager->method('get_role')->willReturnCallback(function ($shortname) {
            return in_array($shortname, ['updated', 'unchanged']) ? (object)['id' => 1] : false;
        });
        $rolemanager->expects($this->once())->method('update_role_from_xml');
        $rolemanager->expects($this->never())->method('mark_last_import_success');

        $fileprocessor = new class {
            use role_file_processor {
                process_role_file_from_path as public;
            }
        };
        $unreadable = $fileprocessor->process_role_file_from_path(__DIR__ . '/fixtures/missing-role.xml', 2001);

        $strategy = $this->createMock(roles_importer_strategy_interface::class);
        $strategy->method('get_last_updated')->willReturn(2000);
        $strategy->method('get_roles')->willReturn([
            $unreadable,
            (object)['invalid' => true, 'xml' => '', 'filename' => 'broken.xml'],
            (object)['xml' => '<role/>', 'shortname' => '!!!', 'filename' => 'bad.xml'],
            (object)['xml' => '<role/>', 'shortname' => 'updated', 'lastchange' => 2001, 'filename' => 'u.xml'],
            (object)['xml' => '<role/>', 'shortname' => 'unchanged', 'lastchange' => 500, 'filename' => 'n.xml'],
        ]);
        $rolesimporter = new testable_roles_importer('task', $rolemanager, ['mock' => get_class($strategy)]);
        $rolesimporter->set_mock_strategy($strategy);

        ob_start();
        $rolesimporter->import_roles('mock');
        $output = ob_get_clean();

        $this->assertStringContainsString('FAILED - missing-role.xml: could not read the file', $output);
        $this->assertStringContainsString('FAILED - broken.xml: not a role definition XML file', $output);
        $this->assertStringContainsString('FAILED - bad.xml (!!!): invalid role shortname "!!!"', $output);
        $this->assertStringContainsString('-updated [updated]', $output);
        $this->assertStringContainsString('-unchanged [X]', $output);
        $this->assertStringContainsString('Created: 0, updated: 1, unchanged: 1, failed: 3', $output);
    }

    /**
     * Exception messages and file names are escaped in the web log.
     *
     * @return void
     */
    public function test_import_roles_escapes_failure_details_in_web_log(): void {
        $rolemanager = $this->createMock(role_manager::class);
        $rolemanager->method('get_lastimport')->willReturn(1000);
        $rolemanager->method('get_role')->willReturn(false);
        $rolemanager->method('create_role_from_xml')
            ->willThrowException(new transaction_rolled_back(new \coding_exception('<b>bad</b>')));

        $strategy = $this->createMock(roles_importer_strategy_interface::class);
        $strategy->method('get_last_updated')->willReturn(2000);
        $strategy->method('get_roles')->willReturn([
            (object)['xml' => '<role/>', 'shortname' => 'webrole', 'filename' => '<i>file</i>.xml'],
        ]);
        $rolesimporter = new testable_roles_importer('web', $rolemanager, ['mock' => get_class($strategy)]);
        $rolesimporter->set_mock_strategy($strategy);

        ob_start();
        $rolesimporter->import_roles('mock');
        $output = ob_get_clean();

        $this->assertStringNotContainsString('<b>', $output);
        $this->assertStringNotContainsString('<i>', $output);
        $this->assertStringContainsString('&lt;b&gt;bad&lt;/b&gt;', $output);
        $this->assertStringContainsString('&lt;i&gt;file&lt;/i&gt;.xml', $output);
    }

    /**
     * Build a Git-sourced role record.
     *
     * @param string $revision Blob ID.
     * @param string $shortname Role shortname.
     * @return \stdClass
     */
    private function git_role(string $revision, string $shortname = 'gitrole'): \stdClass {
        return (object)[
            'xml' => '<role/>',
            'shortname' => $shortname,
            'lastchange' => 100,
            'filename' => "roles/{$shortname}.xml",
            'source' => (object)[
                'provider' => 'gitlab',
                'endpoint' => 'https://gitlab.example.test',
                'repository' => 'group/roles',
                'branch' => 'main',
                'path' => "roles/{$shortname}.xml",
                'revision' => $revision,
            ],
        ];
    }

    /**
     * Run an import with a mocked strategy.
     *
     * @param role_manager $rolemanager
     * @param array $roles
     * @return string Output.
     */
    private function run_import(role_manager $rolemanager, array $roles): string {
        $strategy = $this->createMock(roles_importer_strategy_interface::class);
        $strategy->method('get_last_updated')->willReturn(2000);
        $strategy->method('get_roles')->willReturnCallback(function (callable $findapplied) use ($roles): array {
            return array_map(function ($role) use ($findapplied) {
                if (isset($role->source) && ($applied = $findapplied($role->source))) {
                    return (object)['source' => $role->source, 'shortname' => $applied->shortname, 'unchanged' => true];
                }
                return clone $role;
            }, $roles);
        });
        $rolesimporter = new testable_roles_importer('task', $rolemanager, ['mock' => get_class($strategy)]);
        $rolesimporter->set_mock_strategy($strategy);

        ob_start();
        try {
            $rolesimporter->import_roles('mock');
        } finally {
            $output = ob_get_clean();
        }
        return $output;
    }

    /**
     * Git roles are applied when their file revision changes, regardless of the shared timestamp.
     *
     * @return void
     */
    public function test_git_roles_use_per_file_revisions(): void {
        $roleid = create_role('Git role', 'gitrole', '');

        $rolemanager = $this->createMock(role_manager::class);
        // A manual upload just advanced the shared timestamp past the commit time.
        $rolemanager->method('get_lastimport')->willReturn(999999);
        $rolemanager->method('get_role')->willReturn((object)['id' => $roleid]);
        $rolemanager->expects($this->exactly(2))->method('update_role_from_xml');
        $rolemanager->expects($this->never())->method('mark_last_import_success');

        // Baseline: no stored revision yet, so the file is applied and recorded.
        $output = $this->run_import($rolemanager, [$this->git_role('rev1')]);
        $this->assertStringContainsString('-gitrole [updated]', $output);

        // Same revision: unchanged.
        $output = $this->run_import($rolemanager, [$this->git_role('rev1')]);
        $this->assertStringContainsString('-gitrole [X]', $output);

        // New revision: applied again.
        $output = $this->run_import($rolemanager, [$this->git_role('rev2')]);
        $this->assertStringContainsString('-gitrole [updated]', $output);
    }

    /**
     * A created Git role is recorded only after its allow lists are linked.
     *
     * @return void
     */
    public function test_git_revision_not_recorded_when_linking_fails(): void {
        global $DB;

        $created = false;
        $rolemanager = $this->createMock(role_manager::class);
        $rolemanager->method('get_lastimport')->willReturn(0);
        $rolemanager->method('get_role')->willReturnCallback(function () use (&$created) {
            return $created ? (object)['id' => 7] : false;
        });
        $rolemanager->method('create_role_from_xml')->willReturnCallback(function () use (&$created): int {
            $created = true;
            return 7;
        });
        $rolemanager->method('update_role_allows_from_xml')
            ->willThrowException(new \moodle_exception('error:unknown', 'local_bulk_roles_importer'));

        $role = $this->git_role('rev1');
        try {
            $this->run_import($rolemanager, [$role]);
            $this->fail('Expected a scheduled retry');
        } catch (provider_exception $e) {
            $this->assertSame('error:import_incomplete', $e->errorcode);
        }
        $this->assertFalse($DB->record_exists(revision_store::TABLE, ['sourcekey' => revision_store::make_key($role->source)]));
    }

    /**
     * A failed dependency leaves its referencing role eligible for the next run.
     */
    public function test_git_retries_unchanged_blob_after_missing_allow_target_is_created(): void {
        global $DB;

        $manager = new role_manager();
        $rolea = $this->git_role('a1', 'rolea');
        $rolea->xml = '<role><shortname>rolea</shortname><allowassign><shortname>roleb</shortname></allowassign></role>';
        $roleb = $this->git_role('b1', 'roleb');
        $roleb->invalid = true;
        $roleb->fetcherror = 'Unavailable';

        try {
            $this->run_import($manager, [$rolea, $roleb]);
            $this->fail('Expected the sync to fail');
        } catch (provider_exception $e) {
            $this->assertSame('error:import_incomplete', $e->errorcode);
        }
        $aid = $manager->get_role('rolea')->id;
        $this->assertFalse($DB->record_exists('role_allow_assign', ['roleid' => $aid]));
        $this->assertNull((new revision_store())->find_applied_role($rolea->source));

        $roleb = $this->git_role('b1', 'roleb');
        $roleb->xml = '<role><shortname>roleb</shortname></role>';
        $this->run_import($manager, [$rolea, $roleb]);

        $bid = $manager->get_role('roleb')->id;
        $this->assertTrue($DB->record_exists('role_allow_assign', ['roleid' => $aid, 'allowassign' => $bid]));
        $this->assertStringContainsString('-rolea [X]', $this->run_import($manager, [$rolea, $roleb]));

        // Removing B also removes its links, so both unchanged presets need to be reapplied.
        delete_role($bid);
        $this->run_import($manager, [$rolea, $roleb]);
        $newbid = $manager->get_role('roleb')->id;
        $this->assertNotEquals($bid, $newbid);
        $this->assertTrue($DB->record_exists('role_allow_assign', ['roleid' => $aid, 'allowassign' => $newbid]));
    }

    /**
     * Switching back to a previously imported source reapplies its unchanged blobs.
     */
    public function test_git_reapplies_presets_when_switching_sources(): void {
        $manager = new role_manager();
        $gitlab = $this->git_role('lab');
        $gitlab->xml = '<role><shortname>gitrole</shortname><name>GitLab role</name></role>';
        $github = $this->git_role('hub');
        $github->xml = '<role><shortname>gitrole</shortname><name>GitHub role</name></role>';
        $github->source->provider = 'github';
        $github->source->endpoint = 'https://api.github.example.test';

        $this->run_import($manager, [$gitlab]);
        $this->assertSame('GitLab role', $manager->get_role('gitrole')->name);
        $this->run_import($manager, [$github]);
        $this->assertSame('GitHub role', $manager->get_role('gitrole')->name);
        $this->run_import($manager, [$gitlab]);
        $this->assertSame('GitLab role', $manager->get_role('gitrole')->name);

        $gitlabmirror = clone $gitlab->source;
        $gitlabmirror->endpoint = 'https://other.gitlab.example.test';
        $this->assertNotSame(revision_store::make_key($gitlab->source), revision_store::make_key($gitlabmirror));
    }

    /**
     * Files that could not be fetched fail the run after the other roles are processed.
     *
     * @return void
     */
    public function test_fetch_failures_fail_the_run_after_processing(): void {
        $rolemanager = $this->createMock(role_manager::class);
        $rolemanager->method('get_lastimport')->willReturn(0);
        $rolemanager->method('get_role')->willReturn((object)['id' => 1]);
        $rolemanager->expects($this->once())->method('update_role_from_xml');

        $broken = (object)[
            'invalid' => true, 'xml' => '', 'lastchange' => 0, 'filename' => 'roles/broken.xml',
            'fetcherror' => 'Git provider request failed with HTTP status 500',
        ];
        $strategy = $this->createMock(roles_importer_strategy_interface::class);
        $strategy->method('get_last_updated')->willReturn(2000);
        $strategy->method('get_roles')->willReturn([$broken, $this->git_role('rev1')]);
        $rolesimporter = new testable_roles_importer('task', $rolemanager, ['mock' => get_class($strategy)]);
        $rolesimporter->set_mock_strategy($strategy);

        ob_start();
        try {
            $rolesimporter->import_roles('mock');
            $this->fail('Expected exception');
        } catch (provider_exception $e) {
            $this->assertSame('error:import_incomplete', $e->errorcode);
        } finally {
            $output = ob_get_clean();
        }
        $this->assertStringContainsString('FAILED - roles/broken.xml: Git provider request failed', $output);
        $this->assertStringContainsString('-gitrole [updated]', $output);
    }

    /**
     * Provider failures before roles are listed are logged and rethrown for the task runner.
     *
     * @return void
     */
    public function test_provider_failure_is_rethrown(): void {
        $rolemanager = $this->createMock(role_manager::class);
        $rolemanager->method('get_lastimport')->willReturn(0);
        $rolemanager->expects($this->never())->method('mark_last_import_success');

        $strategy = $this->createMock(roles_importer_strategy_interface::class);
        $strategy->method('get_last_updated')->willThrowException(new provider_exception('error:provider_request', (object)[
            'endpoint' => '/repos/o/r/commits', 'status' => 401, 'message' => 'Bad credentials',
        ]));
        $rolesimporter = new testable_roles_importer('task', $rolemanager, ['mock' => get_class($strategy)]);
        $rolesimporter->set_mock_strategy($strategy);

        ob_start();
        try {
            $rolesimporter->import_roles('mock');
            $this->fail('Expected exception');
        } catch (provider_exception $e) {
            $this->assertSame('error:provider_request', $e->errorcode);
        } finally {
            $output = ob_get_clean();
        }
        $this->assertStringContainsString('HTTP status 401: Bad credentials', $output);
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
        $rolesimporter = new class ('task', $rolemanager, ['mock' => get_class($strategy)]) extends roles_importer {
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
