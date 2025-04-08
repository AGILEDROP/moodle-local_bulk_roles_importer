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
 * Bitbucket plugin settings.
 *
 * File         settings_bitbucket.php
 * Encoding     UTF-8
 *
 * @package     local_bulk_roles_importer
 *
 * @copyright   Agiledrop, 2025
 * @author      Agiledrop ltd. <developer@agiledrop.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die('moodle_internal not defined');

// Header - Bitbucket.
$setting = new admin_setting_heading(
        'local_bulk_roles_importer/bitbucket_roles',
        new lang_string('header:bitbucket', 'local_bulk_roles_importer'),
        new lang_string('header:bitbucketinfo', 'local_bulk_roles_importer')
);
$settingsmain->add($setting);
$settingsmain->hide_if('local_bulk_roles_importer/bitbucket_roles',
    'local_bulk_roles_importer/roleretrievalsource',
    'neq',
    'bitbucket');

// Bitbucket - Url.
$setting = new admin_setting_configtext(
        'local_bulk_roles_importer/bitbucketurl',
        new lang_string('label:bitbucketurl', 'local_bulk_roles_importer'),
        new lang_string('label:bitbucketurl_help', 'local_bulk_roles_importer'),
        'https://api.bitbucket.com'
);
$settingsmain->add($setting);
$settingsmain->hide_if('local_bulk_roles_importer/bitbucketurl',
    'local_bulk_roles_importer/roleretrievalsource',
    'neq',
    'bitbucket');

// Bitbucket - Auth token.
$setting = new admin_setting_configpasswordunmask(
    'local_bulk_roles_importer/bitbuckettoken',
    new lang_string('label:bitbuckettoken', 'local_bulk_roles_importer'),
    new lang_string('label:bitbuckettoken_help', 'local_bulk_roles_importer'),
    ''
);
$settingsmain->add($setting);
$settingsmain->hide_if('local_bulk_roles_importer/bitbuckettoken',
    'local_bulk_roles_importer/roleretrievalsource',
    'neq',
    'bitbucket');

// Bitbucket - Project id.
$setting = new admin_setting_configtext(
        'local_bulk_roles_importer/bitbucketproject',
        new lang_string('label:bitbucketproject', 'local_bulk_roles_importer'),
        new lang_string('label:bitbucketproject_help', 'local_bulk_roles_importer'),
        'moodle/template-01/roles'
);
$settingsmain->add($setting);
$settingsmain->hide_if('local_bulk_roles_importer/bitbucketproject',
    'local_bulk_roles_importer/roleretrievalsource',
    'neq',
    'bitbucket');

// Bitbucket - Main branch.
$setting = new admin_setting_configtext(
    'local_bulk_roles_importer/bitbucketmain',
    new lang_string('label:bitbucketmain', 'local_bulk_roles_importer'),
    new lang_string('label:bitbucketmain_help', 'local_bulk_roles_importer'),
    'main'
);
$settingsmain->add($setting);
$settingsmain->hide_if('local_bulk_roles_importer/bitbucketmain',
    'local_bulk_roles_importer/roleretrievalsource',
    'neq',
    'bitbucket');
