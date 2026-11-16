<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_bulk_roles_importer;

use advanced_testcase;
use local_bulk_roles_importer\local\exception\provider_exception;
use local_bulk_roles_importer\local\util\github_api;
use local_bulk_roles_importer\local\util\gitlab_api;

/**
 * Unit tests for the GitHub and GitLab provider APIs, using a fake HTTP transport.
 *
 * @package     local_bulk_roles_importer
 * @covers      \local_bulk_roles_importer\local\util\gitprovider_api
 * @covers      \local_bulk_roles_importer\local\util\github_api
 * @covers      \local_bulk_roles_importer\local\util\gitlab_api
 * @copyright   Agiledrop, 2025
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class gitprovider_api_test extends advanced_testcase {
    /** @var string GitHub repository API base used by the fakes. */
    private const GH = 'https://api.example.test/repos/owner/repo';

    /** @var string GitLab project API base used by the fakes. */
    private const GL = 'https://gitlab.example.test/api/v4/projects/group%2Fsub%2Froles';

    /**
     * Set up.
     *
     * @return void
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * Fake GitHub API.
     *
     * @param array $responses Map of URL => [status, body]; arrays are JSON encoded.
     * @param string $project Configured project.
     * @return github_api
     */
    private function github(array $responses, string $project = 'owner/repo'): github_api {
        set_config('githuburl', 'https://api.example.test/', 'local_bulk_roles_importer');
        set_config('githubtoken', 'secret-token', 'local_bulk_roles_importer');
        set_config('githubproject', $project, 'local_bulk_roles_importer');
        set_config('githubmain', 'main', 'local_bulk_roles_importer');

        return new class ($responses) extends github_api {
            /** @var array Requested URLs. */
            public array $requested = [];
            /** @var array Responses. */
            private array $responses;

            /**
             * Constructor.
             *
             * @param array $responses
             */
            public function __construct(array $responses) {
                $this->responses = $responses;
                parent::__construct();
            }

            /**
             * Fake transport.
             *
             * @param string $url
             * @return \stdClass
             */
            protected function http_get(string $url): \stdClass {
                $this->requested[] = $url;
                [$status, $body] = $this->responses[$url] ?? [404, ['message' => 'Not Found']];
                return (object)['status' => $status, 'body' => is_string($body) ? $body : json_encode($body), 'error' => ''];
            }
        };
    }

    /**
     * Fake GitLab API.
     *
     * @param array $responses Map of URL => [status, body]; arrays are JSON encoded.
     * @param string $branch Configured branch.
     * @return gitlab_api
     */
    private function gitlab(array $responses, string $branch = 'main'): gitlab_api {
        set_config('gitlaburl', 'https://gitlab.example.test', 'local_bulk_roles_importer');
        set_config('gitlabtoken', 'secret-token', 'local_bulk_roles_importer');
        set_config('gitlabproject', 'group/sub/roles', 'local_bulk_roles_importer');
        set_config('gitlabmain', $branch, 'local_bulk_roles_importer');

        return new class ($responses) extends gitlab_api {
            /** @var array Requested URLs. */
            public array $requested = [];
            /** @var array Responses. */
            private array $responses;

            /**
             * Constructor.
             *
             * @param array $responses
             */
            public function __construct(array $responses) {
                $this->responses = $responses;
                parent::__construct();
            }

            /**
             * Fake transport.
             *
             * @param string $url
             * @return \stdClass
             */
            protected function http_get(string $url): \stdClass {
                $this->requested[] = $url;
                [$status, $body] = $this->responses[$url] ?? [404, ['message' => '404 Not Found']];
                return (object)['status' => $status, 'body' => is_string($body) ? $body : json_encode($body), 'error' => ''];
            }
        };
    }

    /**
     * GitHub head commit response.
     *
     * @return array
     */
    private function github_head(): array {
        return [self::GH . '/commits?sha=main&per_page=1' => [200, [[
            'sha' => 'head1',
            'commit' => ['committer' => ['date' => '2026-01-01T00:00:00Z'], 'tree' => ['sha' => 'tree1']],
        ]]]];
    }

    /**
     * GitHub lists only XML blobs, including nested ones.
     *
     * @return void
     */
    public function test_github_lists_nested_xml_blobs_only(): void {
        $api = $this->github($this->github_head() + [
            self::GH . '/git/trees/tree1?recursive=1' => [200, ['truncated' => false, 'tree' => [
                ['path' => 'README.md', 'type' => 'blob', 'sha' => 'x'],
                ['path' => 'roles', 'type' => 'tree', 'sha' => 't2'],
                ['path' => 'roles/a.xml', 'type' => 'blob', 'sha' => 'x'],
                ['path' => 'roles/deep/b.XML', 'type' => 'blob', 'sha' => 'x'],
                ['path' => 'folder.xml', 'type' => 'tree', 'sha' => 't3'],
            ]]],
        ]);

        $this->assertEquals([
            (object)['path' => 'roles/a.xml', 'revision' => 'x'],
            (object)['path' => 'roles/deep/b.XML', 'revision' => 'x'],
        ], $api->get_files());
    }

    /**
     * A truncated recursive tree is walked folder by folder.
     *
     * @return void
     */
    public function test_github_walks_truncated_tree(): void {
        $api = $this->github($this->github_head() + [
            self::GH . '/git/trees/tree1?recursive=1' => [200, ['truncated' => true, 'tree' => []]],
            self::GH . '/git/trees/tree1' => [200, ['truncated' => false, 'tree' => [
                ['path' => 'a.xml', 'type' => 'blob', 'sha' => 'x'],
                ['path' => 'sub', 'type' => 'tree', 'sha' => 'sub1'],
            ]]],
            self::GH . '/git/trees/sub1' => [200, ['truncated' => false, 'tree' => [
                ['path' => 'b.xml', 'type' => 'blob', 'sha' => 'x'],
                ['path' => 'notes.txt', 'type' => 'blob', 'sha' => 'x'],
            ]]],
        ]);

        $this->assertSame(['a.xml', 'sub/b.xml'], array_column($api->get_files(), 'path'));
    }

    /**
     * GitHub skips blobs that are already applied and fetches changed paths at the branch head.
     *
     * @return void
     */
    public function test_github_only_fetches_blobs_that_need_applying(): void {
        $xml = '<role><shortname>changed</shortname></role>';
        $api = $this->github($this->github_head() + [
            self::GH . '/git/trees/tree1?recursive=1' => [200, ['tree' => [
                ['path' => 'roles/unchanged.xml', 'type' => 'blob', 'sha' => 'stored'],
                ['path' => 'roles/my role.xml', 'type' => 'blob', 'sha' => 'new'],
            ]]],
            self::GH . '/contents/roles/my%20role.xml?ref=head1' => [200, [
                'type' => 'file', 'encoding' => 'base64', 'content' => chunk_split(base64_encode($xml), 4, "\n"),
            ]],
        ]);

        [$unchanged, $changed] = $api->get_roles(
            fn($source) => $source->revision === 'stored' ? (object)['shortname' => 'unchanged'] : null
        );
        $this->assertTrue($unchanged->unchanged);
        $this->assertSame('new', $changed->source->revision);
        $this->assertSame($xml, $changed->xml);
        $this->assertSame([
            self::GH . '/commits?sha=main&per_page=1',
            self::GH . '/git/trees/tree1?recursive=1',
            self::GH . '/contents/roles/my%20role.xml?ref=head1',
        ], $api->requested);
    }

    /**
     * Folder and exact file selections include only their matches, even when paths overlap.
     */
    public function test_github_filters_folders_and_files_before_download(): void {
        $paths = " roles/team/ \r\nroles/team/deep/teacher.xml\n\nextras/student.xml";
        set_config('githubpaths', $paths, 'local_bulk_roles_importer');
        $teacher = '<role><shortname>teacher</shortname></role>';
        $student = '<role><shortname>student</shortname></role>';
        $api = $this->github($this->github_head() + [
            self::GH . '/git/trees/tree1?recursive=1' => [200, ['tree' => [
                ['path' => 'roles/team/deep/teacher.xml', 'type' => 'blob', 'sha' => '1'],
                ['path' => 'roles/teams/other.xml', 'type' => 'blob', 'sha' => '2'],
                ['path' => 'extras/student.xml', 'type' => 'blob', 'sha' => '3'],
                ['path' => 'extras/other.xml', 'type' => 'blob', 'sha' => '4'],
            ]]],
            self::GH . '/contents/roles/team/deep/teacher.xml?ref=head1' => [200, [
                'type' => 'file', 'encoding' => 'base64', 'content' => base64_encode($teacher),
            ]],
            self::GH . '/contents/extras/student.xml?ref=head1' => [200, [
                'type' => 'file', 'encoding' => 'base64', 'content' => base64_encode($student),
            ]],
        ]);

        $this->assertSame(['teacher', 'student'], array_column($api->get_roles(), 'shortname'));
        $this->assertSame([
            self::GH . '/commits?sha=main&per_page=1',
            self::GH . '/git/trees/tree1?recursive=1',
            self::GH . '/contents/roles/team/deep/teacher.xml?ref=head1',
            self::GH . '/contents/extras/student.xml?ref=head1',
        ], $api->requested);
    }

    /**
     * Unmatched paths prevent even the matched presets from being downloaded.
     */
    public function test_github_rejects_unmatched_preset_paths(): void {
        set_config('githubpaths', "roles/teacher.xml\nroles/typo", 'local_bulk_roles_importer');
        $api = $this->github($this->github_head() + [
            self::GH . '/git/trees/tree1?recursive=1' => [200, ['tree' => [
                ['path' => 'roles/teacher.xml', 'type' => 'blob', 'sha' => '1'],
            ]]],
        ]);

        try {
            $api->get_roles();
            $this->fail('Expected an unmatched path error');
        } catch (provider_exception $e) {
            $this->assertSame('error:preset_paths_unmatched', $e->errorcode);
            $this->assertStringContainsString('roles/typo', $e->getMessage());
        }
        $this->assertCount(2, $api->requested);
    }

    /**
     * An absolute or parent-relative path cannot select the full repository by mistake.
     */
    public function test_github_rejects_parent_relative_preset_path(): void {
        set_config('githubpaths', '../roles', 'local_bulk_roles_importer');
        $api = $this->github([]);

        $this->expectException(provider_exception::class);
        $this->expectExceptionMessage('Invalid role preset path "../roles"');
        $api->get_roles();
    }

    /**
     * HTTP errors are reported with status and provider message, never the token.
     *
     * @return void
     */
    public function test_github_http_error_is_reported_without_token(): void {
        $api = $this->github([self::GH . '/commits?sha=main&per_page=1' => [401, ['message' => 'Bad credentials']]]);

        try {
            $api->get_head();
            $this->fail('Expected exception');
        } catch (provider_exception $e) {
            $this->assertStringContainsString('401', $e->getMessage());
            $this->assertStringContainsString('Bad credentials', $e->getMessage());
            $this->assertStringNotContainsString('secret-token', $e->getMessage());
            $this->assertStringNotContainsString('api.example.test', $e->getMessage());
        }
    }

    /**
     * Malformed responses are rejected before their contents are read.
     *
     * @return void
     */
    public function test_github_malformed_response_is_rejected(): void {
        $api = $this->github($this->github_head() + [
            self::GH . '/git/trees/tree1?recursive=1' => [200, '<html>proxy error</html>'],
        ]);

        $this->expectException(provider_exception::class);
        $this->expectExceptionMessage('expected a JSON object');
        $api->get_files();
    }

    /**
     * A GitHub project must be owner/repository.
     *
     * @return void
     */
    public function test_github_rejects_invalid_project(): void {
        $api = $this->github([], 'group/sub/roles');

        $this->expectException(provider_exception::class);
        $api->get_head();
    }

    /**
     * GitLab follows pagination, lists nested XML blobs only and encodes the branch.
     *
     * @return void
     */
    public function test_gitlab_paginates_tree_and_encodes_branch(): void {
        $page1 = [];
        for ($i = 0; $i < 100; $i++) {
            $page1[] = ['path' => "roles/r{$i}." . ($i % 2 ? 'xml' : 'txt'), 'type' => 'blob', 'id' => "blob{$i}"];
        }
        $page1[0] = ['path' => 'roles', 'type' => 'tree'];
        $api = $this->gitlab([
            self::GL . '/repository/branches/feature%2Froles' => [200, [
                'commit' => ['id' => 'head1', 'committed_date' => '2026-01-01T00:00:00Z'],
            ]],
            self::GL . '/repository/tree?ref=head1&recursive=true&per_page=100&page=1' => [200, $page1],
            self::GL . '/repository/tree?ref=head1&recursive=true&per_page=100&page=2' => [200, [
                ['path' => 'roles/deep/last.xml', 'type' => 'blob', 'id' => 'lastblob'],
            ]],
        ], 'feature/roles');

        $files = $api->get_files();
        $this->assertCount(51, $files);
        $this->assertSame('roles/r1.xml', $files[0]->path);
        $this->assertEquals((object)['path' => 'roles/deep/last.xml', 'revision' => 'lastblob'], end($files));
    }

    /**
     * GitLab content uses the encoded file path.
     *
     * @return void
     */
    public function test_gitlab_file_content(): void {
        $api = $this->gitlab([
            self::GL . '/repository/files/roles%2Fa.xml/raw?ref=head1' => [200, '<role/>'],
        ]);

        $this->assertSame('<role/>', $api->get_file_content('head1', 'roles/a.xml'));
    }

    /**
     * A file that cannot be fetched is returned as a failed role; other files still load.
     *
     * @return void
     */
    public function test_get_roles_reports_fetch_failure_per_file(): void {
        set_config('gitlabpaths', "ok.xml\nbroken.xml", 'local_bulk_roles_importer');
        set_config('githubpaths', 'unrelated.xml', 'local_bulk_roles_importer');
        $xml = '<?xml version="1.0"?><role><shortname>gitrole</shortname></role>';
        $api = $this->gitlab([
            self::GL . '/repository/branches/main' => [200, [
                'commit' => ['id' => 'head1', 'committed_date' => '2026-01-01T00:00:00Z'],
            ]],
            self::GL . '/repository/tree?ref=head1&recursive=true&per_page=100&page=1' => [200, [
                ['path' => 'ok.xml', 'type' => 'blob', 'id' => 'rev1'],
                ['path' => 'broken.xml', 'type' => 'blob', 'id' => 'rev2'],
                ['path' => 'unrelated.xml', 'type' => 'blob', 'id' => 'rev3'],
            ]],
            self::GL . '/repository/files/ok.xml/raw?ref=head1' => [200, $xml],
            self::GL . '/repository/files/broken.xml/raw?ref=head1' => [500, ['message' => 'boom']],
        ]);

        $roles = $api->get_roles();
        $this->assertCount(2, $roles);
        [$ok, $broken] = $roles;

        $this->assertSame('gitrole', $ok->shortname);
        $this->assertEquals((object)[
            'provider' => 'gitlab', 'endpoint' => 'https://gitlab.example.test',
            'repository' => 'group/sub/roles', 'branch' => 'main', 'path' => 'ok.xml',
            'revision' => 'rev1',
        ], $ok->source);
        $this->assertTrue($broken->invalid);
        $this->assertStringContainsString('500', $broken->fetcherror);
        $this->assertSame('broken.xml', $broken->filename);
    }
}
