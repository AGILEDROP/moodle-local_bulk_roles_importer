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

use CurlHandle;
use local_bulk_roles_importer\util\gitprovider_api;

/**
 * Definition class for GitHub API.
 */
final class github_api extends gitprovider_api {

    /**
     * {@inheritdoc}
     */
    final function set_url(): void {
        $this->url = get_config('local_bulk_roles_importer', 'githuburl');
    }

    /**
     * {@inheritdoc}
     */
    final function set_token(): void {
        $this->token = get_config('local_bulk_roles_importer', 'githubtoken');
    }

    /**
     * {@inheritdoc}
     */
    final function set_project(): void {
        $project = get_config('local_bulk_roles_importer', 'githubproject');
        $this->project = urlencode($project);
    }

    /**
     * {@inheritdoc}
     */
    final function set_masterbranch(): void {
        $this->masterbranch = get_config('local_bulk_roles_importer', 'githubmaster');
    }

    /**
     * {@inheritdoc}
     */
    final function get_curl($url): CurlHandle|false {
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

    /**
     * {@inheritdoc}
     */
    final function get_branches_url(): string {
        $url = $this->get_url();
        $url .= '/repos/';
        $url .= $this->get_project();
        $url .= '/branches';

        return $url;
    }

    /**
     * {@inheritdoc}
     */
    final function get_master_branch_last_updated_timestamp(): false|string {
        $url = $this->get_url();
        $url .= '/repos/';
        $url .= $this->get_project();
        $url .= '/commits/';
        $url .= $this->get_masterbranch();

        $commit = $this->get_data($url);
        $commit = json_decode($commit);

        return $commit->commit->author->date ?? false;
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
        $url .= '/repos/';
        $url .= $this->get_project();
        $url .= '/git/trees/' . $branch;

        $files = $this->get_data($url);

        if (!$files) {
            return false;
        }

        $files = json_decode($files);

        return $files->tree;
    }

    /**
    /**
     * {@inheritdoc}
     */
    public function get_file_content($branch, $filepath): false|string {
        $url = $this->get_url();
        $url .= '/repos/';
        $url .= $this->get_project();
        $url .= '/contents/' . $filepath;
        $url .= '?ref=' . $branch;

        $data = $this->get_data($url);
        $json = json_decode($data);
        $content_base46 = $json->content;

        return base64_decode($content_base46);
    }

    /**
     * {@inheritdoc}
     */
    final function get_file_last_commit_url($filepath): string {
        $url = $this->get_url();
        $url .= '/repos/';
        $url .= $this->get_project();
        $url .= '/commits?path=' . $filepath;
        $url .= '&ref=' . $this->get_masterbranch();

        return $url;
    }
}
