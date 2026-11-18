# Changelog

## [1.1.0] - 2026-11-17

### Added

- **Role preset paths** setting for GitHub and GitLab. Enter folders or single XML files, one per line,
  to choose which presets to import. If a configured path matches no XML files, the import stops and
  reports the unmatched paths.
- Per-file Git revision tracking in the new `local_bulk_roles_importer_rev` table. Unchanged files are
  skipped, and presets are reapplied once when switching Git sources.
- Import summary with created, updated, unchanged and failed counts. Each failure names the file,
  the role shortname and the reason, and rolled back changes are marked in the log.
- PHPUnit tests for the importer, role manager, Git providers and manual file uploads.

### Changed

- GitHub and GitLab API errors, pagination and truncated trees are handled. Provider and role write
  errors mark the scheduled task as failed so Moodle retries it.
- The last import time is kept when any role fails, so failed roles are processed again on the next run.
- Allow lists of newly created roles are linked in a second pass, so roles in the same batch can
  reference each other.
- Manual upload results are shown in the standard Moodle page layout.
- Provider labels are loaded from language strings.
- Minimum requirement raised to Moodle 4.3 (PHP 8.0 or later).

### Fixed

- Role presets are applied without relying on role editor request parameters. Role data and
  capabilities are saved in one transaction, and `inherit` removes existing capability overrides.
- Role shortnames are normalised and validated consistently.
- Omitted context levels and allow lists are preserved, and explicitly empty sections are cleared.
- Manual upload uses a separate temporary directory for each request, stops when the upload,
  ZIP extraction or file read fails, and the cancel button works when Moodle runs in a subdirectory.
- Moodle coding style, namespace placement and file headers.

### Security

- Web import log output is escaped.
- XML parsing blocks network access.
- Uploaded and extracted files are always removed after the import.

### Upgrade notes

- After upgrading from 1.0.x, the first Git import applies each selected role file once and records
  its revision as the baseline. Existing Moodle roles with matching shortnames are updated in place.
- Sites on Moodle 4.0 to 4.2 must stay on 1.0.1.

## [1.0.1] - 2025-04-17

- Previous release.
