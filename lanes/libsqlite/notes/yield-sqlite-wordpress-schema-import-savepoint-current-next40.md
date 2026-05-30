# yield-sqlite-application-schema-import-savepoint-current-next40

## Scope

Adds `SQLiteSchemaImportSavepointPlan`, a lane-local planner for copied Application `sqlite_schema` import batches replayed under SQLite-style savepoints.

The slice is intentionally distinct from accepted schema bulk import, savepoint page-image rollback, WAL byte truncation, VFS savepoint rollback apply, rollback-journal commit, super-journal commit, and VFS sync/lock/file-writer clusters. It covers schema-cookie/rootpage state across current savepoint rollback, not pager byte application.

## Behavior

- Released schema batches advance the visible schema object set, schema/data versions, rootpage allocation, dirty schema/root pages, and journal-byte estimate.
- Open savepoints keep their imported objects visible to later current statements.
- Duplicate schema-object failures with `on_error=rollback` roll back only the failing savepoint and preserve the current schema version, data version, and next rootpage.
- `on_error=abort` rethrows duplicate-object errors.
- `CREATE ... IF NOT EXISTS` duplicates skip without dirty schema pages or schema-cookie increments.

## Focused Evidence

Command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSchemaImportSavepointCurrentNext40Test.php
```

Output:

```text
Focused test run: 1 selected test files (root lock skipped)
60 PASS lines

1 test files, 60 assertions, 0 failures
```

`phpPass` delta: `14649 -> 14709` (`+60` verified focused PASS lines).

## Application Smoke

`lanes/libsqlite/examples/application-schema-import-savepoint-current-next40.php --self-test` exercises copied `wp_options` plus plugin schema import batches, a duplicate plugin schema rollback, and open-vs-released savepoint visibility.

## Dependency Closure

No new support component is needed. The slice reuses existing bounded native PHP pieces: `SQLiteSchemaBulkImportPlan` for schema statement parsing/rootpage assignment and `SQLiteSavepointStack` for current savepoint rollback state.
