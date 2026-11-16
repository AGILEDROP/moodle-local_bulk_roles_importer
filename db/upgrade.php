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
 * Upgrade steps for local_bulk_roles_importer.
 *
 * @package     local_bulk_roles_importer
 * @copyright   Agiledrop, 2025
 * @author      Agiledrop ltd. <developer@agiledrop.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the plugin.
 *
 * @param int $oldversion Installed version.
 * @return bool
 */
function xmldb_local_bulk_roles_importer_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026111600) {
        // Per-file Git revisions. The table starts empty on purpose: the old shared
        // timestamp may have been advanced by a manual upload, so it cannot prove
        // which Git changes were applied. The first Git import after this upgrade
        // re-applies every role file once and records its revision as the baseline.
        $table = new xmldb_table('local_bulk_roles_importer_rev');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('sourcekey', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, null);
        $table->add_field('provider', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('repository', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('branch', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('filepath', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('revision', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
        $table->add_field('revisiontime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('sourcekey', XMLDB_INDEX_UNIQUE, ['sourcekey']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026111600, 'local', 'bulk_roles_importer');
    }

    if ($oldversion < 2026111601) {
        $table = new xmldb_table('local_bulk_roles_importer_rev');
        $roleid = new xmldb_field('roleid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timemodified');
        $allowtargets = new xmldb_field('allowtargets', XMLDB_TYPE_TEXT, null, null, null, null, null, 'roleid');
        if (!$dbman->field_exists($table, $roleid)) {
            $dbman->add_field($table, $roleid);
        }
        if (!$dbman->field_exists($table, $allowtargets)) {
            $dbman->add_field($table, $allowtargets);
        }

        upgrade_plugin_savepoint(true, 2026111601, 'local', 'bulk_roles_importer');
    }

    return true;
}
