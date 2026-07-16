# yield-sqlite-application-schema-json-savepoint-wal

Status: focused PHP behavior growth for schema-aware Application JSON imports staged through SQLite savepoints while tracking the current/next WAL frame.

Implementation:

- Added `SQLiteSchemaJsonSavepointWalPlan`, a bounded native PHP planner layered over the accepted JSON import WAL savepoint path.
- It validates extracted JSON/JSONB/subtype rows against a Application option schema before row import, including required fields, unknown-field rejection, autoload enum checks, and JSON-text enforcement for `theme_mods_*`, `*_settings`, and `widget_*` options.
- Schema failures roll back the current savepoint without advancing WAL frames, while released earlier batches remain visible and later open batches remain unreleased.
- Added `application-schema-json-savepoint-wal.php` as a copied `wp_options` smoke showing one released theme schema batch and one rejected widget batch.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSchemaJsonSavepointWalTest.php
Focused test run: 1 selected test files (root lock skipped)
54 PASS lines
1 test files, 153 assertions, 0 failures
```

Expected status delta:

- `phpPass`: `17920 -> 17974` (+54 focused PASS cases).
- `benchmarkDenominator.mapped`: unchanged; this is a new lane-local Application/schema behavior surface, not a newly mapped upstream Tcl inventory unit.

Non-overlap:

This avoids accepted JSON table cursor/source/hidden/visible constraint pushdown, JSON host joins, JSON import WAL savepoint current-next35, WAL byte truncation, VFS savepoint rollback/write/sync/lock/rollback-journal clusters, B-tree page/root/overflow clusters, SELECT SQL text/JOIN/GROUP/subquery/ORDER/LIMIT clusters, Unicode GLOB, and rollback-journal commit/super-journal paths. The new behavior is schema validation at the Application JSON import savepoint boundary with current WAL frame preservation on rejection.

Dependency closure:

No new shared support component is needed. The slice reuses lane-local JSON extraction/validity, JSONB/subtype handling, Application import transaction planning, and WAL savepoint frame accounting.
