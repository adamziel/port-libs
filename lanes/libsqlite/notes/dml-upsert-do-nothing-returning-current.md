# dml-upsert-do-nothing-returning-current

Implemented native PHP `INSERT ... ON CONFLICT(...) DO NOTHING RETURNING`
execution in the stable `SQLiteUpsertReturningSql` parser/executor.

Behavior covered:

- `DO NOTHING RETURNING` parses as a distinct UPSERT action without assignments.
- Non-conflicting rows are inserted and returned through the existing RETURNING
  projection machinery.
- Conflicting rows are skipped, make no changes, and emit no RETURNING row.
- Repeated incoming rows in the same statement see the current statement state:
  the first insert is returned, the second conflicting row is skipped.
- SQLite-style NULL conflict-key behavior is preserved: NULL unique keys do not
  conflict with existing NULL keys.
- RETURNING aliases, wildcard rows, and expression projections evaluate only
  over inserted final rows; skipped rows do not evaluate RETURNING expressions.

WordPress smoke:

- `lanes/libsqlite/examples/wordpress-upsert-do-nothing-returning-current.php`
  models idempotent copied `wp_options` import rows that return only newly
  admitted options while existing `siteurl` / `home` rows remain unchanged.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUpsertReturningSql.php`
- `php -l lanes/libsqlite/tests/SQLiteUpsertDoNothingReturningCurrentTest.php`
- `php -l lanes/libsqlite/examples/wordpress-upsert-do-nothing-returning-current.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpsertDoNothingReturningCurrentTest.php`
  - Result: `1 test files, 31 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpsertReturningSqlTest.php lanes/libsqlite/tests/SQLiteUpsertReturningExpressionCurrentNext70Test.php lanes/libsqlite/tests/SQLiteUpsertDoNothingReturningCurrentTest.php`
  - Result: `3 test files, 144 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-upsert-do-nothing-returning-current.php --self-test`
  - Result: `wordpress-upsert-do-nothing-returning-current self-test passed`

Dependency closure:

- No new support component is needed. This reuses the existing bounded UPSERT
  SQL parser, row-array conflict matching, and RETURNING projection evaluator.

Non-overlap:

- This adds parser/executor coverage for SQL-level `DO NOTHING RETURNING`.
  It avoids accepted `DO UPDATE ... RETURNING`, expression RETURNING, trigger
  UPSERT RETURNING, row-value RETURNING/savepoint, recursive view/trigger
  RETURNING, WAL/VFS, JSON, B-tree, pager, and suite-countability clusters.
