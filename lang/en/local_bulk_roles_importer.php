<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin strings are defined here.
 *
 * @package     local_bulk_roles_importer
 * @category    string
 * @copyright   Agiledrop, 2025
 * @author      Agiledrop ltd. <developer@agiledrop.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['error:cannot_obtain_roles'] = 'ERROR - cannot obtain roles';
$string['error:incorrect_format_file'] = 'Incorrect format in file: {$a}';
$string['error:invalid_file_format'] = 'Invalid file format. Must be an XML or a ZIP containing XML files.';
$string['error:invalid_xml'] = 'invalid XML';
$string['error:main_branch_time'] = 'ERROR - cannot obtain main branch last updated time';
$string['error:strategy_does_not_exist'] = 'ERROR - source: {$a} does not exist';
$string['error:strategy_not_automatic'] = 'ERROR - source: {$a} is not automatic';
$string['error:unknown'] = 'Unknown error';

$string['form:description'] = 'Description';
$string['form:descriptiontext'] = 'You can upload either a single XML file, a ZIP file containing XML files.';
$string['form:import'] = 'Import';

$string['header:file'] = 'File';
$string['header:fileinfo'] = 'Select file from which to import roles.';
$string['header:github'] = 'Github';
$string['header:githubinfo'] = 'Enter Github info data to import roles from repository.';
$string['header:gitlab'] = 'Gitlab';
$string['header:gitlabinfo'] = 'Enter Gitlab info data to import roles from repository.';

$string['import:runsuccesslogheader'] = 'Import log';

$string['label:filesource'] = 'Select file';
$string['label:filesource_help'] = 'Select the file with role files to import.';
$string['label:githubmain'] = 'Main branch';
$string['label:githubmain_help'] = 'Enter Github main branch name.';
$string['label:githubproject'] = 'Project';
$string['label:githubproject_help'] = 'Enter Github project name.';
$string['label:githubtoken'] = 'Auth token';
$string['label:githubtoken_help'] = 'Enter your GitHub personal access token. This token is required to access private repositories via the GitHub API. 
You can create one by following the instructions at https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/managing-your-personal-access-tokens';
$string['label:githuburl'] = 'Github URL';
$string['label:githuburl_help'] = 'Enter Github URL without slash at the end.';
$string['label:gitlabmain'] = 'Main branch';
$string['label:gitlabmain_help'] = 'Enter Gitlab main branch name.';
$string['label:gitlabproject'] = 'Project';
$string['label:gitlabproject_help'] = 'Enter Gitlab project name.';
$string['label:gitlabtoken'] = 'Auth token';
$string['label:gitlabtoken_help'] = 'Enter your GitLab personal or project access token. This token is required to access private repositories via the GitLab API.
You can create a personal access token at https://docs.gitlab.com/user/profile/personal_access_tokens/#create-a-personal-access-token
or a project access token at https://docs.gitlab.com/user/project/settings/project_access_tokens/#create-a-project-access-token';$string['label:gitlaburl'] = 'Gitlab URL';
$string['label:gitlaburl_help'] = 'Enter Gitlab URL without slash at the end.';
$string['label:rolesretrievalsource'] = 'Role import source';
$string['label:rolesretrievalsourcedescription'] = 'From which source would you like to import roles.';
$string['label:scheduledtasksettings'] = 'Edit schedule';
$string['label:scheduledtasksettingsdescription'] = 'Go to scheduled task settings.';
$string['label:taskgitlabroles'] = 'Bulk Roles Importer - Import roles from GitLab.';
$string['label:taskimportroles'] = 'Bulk Roles Importer - Import roles from selected source.';

$string['log:import_roles'] = 'IMPORT ROLES FROM SOURCE: {$a}';
$string['log:role_created'] = '- {$a} [created]';
$string['log:role_not_found'] = '[Role not found]';
$string['log:updated'] = '[updated]';

$string['pluginname'] = 'Bulk Roles Importer';

$string['privacy:metadata'] = 'The Bulk Roles Importer plugin does not store any personal data.';

$string['settings:pagetitlefile'] = 'Import roles from a file';
$string['settings:pagetitlemain'] = 'Bulk roles importer settings';
