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

namespace local_bulk_roles_importer\util;

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

use CurlHandle;

/**
 * Definition class for Gitlab API.
 */
final class gitlab_api extends gitprovider_api {

    /**
     * {@inheritdoc}
     */
    final function set_url(): void {
        $this->url = get_config('local_bulk_roles_importer', 'gitlaburl');
    }

    /**
     * {@inheritdoc}
     */
    final function set_token(): void {
        $this->token = get_config('local_bulk_roles_importer', 'gitlabtoken');
    }

    /**
     * {@inheritdoc}
     */
    final function set_project(): void {
        $project = get_config('local_bulk_roles_importer', 'gitlabproject');
        $this->project = urlencode($project);
    }

    /**
     * {@inheritdoc}
     */
    final function set_masterbranch():void {
        $this->masterbranch = get_config('local_bulk_roles_importer', 'gitlabmaster');
    }

    /**
     * {@inheritdoc}
     */
    final function get_curl($url): CurlHandle|false {
        $headers = [
            'PRIVATE-TOKEN: ' . $this->get_token(),
        ];
        // Set request options.
        $handler = curl_init($url);
        curl_setopt($handler, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($handler, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($handler, CURLOPT_POST, false);
        curl_setopt($handler, CURLOPT_HTTPHEADER, array_values($headers));
        curl_setopt($handler, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($handler, CURLOPT_TIMEOUT, 30);

        return $handler;
    }

    /**
     * {@inheritdoc}
     */
    final function get_branches_url(): string {
        $url = $this->get_url();
        $url .= '/api/v4/projects/';
        $url .= $this->get_project();
        $url .= '/repository/branches';

        return $url;
    }

    /**
     * {@inheritdoc}
     */
    final function get_master_branch_last_updated_timestamp(): false|string {
        $masterbranch = $this->get_masterbranch();
        $branch = $this->get_branch($masterbranch);
        if (!$branch) {
            return false;
        }

        return $branch->commit->created_at ?? false;
    }

    /**
     * {@inheritdoc}
     */
    public function get_files($branch = false): false|array {

        if (!$branch) {
            $branch = $this->get_masterbranch();
        }

        if (!$branch) {
            return false;
        }

        $url = $this->get_url();
        $url .= '/api/v4/projects/';
        $url .= $this->get_project();
        $url .= '/repository/tree?ref=' . $branch;
        $url .= '&per_page=100';

        $files = $this->get_data($url);

        if (!$files) {
            return false;
        }

        $files = json_decode($files);

        return $files;
    }

    /**
     * {@inheritdoc}
     */
    public function get_file_content($branch, $filepath): false|string {
        $url = $this->get_url();
        $url .= '/api/v4/projects/';
        $url .= $this->get_project();
        $url .= '/repository/files/' . $filepath;
        $url .= '/raw?ref=' . $branch;

        return $this->get_data($url);
    }

    /**
     * {@inheritdoc}
     */
    final function get_file_last_commit($filepath): false|int {
        $url = $this->get_url();
        $url .= '/api/v4/projects/';
        $url .= $this->get_project();
        $url .= '/repository/files/' . $filepath;
        $url .= '/blame?ref=';
        $url .= $this->get_masterbranch();

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
