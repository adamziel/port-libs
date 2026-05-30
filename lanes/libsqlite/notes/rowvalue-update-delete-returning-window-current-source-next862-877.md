# Row-value UPDATE/DELETE RETURNING window current-source next862-877

This slice extends the consolidated `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan` continuation directly after the completed next846-861 seal.

- Adds `executeNext862()` through `executeNext877()` on the existing consolidated plan class.
- Reuses the established four-step continuation helper for handoff, source audit, preflight, and ready seal metadata.
- Validates that next862 consumes `next861_ready` and that next865, next869, next873, and next877 publish ready seals.

Validation:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next862-877.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext862877Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext846861Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext862877Test.php`
- `php lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next862-877.php --self-test`
- `git diff --check`
