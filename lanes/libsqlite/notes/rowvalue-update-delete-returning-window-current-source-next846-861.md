# Row-value UPDATE/DELETE RETURNING window current-source next846-861

This slice extends the consolidated `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan` continuation directly after the completed next830-845 seal.

- Adds `executeNext846()` through `executeNext861()` on the existing consolidated plan class.
- Reuses the established four-step continuation helper for handoff, source audit, preflight, and ready seal metadata.
- Validates that next846 consumes `next845_ready` and that next849, next853, next857, and next861 publish ready seals.

Validation:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next846-861.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext846861Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext830845Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext846861Test.php`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next846-861.php --self-test`
- `git diff --check`
