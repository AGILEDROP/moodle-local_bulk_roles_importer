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
 * Utility class - GitLab API.
 *
 * File         gitlab_api.php
 * Encoding     UTF-8
 *
 * @package     local_bulk_roles_importer
 *
 * @copyright   Agiledrop, 2025
 * @author      Agiledrop ltd. <developer@agiledrop.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_bulk_roles_importer\util;

use curl;
use CurlHandle;

/**
 * Definition class for Gitlab API.
 */
final class gitlab_api extends gitprovider_api {

    #[\Override]
    public function set_url(): void {
        $this->url = $this->get_config_with_default('gitlaburl', 'https://gitlab.com');
    }

    #[\Override]
    public function set_token(): void {
        $this->token = get_config('local_bulk_roles_importer', 'gitlabtoken');
    }

    #[\Override]
    public function set_project(): void {
        $project = $this->get_config_with_default('gitlabproject', 'moodle/template-01/roles');
        $this->project = urlencode($project);
    }

    #[\Override]
    public function set_mainbranch(): void {
        $this->mainbranch = $this->get_config_with_default('gitlabmain', 'main');
    }

    #[\Override]
    public function get_curl(): curl|false {
        $headers = [
            'PRIVATE-TOKEN: ' . $this->get_token(),
        ];

        $curl = new \curl();
        $curl->setHeader($headers);

        return $curl;
    }

    #[\Override]
    public function get_branches_url(): string {

        return $this->build_api_url(['api/v4/projects', $this->get_project(), 'repository/branches']);
    }

    #[\Override]
    public function get_main_branch_last_updated_timestamp(): false|string {
        $mainbranch = $this->get_mainbranch();
        $branch = $this->get_branch($mainbranch);
        if (!$branch) {
            return false;
        }

        return $branch->commit->created_at ?? false;
    }

    #[\Override]
    public function get_files(?string $branch = null): array|false {

        if (!$branch) {
            $branch = $this->get_mainbranch();
        }

        if (!$branch) {
            return false;
        }

        $url = $this->build_api_url(['api/v4/projects', $this->get_project(), 'repository/tree?ref=' . $branch . '&per_page=100']);

        $files = $this->get_data($url);

        if (!$files) {
            return false;
        }

        $files = json_decode($files);

        return $files;
    }

    #[\Override]
    public function get_file_content(string $branch, string $filepath): false|string {
        $url = $this->build_api_url(['api/v4/projects', $this->get_project(), 'repository/files', $filepath, 'raw?ref=' . $branch]);

        return $this->get_data($url);
    }

    #[\Override]
    public function get_file_last_commit(string $filepath): false|int {
        $url = $this->build_api_url([
                'api/v4/projects',
                $this->get_project(),
                'repository/files', $filepath,
                'blame?ref=' . $this->get_mainbranch(),
        ]);

        $data = $this->get_data($url);
        $json = json_decode($data);

        $lastpart = end($json);
        $date = $lastpart->commit->committed_date ?? false;

        if (!$date) {
            return 0;
        }

        return strtotime($date);
    }
}
