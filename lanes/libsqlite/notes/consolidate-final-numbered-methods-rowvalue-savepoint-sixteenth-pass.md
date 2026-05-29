# Row-value Savepoint Method Consolidation Sixteenth Pass

Consolidated the row-value RETURNING window rollback/retry entrypoint from
`executeNext233()` to `executeReturningWindowRollbackRetry()` and renamed its
private numbered helpers to descriptive rollback/retry window helper names.
Direct callers in the focused test, follow-on next236 test path, and WordPress
smoke now call the canonical descriptive entrypoint.

Verification:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
  - No syntax errors detected.
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext233Test.php`
  - No syntax errors detected.
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext236Test.php`
  - No syntax errors detected.
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next233.php`
  - No syntax errors detected.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext233Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext236Test.php`
  - `2 test files, 150 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next233.php --self-test`
  - `wordpress-rowvalue-returning-window-current-source-next233 self-test passed`
- `git diff --check -- lanes/libsqlite`
  - Passed.

Dependency closure: no new support component is needed; this is a production
method/helper name consolidation over existing row-value RETURNING, savepoint
rollback, and window metadata behavior.
