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
 * External admin page to upload a file for roles importing.
 *
 * @package     local_bulk_roles_importer
 * @category    admin
 * @copyright   Agiledrop, 2025
 * @author      Agiledrop ltd. <developer@agiledrop.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_bulk_roles_importer\form\importfromfile_form;
use local_bulk_roles_importer\roles_importer;

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir.'/adminlib.php');

// Check permissions.
require_login(null, false);
require_capability('moodle/site:config', context_system::instance());

admin_externalpage_setup('local_bulk_roles_importer_settings_file');

$title = get_string('settings:pagetitlefile', 'local_bulk_roles_importer');
$PAGE->set_url(new moodle_url('/local/bulk_roles_importer/import_from_file.php'));
$PAGE->set_title($title);
$PAGE->set_heading($title);


$mform = new importfromfile_form();
if ($mform->is_cancelled()) {
    redirect('/admin/search.php#linkusers');

} else if ($fromform = $mform->get_data()) {
    $content = $mform->get_file_content('userfile');
    $name = $mform->get_new_filename('userfile');

    $importfilepath = make_upload_directory('local_bulk_roles_importer') . "/import_roles_file";
    $success = $mform->save_file('userfile', $importfilepath, true);

    echo '<h3 class="h3">' . get_string('import:runsuccesslogheader', 'local_bulk_roles_importer') . '</h3><div class="log">';

    $rolesimporter = new roles_importer('web');
    $rolesimporter->import_roles('file');

    echo '</div>';
    echo $OUTPUT->single_button(new moodle_url('/local/bulk_roles_importer/import_from_file.php'),
        get_string('back'), 'get');

} else {
    echo $OUTPUT->header();

    $mform->display();

    echo $OUTPUT->footer();
}

