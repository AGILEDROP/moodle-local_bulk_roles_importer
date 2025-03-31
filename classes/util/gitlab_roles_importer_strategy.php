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
 * Strategy to import roles from GitLab repository.
 *
 * File         gitlab_roles_importer_strategy.php
 * Encoding     UTF-8
 *
 * @package     local_bulk_roles_importer
 *
 * @copyright   Agiledrop, 2025
 * @author      Agiledrop ltd. <developer@agiledrop.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_bulk_roles_importer\util;

/**
 * Roles importing strategy for GitLab.
 */
class gitlab_roles_importer_strategy implements roles_importer_strategy_interface {

    /** @var gitlab_api $gitlab Gitlab Api instance. */
    private gitlab_api $gitlab;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->gitlab = new gitlab_api();
    }

    #[\Override]
    public function get_name(): string {
        return 'gitlab';
    }

    #[\Override]
    public function get_last_updated(): int {
        return $this->gitlab->get_main_branch_last_updated();
    }

    #[\Override]
    public function get_roles(): array {
        return $this->gitlab->get_roles();
    }

}
