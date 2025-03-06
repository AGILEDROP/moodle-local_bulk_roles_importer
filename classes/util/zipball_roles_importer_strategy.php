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
 * Strategy to import roles from GitHub repository.
 *
 * File         github_roles_importer_strategy.php
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
 * Roles importing strategy for Zipball file.
 */
class zipball_roles_importer_strategy implements roles_importer_strategy_interface {

    public function get_name(): string
    {
        return 'zipball';
    }

    public function get_last_updated(): int {
        return time();
    }

    public function get_roles(): array {
        $roles = [];
        $importfiledir = make_upload_directory('local_bulk_roles_importer');
        $importfilepath = $importfiledir  . DIRECTORY_SEPARATOR . "import_roles_file";
        $extractedfolder = false;
        $filepaths = [];

        // Prepare files
        $zip = new ZipArchive;
        if ($zip->open($importfilepath) === TRUE) {
            $extractedfolder = $importfiledir  . DIRECTORY_SEPARATOR . "extracted_files";
            mkdir($extractedfolder, 0777, true);
            $zip->extractTo($extractedfolder);
            $zip->close();

            $files = array_diff(scandir($extractedfolder), array('..', '.'));
            if (count($files) === 1) {
                $singleitem = reset($files);
                $singleitempath = $extractedfolder . DIRECTORY_SEPARATOR . $singleitem;
                if (is_dir($singleitempath)) {
                    $extractedfolder = $singleitempath;
                }
            }

            foreach (scandir($extractedfolder) as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) === 'xml') {
                    $filepaths[] = $extractedfolder . DIRECTORY_SEPARATOR . $file;
                }
            }
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
        unlink($importfilepath);
        if ($extractedfolder) {
            $this->delete_directory_recursively($extractedfolder);
        }

        return $roles;
    }

    /**
     * Remove directory and all its contents recursively.
     */
    private function delete_directory_recursively(string $dirpath): void {
        if (is_dir($dirpath)) {
            $objects = scandir($dirpath);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (
                        is_dir($dirpath. DIRECTORY_SEPARATOR .$object)
                        && !is_link($dirpath. DIRECTORY_SEPARATOR .$object)
                    ) {
                        $this->delete_directory_recursively($dirpath. DIRECTORY_SEPARATOR .$object);
                    }
                    else {
                        unlink($dirpath. DIRECTORY_SEPARATOR .$object);
                    }
                }
            }
            rmdir($dirpath);
        }
    }

}
