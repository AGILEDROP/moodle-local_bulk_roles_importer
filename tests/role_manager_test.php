<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_bulk_roles_importer;

use advanced_testcase;
use context_system;
use local_bulk_roles_importer\local\util\role_manager;
use moodle_exception;

/**
 * Unit tests for role_manager class.
 *
 * @package     local_bulk_roles_importer
 * @covers      \local_bulk_roles_importer\local\util\role_manager
 * @copyright   Agiledrop, 2025
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class role_manager_test extends advanced_testcase {
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
     * Build a role preset XML. Sections passed as null are omitted.
     *
     * @param string $shortname Shortname.
     * @param array|null $contextlevels Context level names.
     * @param array|null $allowassign Shortnames allowed to assign.
     * @param array $permissions Map of capability => inherit|allow|prevent|prohibit.
     * @param string $name Role name.
     * @return string
     */
    private function preset(
        string $shortname,
        ?array $contextlevels = null,
        ?array $allowassign = null,
        array $permissions = [],
        string $name = 'Test role'
    ): string {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><role>';
        $xml .= '<shortname>' . s($shortname) . '</shortname>';
        $xml .= '<name>' . s($name) . '</name><description></description><archetype></archetype>';
        if ($contextlevels !== null) {
            $xml .= '<contextlevels>';
            foreach ($contextlevels as $level) {
                $xml .= '<level>' . $level . '</level>';
            }
            $xml .= '</contextlevels>';
        }
        if ($allowassign !== null) {
            $xml .= '<allowassign>';
            foreach ($allowassign as $target) {
                $xml .= '<shortname>' . $target . '</shortname>';
            }
            $xml .= '</allowassign>';
        }
        $xml .= '<permissions>';
        foreach ($permissions as $capability => $permission) {
            $xml .= "<{$permission}>{$capability}</{$permission}>";
        }
        $xml .= '</permissions></role>';
        return $xml;
    }

    /**
     * New roles are created under the normalised shortname.
     *
     * @return void
     */
    public function test_create_role_normalises_shortname(): void {
        global $DB;

        (new role_manager())->create_role_from_xml($this->preset('NewRole', ['course']));

        $role = $DB->get_record('role', ['shortname' => 'newrole'], '*', MUST_EXIST);
        $this->assertEquals([CONTEXT_COURSE], array_values(get_role_contextlevels($role->id)));
    }

    /**
     * Creating a role from XML saves capabilities without submitted form fields.
     */
    public function test_create_role_saves_preset_permissions(): void {
        global $DB;

        $xml = $this->preset('newpermissions', null, null, [
            'moodle/course:view' => 'allow',
            'moodle/user:viewdetails' => 'prohibit',
        ]);
        (new role_manager())->create_role_from_xml($xml);

        $roleid = $DB->get_field('role', 'id', ['shortname' => 'newpermissions'], MUST_EXIST);
        $permissions = $DB->get_records_menu('role_capabilities', [
            'roleid' => $roleid,
            'contextid' => context_system::instance()->id,
        ], '', 'capability,permission');
        $this->assertEquals([
            'moodle/course:view' => CAP_ALLOW,
            'moodle/user:viewdetails' => CAP_PROHIBIT,
        ], $permissions);
    }

    /**
     * Creating a role ignores role-editor request parameters.
     *
     * @return void
     */
    public function test_create_role_ignores_request_parameters(): void {
        global $DB;

        $_POST['shortname'] = 'hijacked';
        $_POST['name'] = 'Hijacked';
        $_POST['moodle/course:view'] = (string)CAP_PROHIBIT;
        try {
            $roleid = (new role_manager())->create_role_from_xml(
                $this->preset('requestrole', ['course'], null, ['moodle/course:view' => 'allow'], 'Request role')
            );
        } finally {
            unset($_POST['shortname'], $_POST['name'], $_POST['moodle/course:view']);
        }

        $role = $DB->get_record('role', ['id' => $roleid], '*', MUST_EXIST);
        $this->assertEquals('requestrole', $role->shortname);
        $this->assertEquals('Request role', $role->name);
        $this->assertEquals(CAP_ALLOW, $DB->get_field('role_capabilities', 'permission', [
            'roleid' => $roleid,
            'contextid' => context_system::instance()->id,
            'capability' => 'moodle/course:view',
        ]));
    }

    /**
     * A role that lists itself in an allow list is linked to itself.
     *
     * @return void
     */
    public function test_allows_self_reference_is_linked(): void {
        global $DB;

        $manager = new role_manager();
        $xml = $this->preset('selfrole', ['course'], ['selfrole']);
        $roleid = $manager->create_role_from_xml($xml);
        $manager->update_role_allows_from_xml($roleid, $xml);

        $this->assertTrue($DB->record_exists('role_allow_assign', ['roleid' => $roleid, 'allowassign' => $roleid]));
    }

    /**
     * Invalid shortnames are rejected before anything is written.
     *
     * @return void
     */
    public function test_invalid_shortname_is_rejected(): void {
        global $DB;

        $count = $DB->count_records('role');
        try {
            (new role_manager())->create_role_from_xml($this->preset('!!!'));
            $this->fail('Expected exception');
        } catch (moodle_exception $e) {
            $this->assertSame('error:invalid_shortname', $e->errorcode);
        }
        $this->assertSame($count, $DB->count_records('role'));
    }

    /**
     * Updating a role whose shortname does not match the preset is rejected.
     *
     * @return void
     */
    public function test_update_rejects_shortname_mismatch(): void {
        $roleid = create_role('Other', 'otherrole', '');

        $this->expectException(moodle_exception::class);
        (new role_manager())->update_role_from_xml($roleid, $this->preset('somethingelse'));
    }

    /**
     * Explicit inherit entries remove existing overrides; other values change in place.
     *
     * @return void
     */
    public function test_update_permissions_inherit_removes_override(): void {
        global $DB;

        $context = context_system::instance();
        $roleid = create_role('Perm', 'permrole', '');
        assign_capability('moodle/course:view', CAP_ALLOW, $roleid, $context->id);
        assign_capability('moodle/user:viewdetails', CAP_ALLOW, $roleid, $context->id);

        (new role_manager())->update_role_from_xml($roleid, $this->preset('permrole', null, null, [
            'moodle/course:view' => 'inherit',
            'moodle/user:viewdetails' => 'prohibit',
        ]));

        $caps = $DB->get_records_menu(
            'role_capabilities',
            ['roleid' => $roleid, 'contextid' => $context->id],
            '',
            'capability,permission'
        );
        $this->assertArrayNotHasKey('moodle/course:view', $caps);
        $this->assertEquals(CAP_PROHIBIT, $caps['moodle/user:viewdetails']);
    }

    /**
     * Omitted context level and allow sections keep the current values.
     *
     * @return void
     */
    public function test_update_preserves_omitted_sections(): void {
        global $DB;

        $studentid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        $roleid = create_role('Keep', 'keeprole', '');
        set_role_contextlevels($roleid, [CONTEXT_COURSE]);
        core_role_set_assign_allowed($roleid, $studentid);

        (new role_manager())->update_role_from_xml($roleid, $this->preset('keeprole', null, null, [], 'Renamed'));

        $this->assertEquals('Renamed', $DB->get_field('role', 'name', ['id' => $roleid]));
        $this->assertEquals([CONTEXT_COURSE], array_values(get_role_contextlevels($roleid)));
        $this->assertTrue($DB->record_exists('role_allow_assign', ['roleid' => $roleid, 'allowassign' => $studentid]));
    }

    /**
     * Explicitly empty context level and allow sections clear the current values.
     *
     * @return void
     */
    public function test_update_clears_empty_sections(): void {
        global $DB;

        $studentid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        $roleid = create_role('Clear', 'clearrole', '');
        set_role_contextlevels($roleid, [CONTEXT_COURSE]);
        core_role_set_assign_allowed($roleid, $studentid);

        (new role_manager())->update_role_from_xml($roleid, $this->preset('clearrole', [], []));

        $this->assertEquals([], get_role_contextlevels($roleid));
        $this->assertFalse($DB->record_exists('role_allow_assign', ['roleid' => $roleid]));
    }

    /**
     * A failure part way through an update rolls back every change to the role.
     *
     * @return void
     */
    public function test_update_failure_rolls_back(): void {
        global $DB;

        $this->preventResetByRollback();
        $roleid = create_role('Original', 'atomicrole', '');
        set_role_contextlevels($roleid, [CONTEXT_COURSE]);

        $manager = new class extends role_manager {
            /**
             * Fail after role info and context levels were written.
             *
             * @param int $roleid
             * @param array $permissions
             * @return void
             */
            protected function update_permissions(int $roleid, array $permissions): void {
                throw new \coding_exception('boom');
            }
        };

        try {
            $manager->update_role_from_xml($roleid, $this->preset('atomicrole', ['system'], null, [], 'Changed'));
            $this->fail('Expected exception');
        } catch (\local_bulk_roles_importer\local\exception\transaction_rolled_back $e) {
            $this->assertInstanceOf(\coding_exception::class, $e->getPrevious());
            $this->assertStringContainsString('boom', $e->getMessage());
        }

        $this->assertEquals('Original', $DB->get_field('role', 'name', ['id' => $roleid]));
        $this->assertEquals([CONTEXT_COURSE], array_values(get_role_contextlevels($roleid)));
    }

    /**
     * Allow lists of a role created earlier in a batch can reference roles created later.
     *
     * @return void
     */
    public function test_update_allows_links_roles_created_later(): void {
        global $DB;

        $manager = new role_manager();
        $firstxml = $this->preset('firstrole', ['course'], ['secondrole'], [], 'First');
        $manager->create_role_from_xml($firstxml);
        $manager->create_role_from_xml($this->preset('secondrole', ['course']));

        $firstid = $DB->get_field('role', 'id', ['shortname' => 'firstrole']);
        $secondid = $DB->get_field('role', 'id', ['shortname' => 'secondrole']);
        $this->assertFalse($DB->record_exists('role_allow_assign', ['roleid' => $firstid]));

        $manager->update_role_allows_from_xml($firstid, $firstxml);

        $this->assertTrue($DB->record_exists('role_allow_assign', ['roleid' => $firstid, 'allowassign' => $secondid]));
    }
}
