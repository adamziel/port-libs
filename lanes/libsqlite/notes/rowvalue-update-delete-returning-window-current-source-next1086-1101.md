# Row-value UPDATE/DELETE RETURNING window current-source next1086-1101

## Scope

- Extends the existing consolidated `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan` class.
- Adds `executeNext1086()` through `executeNext1101()` as the direct continuation from `next1085_ready`.
- Reuses the existing four-step continuation helper for handoff, source audit, preflight, and final ready seals.
- Validates that next1086 consumes `next1085_ready` and that next1089, next1093, next1097, and next1101 publish ready seals.

## Validation

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next1086-1101.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext10861101Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext10701085Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext10861101Test.php`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-current-source-next1086-1101.php --self-test`
- `git diff --check`
