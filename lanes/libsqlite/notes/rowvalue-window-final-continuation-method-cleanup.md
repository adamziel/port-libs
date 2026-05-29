# Rowvalue Window Final Continuation Method Cleanup

## Scope

- Consolidated the `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan` final-continuation production entry points into descriptive canonical methods.
- Renamed the direct focused test and WordPress example away from the numbered current-source suffix.
- Preserved existing payload keys and assertions so behavior coverage remains stable while production method names stop carrying worker numbers.

## Verification

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowFinalContinuationSealTest.php`
- `php -l lanes/libsqlite/examples/wordpress-rowvalue-returning-window-final-continuation-seal.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowFinalContinuationSealTest.php`
- `php lanes/libsqlite/examples/wordpress-rowvalue-returning-window-final-continuation-seal.php --self-test`
- `git diff --check -- lanes/libsqlite`

## Dependency Closure

No new support component is needed. This is a production helper-method consolidation over existing rowvalue/window savepoint and RETURNING metadata.
