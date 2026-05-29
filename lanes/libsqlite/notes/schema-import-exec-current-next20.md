# Schema Import Exec Current Next20

- Scope: bounded WordPress schema import execution for `CREATE TABLE`, `CREATE TEMP TABLE`, `CREATE INDEX`, and `CREATE UNIQUE INDEX` statements.
- Behavior: `SQLiteSchemaImportExecutor` materializes imported DDL into `SQLiteSchemaRecord` rows, allocates per-schema rootpages/rowids, creates SQLite-style autoindex records for inline/table `PRIMARY KEY` and `UNIQUE` constraints, honors `IF NOT EXISTS`, routes unqualified objects through the current schema, and hands the imported records to `SQLiteAttachedSchemaCatalog` for PRAGMA introspection.
- Focused verification: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSchemaImportExecutorCurrentNext20Test.php` passed with `1 test files, 54 assertions, 0 failures` and 28 PASS lines.
- WordPress smoke: `php lanes/libsqlite/examples/wordpress-schema-import-exec-current-next20.php` prints imported `wp_options`, temp staging, and attached archive schema objects plus PRAGMA-derived columns/indexes.
- Dashboard movement: `lane-status.json` `phpPass` increases by the exact verified focused PASS-line delta, `+28`. No mapped upstream denominator change is claimed.
- Non-overlap: this mutates/imports schema records from DDL and avoids accepted PRAGMA read-only catalog, ATTACH/DETACH, SELECT SQL text/JOIN/GROUP/subquery/ORDER/LIMIT, JSON table cursor/source/constraints, VFS writer/sync/lock/rollback, WAL checkpoint/savepoint, B-tree page move/root-collapse/overflow, and Unicode GLOB clusters.
- Dependency closure: no new support component is required; the slice reuses `SQLiteSchemaRecord` and `SQLiteAttachedSchemaCatalog`.
