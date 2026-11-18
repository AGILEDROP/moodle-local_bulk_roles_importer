# Bulk Roles Importer #

Bulk Roles Importer is a Moodle plugin that enables administrators to import and manage multiple roles in bulk using XML role presets.
Role definitions can be uploaded manually or synced automatically from a Git-based repository (e.g. GitHub, GitLab).

This plugin is ideal for Moodle sites managing many custom roles across multiple environments or needing regular synchronization
with a version-controlled repository.

### Requirements ###
- Moodle 4.3 or later
- PHP 8.0 or later (as required by your Moodle version)

### Features ###
-  **Manual Import:**
Upload a single XML file or a ZIP archive containing multiple XML files with role definitions.

- **Automatic Import:**
Configure a repository (e.g. GitHub, GitLab) as the source for role XML files and define a schedule for periodic imports.
At each scheduled time, the plugin will fetch the latest role definitions and update Moodle roles accordingly.

### Manual Import

Navigate to _Site administration > Users > Permissions > Import roles from a file_ to manually upload an XML file
or a ZIP archive containing multiple XML files with Moodle role definitions. After uploading, click Import.
The plugin processes each file and shows the results. It logs and skips files that are not valid Moodle role presets.
If the upload cannot be saved, the ZIP archive cannot be extracted or the file cannot be read, an error is shown and no roles are imported.

### Automatic Import

Open _Site administration > Users > Permissions > Bulk Roles Importer settings_ and select **GitHub** or **GitLab**
under **Role import source**. The scheduled task uses the selected source.

| Setting | GitHub | GitLab |
| --- | --- | --- |
| URL | `https://api.github.com` | `https://gitlab.com` or the base URL of your GitLab instance |
| Project | `owner/repository` | `group/subgroup/project` or the numeric project ID |
| Main branch | The branch containing the presets, such as `main` | The branch containing the presets, such as `main` |
| Auth token | A fine-grained personal access token scoped to the repository with read-only **Contents** permission | A project access token with **Reporter** and `read_api`, or a personal access token with `read_api` and access to the project |

Private repositories require a token. Public repositories may work without one, subject to the provider's API limits.
[GitHub token setup](https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/managing-your-personal-access-tokens)
· [GitLab project tokens](https://docs.gitlab.com/user/project/settings/project_access_tokens/)
· [GitLab token scopes](https://docs.gitlab.com/security/tokens/access_token_scopes/)

Use **Role preset paths** in the GitHub or GitLab settings to choose which presets to import.
Enter one path per line, relative to the root of the configured branch.

- `roles/moodle-5` selects all XML presets under that folder, including nested folders.
- `roles/teacher.xml` selects that file.
- An empty field imports all XML files on the branch.

If a configured path matches no XML files, the import stops and reports the unmatched paths.
Include the presets for linked roles if those roles do not already exist in Moodle.

For each file the plugin stores the last successfully applied Git blob ID, scoped to the provider,
API URL, repository, branch and file path. Unchanged files are skipped before download when their role
and linked targets still exist. Manual file uploads do not change Git revision tracking. Switching Git
sources reapplies the selected source's presets once, including when switching back to an earlier source.

A revision is stored only after the role and its allow lists are applied. If a preset references a
missing role, the importer reports its shortname and retries the referring preset after the target is available.

After upgrading from 1.0.x, the first Git import applies each selected role file once and records its blob ID
as the baseline. Existing Moodle roles with matching shortnames are updated in place.

### Testing a Git import

Use a test branch with a few role presets. After saving the settings, follow **Edit schedule** to the scheduled task
and select **Run now** if available. You can also run the task from the Moodle directory that contains `admin/cli`.

```sh
php admin/cli/scheduled_task.php --execute='\local_bulk_roles_importer\task\import_roles'
```

1. Run the task and verify a created or updated role under _Site administration > Users > Permissions > Define roles_.
2. Run it again against the same branch with the same files. Successfully imported files should be counted as `unchanged`.
3. Change one test role's description in the repository, commit and push it, then run the task again. Only that role should be updated.

### Reading the import log

Check the `Created`, `updated`, `unchanged`, and `failed` counts at the end of the log. Moodle can report the
scheduled task as `Success` when it skips an invalid preset, such as a file whose root element is not `<role>`.
The log identifies the failed file while the other roles can still import. Correct the file or exclude it with
**Role preset paths**, then rerun the task. Provider and role-write errors mark the scheduled task as failed so
Moodle can retry it.

### Role presets from Moodle 4.5

When using a 4.5 role export on Moodle 5.x, update the capability names in a separate copy of the presets.

- Rename `tiny/premium:accesspremium` to `tiny/premium:use`. Keep it in the same permission element.
- Remove `mod/data:comment` and `mod/data:managecomments`. Moodle 5.0 deprecated these unused capabilities without replacements.

An import can complete with deprecation warnings. Use the Moodle 5.x copy to apply the renamed capability
under its current name, and verify the resulting permissions in Moodle.

### Example Role XML

For your convenience, an example role XML file is provided in the `/samples` directory. This sample demonstrates
a simplified role definition that includes only a few key permissions, making it easier to customize for your needs.

## Changelog ##

See [CHANGELOG.md](CHANGELOG.md) for the changes in each release.

## Installation

### Composer

Once a stable release is available on Packagist, run this command from the root of your Composer-managed Moodle project:

```bash
composer require agiledrop/moodle-local_bulk_roles_importer
```

### ZIP

Choose a release compatible with your Moodle version. Open **Site administration → Plugins → Install plugins**, upload the plugin ZIP and follow the installation prompts.

### Manual or Git installation

Extract the plugin or check out the matching release from the [GitHub repository](https://github.com/AGILEDROP/moodle-local_bulk_roles_importer) into `local/bulk_roles_importer/` under Moodle's web root. If your project uses a `public/` web root, the path from the project root is `public/local/bulk_roles_importer/`. The directory should contain the plugin's `version.php`.

### Complete the installation

After installing the files with Composer, manually or with Git, open **Site administration → Notifications**. Alternatively, run the following command from the Moodle project root:

```bash
php admin/cli/upgrade.php
```

## License ##

2025 Agiledrop ltd. <developer@agiledrop.com>

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program.  If not, see <https://www.gnu.org/licenses/>.
