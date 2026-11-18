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

namespace local_bulk_roles_importer\local\util;

use local_bulk_roles_importer\local\exception\provider_exception;
use stdClass;

/**
 * Definition class for GitHub API.
 */
class github_api extends gitprovider_api {
    #[\Override]
    public function get_provider_name(): string {
        return 'github';
    }

    #[\Override]
    protected function set_url(): void {
        $this->url = $this->get_config_with_default('githuburl', 'https://api.github.com');
    }

    #[\Override]
    protected function set_token(): void {
        $this->token = (string)get_config('local_bulk_roles_importer', 'githubtoken');
    }

    #[\Override]
    protected function set_project(): void {
        $this->project = trim($this->get_config_with_default('githubproject', 'moodle/template-01/roles'), '/');
    }

    #[\Override]
    protected function set_mainbranch(): void {
        $this->mainbranch = $this->get_config_with_default('githubmain', 'main');
    }

    #[\Override]
    protected function get_headers(): array {
        $headers = [
            'Accept: application/vnd.github+json',
            'X-GitHub-Api-Version: 2022-11-28',
        ];
        if ($this->get_token() !== '') {
            $headers[] = 'Authorization: Bearer ' . $this->get_token();
        }
        return $headers;
    }

    /**
     * Base URL of the repository API, with owner and repository encoded.
     *
     * @return string
     * @throws provider_exception When the project is not in owner/repository form.
     */
    protected function get_repo_url(): string {
        $parts = explode('/', $this->get_project());
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new provider_exception('error:provider_config', get_string('label:githubproject', 'local_bulk_roles_importer'));
        }
        return $this->get_url() . '/repos/' . rawurlencode($parts[0]) . '/' . rawurlencode($parts[1]);
    }

    #[\Override]
    protected function fetch_head(): stdClass {
        $url = $this->get_repo_url() . '/commits?' . $this->query(['sha' => $this->get_mainbranch(), 'per_page' => 1]);
        $commits = $this->request_list($url);
        if (empty($commits)) {
            throw $this->malformed($url, 'no commits on branch');
        }

        return (object)[
            'sha' => $this->require_string($commits[0], ['sha'], $url),
            'time' => $this->require_time($commits[0], ['commit', 'committer', 'date'], $url),
            'tree' => $this->require_string($commits[0], ['commit', 'tree', 'sha'], $url),
        ];
    }

    #[\Override]
    public function get_files(): array {
        $treesha = $this->get_head()->tree;
        $url = $this->get_repo_url() . '/git/trees/' . rawurlencode($treesha) . '?' . $this->query(['recursive' => 1]);
        $tree = $this->request_object($url);

        if (!empty($tree->truncated)) {
            // The recursive listing hit GitHub's limit: walk the folders one by one.
            return $this->walk_tree($treesha, '');
        }

        return $this->collect_xml_blobs($tree, $url, '');
    }

    /**
     * List XML files of a tree folder by folder.
     *
     * @param string $treesha Tree id.
     * @param string $prefix Path of the tree inside the repository.
     * @return stdClass[]
     * @throws provider_exception
     */
    protected function walk_tree(string $treesha, string $prefix): array {
        $url = $this->get_repo_url() . '/git/trees/' . rawurlencode($treesha);
        $tree = $this->request_object($url);
        if (!empty($tree->truncated)) {
            throw $this->malformed($url, 'tree listing is truncated');
        }

        $files = $this->collect_xml_blobs($tree, $url, $prefix);
        foreach ($tree->tree as $entry) {
            if (($entry->type ?? '') === 'tree') {
                $path = $this->require_string($entry, ['path'], $url);
                $sha = $this->require_string($entry, ['sha'], $url);
                $files = array_merge($files, $this->walk_tree($sha, $prefix . $path . '/'));
            }
        }
        return $files;
    }

    /**
     * Collect XML blob paths from a tree response.
     *
     * @param stdClass $tree Tree response.
     * @param string $url Request URL, for error messages.
     * @param string $prefix Path prefix.
     * @return stdClass[]
     * @throws provider_exception
     */
    protected function collect_xml_blobs(stdClass $tree, string $url, string $prefix): array {
        if (!isset($tree->tree) || !is_array($tree->tree)) {
            throw $this->malformed($url, 'missing tree');
        }

        $files = [];
        foreach ($tree->tree as $entry) {
            if (!$entry instanceof stdClass || ($entry->type ?? '') !== 'blob') {
                continue;
            }
            $path = $prefix . $this->require_string($entry, ['path'], $url);
            if ($this->is_xml_path($path)) {
                $files[] = (object)['path' => $path, 'revision' => $this->require_string($entry, ['sha'], $url)];
            }
        }
        return $files;
    }

    #[\Override]
    public function get_file_content(string $ref, string $filepath): string {
        $url = $this->get_repo_url() . '/contents/' . $this->encode_path_segments($filepath) . '?' .
            $this->query(['ref' => $ref]);
        $file = $this->request_object($url);

        if (($file->type ?? '') !== 'file') {
            throw $this->malformed($url, 'not a file');
        }
        if (($file->encoding ?? '') !== 'base64') {
            throw $this->malformed($url, 'unsupported content encoding (file may be larger than 1 MB)');
        }
        $content = base64_decode(preg_replace('/\s+/', '', $this->require_string($file, ['content'], $url)), true);
        if ($content === false) {
            throw $this->malformed($url, 'invalid base64 content');
        }
        return $content;
    }
}
