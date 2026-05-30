# Session changeset current-source fix next9

## Behavior

- Added `SQLiteSessionChangeset` for bounded SQLite session-style changesets.
- Covers table headers, primary-key flags, insert/delete/update records, undefined-vs-NULL encoding, scalar/blob decoding, row-array apply, and conflict classification.
- Application smoke uses copied `wp_options` rows to diff, encode/decode, and apply an update/delete/insert changeset without ext/sqlite.

## Verification

- `php -l lanes/libsqlite/src/SQLiteSessionChangeset.php`
- `php -l lanes/libsqlite/tests/SQLiteSessionChangesetTest.php`
- `php -l lanes/libsqlite/examples/application-session-changeset-apply.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSessionChangesetTest.php`
  - `1 test files, 40 assertions, 0 failures`
  - `40` PASS lines
- `php lanes/libsqlite/examples/application-session-changeset-apply.php`
  - `applied: 3`
  - `conflicts: 0`
  - `option_names: ["siteurl", "plugin_enabled"]`

## Status delta

- `lane-status.json` `phpPass`: `2311 -> 2351` from the 40 newly passing focused PHP test cases.
- `benchmarkDenominator.mapped`: unchanged; this does not claim a newly mapped upstream inventory unit.

## Non-overlap

This slice intentionally avoids accepted SQL SELECT text, JSON table cursor/source/constraint, WAL byte/checkpoint/rollback, VFS file-writer/locking/sync, B-tree page-move/root-collapse/overflow, Unicode GLOB, and batch5b corpus clusters. It addresses the named session changeset current-source conflict with a separate bounded session/change application surface.

## Dependency closure

No new support component is needed. The implementation reuses existing lane autoloading plus `SQLiteVarint` for session payload lengths.
