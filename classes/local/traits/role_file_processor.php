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
 * Trait for role file processing.
 *
 * File         role_file_processor.php.php
 * Encoding     UTF-8
 *
 * @package     local_bulk_roles_importer
 *
 * @copyright   Agiledrop, 2025
 * @author      Agiledrop ltd. <developer@agiledrop.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_bulk_roles_importer\local\traits;

use stdClass;

/**
 * Trait for role file processing.
 */
trait role_file_processor {

    /**
     * Process a role XML string.
     *
     * @param string $xml The XML content.
     * @param string $filepath The file name or path.
     * @param int $lastchange The last modified timestamp.
     * @return stdClass
     */
    protected function process_role_file_from_string(string $xml, string $filepath, int $lastchange): stdClass {
        $xmlobject = simplexml_load_string($xml);

        $role = new stdClass();
        $role->lastchange = $lastchange;
        $role->xml = $xml;

        if (!$xmlobject || $xmlobject->getName() !== 'role') {
            $role->invalid = true;
            $role->filename = basename($filepath);
        } else {
            $json = json_encode($xmlobject);
            $jsondata = json_decode($json, true);
            $role->shortname = $jsondata['shortname'] ?? false;
            $role->filename = $filepath;
        }

        return $role;
    }

    /**
     * Convenience method for reading from a file path.
     *
     * @param string $filepath
     * @param int $lastchange
     * @return stdClass|null
     */
    protected function process_role_file_from_path(string $filepath, int $lastchange): ?stdClass {
        if (!file_exists($filepath)) {
            return null;
        }

        $xml = file_get_contents($filepath);
        return $this->process_role_file_from_string($xml, $filepath, $lastchange);
    }
}
