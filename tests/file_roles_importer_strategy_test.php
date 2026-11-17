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
 * PHPUnit tests for the file roles importer strategy.
 *
 * @package     local_bulk_roles_importer
 * @copyright   Agiledrop, 2025
 * @author      Agiledrop ltd. <developer@agiledrop.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_bulk_roles_importer;

use advanced_testcase;
use coding_exception;
use local_bulk_roles_importer\local\util\file_roles_importer_strategy;
use local_bulk_roles_importer\local\util\roles_importer_strategies_manager;
use moodle_exception;
use ZipArchive;

/**
 * Unit tests for the file roles importer strategy.
 *
 * @covers \local_bulk_roles_importer\local\util\file_roles_importer_strategy
 * @covers \local_bulk_roles_importer\local\util\roles_importer_strategies_manager
 * @copyright   Agiledrop, 2025
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class file_roles_importer_strategy_test extends advanced_testcase {
    /**
     * Build a role XML document.
     *
     * @param string $shortname
     * @return string
     */
    private function role_xml(string $shortname): string {
        return '<?xml version="1.0" encoding="UTF-8"?><role><shortname>' . $shortname . '</shortname></role>';
    }

    /**
     * Write content to an upload file in a new request directory.
     *
     * @param string $content
     * @return string Path of the upload file.
     */
    private function make_upload(string $content): string {
        $path = make_request_directory() . DIRECTORY_SEPARATOR . 'import_roles_file';
        file_put_contents($path, $content);
        return $path;
    }

    /**
     * A single XML upload returns one role that falls back to the uploaded file name.
     */
    public function test_single_xml_file(): void {
        $path = $this->make_upload($this->role_xml('single'));

        $roles = (new file_roles_importer_strategy($path))->get_roles();

        $this->assertCount(1, $roles);
        $this->assertSame('single', $roles[0]->shortname);
        $this->assertNull($roles[0]->filename);
        $this->assertTrue(is_file($path), 'The caller owns the upload and removes it.');
    }

    /**
     * A ZIP upload returns roles from nested XML files only, named by the file inside the ZIP.
     */
    public function test_zip_file_with_nested_xml(): void {
        $path = make_request_directory() . DIRECTORY_SEPARATOR . 'import_roles_file';
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path, ZipArchive::CREATE));
        $zip->addFromString('first.xml', $this->role_xml('first'));
        $zip->addFromString('nested/second.xml', $this->role_xml('second'));
        $zip->addFromString('readme.txt', 'not a role');
        $zip->close();

        $roles = (new file_roles_importer_strategy($path))->get_roles();

        $shortnames = array_column(array_map(fn($role) => (array)$role, $roles), 'shortname', 'filename');
        ksort($shortnames);
        $this->assertSame(['first.xml' => 'first', 'second.xml' => 'second'], $shortnames);
    }

    /**
     * A file that is neither XML nor ZIP is rejected with a language string ID.
     */
    public function test_invalid_file_format(): void {
        $path = $this->make_upload('this is not xml');

        try {
            (new file_roles_importer_strategy($path))->get_roles();
            $this->fail('Expected exception was not thrown.');
        } catch (moodle_exception $e) {
            $this->assertSame('error:invalid_file_format', $e->errorcode);
            $this->assertSame('local_bulk_roles_importer', $e->module);
        }
    }

    /**
     * A missing upload is rejected before importing.
     */
    public function test_unreadable_upload(): void {
        $path = make_request_directory() . DIRECTORY_SEPARATOR . 'missing';

        try {
            (new file_roles_importer_strategy($path))->get_roles();
            $this->fail('Expected exception was not thrown.');
        } catch (moodle_exception $e) {
            $this->assertSame('error:cannot_read_upload', $e->errorcode);
        }
    }

    /**
     * Uploads staged in different request directories do not affect each other.
     */
    public function test_request_directories_are_isolated(): void {
        $first = $this->make_upload($this->role_xml('first'));
        $second = $this->make_upload($this->role_xml('second'));
        $this->assertNotSame(dirname($first), dirname($second));

        $firstroles = (new file_roles_importer_strategy($first))->get_roles();
        $secondroles = (new file_roles_importer_strategy($second))->get_roles();

        $this->assertSame('first', $firstroles[0]->shortname);
        $this->assertSame('second', $secondroles[0]->shortname);
        $this->assertTrue(is_file($first));
        $this->assertTrue(is_file($second));
    }

    /**
     * The strategy needs an upload path.
     */
    public function test_requires_file_path(): void {
        $this->expectException(coding_exception::class);
        new file_roles_importer_strategy();
    }

    /**
     * The roles importer passes the upload path to the file strategy and imports from it.
     */
    public function test_roles_importer_uses_upload_path(): void {
        $this->resetAfterTest();
        $path = $this->make_upload($this->role_xml('fromupload'));

        $importer = new roles_importer('web');
        $importer->set_import_filename('upload.xml');
        $importer->set_import_filepath($path);

        ob_start();
        $importer->import_roles('file');
        $output = ob_get_clean();

        $this->assertStringContainsString('fromupload [created]', $output);
        $this->assertStringContainsString('failed: 0', $output);
        $this->assertTrue((bool)array_filter(get_all_roles(), fn($role) => $role->shortname === 'fromupload'));
    }

    /**
     * Provider labels are resolved from language strings.
     */
    public function test_provider_labels_use_language_strings(): void {
        $this->assertSame(
            [
                'github' => get_string('provider:github', 'local_bulk_roles_importer'),
                'gitlab' => get_string('provider:gitlab', 'local_bulk_roles_importer'),
            ],
            roles_importer_strategies_manager::get_automatic_strategies_names()
        );
        $this->assertSame(
            get_string('provider:file', 'local_bulk_roles_importer'),
            roles_importer_strategies_manager::get_strategies_names()['file']
        );
    }
}
