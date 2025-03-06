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

use dml_exception;
use stdClass;

/**
 * Definition class for GitHub API.
 */
class github_api {

    /** @var string $url Url link to root of repositories. */
    private $url;

    /** @var string $token Access token, generated in GitHub account settings. */
    private $token;

    /** @var string $project Project name. */
    private $project;

    /** @var string $masterbranch Master/main branch name. */
    private $masterbranch;

    /** @var bool $error Has error. */
    private $error;

    /** @var int $errorcode Error code. */
    private $errorcode;

    /** @var string $errormessage Error message. */
    private $errormessage;

    /**
     * Construct method.
     */
    public function __construct() {
        $this->set_url();
        $this->set_token();
        $this->set_project();
        $this->set_masterbranch();
        $this->set_error(false);
    }

    /**
     * Set url.
     *
     * @return void
     * @throws dml_exception
     */
    private function set_url() {
        $this->url = get_config('local_bulk_roles_importer', 'githuburl');
    }

    /**
     * Get url.
     *
     * @return string
     */
    private function get_url() {
        return $this->url;
    }

    /**
     * Set access token.
     */
    private function set_token() {
        $this->token = get_config('local_bulk_roles_importer', 'githubtoken');
    }

    /**
     * Get access token.
     *
     * @return string
     */
    private function get_token() {
        return $this->token;
    }

    /**
     * Set project name.
     *
     * @return void
     * @throws dml_exception
     */
    private function set_project() {
        $project = get_config('local_bulk_roles_importer', 'githubproject');
        $this->project = urlencode($project);
    }

    /**
     * Get project name.
     *
     * @return string
     */
    private function get_project() {
        return $this->project;
    }

    /**
     * Set master/main branch name.
     *
     * @return void
     * @throws dml_exception
     */
    private function set_masterbranch() {
        $this->masterbranch = get_config('local_bulk_roles_importer', 'githubmaster');
    }

    /**
     * Get master/main branch name.
     *
     * @return string
     */
    private function get_masterbranch() {
        return $this->masterbranch;
    }

    /**
     * Set error.
     *
     * @param bool $error
     * @return void
     */
    private function set_error($error) {
        $this->error = $error;
    }

    /**
     * Get error.
     *
     * @return bool
     */
    private function get_error() {
        return $this->error;
    }

    /**
     * Set error code.
     *
     * @param int $code Error code.
     * @return void
     */
    private function set_errorcode($code) {
        $this->errorcode = $code;
    }

    /**
     * Get error code.
     *
     * @return int
     */
    private function get_errorcode() {
        return $this->errorcode;
    }

    /**
     * Set error message.
     *
     * @param string $message Error message.
     * @return void
     */
    private function set_errormessage($message) {
        $this->errormessage = $message;
    }

    /**
     * Get error message.
     *
     * @return string
     */
    private function get_errormessage() {
        return $this->errormessage;
    }

    /**
     * Get json decoded response from given url or false.
     *
     * @param string $url Url.
     * @return bool|string
     */
    private function get_data($url) {

        $url = urldecode($url);
        $headers = [
            'Authorization: Bearer ' . $this->get_token(),
            'Accept: application/vnd.github+json',
            'X-GitHub-Api-Version: 2022-11-28',
        ];
        // Set request options.
        $handler = curl_init();
        curl_setopt($handler, CURLOPT_URL, $url);
        curl_setopt($handler, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($handler, CURLOPT_USERAGENT, 'moodle-local_bulk_roles_importer');
        curl_setopt($handler, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($handler, CURLOPT_POST, false);
        curl_setopt($handler, CURLOPT_HTTPHEADER, array_values($headers));
        curl_setopt($handler, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($handler, CURLOPT_TIMEOUT, 30);

        $data = curl_exec($handler);

        $json = json_decode($data);

        $responsecode = curl_getinfo($handler, CURLINFO_HTTP_CODE);

        if ($responsecode != 200) {
            $message = $json->message ?? 'unknown error';
            $this->set_error(true);
            $this->set_errorcode($responsecode);
            $this->set_errormessage($message);

            return false;
        }

        return $data;

    }

    /**
     * Get array of branches or false.
     *
     * @return false|mixed
     */
    private function get_branches() {
        $url = $this->get_url();
        $url .= '/repos/';
        $url .= $this->get_project();
        $url .= '/branches';

        $branches = $this->get_data($url);

        if (!$branches) {
            return false;
        }

        return json_decode($branches);
    }

    /**
     * Get selected branch info or false.
     *
     * @param string $name Branch name.
     * @return false|mixed
     */
    public function get_branch($name) {
        $branches = $this->get_branches();
        if (!$branches) {
            return false;
        }

        foreach ($branches as $branch) {
            if ($branch->name == $name) {
                return $branch;
            }
        }

        return false;
    }

    /**
     * Get timestamp of master branch last updated time or false.
     *
     * @return false|int
     */
    public function get_master_branch_last_updated() {
        $url = $this->get_url();
        $url .= '/repos/';
        $url .= $this->get_project();
        $url .= '/commits/';
        $url .= $this->get_masterbranch();

        $commit = $this->get_data($url);
        $commit = json_decode($commit);

        $timestamp = $commit->commit->author->date ?? false;

        if (!$timestamp) {
            return false;
        }

        return strtotime($timestamp);
    }

    /**
     * Get files list for selected branch, by default from master branch.
     *
     * @param $branch
     * @return false|mixed
     */
    public function get_files($branch = false) {

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

        return $files;
    }

    /**
     * Get file content for selected filepath and branch.
     * @param string $branch Branch name.
     * @param string $filepath File path.
     *
     * @return bool|string
     */
    public function get_file_content($branch, $filepath) {
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
     * Get timestamp for last commit or 0.
     *
     * @param $filepath
     * @return false|int
     */
    private function get_file_last_commit($filepath) {

        $url = $this->get_url();
        $url .= '/repos/';
        $url .= $this->get_project();
        $url .= '/commits?path=' . $filepath;
        $url .= '&ref=' . $this->get_masterbranch();

        $data = $this->get_data($url);

        $json = json_decode($data);

        $lastpart = end($json);
        $date = $lastpart->commit->author->date ?? false;

        if (!$date) {
            return 0;
        }

        return strtotime($date);
    }

    /**
     * Get array of roles.
     *
     * @return array
     */
    public function get_roles() {
        $roles = [];
        $files = $this->get_files();

        if (!$files) {
            return $roles;
        }

        foreach ($files->tree as $file) {
            $fileinfo = pathinfo($file->path);
            $filetype = $fileinfo['extension'] ?? '';

            if ($filetype != 'xml') {
                continue;
            }

            $lastcommit = $this->get_file_last_commit($file->path);
            $xml = $this->get_file_content($this->get_masterbranch(), $file->path);
            $xmlstring = simplexml_load_string($xml);

            $json = json_encode($xmlstring);
            $jsondata = json_decode($json, true);
            $shortname = $jsondata['shortname'] ?? false;

            $role = new stdClass();
            $role->shortname = $shortname;
            $role->lastchange = $lastcommit;
            $role->xml = $xml;

            $roles[] = $role;
        }

        return $roles;
    }
}
