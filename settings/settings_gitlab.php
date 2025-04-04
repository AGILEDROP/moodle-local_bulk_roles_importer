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
 * GitLab plugin settings.
 *
 * File         settings_gitlab.php
 * Encoding     UTF-8
 *
 * @package     local_bulk_roles_importer
 *
 * @copyright   Agiledrop, 2025
 * @author      Agiledrop ltd. <developer@agiledrop.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die('moodle_internal not defined');

// Header - GitLab.
$setting = new admin_setting_heading(
        'local_bulk_roles_importer/gitlab_roles',
        new lang_string('header:gitlab', 'local_bulk_roles_importer'),
        new lang_string('header:gitlabinfo', 'local_bulk_roles_importer'),
);
$settingsmain->add($setting);
$settingsmain->hide_if('local_bulk_roles_importer/gitlab_roles',
    'local_bulk_roles_importer/roleretrievalsource',
    'neq',
    'gitlab');

// GitLab - Url.
$setting = new admin_setting_configtext(
        'local_bulk_roles_importer/gitlaburl',
        new lang_string('label:gitlaburl', 'local_bulk_roles_importer'),
        new lang_string('label:gitlaburl_help', 'local_bulk_roles_importer'),
        'https://gitlab.com'
);
$settingsmain->add($setting);
$settingsmain->hide_if('local_bulk_roles_importer/gitlaburl',
    'local_bulk_roles_importer/roleretrievalsource',
    'neq',
    'gitlab');

// GitLab - Auth token.
$setting = new admin_setting_configpasswordunmask(
    'local_bulk_roles_importer/gitlabtoken',
    new lang_string('label:gitlabtoken', 'local_bulk_roles_importer'),
    new lang_string('label:gitlabtoken_help', 'local_bulk_roles_importer'),
    ''
);
$settingsmain->add($setting);
$settingsmain->hide_if('local_bulk_roles_importer/gitlabtoken',
    'local_bulk_roles_importer/roleretrievalsource',
    'neq',
    'gitlab');

// GitLab - Project id.
$setting = new admin_setting_configtext(
        'local_bulk_roles_importer/gitlabproject',
        new lang_string('label:gitlabproject', 'local_bulk_roles_importer'),
        new lang_string('label:gitlabproject_help', 'local_bulk_roles_importer'),
        'moodle/template-01/roles'
);
$settingsmain->add($setting);
$settingsmain->hide_if('local_bulk_roles_importer/gitlabproject',
    'local_bulk_roles_importer/roleretrievalsource',
    'neq',
    'gitlab');

// GitLab - Main branch.
$setting = new admin_setting_configtext(
    'local_bulk_roles_importer/gitlabmain',
    new lang_string('label:gitlabmain', 'local_bulk_roles_importer'),
    new lang_string('label:gitlabmain_help', 'local_bulk_roles_importer'),
    'main'
);
$settingsmain->add($setting);
$settingsmain->hide_if('local_bulk_roles_importer/gitlabmain',
    'local_bulk_roles_importer/roleretrievalsource',
    'neq',
    'gitlab');
