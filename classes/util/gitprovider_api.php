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

namespace local_bulk_roles_importer\util;

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

    /** @var string $mainbranch Main/master branch name. */
    protected $mainbranch;

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
        $this->set_mainbranch();
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
    abstract protected function set_token(): void;

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
    abstract protected function set_project(): void;

    /**
     * Get project name.
     *
     * @return string
     */
    protected function get_project(): string {
        return $this->project;
    }

    /**
     * Set main/master branch name.
     *
     * @return void
     * @throws dml_exception
     */
    abstract protected function set_mainbranch(): void;

    /**
     * Get main/master branch name.
     *
     * @return string
     */
    protected function get_mainbranch(): string {
        return $this->mainbranch;
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
     * Returns whether an error has occurred.
     *
     * @return bool
     */
    protected function is_error(): bool {
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
     * @return CurlHandle|false
     */
    abstract protected function get_curl(string $url): CurlHandle|false;

    /**
     * Get json decoded response from given url or false.
     *
     * @param string $url Url.
     * @return bool|string
     */
    protected function get_data(string $url): bool|string {
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
    abstract protected function get_branches_url(): string;

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

    #[\Override]
    public function get_branch(string $name): false|stdClass {
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
     * Return last commit timestamp from main branch.
     *
     * @return string|false
     */
    abstract protected function get_main_branch_last_updated_timestamp(): false|string;

    #[\Override]
    public function get_main_branch_last_updated(): false|int {
        $timestamp = $this->get_main_branch_last_updated_timestamp();

        if (!$timestamp) {
            return false;
        }

        return strtotime($timestamp);
    }

    #[\Override]
    abstract public function get_files(?string $branch = null): array|false;

    #[\Override]
    abstract public function get_file_content(string $branch, string $filepath): false|string;

    /**
     * Get timestamp for last commit or 0.
     *
     * @param string $filepath
     * @return false|int
     */
    abstract protected function get_file_last_commit(string $filepath): false|int;

    #[\Override]
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
            $xml = $this->get_file_content($this->get_mainbranch(), $file->path);
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


    /**
     * Build an API URL from the given parts.
     *
     * @param array $parts An array of URL parts.
     * @return string The full API URL.
     */
    protected function build_api_url(array $parts): string {
        $url = rtrim($this->get_url(), '/');
        foreach ($parts as $part) {
            $url .= '/' . ltrim($part, '/');
        }
        return $url;
    }
}
