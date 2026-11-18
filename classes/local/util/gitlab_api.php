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

namespace local_bulk_roles_importer\local\util;

use local_bulk_roles_importer\local\exception\provider_exception;
use stdClass;

/**
 * Definition class for Gitlab API.
 */
class gitlab_api extends gitprovider_api {
    /** @var int Page size for paginated requests. */
    protected const PER_PAGE = 100;

    /** @var int Safety limit on the number of tree pages. */
    protected const MAX_PAGES = 1000;

    #[\Override]
    public function get_provider_name(): string {
        return 'gitlab';
    }

    #[\Override]
    protected function set_url(): void {
        $this->url = $this->get_config_with_default('gitlaburl', 'https://gitlab.com');
    }

    #[\Override]
    protected function set_token(): void {
        $this->token = (string)get_config('local_bulk_roles_importer', 'gitlabtoken');
    }

    #[\Override]
    protected function set_project(): void {
        $this->project = trim($this->get_config_with_default('gitlabproject', 'moodle/template-01/roles'), '/');
    }

    #[\Override]
    protected function set_mainbranch(): void {
        $this->mainbranch = $this->get_config_with_default('gitlabmain', 'main');
    }

    #[\Override]
    protected function get_headers(): array {
        return ($this->get_token() !== '') ? ['PRIVATE-TOKEN: ' . $this->get_token()] : [];
    }

    /**
     * Base URL of the project API; the project path is encoded as one segment.
     *
     * @return string
     * @throws provider_exception When no project is configured.
     */
    protected function get_project_url(): string {
        if ($this->get_project() === '') {
            throw new provider_exception('error:provider_config', get_string('label:gitlabproject', 'local_bulk_roles_importer'));
        }
        return $this->get_url() . '/api/v4/projects/' . rawurlencode($this->get_project());
    }

    #[\Override]
    protected function fetch_head(): stdClass {
        $url = $this->get_project_url() . '/repository/branches/' . rawurlencode($this->get_mainbranch());
        $branch = $this->request_object($url);

        return (object)[
            'sha' => $this->require_string($branch, ['commit', 'id'], $url),
            'time' => $this->require_time($branch, ['commit', 'committed_date'], $url),
        ];
    }

    #[\Override]
    public function get_files(): array {
        $files = [];
        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            $url = $this->get_project_url() . '/repository/tree?' . $this->query([
                'ref' => $this->get_head()->sha,
                'recursive' => 'true',
                'per_page' => self::PER_PAGE,
                'page' => $page,
            ]);
            $entries = $this->request_list($url);

            foreach ($entries as $entry) {
                if (!$entry instanceof stdClass || ($entry->type ?? '') !== 'blob') {
                    continue;
                }
                $path = $this->require_string($entry, ['path'], $url);
                if ($this->is_xml_path($path)) {
                    $files[] = (object)['path' => $path, 'revision' => $this->require_string($entry, ['id'], $url)];
                }
            }

            if (count($entries) < self::PER_PAGE) {
                return $files;
            }
        }

        throw new provider_exception('error:provider_response', (object)[
            'endpoint' => 'repository/tree',
            'message' => 'too many pages',
        ]);
    }

    #[\Override]
    public function get_file_content(string $ref, string $filepath): string {
        $url = $this->get_project_url() . '/repository/files/' . rawurlencode($filepath) . '/raw?' .
            $this->query(['ref' => $ref]);
        return $this->request($url);
    }
}
