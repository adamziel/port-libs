# Rowvalue Window Numbered Method Consolidation Forty-eighth Pass

- Consolidated `executeNext366()` through `executeNext373()` in `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan` into the descriptive `executeCurrentSourceHandoffContinuationStep()` helper.
- Updated the direct WordPress rowvalue window example for that range to call the canonical continuation helper instead of numbered production methods.
- Preserved the historical `next358-365` handoff range label for the next366 receipt so existing focused assertions and downstream handoff hashes remain compatible.

Verification:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next366-373.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext366373Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext366373Test.php`
  - `1 test files, 18 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next366-373.php --self-test`
  - `wordpress-rowvalue-returning-window-current-source-next366-373 self-test passed`

Dependency closure: no new support component is needed; this reuses the existing row-value UPDATE/DELETE RETURNING window continuation implementation.
