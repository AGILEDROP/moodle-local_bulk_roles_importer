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
 * Utility class - GitHub API.
 *
 * File         github_api.php
 * Encoding     UTF-8
 *
 * @package     local_bulk_roles_importer
 *
 * @copyright   Agiledrop, 2025
 * @author      Agiledrop ltd. <developer@agiledrop.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_bulk_roles_importer\util;

use CurlHandle;
use local_bulk_roles_importer\util\gitprovider_api;

/**
 * Definition class for GitHub API.
 */
final class github_api extends gitprovider_api {

    #[\Override]
    public function set_url(): void {
        $this->url = get_config('local_bulk_roles_importer', 'githuburl');
    }

    #[\Override]
    public function set_token(): void {
        $this->token = get_config('local_bulk_roles_importer', 'githubtoken');
    }

    #[\Override]
    public function set_project(): void {
        $project = get_config('local_bulk_roles_importer', 'githubproject');
        $this->project = urlencode($project);
    }

    #[\Override]
    public function set_mainbranch(): void {
        $this->mainbranch = get_config('local_bulk_roles_importer', 'githubmain');
    }

    #[\Override]
    public function get_curl(string $url): CurlHandle|false {
        $url = urldecode($url);
        $headers = [
            'Authorization: Bearer ' . $this->get_token(),
            'Accept: application/vnd.github+json',
            'X-GitHub-Api-Version: 2022-11-28',
        ];
        // Set request options.
        $handler = curl_init($url);
        curl_setopt($handler, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($handler, CURLOPT_USERAGENT, 'moodle-local_bulk_roles_importer');
        curl_setopt($handler, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($handler, CURLOPT_POST, false);
        curl_setopt($handler, CURLOPT_HTTPHEADER, array_values($headers));
        curl_setopt($handler, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($handler, CURLOPT_TIMEOUT, 30);

        return $handler;
    }

    #[\Override]
    public function get_branches_url(): string {
        $url = $this->build_api_url(['repos', $this->get_project(), 'branches']);

        return $url;
    }

    #[\Override]
    public function get_main_branch_last_updated_timestamp(): false|string {
        $url = $this->build_api_url(['repos', $this->get_project(), 'commits', $this->get_mainbranch()]);

        $commit = $this->get_data($url);
        $commit = json_decode($commit);

        return $commit->commit->author->date ?? false;
    }

    #[\Override]
    public function get_files(?string $branch = null): array|false {
        if (!$branch) {
            $branch = $this->get_mainbranch();
        }

        if (!$branch) {
            return false;
        }

        $url = $this->build_api_url(['repos', $this->get_project(), 'git/trees', $branch]);

        $files = $this->get_data($url);

        if (!$files) {
            return false;
        }

        $files = json_decode($files);

        return $files->tree;
    }

    #[\Override]
    public function get_file_content(string $branch, string $filepath): false|string {
        $url = $this->build_api_url(['repos', $this->get_project(), 'contents', $filepath, '?ref=' . $branch]);

        $data = $this->get_data($url);
        $json = json_decode($data);
        $contentbase46 = $json->content;

        return base64_decode($contentbase46);
    }

    #[\Override]
    public function get_file_last_commit(string $filepath): false|int {
        $url = $this->build_api_url(['repos', $this->get_project(), 'commits?path=' . $filepath . '&ref=' . $this->get_mainbranch()]);

        $data = $this->get_data($url);
        $json = json_decode($data);

        $lastpart = end($json);
        $date = $lastpart->commit->author->date ?? false;

        if (!$date) {
            return 0;
        }

        return strtotime($date);
    }
}
