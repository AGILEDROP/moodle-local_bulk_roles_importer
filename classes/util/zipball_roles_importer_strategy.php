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
 * @author      Agiledrop 2024 <developer@agiledrop.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use stdClass;

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
        $zipball = get_config('local_bulk_roles_importer/filesource');

        $files = "TODO: get files list";

//        foreach ($files as $file) {
//            $fileinfo = pathinfo($file->path);
//            $filetype = $fileinfo['extension'] ?? '';
//
//            if ($filetype != 'xml') {
//                continue;
//            }
//
//            $file_data = "TODO: get file data from $file";
//
//            $xml = $file_data->TODOgetXMLstring;
//            $xmlstring = simplexml_load_string($xml);
//
//            $json = json_encode($xmlstring);
//            $jsondata = json_decode($json, true);
//            $shortname = $jsondata['shortname'] ?? false;
//
//            $role = new stdClass();
//            $role->shortname = $shortname;
//            $role->lastchange = time();
//            $role->xml = $xml;
//
//            $roles[] = $role;
//        }
//
//        // TODO: remove file from config

        return $roles;
    }

}
