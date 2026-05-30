# PRAGMA Catalog Rebase Current Next16

This slice rebases the remaining PRAGMA catalog conflict onto the current
accepted libsqlite source with focused native PHP behavior:

- `PRAGMA database_list` now executes from the attached-schema catalog and
  returns SQLite-shaped `seq`, `name`, and `file` rows for `main`, `temp`, and
  attached databases.
- `PRAGMA foreign_key_list(table)` now executes from the schema catalog for
  column-level and table-level foreign keys.
- Unqualified `foreign_key_list` follows current-source resolution order:
  `temp`, `main`, then attached databases.
- Schema-qualified `foreign_key_list` stays pinned to the requested catalog.
- Returned rows include `id`, `seq`, `table`, `from`, `to`, `on_update`,
  `on_delete`, and `match`, including composite table-level foreign keys and
  default `NO ACTION` / `NONE` behavior.

Focused verification:

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaCatalogRebaseCurrentNext16Test.php
Focused test run: 1 selected test files (root lock skipped)
62 PASS lines, 66 assertions, 0 failures

$ php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaCatalogRebaseCurrentNext16Test.php lanes/libsqlite/tests/SQLitePragmaSchemaCatalogTest.php
Focused test run: 2 selected test files (root lock skipped)
89 PASS cases, 138 assertions, 0 failures
```

Dashboard delta:

- `phpPass`: +62, from 5433 to 5495.
- `benchmarkDenominator.mapped`: unchanged at 456 / 1589. This is focused PHP
  PRAGMA catalog behavior coverage and does not claim a new hydrated upstream
  Tcl inventory unit.

Application smoke:

```text
$ php lanes/libsqlite/examples/application-pragma-catalog-rebase-current-next16.php
```

The smoke reports copied Application schema introspection for attached database
order plus temp/main/attached `foreign_key_list` rows without requiring
`ext/sqlite`.

Non-overlap:

This avoids accepted locking-mode, synchronous/journal, encoding/temp_store,
integrity/quick_check, foreign_key_check, table_info/index_info catalog rows,
JSON table/source/cursor/constraint work, SELECT SQL text/subquery/GROUP/ORDER
clusters, VFS writer/sync/lock/rollback clusters, WAL checkpoint/savepoint byte
truncation, B-tree page move/root collapse/overflow release, and Unicode GLOB.

Dependency closure:

No new support component is needed. The slice reuses lane-local
`SQLiteAttachedSchemaCatalog`, `SQLitePragmaSchemaCatalog`, and
`SQLiteSchemaRecord` primitives.
