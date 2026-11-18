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

namespace local_bulk_roles_importer\local\util;

defined('MOODLE_INTERNAL') || die();

use core_text;
use dml_exception;
use local_bulk_roles_importer\local\exception\provider_exception;
use local_bulk_roles_importer\local\traits\role_file_processor;
use stdClass;

global $CFG;
require_once($CFG->libdir . '/filelib.php');

/**
 * Definition class for Git provider API.
 */
abstract class gitprovider_api implements gitprovider_api_interface {
    use role_file_processor;

    /** @var string $url Url link to root of repositories. */
    protected string $url;

    /** @var string $token Access token, generated in Git provider account settings. */
    protected string $token;

    /** @var string $project Project path as configured, without encoding. */
    protected string $project;

    /** @var string $mainbranch Main/master branch name. */
    protected string $mainbranch;

    /** @var stdClass|null $head Cached head commit of the configured branch. */
    private ?stdClass $head = null;

    /**
     * Construct method.
     */
    public function __construct() {
        $this->set_url();
        $this->set_token();
        $this->set_project();
        $this->set_mainbranch();
    }

    /**
     * Short provider name used for revision tracking, e.g. github.
     *
     * @return string
     */
    abstract public function get_provider_name(): string;

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
        return rtrim($this->url, '/');
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
     * Get project path as configured.
     *
     * @return string
     */
    public function get_project(): string {
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
        $value = trim((string)get_config('local_bulk_roles_importer', $configname));
        return ($value === '') ? $default : $value;
    }

    /**
     * Get main/master branch name.
     *
     * @return string
     */
    public function get_mainbranch(): string {
        return $this->mainbranch;
    }

    /**
     * Request headers, including authentication.
     *
     * @return string[]
     */
    abstract protected function get_headers(): array;

    /**
     * Fetch the head commit of the configured branch.
     *
     * @return stdClass Object with sha and time.
     */
    abstract protected function fetch_head(): stdClass;

    #[\Override]
    public function get_head(): stdClass {
        if ($this->head === null) {
            $this->head = $this->fetch_head();
        }
        return $this->head;
    }

    #[\Override]
    public function get_main_branch_last_updated(): int {
        return $this->get_head()->time;
    }

    /**
     * Perform a GET request.
     *
     * Kept separate so tests can replace the transport.
     *
     * @param string $url Full URL.
     * @return stdClass Object with status (int), body (string) and error (string).
     */
    protected function http_get(string $url): stdClass {
        $curl = new \curl();
        $curl->setHeader($this->get_headers());
        $body = $curl->get($url);
        $info = $curl->get_info();

        return (object)[
            'status' => (int)($info['http_code'] ?? 0),
            'body' => is_string($body) ? $body : '',
            'error' => $curl->get_errno() ? (string)$curl->error : '',
        ];
    }

    /**
     * Perform a GET request and return the body of a successful response.
     *
     * @param string $url Full URL.
     * @return string
     * @throws provider_exception On transport errors and non-200 responses.
     */
    protected function request(string $url): string {
        $response = $this->http_get($url);
        if ($response->status !== 200 || $response->error !== '') {
            $message = ($response->error !== '') ? $response->error : $this->extract_error_message($response->body);
            throw new provider_exception('error:provider_request', (object)[
                'endpoint' => $this->describe_endpoint($url),
                'status' => $response->status,
                'message' => $message,
            ]);
        }
        return $response->body;
    }

    /**
     * Perform a GET request and decode a JSON object response.
     *
     * @param string $url Full URL.
     * @return stdClass
     * @throws provider_exception
     */
    protected function request_object(string $url): stdClass {
        $data = json_decode($this->request($url));
        if (!$data instanceof stdClass) {
            throw $this->malformed($url, 'expected a JSON object');
        }
        return $data;
    }

    /**
     * Perform a GET request and decode a JSON array response.
     *
     * @param string $url Full URL.
     * @return array
     * @throws provider_exception
     */
    protected function request_list(string $url): array {
        $data = json_decode($this->request($url));
        if (!is_array($data)) {
            throw $this->malformed($url, 'expected a JSON array');
        }
        return $data;
    }

    /**
     * Read a non-empty string property, following a list of nested property names.
     *
     * @param mixed $data Decoded JSON.
     * @param string[] $path Property names.
     * @param string $url Request URL, for error messages.
     * @return string
     * @throws provider_exception
     */
    protected function require_string($data, array $path, string $url): string {
        foreach ($path as $property) {
            if (!$data instanceof stdClass || !property_exists($data, $property)) {
                throw $this->malformed($url, 'missing ' . implode('.', $path));
            }
            $data = $data->$property;
        }
        if (!is_string($data) || $data === '') {
            throw $this->malformed($url, 'invalid ' . implode('.', $path));
        }
        return $data;
    }

    /**
     * Read and parse a date property as a timestamp.
     *
     * @param mixed $data Decoded JSON.
     * @param string[] $path Property names.
     * @param string $url Request URL, for error messages.
     * @return int
     * @throws provider_exception
     */
    protected function require_time($data, array $path, string $url): int {
        $time = strtotime($this->require_string($data, $path, $url));
        if ($time === false) {
            throw $this->malformed($url, 'invalid ' . implode('.', $path));
        }
        return $time;
    }

    /**
     * Build a malformed response exception.
     *
     * @param string $url Request URL.
     * @param string $message Problem description.
     * @return provider_exception
     */
    protected function malformed(string $url, string $message): provider_exception {
        return new provider_exception('error:provider_response', (object)[
            'endpoint' => $this->describe_endpoint($url),
            'message' => $message,
        ]);
    }

    /**
     * Extract a short error message from a provider error body.
     *
     * @param string $body Response body.
     * @return string
     */
    protected function extract_error_message(string $body): string {
        $json = json_decode($body);
        $message = null;
        if ($json instanceof stdClass) {
            $message = $json->message ?? $json->error ?? null;
            if (!is_string($message) && $message !== null) {
                $message = json_encode($message);
            }
        }
        if (!is_string($message) || trim($message) === '') {
            return get_string('error:unknown', 'local_bulk_roles_importer');
        }
        return core_text::substr(clean_param($message, PARAM_NOTAGS), 0, 200);
    }

    /**
     * Describe a request URL for logs: path and query only, no host or credentials.
     *
     * @param string $url Full URL.
     * @return string
     */
    protected function describe_endpoint(string $url): string {
        $parts = parse_url($url);
        $endpoint = $parts['path'] ?? '';
        if (!empty($parts['query'])) {
            $endpoint .= '?' . $parts['query'];
        }
        return $endpoint;
    }

    /**
     * Build a query string encoded as RFC 3986.
     *
     * @param array $params Query parameters.
     * @return string
     */
    protected function query(array $params): string {
        return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Encode each segment of a file path, keeping the slashes.
     *
     * @param string $path File path.
     * @return string
     */
    protected function encode_path_segments(string $path): string {
        return implode('/', array_map('rawurlencode', explode('/', $path)));
    }

    /**
     * Whether a repository path is an XML file.
     *
     * @param string $path File path.
     * @return bool
     */
    protected function is_xml_path(string $path): bool {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'xml';
    }

    /**
     * Read the configured repository-relative folder and role preset file paths.
     *
     * @return string[] Configured paths, or an empty array to import the entire branch.
     */
    private function get_preset_paths(): array {
        $configured = $this->get_config_with_default($this->get_provider_name() . 'paths', '');
        $paths = [];
        foreach (preg_split('/\r\n|\r|\n/', $configured) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $path = rtrim($line, '/');
            if (str_contains($path, '\\') || array_intersect(explode('/', $path), ['', '.', '..'])) {
                throw new provider_exception('error:preset_path_invalid', $line);
            }
            $paths[] = $path;
        }
        return array_unique($paths);
    }

    /**
     * Select only XML blobs under the configured paths before checking revisions or downloading files.
     *
     * @param stdClass[] $files Listed XML blobs.
     * @param string[] $paths Configured paths, or an empty array to select all files.
     * @return stdClass[] XML blobs matching the configured paths.
     */
    private function select_preset_files(array $files, array $paths): array {
        if ($paths === []) {
            return $files;
        }

        $matched = array_fill_keys($paths, false);
        $selected = [];
        foreach ($files as $file) {
            foreach ($paths as $path) {
                if ($file->path === $path || str_starts_with($file->path, $path . '/')) {
                    $matched[$path] = true;
                    $selected[$file->path] = $file;
                }
            }
        }

        $unmatched = array_keys(array_filter($matched, fn($found) => !$found));
        if ($unmatched) {
            throw new provider_exception('error:preset_paths_unmatched', (object)[
                'branch' => $this->get_mainbranch(),
                'paths' => implode(', ', $unmatched),
            ]);
        }
        return array_values($selected);
    }

    #[\Override]
    public function get_roles(?callable $findapplied = null): array {
        $paths = $this->get_preset_paths();
        $head = $this->get_head();
        $roles = [];

        foreach ($this->select_preset_files($this->get_files(), $paths) as $file) {
            $path = $file->path;
            $source = (object)[
                'provider' => $this->get_provider_name(),
                'endpoint' => $this->get_url(),
                'repository' => $this->get_project(),
                'branch' => $this->get_mainbranch(),
                'path' => $path,
                'revision' => $file->revision,
            ];
            $applied = $findapplied === null ? null : $findapplied($source);
            if ($applied !== null) {
                $role = (object)['shortname' => $applied->shortname, 'unchanged' => true];
            } else {
                try {
                    $xml = $this->get_file_content($head->sha, $path);
                    $role = $this->process_role_file_from_string($xml, $path, $head->time);
                } catch (provider_exception $e) {
                    // Report this file and continue; the importer fails the run afterwards.
                    $role = (object)['invalid' => true, 'fetcherror' => $e->getMessage()];
                }
            }
            $role->filename = $path;
            $role->source = $source;
            $roles[] = $role;
        }

        return $roles;
    }
}
