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

namespace local_bulk_roles_importer\form;

use moodleform;

/**
 * Form to upload a file for roles importing.
 *
 * @package     local_bulk_roles_importer
 * @category    admin
 * @copyright   Agiledrop, 2025
 * @author      Agiledrop ltd. <developer@agiledrop.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("$CFG->libdir/formslib.php");

class importfromfile_form extends moodleform {
    // Add elements to form.
    public function definition() {
        $mform = $this->_form;

        $mform->addElement(
            'static',
            'description',
            get_string('form:description', 'local_bulk_roles_importer'),
            get_string('form:descriptiontext', 'local_bulk_roles_importer')
        );
        $mform->addElement(
            'filepicker',
            'userfile',
            get_string('file'),
            null,
            [
                'accepted_types' => ['.zip', '.xml'],
                'maxfiles' => 1,
                'subdirs' => 0,
            ],
        );

        $this->add_action_buttons(true, get_string('form:import', 'local_bulk_roles_importer'));
    }
}