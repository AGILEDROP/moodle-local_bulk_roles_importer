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
 * GitHub plugin settings.
 *
 * File         settings_github.php
 * Encoding     UTF-8
 *
 * @package     local_bulk_roles_importer
 *
 * @copyright   Agiledrop, 2025
 * @author      Agiledrop ltd. <developer@agiledrop.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die('moodle_internal not defined');

// Header - GitHub.
$setting = new admin_setting_heading(
        'local_bulk_roles_importer/github_roles',
        new lang_string('header:github', 'local_bulk_roles_importer'),
        new lang_string('header:githubinfo', 'local_bulk_roles_importer'),
);
$settingsmain->add($setting);
$settingsmain->hide_if('local_bulk_roles_importer/github_roles',
    'local_bulk_roles_importer/roleretrievalsource',
    'neq',
    'github');

// GitHub - Url.
$setting = new admin_setting_configtext(
        'local_bulk_roles_importer/githuburl',
        new lang_string('label:githuburl', 'local_bulk_roles_importer'),
        new lang_string('label:githuburl_help', 'local_bulk_roles_importer'),
        'https://api.github.com'
);
$settingsmain->add($setting);
$settingsmain->hide_if('local_bulk_roles_importer/githuburl',
    'local_bulk_roles_importer/roleretrievalsource',
    'neq',
    'github');

// GitHub - Auth token.
$setting = new admin_setting_configpasswordunmask(
    'local_bulk_roles_importer/githubtoken',
    new lang_string('label:githubtoken', 'local_bulk_roles_importer'),
    new lang_string('label:githubtoken_help', 'local_bulk_roles_importer'),
    ''
);
$settingsmain->add($setting);
$settingsmain->hide_if('local_bulk_roles_importer/githubtoken',
    'local_bulk_roles_importer/roleretrievalsource',
    'neq',
    'github');

// GitHub - Project id.
$setting = new admin_setting_configtext(
        'local_bulk_roles_importer/githubproject',
        new lang_string('label:githubproject', 'local_bulk_roles_importer'),
        new lang_string('label:githubproject_help', 'local_bulk_roles_importer'),
        'moodle/template-01/roles'
);
$settingsmain->add($setting);
$settingsmain->hide_if('local_bulk_roles_importer/githubproject',
    'local_bulk_roles_importer/roleretrievalsource',
    'neq',
    'github');

// GitHub - Master branch.
$setting = new admin_setting_configtext(
    'local_bulk_roles_importer/githubmaster',
    new lang_string('label:githubmaster', 'local_bulk_roles_importer'),
    new lang_string('label:githubmaster_help', 'local_bulk_roles_importer'),
    'main'
);
$settingsmain->add($setting);
$settingsmain->hide_if('local_bulk_roles_importer/githubmaster',
    'local_bulk_roles_importer/roleretrievalsource',
    'neq',
    'github');
