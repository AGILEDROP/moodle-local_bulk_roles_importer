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
 * Abstract class - Git provider API.
 *
 * File         gitprovider_api.php
 * Encoding     UTF-8
 *
 * @package     local_bulk_roles_importer
 *
 * @copyright   Agiledrop, 2025
 * @author      Agiledrop ltd. <developer@agiledrop.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use CurlHandle;
use dml_exception;
use stdClass;

/**
 * Definition class for Git provider API.
 */
abstract class gitprovider_api implements gitprovider_api_interface {

    /** @var string $url Url link to root of repositories. */
    protected $url;

    /** @var string $token Access token, generated in Git provider account settings. */
    protected $token;

    /** @var string $project Project name. */
    protected $project;

    /** @var string $masterbranch Master/main branch name. */
    protected $masterbranch;

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
    protected function set_url(): void {
        $this->url = get_config('local_bulk_roles_importer', 'githuburl');
    }

    /**
     * Get url.
     *
     * @return string
     */
    protected function get_url(): string {
        return $this->url;
    }

    /**
     * Set access token.
     *
     * @return void
     */
    protected function set_token(): void {
        // This has to be implemented in child class.
        $this->token = '';
    }

    /**
     * Get access token.
     *
     * @return string
     */
    protected function get_token(): string {
        return $this->token;
    }

    /**
     * Set project name.
     *
     * @return void
     * @throws dml_exception
     */
    protected function set_project(): void {
        // This has to be implemented in child class.
        $project = '';
        $this->project = urlencode($project);
    }

    /**
     * Get project name.
     *
     * @return string
     */
    protected function get_project(): string {
        return $this->project;
    }

    /**
     * Set master/main branch name.
     *
     * @return void
     * @throws dml_exception
     */
    protected function set_masterbranch(): void {
        // This has to be implemented in child class.
        $this->masterbranch = '';
    }

    /**
     * Get master/main branch name.
     *
     * @return string
     */
    protected function get_masterbranch(): string {
        return $this->masterbranch;
    }

    /**
     * Set error.
     *
     * @param bool $error
     * @return void
     */
    protected function set_error($error): void {
        $this->error = $error;
    }

    /**
     * Get error.
     *
     * @return bool
     */
    protected function get_error(): bool {
        return $this->error;
    }

    /**
     * Set error code.
     *
     * @param int $code Error code.
     * @return void
     */
    protected function set_errorcode($code): void {
        $this->errorcode = $code;
    }

    /**
     * Get error code.
     *
     * @return int
     */
    protected function get_errorcode(): int {
        return $this->errorcode;
    }

    /**
     * Set error message.
     *
     * @param string $message Error message.
     * @return void
     */
    protected function set_errormessage($message): void {
        $this->errormessage = $message;
    }

    /**
     * Get error message.
     *
     * @return string
     */
    protected function get_errormessage(): string {
        return $this->errormessage;
    }

    /**
     * Get curl response from given url or false.
     *
     * @param string $url Url.
     * @return array
     */
    protected function get_curl($url): CurlHandle|false {
        // This has to be implemented in child class.
        return curl_init($url);
    }

    /**
     * Get json decoded response from given url or false.
     *
     * @param string $url Url.
     * @return bool|string
     */
    protected function get_data($url): bool|string {
        $handler = $this->get_curl($url);
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
     * Get branches url
     *
     * @return string
     */
    protected function get_branches_url(): string {
        // This has to be implemented in child class.
        return '';
    }

    /**
     * Get array of branches or false.
     *
     * @return false|array
     */
    protected function get_branches(): false|array {
        $url = $this->get_branches_url();
        $branches = $this->get_data($url);

        if (!$branches) {
            return false;
        }

        return json_decode($branches);
    }

    /**
     * {@inheritdoc}
     */
    public function get_branch($name): false|stdClass {
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
     * Return last commit timestamp from master branch.
     *
     * @return string|false
     */
    protected function get_master_branch_last_updated_timestamp(): false|string {
        // This has to be implemented in child class.
        return 0;
    }

    /**
     * {@inheritdoc}
     */
    public function get_master_branch_last_updated(): false|int {
        $timestamp = $this->get_master_branch_last_updated_timestamp();

        if (!$timestamp) {
            return false;
        }

        return strtotime($timestamp);
    }

    /**
     * {@inheritdoc}
     */
    public function get_files($branch = false): false|array {
        // This has to be implemented in child class.
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function get_file_content($branch, $filepath): false|string {
        // This has to be implemented in child class.
        return '';
    }

    /**
     * Return last commited version of file url.
     *
     * @param $filepath
     * @return false|int
     */
    protected function get_file_last_commit_url($filepath): string {
        // This has to be implemented in child class.
        return '';
    }

    /**
     * Get timestamp for last commit or 0.
     *
     * @param $filepath
     * @return false|int
     */
    protected function get_file_last_commit($filepath): false|int {
        $url = $this->get_file_last_commit_url($filepath);
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
     * {@inheritdoc}
     */
    public function get_roles(): array {
        $roles = [];
        $files = $this->get_files();

        if (!$files) {
            return $roles;
        }

        foreach ($files as $file) {
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
