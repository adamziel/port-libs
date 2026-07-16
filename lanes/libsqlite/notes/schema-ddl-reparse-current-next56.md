# schema-ddl-reparse-current-next56

Status: focused PHP behavior growth for bounded sqlite_schema DDL reparse on the current connection.

Implemented:

- Added `SQLiteSchemaDdlReparsePlan` to apply bounded schema DDL batches over current `SQLiteSchemaRecord` rows.
- Covers `CREATE TABLE`, `CREATE INDEX`, `DROP INDEX`, `DROP TABLE`, and `ALTER TABLE ... RENAME TO ...` for current sqlite_schema text.
- Rebuilds a `SQLitePragmaSchemaCatalog` after DDL so table/index PRAGMA rows reflect the next schema image.
- Advances the schema cookie once per changed DDL statement and reports stale prepared statement IDs that must be reprepared.
- Added a Application smoke for copied `wp_options` imports that create a partial index plus an optionmeta table and invalidate a prepared import statement.

Focused verification:

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteSchemaDdlReparseCurrentNext56Test.php
Focused test run: 1 selected test files (root lock skipped)
PASS schema ddl reparse current next56 creates partial index and reparses catalog
PASS schema ddl reparse current next56 drops index and removes stale pragma rows
PASS schema ddl reparse current next56 drops table and dependent indexes together
PASS schema ddl reparse current next56 renames table and rewrites dependent schema sql
PASS schema ddl reparse current next56 creates new table with rowid and root page allocation
PASS schema ddl reparse current next56 applies mixed DDL batch with cookie per changed operation
PASS schema ddl reparse current next56 no-op create existing objects keeps cookie stable
PASS schema ddl reparse current next56 rejects unsupported or unsafe DDL

1 test files, 100 assertions, 0 failures
```

Dashboard delta:

- `phpPass`: `20008 -> 20016` from 8 newly passing focused PASS lines.
- `benchmarkDenominator.mapped`: unchanged; this is lane-local current schema behavior, not a newly mapped upstream Tcl inventory unit.

Non-overlap:

This avoids queued/accepted attach schema cache, PRAGMA schema/data version, ALTER table generated CHECK/current-row scan, ALTER rename-column/rename-trigger rewrites, schema PRAGMA catalog cursor behavior, VFS/WAL/B-tree/JSON/SELECT SQL clusters, and batch51-batch55 queued surfaces. The new behavior is current sqlite_schema DDL reparse plus prepared-statement invalidation after schema-cookie movement.

Dependency closure:

No new support component is needed. The slice reuses existing lane-local `SQLiteSchemaRecord`, `SQLitePragmaSchemaCatalog`, and bounded ALTER table rename SQL rewriting.
