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
 * Strategy to import roles from a file.
 *
 * File         file_roles_importer_strategy.php
 * Encoding     UTF-8
 *
 * @package     local_bulk_roles_importer
 *
 * @copyright   Agiledrop, 2025
 * @author      Agiledrop ltd. <developer@agiledrop.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_bulk_roles_importer\util;

use local_bulk_roles_importer\traits\role_file_processor;
use moodle_exception;
use ZipArchive;

/**
 * Roles importing strategy for a file.
 */
class file_roles_importer_strategy implements roles_importer_strategy_interface {
    use role_file_processor;
    /**
     * @var bool $iszip True if the file is a zip file.
     */
    private bool $iszip = false;

    #[\Override]
    public function get_name(): string {
        return 'file';
    }

    #[\Override]
    public function get_last_updated(): int {
        return time();
    }

    #[\Override]
    public function get_roles(): array {
        $roles = [];
        $importfiledir = make_upload_directory('local_bulk_roles_importer');
        $importfilepath = $importfiledir . DIRECTORY_SEPARATOR . "import_roles_file";
        $filepaths = [];

        // Prepare files.
        $zip = new ZipArchive;
        if ($zip->open($importfilepath) === true) {
            $this->iszip = true;

            $extractedfolder = $importfiledir . DIRECTORY_SEPARATOR . "extracted_files";
            $this->delete_directory_recursively($extractedfolder);
            mkdir($extractedfolder, 0777, true);
            $zip->extractTo($extractedfolder);
            $zip->close();

            $filepaths = $this->extract_xml_paths_from_directory_recursively($extractedfolder);
        } else if (simplexml_load_file($importfilepath)) {
            $filepaths[] = $importfilepath;
        } else {
            unlink($importfilepath);
            throw new moodle_exception('Invalid file format. Must be an XML or a ZIP containing XML files.');
        }

        // Process XML files.
        foreach ($filepaths as $filepath) {
            $role = $this->process_role_file_from_path($filepath, time());

            // Override filename for display if imported from ZIP.
            if ($this->iszip) {
                $role->filename = basename($filepath);
            }

            $roles[] = $role;
        }

        $this->delete_directory_recursively($importfiledir);

        return $roles;
    }


    /**
     * Remove directory and all its contents recursively.
     *
     * @param string $dirpath
     * @return array|false
     */
    private function extract_xml_paths_from_directory_recursively(string $dirpath): array|false {
        if (!is_dir($dirpath)) {
            return false;
        }

        $filepaths = [];

        foreach (scandir($dirpath) as $object) {
            if ($object != "." && $object != "..") {
                $objectpath = $dirpath . DIRECTORY_SEPARATOR . $object;
                if (
                    is_dir($objectpath)
                    && !is_link($objectpath)
                ) {
                    $filepaths = array_merge(
                        $filepaths,
                        $this->extract_xml_paths_from_directory_recursively($objectpath),
                    );
                } else if (pathinfo($object, PATHINFO_EXTENSION) === 'xml') {
                    $filepaths[] = $objectpath;
                }
            }
        }

        return $filepaths;
    }

    /**
     * Remove directory and all its contents recursively.
     *
     * @param string $dirpath
     * @return void
     */
    private function delete_directory_recursively(string $dirpath): void {
        if (is_dir($dirpath)) {
            foreach (scandir($dirpath) as $object) {
                if ($object != "." && $object != "..") {
                    $objectpath = $dirpath . DIRECTORY_SEPARATOR . $object;
                    if (
                        is_dir($objectpath)
                        && !is_link($objectpath)
                    ) {
                        $this->delete_directory_recursively($objectpath);
                    } else {
                        unlink($objectpath);
                    }
                }
            }
            rmdir($dirpath);
        }
    }
}
