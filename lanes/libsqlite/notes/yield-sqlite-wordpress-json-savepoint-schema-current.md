# yield-sqlite-wordpress-json-savepoint-schema-current

Status: focused PHP behavior growth for WordPress JSON import batches that carry
`sqlite_schema` DDL changes inside savepoint/WAL current planning.

Implementation:

- Added `SQLiteWordPressJsonSavepointSchemaCurrentPlan`, a bounded native PHP
  planner layered over the accepted schema-aware JSON savepoint/WAL import
  path.
- Tracks `schema_version` and `data_version` current values across JSON import
  batches, schema CREATE/DROP rows, WAL row frames, and page-1 schema-cookie
  frames.
- Invalid DDL changes roll back the current savepoint without advancing WAL,
  `schema_version`, `data_version`, option rows, or released schema rows.
- Added `wordpress-json-savepoint-schema-current.php` as a copied
  WordPress `wp_options` smoke showing one released schema batch and one
  rejected duplicate schema batch.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWordPressJsonSavepointSchemaCurrentTest.php
Focused test run: 1 selected test files (root lock skipped)
48 PASS lines
1 test files, 160 assertions, 0 failures
```

Expected status delta:

- `phpPass`: `19277 -> 19325` (+48 focused PASS cases).
- `benchmarkDenominator.mapped`: unchanged; this is a lane-local WordPress
  schema/savepoint current behavior surface, not a newly mapped upstream
  Tcl inventory unit.

Non-overlap:

This avoids accepted schema-aware JSON import schema-aware JSON import row validation,
WAL checkpoint/savepoint and rollback-journal apply clusters, VFS file writer,
sync, process-lock, and lock-state clusters, B-tree page/root/overflow clusters,
JSON table cursor/source/hidden/visible constraint clusters, SELECT SQL text /
JOIN / GROUP / subquery / ORDER / LIMIT clusters, Unicode GLOB, and rollback or
super-journal commit paths. The new behavior is schema-cookie/data-version
current-state preservation and WAL page-1 schema-cookie framing around
WordPress JSON savepoint batches.

Dependency closure:

No new shared support component is needed. The slice reuses lane-local JSON
extraction/validity, schema-aware WordPress JSON WAL savepoint import planning,
and savepoint/WAL current-frame accounting.
