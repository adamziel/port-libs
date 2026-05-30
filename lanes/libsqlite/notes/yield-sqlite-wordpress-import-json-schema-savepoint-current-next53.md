# yield-sqlite-application-import-json-schema-savepoint-current-next53

Status: focused PHP behavior growth for Application JSON imports that apply schema defaults before entering the current savepoint and expose the next savepoint/WAL frame after import.

Implementation:

- Added `SQLiteImportJsonSchemaSavepointCurrentNext53Plan`, a bounded native PHP planner layered over the accepted JSON import WAL savepoint path.
- It accepts JSON text, JSONB blobs, and JSON subtype payloads; extracts object rows; normalizes Application `name`/`value` aliases; applies schema defaults; generates option ids; validates JSON-valued option names; and then imports through the existing savepoint/WAL planner.
- It records current and next savepoint snapshots around each batch, generated ids, defaulted fields, and replacement conflict metadata. Schema and malformed JSON failures roll back without advancing the WAL frame.
- Added `application-import-json-schema-savepoint-current-next53.php` as a copied `wp_options` smoke showing a defaulted import batch followed by a schema rollback.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteImportJsonSchemaSavepointCurrentNext53Test.php
Focused test run: 1 selected test files (root lock skipped)
49 PASS lines
1 test files, 146 assertions, 0 failures
```

Expected status delta:

- `phpPass`: `19277 -> 19326` (`+49`) by the exact focused PASS-line count verified locally.
- `benchmarkDenominator.mapped`: unchanged; this is a lane-local Application/schema behavior surface, not a newly mapped upstream Tcl inventory unit.

Non-overlap:

This avoids accepted schema rejection next49, JSON import insert next48, JSON import WAL savepoint next35, savepoint page-image rollback, WAL byte truncation, VFS savepoint rollback/write/sync/lock/rollback-journal clusters, B-tree page/root/overflow clusters, SELECT SQL text/JOIN/GROUP/subquery/ORDER/LIMIT clusters, JSON table cursor/source/hidden/visible constraints, Unicode GLOB, and super-journal/rollback-journal commit paths. The new behavior is schema default/generation plus current/next savepoint snapshots before calling the accepted import savepoint planner.

Dependency closure:

No new shared support component is needed. The slice reuses lane-local JSON extraction/validity, JSONB/subtype handling, Application import transaction planning, and WAL savepoint frame accounting.
