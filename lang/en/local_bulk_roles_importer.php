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
 * @copyright   2025 Your Name <developer@agiledrop.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Bulk Roles Importer';
$string['settings:pagetitle'] = 'Bulk Roles Importer Settings';

$string['label:githuboption'] = 'Github';
$string['label:gitlaboption'] = 'Gitlab';
$string['label:bitbucketoption'] = 'Bitbucket';
$string['label:fileoption'] = 'File';
$string['label:rolesretrievalsource'] = 'Role import source';
$string['label:rolesretrievalsourcedescription'] = 'From which source would you like to import roles.';
$string['label:taskruntimeenabled'] = 'Enable automatic import';
$string['label:taskruntimeenableddescription'] = 'Should the import of roles be ran automatically every day.';
$string['label:taskruntime'] = 'Automatic import time';
$string['label:taskruntimedescription'] = 'When should the automatic import happen.';

$string['header:github'] = 'Github';
$string['header:githubinfo'] = 'Enter Github info data to import roles from repository.';
$string['label:githuburl'] = 'Github URL';
$string['label:githuburl_help'] = 'Enter Github URL without slash at the end.';
$string['label:githubtoken'] = 'Auth token';
$string['label:githubtoken_help'] = 'Enter Github auth token.';
$string['label:githubproject'] = 'Project';
$string['label:githubproject_help'] = 'Enter Github project name.';
$string['label:githubmaster'] = 'Master branch';
$string['label:githubmaster_help'] = 'Enter Github master branch name.';

$string['header:gitlab'] = 'Gitlab';
$string['header:gitlabinfo'] = 'Enter Gitlab info data to import roles from repository.';
$string['label:gitlaburl'] = 'Gitlab URL';
$string['label:gitlaburl_help'] = 'Enter Gitlab URL without slash at the end.';
$string['label:gitlabtoken'] = 'Auth token';
$string['label:gitlabtoken_help'] = 'Enter Gitlab auth token.';
$string['label:gitlabproject'] = 'Project';
$string['label:gitlabproject_help'] = 'Enter Gitlab project name.';
$string['label:gitlabmaster'] = 'Master branch';
$string['label:gitlabmaster_help'] = 'Enter Gitlab master branch name.';

$string['header:bitbucket'] = 'Bitbucket';
$string['header:bitbucketinfo'] = 'Enter Bitbucket info data to import roles from repository.';
$string['label:bitbucketurl'] = 'Bitbucket URL';
$string['label:bitbucketurl_help'] = 'Enter Bitbucket URL without slash at the end.';
$string['label:bitbuckettoken'] = 'Auth token';
$string['label:bitbuckettoken_help'] = 'Enter Bitbucket auth token.';
$string['label:bitbucketproject'] = 'Project';
$string['label:bitbucketproject_help'] = 'Enter Bitbucket project name.';
$string['label:bitbucketmaster'] = 'Master branch';
$string['label:bitbucketmaster_help'] = 'Enter Bitbucket master branch name.';

$string['header:file'] = 'File';
$string['header:fileinfo'] = 'Select file from which to import roles.';
$string['label:filesource'] = 'Select file';
$string['label:filesource_help'] = 'Select the file with role files to import.';

$string['label:taskgitlabroles'] = 'Bulk Roles Importer - Import roles from GitLab.';
