Status: focused full-run parity behavior fix for row-value UPDATE/DELETE LIMIT
dynamic offset handling.

This slice fixes `SQLiteUpdateDeleteReturningSql` and
`SQLiteUpdateDeleteLimitPlan` so signed DML `OFFSET` values parse for both
`LIMIT n OFFSET m` and comma `LIMIT offset,count` forms. Execution now mirrors
SQLite SELECT LIMIT behavior by clamping negative offsets to zero at selection
time instead of rejecting them.

Focused behavior:

- Generic `app_settings` / `app_setting_targets` fixtures only.
- Row-value `IN (SELECT ...)` UPDATE and DELETE predicates with nested LIMIT
  clauses.
- Outer UPDATE/DELETE `ORDER BY ... LIMIT ... OFFSET -n` and comma
  `LIMIT -n,count` forms.
- Dynamic seeded UPDATE/DELETE coverage over negative offset windows.

Upstream source references:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test`
  `limit-1.2.5` negative offset semantics.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_update.test` and
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_delete.test` for
  UPDATE/DELETE ORDER BY/LIMIT behavior.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUpdateDeleteLimitPlan.php`
- `php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
  - `1 test files, 312 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpdateDeleteReturningSqlTest.php lanes/libsqlite/tests/SQLiteRowValueNestedSavepointReturningTest.php`
  - `2 test files, 129 assertions, 0 failures`

Dependency closure: no new support component is needed. This reuses the
existing native PHP UPDATE/DELETE RETURNING executor, row-value subquery
support, and SELECT-style LIMIT slicing.

Non-overlap: this does not repeat accepted nested savepoint returning,
row-value subquery positive/negative LIMIT without negative OFFSET, JSON,
WAL/VFS, B-tree, PRAGMA, trigger, suite-evidence, source-neutral cleanup, or
dashboard-only work. The new surface is signed OFFSET parity in DML LIMIT
selection.
