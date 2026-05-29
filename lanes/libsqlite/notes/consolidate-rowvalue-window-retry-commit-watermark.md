# Row-Value Window Retry Commit Watermark Consolidation

## Scope

- Removed the touched `next256` retry commit watermark result keys, status values, dependency labels, and default savepoint from `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan`.
- Renamed the direct retry commit watermark test file to a stable canonical filename and updated the WordPress smoke to consume the unsuffixed keys.
- No production compatibility shim or numbered replacement helper was added.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowRetryCommitWatermarkCanonicalTest.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-retry-commit-watermark.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowRetryCommitWatermarkCanonicalTest.php`
  - Result: `1 test files, 71 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-retry-commit-watermark.php --self-test`
  - Result: `wordpress-rowvalue-returning-window-retry-commit-watermark self-test passed`
- `git diff --check -- lanes/libsqlite`

## Dependency Closure

No new support component is needed; this pass only renames a consolidated row-value/window result surface and keeps the existing native PHP row-value UPDATE/DELETE RETURNING, chunk cursor, and retry commit watermark behavior.
