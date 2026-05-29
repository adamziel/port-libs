# Row-value UPDATE/DELETE RETURNING window current-source next878-893

This slice extends the consolidated `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan` continuation directly after the completed next862-877 seal.

- Adds `executeNext878()` through `executeNext893()` on the existing consolidated plan class.
- Reuses the established four-step continuation helper for handoff, source audit, preflight, and ready seal metadata.
- Validates that next878 consumes `next877_ready` and that next881, next885, next889, and next893 publish ready seals.

Validation:

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next878-893.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext878893Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext862877Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext878893Test.php`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next878-893.php --self-test`
- `git diff --check`
