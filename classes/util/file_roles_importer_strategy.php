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

use moodle_exception;
use stdClass;
use ZipArchive;

/**
 * Roles importing strategy for a file.
 */
class file_roles_importer_strategy implements roles_importer_strategy_interface {

    public function get_name(): string
    {
        return 'file';
    }

    public function get_last_updated(): int {
        return time();
    }

    public function get_roles(): array {
        $roles = [];
        $importfiledir = make_upload_directory('local_bulk_roles_importer');
        $importfilepath = $importfiledir  . DIRECTORY_SEPARATOR . "import_roles_file";
        $filepaths = [];

        // Prepare files
        $zip = new ZipArchive;
        if ($zip->open($importfilepath) === TRUE) {
            $extractedfolder = $importfiledir  . DIRECTORY_SEPARATOR . "extracted_files";
            $this->delete_directory_recursively($extractedfolder);
            mkdir($extractedfolder, 0777, true);
            $zip->extractTo($extractedfolder);
            $zip->close();

            $filepaths = $this->extract_xml_paths_from_directory_recursively($extractedfolder);
        } elseif (simplexml_load_file($importfilepath)) {
            $filepaths[] = $importfilepath;
        } else {
            unlink($importfilepath);
            throw new moodle_exception('Invalid file format. Must be an XML or a ZIP containing XML files.');
        }

        // Process XML files
        foreach ($filepaths as $filepath) {
            $xmlobject = simplexml_load_file($filepath);
            if ($xmlobject) {
                $json = json_encode($xmlobject);
                $jsondata = json_decode($json, true);
                $shortname = $jsondata['shortname'] ?? false;

                $role = new stdClass();
                $role->shortname = $shortname;
                $role->lastchange = time();
                $role->xml = file_get_contents($filepath);

                $roles[] = $role;
            }
        }

        // Cleanup - Delete file and extracted files
        $this->delete_directory_recursively($importfiledir);

        return $roles;
    }

    /**
     * Remove directory and all its contents recursively.
     *
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
                }
                else if (pathinfo($object, PATHINFO_EXTENSION) === 'xml') {
                    $filepaths[] = $objectpath;
                }
            }
        }

        return $filepaths;
    }

    /**
     * Remove directory and all its contents recursively.
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
                    }
                    else {
                        unlink($objectpath);
                    }
                }
            }
            rmdir($dirpath);
        }
    }

}
