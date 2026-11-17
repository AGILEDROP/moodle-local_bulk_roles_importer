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

namespace local_bulk_roles_importer\local\util;

use coding_exception;
use local_bulk_roles_importer\local\traits\role_file_processor;
use moodle_exception;
use ZipArchive;

/**
 * Roles importing strategy for a file.
 */
class file_roles_importer_strategy implements roles_importer_strategy_interface {
    use role_file_processor;

    /** @var string $importfilepath Uploaded file in a request-specific directory owned by the caller. */
    private string $importfilepath;

    /**
     * Constructor.
     *
     * @param string|null $importfilepath Uploaded file in a request-specific directory owned by the caller.
     * @throws coding_exception If no file path is given.
     */
    public function __construct(?string $importfilepath = null) {
        if ($importfilepath === null || $importfilepath === '') {
            throw new coding_exception('The file roles importer strategy requires an import file path.');
        }
        $this->importfilepath = $importfilepath;
    }

    #[\Override]
    public function get_name(): string {
        return 'file';
    }

    #[\Override]
    public function get_last_updated(): int {
        return time();
    }

    /**
     * Get roles from the uploaded XML or ZIP file.
     *
     * ZIP files are extracted next to the upload. The caller owns the directory and removes it.
     *
     * @param callable|null $findapplied Not used by this strategy.
     * @return array
     * @throws moodle_exception If the archive cannot be extracted or the file cannot be read or parsed.
     */
    #[\Override]
    public function get_roles(?callable $findapplied = null): array {
        $importfilepath = $this->importfilepath;

        $zip = new ZipArchive();
        if ($zip->open($importfilepath) === true) {
            $extractedfolder = dirname($importfilepath) . DIRECTORY_SEPARATOR . 'extracted_files';
            $extracted = check_dir_exists($extractedfolder) && $zip->extractTo($extractedfolder);
            $zip->close();
            if (!$extracted) {
                throw new moodle_exception('error:extract_failed', 'local_bulk_roles_importer');
            }

            $roles = [];
            foreach ($this->extract_xml_paths_from_directory_recursively($extractedfolder) as $filepath) {
                $role = $this->process_role_file_from_path($filepath, time());
                // Show the name inside the ZIP.
                $role->filename = basename($filepath);
                $roles[] = $role;
            }
            return $roles;
        }

        $xml = is_file($importfilepath) && is_readable($importfilepath) ? @file_get_contents($importfilepath) : false;
        if ($xml === false) {
            throw new moodle_exception('error:cannot_read_upload', 'local_bulk_roles_importer');
        }
        $xmlobject = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        if ($xmlobject === false) {
            throw new moodle_exception('error:invalid_file_format', 'local_bulk_roles_importer');
        }

        $role = $this->process_role_file_from_string($xml, $importfilepath, time());
        // A single upload falls back to the uploaded file name.
        $role->filename = null;

        return [$role];
    }

    /**
     * Find XML file paths in a directory recursively.
     *
     * @param string $dirpath
     * @return array
     */
    private function extract_xml_paths_from_directory_recursively(string $dirpath): array {
        if (!is_dir($dirpath)) {
            return [];
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
}
