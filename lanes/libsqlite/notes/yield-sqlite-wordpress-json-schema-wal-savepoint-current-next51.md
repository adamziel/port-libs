# Application JSON Schema WAL Savepoint Current Next51

This slice adds `SQLiteJsonSchemaWalSavepointPlan`, a bounded native PHP
planner for Application JSON schema import DDL inside a WAL transaction savepoint.
It covers schema-cookie and data-version increments for applied schema edits,
statement-journal rollback for a failed schema step, WAL frame discard planning,
and page-image rollback metadata for the active savepoint.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonSchemaWalSavepointCurrentNext51Test.php`
- Result: `1 test files, 51 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-json-schema-wal-savepoint-current-next51.php`
- Result: JSON smoke reports `ready`, final schema cookie `9`, data version `5`,
  schema names `wp_options`, `wp_json_schema`, `wp_json_schema_validate`, and
  WAL rollback frames `[1,2]`.

Dashboard delta:

- `phpPass`: `18565 -> 18616` from the 51 newly passing focused assertions.
- `benchmarkDenominator.mapped`: unchanged; this is native focused Application
  schema/WAL savepoint coverage and does not claim a new upstream inventory unit.

Non-overlap:

- Avoids accepted WAL byte truncation, VFS savepoint rollback application,
  rollback-journal commit/super-journal paths, parser-level JSON table sources,
  JSON visible/hidden constraints, B-tree page moves/root collapse/overflow
  freelist release, and grouped/subquery/ORDER BY SELECT SQL text dispatch.

Dependency closure:

- No new support component is needed. The slice reuses existing bounded
  `SQLiteSavepointStack` statement journal, page-image, and WAL frame tracking.
