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
use local_bulk_roles_importer\traits\role_file_processor;
use stdClass;
use curl;
global $CFG;
require_once ($CFG->libdir . '/filelib.php');

/**
 * Definition class for Git provider API.
 */
abstract class gitprovider_api implements gitprovider_api_interface {

    use role_file_processor;

    /** @var string $url Url link to root of repositories. */
    protected string $url;

    /** @var string $token Access token, generated in Git provider account settings. */
    protected string $token;

    /** @var string $project Project name. */
    protected string $project;

    /** @var string $mainbranch Main/master branch name. */
    protected string $mainbranch;

    /** @var bool $error Has error. */
    private bool $error;

    /** @var int $errorcode Error code. */
    private int $errorcode;

    /** @var string $errormessage Error message. */
    private string $errormessage;

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
    abstract protected function set_url(): void;

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
     * Get config with default value.
     *
     * @param string $configname
     * @param string $default
     * @return string
     * @throws dml_exception
     */
    protected function get_config_with_default(string $configname, string $default): string {
        $value = get_config('local_bulk_roles_importer', $configname);
        return empty($value) ? $default : $value;
    }

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
    protected function set_error(bool $error): void {
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
    protected function set_errorcode(int $code): void {
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
    protected function set_errormessage(string $message): void {
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
     * @return curl|false
     */
    abstract protected function get_curl(): curl|false;

    /**
     * Get json decoded response from given url or false.
     *
     * @param string $url Url.
     * @return bool|string
     */
    protected function get_data(string $url): bool|string {
        $curl = $this->get_curl();
        $data = $curl->get($url);

        $info = $curl->get_info();
        $responsecode = $info['http_code'] ?? 0;

        if ($responsecode != 200) {
            $json = json_decode($data);
            $message = $json->message ?? get_string('error:unknown', 'local_bulk_roles_importer');
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
            $xml = $this->get_file_content($this->get_mainbranch(), $file->path);
            $lastcommit = $this->get_file_last_commit($file->path);

            $role = $this->process_role_file_from_string($xml, $file->path, $lastcommit);
            $roles[] = $role;
        }

        return $roles;
    }

    /**
     * Process a file and return a role object.
     *
     * If the file is not an XML or does not contain a <role> root element,
     * an "invalid" role object is returned with the filename and XML contents.
     *
     * @param object $file A file object with a 'path' property.
     * @return \stdClass|null The role object, or null if the file should be skipped.
     */
    protected function process_role_file(object $file): ?\stdClass {
        $fileinfo = pathinfo($file->path);
        $filetype = strtolower($fileinfo['extension'] ?? '');

        // Skip non-XML files.
        if ($filetype !== 'xml') {
            return null;
        }

        $lastcommit = $this->get_file_last_commit($file->path);
        $xml = $this->get_file_content($this->get_mainbranch(), $file->path);
        $xmlstring = simplexml_load_string($xml);

        $role = new \stdClass();
        $role->lastchange = $lastcommit;
        $role->xml = $xml;

        // Check if the XML is valid and has <role> as the root.
        if (!$xmlstring || $xmlstring->getName() !== 'role') {
            $role->invalid = true;
            $role->filename = basename($file->path);
        } else {
            $json = json_encode($xmlstring);
            $jsondata = json_decode($json, true);
            $role->shortname = $jsondata['shortname'] ?? false;
            $role->filename = $file->path;
        }

        return $role;
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
